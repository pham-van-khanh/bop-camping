# Design Spec — Thu tiền theo từng khoản (phụ phí tách riêng)

- **Bead:** bopcamping-urqo
- **Ngày:** 16/08/2026
- **Nối tiếp:** [design_spec_payment_qr.md](design_spec_payment_qr.md)

## 1. Vấn đề

Phụ phí đang bị gộp vào tiền thuê (`rental_due = total_price + extra_fee − discount_total`),
mà thu tiền chỉ theo dõi ở hai khoản: tiền thuê và cọc. Hệ quả: chủ shop **không biết
khoản nào đã thu**. Đơn có phí ship 50k mà khách mới trả tiền thuê thì hệ thống chỉ nói
được "tiền thuê còn thiếu 50k", không nói được thiếu ở đâu.

## 2. Ba khoản thu

```
base_rental_due = total_price − discount_total     (tiền thuê gốc)
fee_due         = extra_fee                        (phụ phí, gộp mọi dòng)
deposit_total                                      (cọc)

rental_due = base_rental_due + fee_due   ← GIỮ NGUYÊN nghĩa cũ
```

`rental_due` **không đổi** vì nó đang được đọc ở `StatsController` (thống kê doanh thu) và
`DeliveryScheduleService` (chỉ dẫn cho shipper). Đổi nghĩa nó là kéo theo cả tài chính —
không đáng, khi chỉ cần thêm hai accessor dẫn xuất.

Khoản `'fee'` thêm vào `Order::PAYMENT_KINDS`; ba cột mới `fee_paid_at` / `fee_paid_by` /
`fee_paid_amount`, đúng khuôn `rental_*` và `deposit_*` sẵn có.

Đã cân nhắc bảng `order_payments` chuẩn hoá. Loại: phải chuyển cả ba khoản sang mô hình
mới, đụng `markPaid()`, shipper, thống kê — mà nhu cầu ở đây không cần gì hơn ba cột.

**Mức chi tiết:** mọi dòng phụ phí gộp thành MỘT khoản, không tách từng dòng. Đơn thực tế
hiếm khi quá 1–2 khoản (thường chỉ phí ship), và tách từng dòng thì phải gắn định danh ổn
định cho từng phần tử trong mảng JSON `extra_fees` — `updateExtraFee()` đang thay nguyên
mảng mỗi lần lưu, nên cờ đã-thu sẽ dính nhầm dòng khi admin sửa danh sách.

Dòng phụ phí trên giao diện hiện kèm **tên các khoản** (`phí ship, phí ngoài giờ`) để trả
lời đúng câu hỏi "khoản nào".

## 3. Đơn cũ — không ghi gì vào DB

Migration **chỉ thêm cột, không backfill**. Đơn cũ đã bấm "đã thu tiền thuê" mang nghĩa cũ
là thu cả phụ phí; luật suy ra từ chính dữ liệu, không cần mốc thời gian:

> **`rental_paid_amount >= rental_due` → coi như phụ phí đã thu.**

| Ca | Số đã ghi | `rental_due` | Kết luận |
|---|---|---|---|
| Đơn cũ, thu 550k gồm cả ship | 550.000 | 550.000 | ≥ → phụ phí đã thu ✓ |
| Đơn mới, thu 500k gốc rồi mới thêm ship 50k | 500.000 | 550.000 | < → phụ phí còn nợ ✓ |

Tự đúng cho cả hai chiều, không đơn nào bị báo nợ oan, và **không có ngày hardcode** trong
code — thứ luôn mục nát sau vài lần deploy.

`markPaid('rental')` từ nay chụp `base_rental_due` (không gồm phụ phí), nên đơn mới luôn
rơi vào vế "<" khi có phụ phí phát sinh sau.

## 4. QR và hoàn cọc

Hai con số tách bạch:

| | Công thức | Dùng ở |
|---|---|---|
| `outstanding_due` | ba khoản còn thiếu | tổng nội bộ, đối chiếu |
| `transfer_due` | **bỏ phụ phí ra khi tiền thuê đã thu** | ảnh QR, ô "Còn phải trả" |

```
transfer_due = max(0, gốc − đã thu)
             + (tiền thuê đã thu ? 0 : max(0, phụ phí − đã thu))
             + max(0, cọc − đã thu)
```

Lý do: khách đã chuyển tiền thuê rồi thì không bắt chuyển thêm một lần nữa cho khoản
50k lẻ. Phụ phí chưa thu được **trừ vào cọc lúc hoàn**:

```
refund_due = cọc đã thu − phụ phí còn thiếu     (kẹp ≥ 0)
```

Nhờ vậy khách **không phải cầm thêm tiền mặt**, nên ô "Còn phải trả" dùng `transfer_due`
là trung thực — không có khoản ẩn nào chờ đòi lúc giao đồ. Dưới QR ghi rõ:
*"Phụ phí 50.000đ sẽ trừ vào tiền cọc khi hoàn."*

Cột mới `deposit_refund_amount` lưu số hoàn thật (hiện chỉ có `deposit_refund_status` +
`deposit_refund_note`, không có số tiền). Khi admin/shipper đánh dấu đã hoàn, phần phụ phí
được trừ sẽ **tự đánh dấu khoản phụ phí là đã thu** với đúng số đó — tiền về tay shop qua
đường giữ lại, không có lý do gì để nó treo "chưa thu" mãi.

**Phụ phí lớn hơn cọc:** kẹp hoàn về 0 và báo admin phần còn thiếu để thu tay. Không để ra
số âm.

## 5. Giao diện

| Màn | Thay đổi |
|---|---|
| Admin — khối Thu tiền | 3 dòng; dòng phụ phí kèm tên khoản |
| Admin — hoàn cọc | Hiện số hoàn sau khi trừ, và phần thiếu nếu phụ phí > cọc |
| Khách (`/tra-cuu`, `/tai-khoan`) | `PaymentStatus` 3 dòng; không phụ phí thì không hiện dòng đó |
| Shipper | Thêm nút thu phụ phí; sửa chỗ hardcode `rental`/`deposit` |

Shipper hiện hardcode hai khoản ở `ScheduleController` và `Schedule.tsx`. Bỏ qua chỗ này
thì shipper cầm tiền phụ phí về mà không có nút nào để ghi nhận.

## 6. Test

- `base_rental_due + fee_due === rental_due` — khoá lại để ai sửa công thức là biết ngay
- Ba khoản thu độc lập
- Luật đơn cũ, **cả hai chiều**: đơn cũ không báo nợ oan; đơn mới thêm phụ phí sau thì còn nợ
- `transfer_due` đổi theo trạng thái tiền thuê (chưa thu → gồm phụ phí; đã thu → không)
- Hoàn cọc trừ đúng phụ phí; phụ phí > cọc thì kẹp 0 và không âm
- Hoàn cọc tự đánh dấu phụ phí đã thu
- Shipper thu được phụ phí

## 7. Năm lỗi bắt được khi soi trước production

Soi bằng 4 góc độc lập + phản biện chéo. Tất cả đều đã tái hiện được bằng số thật.

**1. Shipper trả NGUYÊN cọc trong khi sổ ghi đã giữ lại phụ phí — mất tiền mặt thật.**
Thẻ trả cọc in `deposit_total`, còn `markRefunded()` lại ghi là đã giữ 50k và đánh dấu
phụ phí đã thu. Shipper đưa khách đủ 200k → shop mất 50k, sổ nói đã thu. Ba agent độc lập
cùng chỉ vào chỗ này. Sửa: thẻ dùng `refund_due` và nói rõ phần giữ lại.

**2. `markRefunded()` không idempotent.** Giữ lại phụ phí ở MỌI lần gọi, nên admin chỉ cần
bổ sung ghi chú sau khi đã hoàn là số hoàn ghi lại nhảy từ 150.000 lên NGUYÊN cọc 200.000.
Sửa: chỉ chốt số ở đúng lần chuyển `pending → refunded`, và hoàn tác được ở chiều ngược lại.

**3. Luật đơn cũ là CỜ nên lật sai khi giá đổi.** `rental_paid_amount >= rental_due` lật về
false khi admin nâng phụ phí 50k→80k trên đơn cũ → hệ thống đòi lại cả 80k thay vì 30k
chênh, khách bị trừ cọc thừa 50k. Và không bỏ đánh dấu được: `markPaid('fee', false)` xong
cờ vẫn true. Sửa: suy ra **SỐ TIỀN** (`legacyFeeCredit()`) thay vì cờ, và ghi 0 thay vì
null khi bỏ đánh dấu để đè được lên phần suy ra.

**4. Ba chỗ vẫn giả định chỉ có hai khoản.** Ô "còn phải thu" ở lịch giao admin, tin Zalo
giao việc ("khách đã chuyển đủ — không cần thu gì"), và thông báo của admin ("đã cập nhật
tiền cọc" khi tích phụ phí).

**5. Câu "phụ phí trừ vào cọc" không bao giờ hiện cho khách.** Nó lấy từ `payment_qr`, mà
khách chỉ có QR ở đơn `pending` — đúng ca cần giải thích nhất (đơn **đã** xác nhận) thì
không có QR. Sửa: đưa `fee_from_deposit` lên cấp cao nhất của shape khách.

## 8. Không đụng tới

`rental_due`, `StatsController`, `FinanceService`, `DeliveryScheduleService`, và toàn bộ
luật hiện/ẩn QR đã chốt ở [design_spec_payment_qr.md](design_spec_payment_qr.md).

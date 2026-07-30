# PRD — Thanh toán QR qua SePay

**Ngày**: 2026-07-28 · **ADR**: [adr_sepay_qr_payment.md](adr_sepay_qr_payment.md)
**Plan**: [plan_sepay_qr_payment.md](plan_sepay_qr_payment.md)
**Kế thừa**: epic bopcamping-ust (nhãn hiển thị gộp, edge cases) — nhánh
`feature/checkout-2tier-payment` bị bỏ, xem ADR mục Alternatives B.

## 1. Vấn đề

**Đặt ảo khoá tồn kho.** Đơn `pending` tính vào `AvailabilityService` ngay khi đặt. Khách
đặt cho vui cũng chặn khách thật, đau nhất cuối tuần/cao điểm. Không có cơ chế nào bắt
khách "cam kết" bằng tiền.

**Admin đối soát tay.** Mỗi đơn = một lần mở app ngân hàng, tìm giao dịch, bấm nút ở
`/admin/orders/{order}/payment`. Không scale, dễ sai, không audit trail.

## 2. Mục tiêu

| Mục tiêu | Đo bằng |
|---|---|
| Tự động xác nhận tiền vào | Đơn CK qua QR không cần admin bấm tay |
| Giảm đặt ảo | Đơn `pending` chưa CK quá 60' tự huỷ + nhả tồn |
| Audit trail thanh toán | Mọi giao dịch có raw payload + reference code trong DB |
| Không giảm tỷ lệ chốt đơn | Giữ COD thuần làm lựa chọn hợp lệ |

**Không phải mục tiêu (out of scope):**
- Thẻ quốc tế / ví điện tử (Momo, ZaloPay) — xem ADR, không dùng cổng thanh toán.
- Tự động hoàn cọc. Vẫn xử lý ngoài hệ thống.
- QR thứ 2 cho phần thu khi nhận đồ. Phần đó thu tiền mặt.

## 3. Người dùng & luồng

### 3.1 Khách thuê — checkout

Thêm khối chọn hình thức trả trước. **Mặc định `rental`** (CK phí thuê).

| `prepay_choice` | Nhãn hiển thị | CK trước | Thu khi nhận đồ |
|---|---|---|---|
| `none` | Thanh toán toàn bộ khi nhận đồ | — | Cọc + phí thuê |
| **`rental`** ⭐ | Chuyển trước phí thuê | Phí thuê | Cọc |
| `deposit` | Chuyển trước tiền cọc | Cọc | Phí thuê |
| `both` | Chuyển trước toàn bộ | Cọc + phí thuê | — |

Mỗi lựa chọn hiện rõ **2 số tiền**: "Chuyển trước: X đ" và "Trả khi nhận đồ: Y đ".

Công thức (dùng lại accessor sẵn có, không tính lại):
```
rentalDue  = total_price - discount_total
depositDue = deposit_total
amount_due = total_price + deposit_total + extra_fee - discount_total   (đã có)
```

- `none`   → prepay = 0
- `rental` → prepay = rentalDue
- `deposit`→ prepay = depositDue
- `both`   → prepay = rentalDue + depositDue

> `extra_fee` (phí ngoài giờ) do admin nhập **sau** khi đặt đơn → luôn thu khi nhận đồ,
> không nằm trong QR. Ghi rõ ở UI để khách không bất ngờ.

### 3.2 Khách thuê — trang thanh toán

Sau khi đặt đơn với `prepay_choice != none` → chuyển tới `/don-hang/{code}/thanh-toan`:

- **Ảnh QR động** (`template=compact`, có logo NAPAS/VietQR/bank để khách tin tưởng),
  số tiền + nội dung CK đã nhúng sẵn trong QR.
- Khối thông tin CK thủ công dự phòng (số TK, chủ TK, ngân hàng, **nội dung CK có nút copy**)
  — cho khách dùng app không quét được QR.
- Đồng hồ đếm ngược tới `payment_expires_at`.
- **Poll `/don-hang/{code}/tinh-trang` mỗi 3 giây** → CK xong tự chuyển sang trang thành công,
  khách không phải F5.
- Nút "Tôi sẽ chuyển sau" → về trang tra cứu đơn, QR vẫn truy cập lại được cho tới khi hết hạn.

**Xác nhận thành công** phải nói rõ đã trả khoản nào và còn phải trả bao nhiêu khi nhận đồ.

### 3.3 Admin

- Cột **Thanh toán** hiện nhãn gộp (mục 4.3) + badge hình thức trả trước.
- Nút đánh dấu tay **vẫn giữ**: "Đã nhận cọc" / "Đã nhận phí thuê" — cho khách CK ngoài
  luồng hoặc trả tiền mặt. Đi qua **cùng** `PaymentMatcher`, không có đường ghi thứ hai.
- **Hàng chờ lệch tiền**: đơn `mismatched` (CK thiếu) hiện nổi bật, admin xử tay.
- Xem được lịch sử `order_payments` của đơn: số tiền, thời điểm, reference code, ngân hàng.

## 4. Yêu cầu chức năng

### 4.1 Sinh QR

- Ảnh QR từ URL public, **không cần API key**:
  `{base}/img?acc=&bank=&amount=&des=&template=compact`
- `base` đọc từ config (mặc định `https://vietqr.app`) — đổi được sang `https://qr.sepay.vn`.
- `des` = mã thanh toán: **prefix `BOP` + 6 ký tự số** (khớp mẫu cấu hình ở `my.sepay.vn`).
- Mã thanh toán **UNIQUE**, không trùng mã đơn hàng (`orders.code`) để tránh nhầm lẫn khi
  một đơn có nhiều lần CK.

### 4.2 Nhận & đối soát webhook

`POST /webhook/sepay` — **miễn CSRF**, không session/auth middleware.

| Bước | Yêu cầu |
|---|---|
| Xác thực | HMAC-SHA256 trên **raw body** (`$request->getContent()`), `hash_equals`, timestamp ±5' |
| Phản hồi | HTTP 200 + body `{"success": true}`, **trong 30 giây** |
| Xử lý | Đẩy job vào queue ngay, controller không làm việc nặng |
| Idempotency | `order_payments.sepay_transaction_id` **UNIQUE** — SePay retry 8 lần cùng `id` |
| Bỏ qua | `transferType != "in"` (tiền ra), `code` rỗng (không match mẫu) |

Đối soát trong job:
1. Tìm `order_payments` theo `pay_code` = `code` từ payload, status `pending`.
2. Không tìm thấy → ghi log + thông báo admin (giao dịch lạ), **không** báo lỗi cho SePay.
3. `transfer_amount >= expected_amount` → `paid`, set `deposit_paid_at` / `rental_paid_at`
   theo `kind`, xoá `payment_expires_at`, gửi mail xác nhận cho khách.
4. `transfer_amount < expected_amount` → `mismatched` + thông báo admin, **không tự xác nhận**.

### 4.3 Trạng thái thanh toán — dẫn xuất, không lưu cột

Bỏ cột `payment_status`, tính từ `deposit_paid_at` + `rental_paid_at`:

| `deposit_paid_at` | `rental_paid_at` | `payment_status` (accessor) | Nhãn hiển thị |
|---|---|---|---|
| null | null | `unpaid` | Chưa thanh toán |
| ✓ | null | `deposit` | Đã nhận cọc |
| null | ✓ | `rental` ← **mới** | Đã nhận phí thuê |
| ✓ | ✓ | `full` | Đã thanh toán đủ |

Backfill đơn cũ: `deposit` → `deposit_paid_at = updated_at`; `full` → cả hai; `unpaid` → null.

Trạng thái xử lý (`orders.status`: `pending → confirmed → renting → returned / cancelled`)
**giữ nguyên**, orthogonal với lớp thanh toán — nó đang được dùng cho `AvailabilityService`
và thống kê admin, không đụng để tránh rủi ro.

### 4.4 Tự huỷ đơn chưa CK

- Chỉ đơn `prepay_choice != 'none'` **và** `status = 'pending'` **và** chưa nhận đồng nào.
- `payment_expires_at = created_at + 60 phút` (config được).
- Job scheduler quét định kỳ. **Trước khi huỷ, gọi API SePay xác minh lần cuối** — không
  tin mỗi trạng thái DB.
- Huỷ → `status = 'cancelled'`, `order_payments.status = 'expired'`, nhả tồn kho, mail thông báo.
- **60 phút là con số có lý do**: webhook retry ~33 phút, ngưỡng ngắn hơn sẽ huỷ oan đơn
  đã CK thật mà webhook tới muộn.

### 4.5 Job đối soát định kỳ

`GET https://userapi.sepay.vn/v2/transactions?webhook_success=0` (Bearer token, **rate
limit 3 req/s**) → cứu giao dịch webhook fail hẳn sau ~33 phút.

⚠️ Field API v2 là snake_case (`amount_in`, `reference_number`), webhook là camelCase
(`transferAmount`, `referenceCode`) → **DTO riêng cho từng nguồn**, không dùng chung.

## 5. Yêu cầu phi chức năng

| Loại | Yêu cầu |
|---|---|
| Bảo mật | Secret HMAC + API token chỉ trong `.env`, **không commit**. Không log secret, không log full payload chứa thông tin TK khách. |
| Bảo mật | Webhook không có session/CSRF nhưng **phải** verify HMAC. IP whitelist là lớp phụ, không phải duy nhất. |
| Hiệu năng | Webhook trả trong < 30s (bắt buộc). Việc nặng sang queue — đã có worker. |
| Tin cậy | Idempotency qua UNIQUE constraint ở tầng DB, không chỉ tầng app. |
| Tin cậy | Mọi ghi trạng thái thanh toán qua **một** service (`PaymentMatcher`) — webhook, reconciler, admin bấm tay dùng chung. |
| Khả kiểm | Raw payload lưu JSON cho tra cứu sự cố. |
| Tương thích | Migration chạy được trên **cả sqlite và MySQL** (không enum DB, dùng string). |
| Test | Test collation-safe (chạy đúng trên sqlite lẫn MySQL `utf8mb4_unicode_ci`). |

## 6. Acceptance criteria

**Sinh QR**
- [ ] `prepay_choice=rental` → QR có `amount` = `total_price - discount_total`
- [ ] `prepay_choice=both` → QR có `amount` = cọc + phí thuê (sau giảm giá)
- [ ] `des` khớp mẫu `BOP` + 6 số, unique
- [ ] `prepay_choice=none` → **không** sinh QR, không set `payment_expires_at`

**Webhook — bảo mật**
- [ ] Signature sai → 401, không tạo/sửa record nào
- [ ] Timestamp lệch > 5 phút → 401 (chống replay)
- [ ] Signature tính từ raw body, **không** từ `json_encode($request->all())`
- [ ] Thành công → HTTP 200 + đúng body `{"success": true}`

**Webhook — đối soát**
- [ ] Gửi **cùng payload 3 lần** → chỉ 1 record `paid`, không cộng tiền trùng
- [ ] `transferType = "out"` → bỏ qua, không đụng đơn
- [ ] `code` rỗng → bỏ qua + log, vẫn trả 200
- [ ] `code` không khớp đơn nào → log + báo admin, vẫn trả 200
- [ ] CK đủ → đúng mốc thời gian theo `kind` được set
- [ ] CK thừa → `paid` (không phải `mismatched`)
- [ ] CK thiếu → `mismatched`, **không** set mốc thời gian, báo admin

**Trạng thái dẫn xuất**
- [ ] 4 tổ hợp mốc thời gian → đúng 4 giá trị `payment_status`
- [ ] Backfill: đơn cũ `full`/`deposit`/`unpaid` giữ đúng nhãn sau migration

**Tự huỷ**
- [ ] Đơn `rental` quá 60' chưa CK → cancelled + nhả tồn
- [ ] Đơn `none` quá 60' → **không** bị huỷ
- [ ] Đơn đã `paid` → **không** bị huỷ dù quá hạn
- [ ] Job gọi API SePay xác minh trước khi huỷ; API báo đã có giao dịch → không huỷ, đánh dấu paid

**Tồn kho**
- [ ] Đơn tự huỷ → `AvailabilityService` trả lại số lượng đúng
- [ ] Chạy đúng trên **cả đơn cha và đơn con** (parent/child orders)

**Admin**
- [ ] Bấm tay "Đã nhận cọc" → cùng kết quả như webhook (qua `PaymentMatcher`)
- [ ] Đơn `mismatched` hiện trong hàng chờ xử lý

## 7. Edge cases

| Tình huống | Xử lý |
|---|---|
| Khách CK 2 lần cùng mã | Lần 2 thành `order_payments` riêng (`sepay_transaction_id` khác) → báo admin để hoàn |
| Khách quét QR cũ của đơn đã huỷ | `pay_code` status `expired` → không tự xác nhận, báo admin |
| Khách CK đúng tiền, sai nội dung | `code` rỗng → job đối soát + admin xử tay |
| Webhook tới sau khi đơn đã bị huỷ | Không tự mở lại đơn; báo admin quyết định (hoàn tiền hay phục hồi) |
| Đơn cha/con | QR sinh theo **đơn con** (đơn thực tế có tiền); đơn cha tổng hợp trạng thái từ con |
| Admin đã bấm tay rồi webhook mới tới | Idempotent: mốc thời gian đã có → không ghi đè, chỉ lưu `order_payments` để audit |
| SePay down khi khách CK | Job đối soát định kỳ cứu; nếu quá 60' đơn bị huỷ oan → API xác minh chặn được |
| Huỷ đơn sau khi đã nhận cọc | **Ngoài scope** — hoàn tiền xử lý ngoài hệ thống (kế thừa từ epic ust) |

## 8. Phụ thuộc

| Phụ thuộc | Trạng thái |
|---|---|
| Tài khoản SePay + TK Vietcombank liên kết | **Chưa có** — task đầu tiên, blocking |
| Cấu hình mẫu mã `BOP` + 6 số ở `my.sepay.vn` | Chưa có |
| Secret HMAC + API token trong `.env` | Chưa có |
| Queue worker chạy | Đã có (`composer run dev`) |
| **Cron scheduler trên server** | **Chưa có — bopcamping-ybsm.** Blocker cho *production*, không cho dev |
| HTTPS + domain public cho webhook | Cần khi test thật; dev dùng Test mode + tunnel |

## 9. Rủi ro

| Rủi ro | Mức | Giảm thiểu |
|---|---|---|
| Giao credentials internet banking cho SePay | **Cao** | TK riêng chỉ nhận tiền, hạn mức chuyển ra thấp, ưu tiên OAuth nếu VCB hỗ trợ. Xem ADR. |
| HMAC có thể không khả dụng ở gói FREE | Trung bình | Test sớm ở task 1. Fallback: API Key + IP whitelist |
| Đặt ảo vẫn còn vì `none` được phép | Trung bình | Chấp nhận (quyết định user). Theo dõi tỷ lệ `none` vs `rental` để tính lại sau |
| Số tiền trong QR có thể sửa được ở app một số bank | Thấp | Nhánh `mismatched` làm lưới an toàn |
| Vượt 50 giao dịch/tháng gói FREE | Thấp | Nâng gói STARTUP; confirm giá với SePay trước |

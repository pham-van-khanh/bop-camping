# Design Spec — QR thanh toán SePay

- **Bead:** bopcamping-55rh
- **Ngày:** 15/08/2026
- **Trạng thái:** đã chốt với chủ shop, đang triển khai

## 1. Vấn đề

Shop thu tiền COD. Khách muốn chuyển khoản thì admin phải đọc số tài khoản qua Zalo,
khách tự gõ số tiền và nội dung — sai một chữ là mất công dò sao kê. Cần một mã QR
có **sẵn số tiền và sẵn nội dung chuyển khoản** cho từng đơn.

## 2. Phạm vi

**Có:**

- Sinh URL ảnh QR (SePay/VietQR) cho một đơn thuê.
- Admin xem QR trong trang chi tiết đơn, tải ảnh về để gửi khách qua Zalo.
- Khách tự xem QR đơn của mình ở trang tra cứu đơn và trang tài khoản.

**Không có (đã cân nhắc và loại):**

- **Webhook SePay + tự đối soát.** Từng nằm trong phạm vi, chủ shop bỏ ngày 15/08/2026:
  admin tự kiểm tiền về rồi bấm nút "đã thu" như đang làm. Kéo theo: không có bảng lưu
  giao dịch, không có logic khớp tiền, không có xử lý lệch tiền.
- **QR trong email xác nhận đơn.** Ảnh trong mail là bản đóng băng; đơn sửa giá sau đó
  thì QR trong hộp thư khách vẫn in số tiền cũ, khách quét là chuyển sai. Web thì mỗi
  lần mở là dựng lại URL theo giá hiện tại nên không có bản cũ nào tồn tại.
- **Tách QR riêng cho tiền thuê và tiền cọc.** Một QR cho tổng `amount_due`.

## 3. Quyết định thiết kế

### 3.1 Dựng URL ảnh của SePay, không tự sinh QR

`PaymentQrService` ráp một URL trỏ tới `https://qr.sepay.vn/img`, React chỉ việc `<img src>`.
Không lưu file, không gọi API, không thêm thư viện.

Hai cách còn lại đã loại:

- **Tự sinh QR trong app** (dựng payload EMVCo VietQR + thư viện sinh ảnh): không phụ
  thuộc bên ngoài, nhưng phải tự implement TLV theo chuẩn EMVCo và CRC16, thêm
  dependency, vẫn phải bám đúng format tài khoản ảo SePay. Nhiều chỗ sai, đổi lại chỉ
  bớt được một lần tải ảnh.
- **Gọi API SePay tạo QR rồi lưu ảnh về**: thừa, vì endpoint ảnh vốn stateless. Lưu về
  chỉ tạo thêm ảnh cũ lệch số tiền — đúng cái bẫy đã né khi bỏ QR trong email.

Đánh đổi đã chấp nhận: SePay sập thì mất ảnh (không mất tiền), và trình duyệt khách gọi
thẳng `qr.sepay.vn` nên mã đơn + số tiền đi sang bên thứ ba. Bên thứ ba đó chính là
SePay, nơi vốn đã giữ toàn bộ dữ liệu giao dịch này, nên không phát sinh phơi nhiễm mới.

### 3.2 Cấu hình tài khoản nhận tiền để trong `.env`

`config/services.php` đọc `SEPAY_BANK`, `SEPAY_ACCOUNT`, `SEPAY_HOLDER`.

Đã cân nhắc để trong `SiteSetting` cho chủ shop tự sửa không cần gọi dev. Chọn `.env`
vì gõ nhầm số tài khoản là tiền chuyển sang người lạ, mà `.env` thì phải deploy mới
đổi được — chính bước deploy là lớp chặn. Không có secret nào ở đây (số tài khoản và
tên chủ vốn hiện công khai ngay trên QR).

### 3.3 Nội dung chuyển khoản bỏ dấu gạch

`des` = mã đơn đã lược hết ký tự không phải chữ/số:

| Mã đơn | Nội dung CK |
|---|---|
| `BOP-1485E3` | `BOP1485E3` |
| `BOP-1485E3-1` (đơn con) | `BOP1485E31` |

Tài liệu VietQR ghi tham số `des` chỉ nhận chữ và số. Giữ dấu gạch thì tuỳ ngân hàng mà
bị cắt hoặc thay ký tự, nội dung về sao kê không còn đọc được.

Mọi chỗ hiện QR **phải hiện kèm chuỗi nội dung CK dưới dạng chữ**. Không có webhook thì
đây là đầu mối đối soát tay duy nhất của admin — giấu đi là bắt admin tự đoán cần dò gì
trong sao kê.

### 3.4 Đơn cha–con

Đơn thuê nhiều khoảng ngày bị `OrderSplitter` tách thành đơn cha (vỏ chứa, không món)
và N đơn con, mỗi con một đợt giao/trả với cọc riêng. **Mỗi đơn con một QR; đơn cha
không có QR** — khớp với thực tế giao nhận và với việc `markPaid()` đánh dấu theo từng
đơn con.

## 4. Thành phần

### `app/Services/PaymentQrService.php`

Nguồn **duy nhất** dựng URL QR. Không nơi nào khác được tự ráp — cùng nguyên tắc
`AvailabilityService` và `MediaVariantService` đang theo.

```php
urlFor(Order $order, bool $download = false): ?string
transferContentFor(Order $order): string
```

`urlFor()` ráp `acc`, `bank`, `holder` từ config; `amount` = `$order->amount_due`;
`des` = `transferContentFor()`; cộng `template=compact`, `showinfo=true`, `fullacc=true`.
Cờ `$download` thêm `download=true` để SePay trả về kèm `Content-Disposition`.

### Điều kiện hiện QR

`urlFor()` trả `null` khi bất kỳ điều nào sau đây sai. Giao diện không suy luận lại —
có prop thì vẽ, `null` thì thôi.

| Điều kiện | Lý do |
|---|---|
| config có đủ `bank` + `account` | thiếu thì URL vô nghĩa |
| `$order->isConfirmed()` | đơn còn `pending` thì **giá chưa chắc**; QR in số tiền sai còn tệ hơn không có QR |
| `amount_due > 0` | không có gì để thu |
| `payment_status !== 'full'` | thu đủ rồi mà còn chìa QR là mời khách trả lần hai |
| `! $order->is_parent` | mỗi đơn con một QR; cha chỉ là vỏ chứa |

### Hiển thị

| Trang | Nội dung |
|---|---|
| `Admin/Orders/Show` | QR + số tiền + nội dung CK + nút **Tải ảnh QR** |
| `OrderLookup` | QR + số tiền + nội dung CK |
| `Account` | QR + số tiền + nội dung CK |

Dùng chung `resources/js/Components/PaymentQr.tsx`, nhận `url`, `amount`, `content`, và
`downloadUrl` tuỳ chọn (có thì hiện nút tải).

Nút tải dựa vào `download=true` của SePay chứ không dùng thuộc tính `download` của thẻ
`<a>` — thuộc tính đó bị trình duyệt bỏ qua với link khác origin.

## 5. Test

**Unit — `PaymentQrService`**

- URL chứa đủ tham số; `amount` đúng bằng `amount_due`.
- `des` chuẩn hoá đúng cho cả đơn thường lẫn đơn con.
- Trả `null` ở từng ca: thiếu config, chưa `isConfirmed()`, `amount_due` = 0, đã thu đủ,
  đơn cha.
- `download=true` chỉ xuất hiện khi được yêu cầu.

**Feature**

- Prop QR có mặt ở `Admin/Orders/Show` và `OrderLookup`.
- Đơn `pending` không lộ QR.
- Đơn cha không có QR.

**Component (Vitest)**

- `PaymentQr` vẽ đúng ảnh, số tiền, nội dung CK.
- Nút tải chỉ hiện khi có `downloadUrl`.

## 6. Không đụng tới

`markPaid()`, `payment_status`, `FinanceService`, `payment_method` (vẫn `cod`).

Tính năng này **chỉ sinh ảnh QR**. Không viết một dòng nào vào trạng thái tiền của đơn.

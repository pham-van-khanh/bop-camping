# Design Spec — QR thanh toán SePay

- **Bead:** bopcamping-55rh, bopcamping-pew1
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

`urlFor()` ráp `acc`, `bank`, `holder` từ config; `amount` = **`$order->outstanding_due`**;
`des` = `transferContentFor()`; cộng `template=compact`, `showinfo=true`, `fullacc=true`.
Cờ `$download` thêm `download=true` để SePay trả về kèm `Content-Disposition`.

> **`outstanding_due`, KHÔNG phải `amount_due`.** Hai khoản thu độc lập nên khách trả tiền
> thuê trước rồi còn nợ mỗi cọc là chuyện thường. Bản đầu luôn ghi `amount_due`, nên đơn
> 540k đã thu 240k tiền thuê vẫn hiện QR đòi đủ 540k — khách quét là **chuyển thừa đúng
> 240k**. Accessor `Order::$outstanding_due` là nguồn duy nhất cho "còn phải thu bao nhiêu";
> chỗ nào đòi tiền khách phải dùng nó.

### Điều kiện hiện QR — luật KHÁCH và luật ADMIN tách đôi (bopcamping-pew1)

`urlFor()` trả `null` khi bất kỳ điều nào sau đây sai. Giao diện không suy luận lại —
có prop thì vẽ, `null` thì thôi.

Chung cho cả hai:

| Điều kiện | Lý do |
|---|---|
| config có đủ `bank` + `account` | thiếu thì URL vô nghĩa |
| `outstanding_due > 0` | không còn đồng nào để thu — gồm luôn ca đã thu đủ cả hai khoản |
| `! $order->is_parent` | mỗi đơn con một QR; cha chỉ là vỏ chứa |
| status `!== 'cancelled'` | đơn huỷ thì không đòi tiền nữa |

Rồi tách theo người xem:

| Người xem | Điều kiện thêm | Lý do |
|---|---|---|
| **Khách** | `status === 'pending'` | Quy trình shop là khách chuyển tiền **xong** mới xác nhận đơn. Đơn đã xác nhận nghĩa là đã trả — còn chìa QR là mời chuyển lần hai. |
| **Admin** | không có | Admin là người **gửi** QR đi đòi tiền. Ẩn theo luật khách thì admin chỉ thấy QR đúng lúc khách đã hết cần. Đây cũng là đường thoát cho đơn lỡ xác nhận khi khách chưa trả: admin vẫn tải được ảnh gửi tay. |

> **Đã đảo chiều so với bản đầu.** Bản đầu chỉ hiện QR khi `isConfirmed()`, với lý do đơn
> `pending` thì giá chưa chắc (shop còn sửa lịch/phụ phí). Chủ shop chốt lại ngày
> 15/08/2026 theo quy trình thật. Rủi ro còn lại được ghi nhận và chấp nhận: khách trả
> theo giá lúc đặt, nếu sau đó admin sửa giá thì bù trừ tay.

### Hiển thị

| Trang | Nội dung |
|---|---|
| `Admin/Orders/Show` | QR + số tiền + nội dung CK + nút **Tải ảnh QR** |
| `OrderLookup` | Tình trạng thanh toán + QR (khi đơn còn `pending`) |
| `Account` | Tình trạng thanh toán + QR (khi đơn còn `pending`) |

Dùng chung `resources/js/Components/PaymentQr.tsx`, nhận `url`, `amount`, `content`, và
`downloadUrl` tuỳ chọn (có thì hiện nút tải).

### Tình trạng thanh toán cho khách (bopcamping-pew1)

`rental_paid` / `deposit_paid` trước đây **không hề ra tới khách** ở cả hai trang, nên
khách chuyển khoản xong không có cách nào biết shop đã ghi nhận chưa — chỉ còn nước nhắn
hỏi. Nay shape khách có thêm `rental_due`, `rental_paid`, `deposit_paid`, render bằng
`resources/js/Components/PaymentStatus.tsx`.

Hai khoản báo **độc lập** (khớp đúng `markPaid('rental'|'deposit')`), đơn không cọc thì
không đẻ ra dòng cọc luôn "chưa nhận". Chữ dùng cố ý trung tính với hình thức trả
("Shop đã nhận", không phải "đã chuyển khoản") vì đơn COD trả tiền mặt lúc nhận đồ cũng
đi qua đúng hai cột này.

Đơn gộp: khối này nằm trong **từng đợt**, không gộp ở cấp cha — tiền thu theo từng đơn con.

Nút tải dựa vào `download=true` của SePay chứ không dùng thuộc tính `download` của thẻ
`<a>` — thuộc tính đó bị trình duyệt bỏ qua với link khác origin.

## 5. Test

**Unit — `PaymentQrService`**

- URL chứa đủ tham số; `amount` đúng bằng `amount_due`.
- `des` chuẩn hoá đúng cho cả đơn thường lẫn đơn con.
- Trả `null` ở từng ca: thiếu config, `amount_due` = 0, đã thu đủ, đơn cha, đơn huỷ.
- Khách chỉ thấy QR ở `pending`; `confirmed`/`renting`/`returned`/`cancelled` đều `null`.
- Admin vẫn thấy QR ở `pending`/`confirmed`/`renting`.
- `download=true` chỉ xuất hiện khi được yêu cầu.

**Feature**

- Prop QR có mặt ở `Admin/Orders/Show` (kèm `download_url`) và `OrderLookup` (không kèm).
- Đơn đã xác nhận không còn lộ QR ở trang tra cứu.
- `rental_due` / `rental_paid` / `deposit_paid` ra tới cả `OrderLookup` lẫn `Account`.

**Component (Vitest)**

- `PaymentQr` vẽ đúng ảnh, số tiền, nội dung CK; nút tải chỉ hiện khi có `downloadUrl`.
- `PaymentStatus` báo hai khoản độc lập; đơn không cọc không có dòng cọc; câu nhắc chờ
  chỉ hiện khi còn khoản chưa nhận.

## 6. Bảo mật

**Rò referrer sang bên thứ ba.** Trang tra cứu mang SĐT khách ngay trên URL
(`/tra-cuu?code=…&phone=…`), mà ảnh QR tải từ miền SePay. Trình duyệt hiện đại mặc định
`strict-origin-when-cross-origin` nên chỉ gửi origin — nhưng mặc định là thứ đổi được, và
site chưa đặt `Referrer-Policy` riêng. Thẻ `<img>` khoá cứng `referrerPolicy="no-referrer"`,
link tải của admin thêm `rel="noreferrer"`. Đã đo: ảnh vẫn tải bình thường khi khoá.

**Chống dò `/tra-cuu`.** Route này trước đây **không có throttle**, trong khi hầu hết
endpoint công khai khác trong `routes/web.php` đều có. Mã đơn sinh từ `uniqid()` nên đoán
được kha khá, và từ bopcamping-pew1 trang này trả cả tình trạng thu tiền lẫn QR — tức
phần thưởng khi dò được đã tăng lên. Đặt `throttle:60,1` (đã đo: đúng 60 lượt/phút/IP rồi
429). Cặp mã đơn + SĐT vẫn là lớp chặn chính; throttle chỉ chặn máy quét.

**Không có lỗ phân quyền.** Toàn bộ prefix `admin` nằm trong nhóm middleware `admin`, kể
cả route đánh dấu thu tiền. Khách chỉ tra được đơn khi khớp CẢ mã đơn lẫn SĐT.

**Không có đường tiêm mã.** Mọi tham số URL đều là hằng, cột số, hoặc chuỗi đã lược về
`[A-Za-z0-9]`, rồi đi qua `http_build_query(..., PHP_QUERY_RFC3986)`. Không có secret nào
trong config này (số tài khoản + tên chủ vốn hiện công khai ngay trên QR).

## 7. Không đụng tới

`markPaid()`, `payment_status`, `FinanceService`, `payment_method` (vẫn `cod`).

Tính năng này **chỉ sinh ảnh QR**. Không viết một dòng nào vào trạng thái tiền của đơn.

# ADR: Thanh toán QR qua SePay (webhook biến động số dư, không dùng cổng thanh toán)

**Status**: Proposed
**Ngày**: 2026-07-28
**Bead**: bopcamping-ust (epic gốc) → epic mới cho SePay
**Thay thế**: nhánh `feature/checkout-2tier-payment` (commit `006284e`, park 28/06/2026) — **bỏ**, xem mục Alternatives

## Context

Hiện tại BopCamping thu tiền **thuần COD**: khách đặt đơn, admin tự mở app ngân hàng
kiểm tra rồi bấm tay nút đánh dấu ở `/admin/orders/{order}/payment`
(`payment_status` ∈ `unpaid|deposit|full`, thêm bởi bopcamping-7be). Hai vấn đề:

1. **Đặt ảo khoá tồn kho.** ⚠️ **ĐÍNH CHÍNH 2026-08-01:** câu dưới đây SAI. Đã kiểm
   `app/Models/Order.php:452` — `Order::activeStatuses()` trả `['confirmed', 'renting']`, tức đơn
   `pending` **KHÔNG** tính vào `AvailabilityService` và **không** khoá tồn kho. Tiền đề này của epic
   SePay cần xem lại trước khi triển khai. (Giữ nguyên văn cũ bên dưới để đối chiếu.)
   ~~Đơn `pending` vẫn tính vào `AvailabilityService`, nên khách
   đặt cho vui cũng chặn khách thật — đau nhất vào cuối tuần/cao điểm.
2. **Admin phải đối soát tay.** Mỗi đơn là một lần mở app bank, tìm giao dịch, bấm nút.
   Không scale, dễ sai, không có audit trail.

`tech-strategy.md` hiện ghi *"Không thanh toán online (chỉ COD) → chưa tích hợp cổng thanh toán"*.
ADR này là thay đổi **có chủ đích** vào golden path đó; `tech-strategy.md` sẽ cập nhật theo.

User yêu cầu nghiên cứu SePay (https://developer.sepay.vn/vi) làm giải pháp QR.

### Phát hiện quan trọng khi nghiên cứu

SePay có **hai sản phẩm khác nhau**, thường bị lẫn:

| | **A. SePay Webhooks** (biến động số dư) | **B. Cổng thanh toán** (`pgapi.sepay.vn`) |
|---|---|---|
| Tiền chảy đâu | **Thẳng vào TK ngân hàng shop** — SePay không giữ tiền | Qua SePay rồi về shop |
| API tạo đơn | Không có. Tự tạo đơn, tự sinh QR bằng URL ảnh | Có (hosted checkout) |
| Thẻ quốc tế | Không | Visa/MC/JCB + 3DS |
| Giấy tờ | Không cần | Cần giấy tờ DN, duyệt 3–7 ngày |
| Phí | 200–500đ/giao dịch (fix theo gói) | NAPAS 0.3%/giao dịch |
| Xác nhận | Webhook biến động số dư | IPN webhook |

Nguồn: [Cổng thanh toán VietQR](https://sepay.vn/cong-thanh-toan-vietqr.html) ·
[Giới thiệu cổng thanh toán](https://developer.sepay.vn/vi/cong-thanh-toan/gioi-thieu)

## Decision

### 1. Chọn hướng A — SePay Webhooks + VietQR động. Không dùng cổng thanh toán.

Shop chỉ nhận chuyển khoản nội địa, không nhận thẻ quốc tế. Hướng A: tiền vào thẳng
TK shop, không cần giấy tờ doanh nghiệp, và **QR sinh bằng URL ảnh public — không cần API key**:

```
https://vietqr.app/img?acc=<số TK>&bank=Vietcombank&amount=450000&des=BOP123456&template=compact
```

Nhúng thẳng `<img src>`. Docs hiện hành dùng `vietqr.app/img`; `qr.sepay.vn/img` là alias
legacy (đã kiểm: cùng backend, cùng byte size) → **đặt base URL vào config** để đổi được.

### 2. Ngân hàng: Vietcombank. Đối soát theo nội dung CK, không dùng VA.

User xác nhận VCB được SePay hỗ trợ webhook. VCB **không yêu cầu chuỗi cố định** trong
nội dung CK (chỉ VietinBank cá nhân/HKD mới bắt buộc chuỗi `SEVQR`) → nội dung CK sạch,
chỉ chứa mã đơn.

Cấu hình mẫu mã thanh toán ở `my.sepay.vn`: **prefix `BOP` + 6 ký tự số**.
SePay bóc `code` từ nội dung CK theo mẫu này và trả về trong payload webhook.

Ràng buộc VA theo bank (để tham chiếu nếu đổi bank sau):
- Bắt buộc VA: BIDV, MSB, KienlongBank, OCB
- VA tuỳ chọn: ACB, MBBank
- Chỉ nội dung CK: Sacombank, TPBank, VPBank, VietinBank (chỉ TK doanh nghiệp)

### 3. Số tiền nhúng vào QR (QR động)

Tham số `amount` đưa số tiền vào payload QR (field 54 theo chuẩn EMVCo/VietQR) → app
ngân hàng prefill sẵn cả số tiền lẫn nội dung CK, khách chỉ bấm xác nhận.

**Giới hạn phải ghi nhận:** docs SePay *không tuyên bố* số tiền bị khoá không cho sửa.
Theo chuẩn VietQR, QR có field 54 là QR động và app ngân hàng VN thường hiển thị số tiền
ở dạng không sửa được — nhưng đó là hành vi của **app từng ngân hàng**, không phải thứ
SePay hay ta kiểm soát. Vì vậy vẫn cần nhánh xử lý lệch tiền (mục 6).

### 4. Bốn lựa chọn trả trước — QR là lớp *tự nguyện* đặt trên COD

Hai khoản tiền độc lập (cọc, phí thuê), mỗi khoản CK trước hoặc thu khi nhận đồ:

| `prepay_choice` | CK trước qua QR | Thu khi nhận đồ |
|---|---|---|
| `none` | — | Cọc + phí thuê (COD thuần, hành vi hiện tại) |
| **`rental`** ⭐ mặc định | Phí thuê | Cọc |
| `deposit` | Cọc | Phí thuê |
| `both` | Cọc + phí thuê | — |

**Vì sao mặc định là `rental`, không phải `deposit`:** phí thuê là doanh thu thật, chốt
trước được thì tốt; cọc là tiền **hoàn lại** — CK qua rồi CK về là 2 lần giao dịch +
2 lần đối soát, thu/trả tiền mặt lúc giao-nhận đồ gọn hơn nhiều.

**`none` vẫn giữ** (quyết định của user): không chặn khách quen COD, tránh giảm tỷ lệ
chốt đơn. Hệ quả: QR chỉ giảm đặt ảo ở phần khách *chọn* trả trước, không triệt tiêu.

### 5. `payment_status` chuyển thành accessor dẫn xuất — bỏ cột

Enum hiện tại `unpaid|deposit|full` **không biểu diễn được** trạng thái "đã trả phí thuê,
chưa trả cọc" — mà đó chính là luồng mặc định (`rental`). Thay bằng 2 mốc thời gian độc lập:

```
deposit_paid_at  timestamp nullable
rental_paid_at   timestamp nullable
```

`payment_status` thành accessor tính từ 2 cột này → **một nguồn sự thật duy nhất**, không
thể lệch. Giữ cột song song với 2 timestamp sẽ tạo 2 nguồn sự thật, vi phạm DRY.

Cần **backfill** đơn cũ: `deposit` → set `deposit_paid_at = updated_at`;
`full` → set cả hai; `unpaid` → để null.

### 6. Xác thực webhook: HMAC-SHA256, không dùng API Key trần

SePay hỗ trợ 4 kiểu: None, API Key, **HMAC-SHA256** (docs khuyến nghị), OAuth 2.0.
Basic Auth **không** có cho webhook (nhiều tutorial cũ nói sai điều này).

Chọn HMAC-SHA256:
- Header `X-SePay-Signature: sha256={hex}` + `X-SePay-Timestamp: {unix_seconds}`
- Chuỗi ký: `{timestamp}.{raw_body}`, HMAC-SHA256 với Secret Key, output hex
- **Phải dùng raw body**: `$request->getContent()`. Dùng `json_encode($request->all())`
  sẽ sai signature (khác unicode escape / thứ tự key / whitespace)
- So sánh bằng `hash_equals` (constant-time)
- Kiểm `X-SePay-Timestamp` lệch **±5 phút** để chống replay

IP whitelist coi là **lớp phụ**, không phải cơ chế duy nhất — docs ghi rõ danh sách IP
"có thể mở rộng" nên không hardcode:
```
172.236.138.20  172.233.83.68   171.244.35.2
151.158.108.68  151.158.109.79  103.255.238.139
2400:8905::2000:8cff:fe98:45cd  2600:3c15::2000:8aff:fedd:874b
```

### 7. Idempotency là bắt buộc, không phải tuỳ chọn

SePay retry webhook **8 lần** (1 + 7 retry), backoff Fibonacci 0→1→1→2→3→5→8→13 phút,
**tổng ~33 phút**, giữ nguyên `id` qua mọi lần retry.

→ Cột `order_payments.sepay_transaction_id` **UNIQUE**. Thiếu ràng buộc này là cộng tiền
trùng tới 8 lần.

### 8. Lệch tiền: thừa thì nhận, thiếu thì gắn cờ

- `transfer_amount >= expected_amount` → `paid`, set mốc thời gian tương ứng. Phần thừa
  ghi chú để trả lại lúc giao đồ. (Cùng cách SePay làm: `amount <= :amount`.)
- `transfer_amount < expected_amount` → `mismatched`, thông báo admin, **không tự xác nhận**.

### 9. Tự huỷ đơn chưa CK sau 60 phút — chỉ đơn có `prepay_choice != none`

Đơn `none` là đơn hợp lệ, **không bị huỷ** (giữ hành vi hiện tại).

**60 phút là con số có lý do**: cửa sổ retry webhook là ~33 phút. Ngưỡng 30 phút sẽ huỷ
oan đơn khách đã CK thật mà webhook tới muộn. 60 phút cho biên an toàn gần gấp đôi.

Thêm lớp bảo vệ: job huỷ **gọi API SePay xác minh lần cuối** trước khi huỷ, không tin
mỗi trạng thái DB.

### 10. Job đối soát định kỳ làm lưới an toàn cho webhook

`GET https://userapi.sepay.vn/v2/transactions?webhook_success=0` (Bearer token,
**rate limit 3 req/s**) → cứu giao dịch webhook fail hẳn sau ~33 phút.

⚠️ Tên field **API v2 khác webhook**: API v2 snake_case (`amount_in`, `reference_number`),
webhook camelCase (`transferAmount`, `referenceCode`) → **tách DTO riêng**, không dùng chung.

### 11. Dev/test trên Test mode, không chuyển tiền thật

SePay có sandbox từ 08/05/2026: Test mode ở `my.sepay.vn` (icon ống nghiệm) có form
"Mô phỏng giao dịch" sinh payload **y hệt Live**, không đụng TK bank thật. Webhook test
tách biệt hoàn toàn với Live. API sandbox: `https://userapi-sandbox.sepay.vn/v2`.

Gói **FREE 0đ/tháng, 50 giao dịch/tháng, có webhook + API** → đủ dev và chạy thử MVP.
(Gói SHOP 99k/tháng **không có API** → đừng chọn.)

## Alternatives considered

### A. Cổng thanh toán SePay (`pgapi.sepay.vn`)
**Bỏ.** Có hosted checkout + thẻ quốc tế, nhưng: cần giấy tờ doanh nghiệp (duyệt 3–7 ngày),
phí NAPAS 0.3%/giao dịch thay vì 200–500đ fix, tiền qua trung gian. Shop không nhận thẻ
quốc tế nên toàn bộ phần đắt thêm không dùng tới.
Ghi nhận: path + payload endpoint tạo đơn (`checkout/init`) **không xác minh được** từ
docs public — URL đoán trả 404. Nếu sau này cần hướng này, phải lấy từ dashboard/CSKH.

### B. Tiếp tục nhánh `feature/checkout-2tier-payment` rồi thêm SePay lên trên
**Bỏ** (quyết định của user). Nhánh đó có design spec + 144 dòng test và model
`payment_option` ∈ `full|deposit`. Nhưng model 2 lựa chọn đó **không biểu diễn được**
`rental` (chỉ CK phí thuê) — chính là luồng ưu tiên của user. Sửa nó tốn công gần bằng
làm lại, và mang theo nợ thiết kế. Thiết kế lại từ schema hiện tại.
Nội dung còn giá trị của nhánh đó (nhãn hiển thị gộp, edge cases huỷ/hỏng đồ) được kế thừa
vào PRD mới.

### C. Package `sepayvn/laravel-sepay` (chính thức) hoặc `datlechin/sepay-php`
**Bỏ.** Package chính thức (~24 star) thiên về use-case *nạp tiền vào ví user*, không
phải đơn thuê theo ngày; không xác minh được nó đã hỗ trợ HMAC-SHA256 và API v2 chưa.
`datlechin/sepay-php` quá mỏng (~6 star, 5 commit). Ta cần đúng một việc: match `code` →
`orders`. Tự viết ~100 dòng, kiểm soát được, không phụ thuộc bên thứ ba cho luồng tiền.

### D. Virtual Account (VA) thay vì đối soát nội dung CK
**Bỏ ở giai đoạn này.** VA cho mỗi đơn một số TK ảo → không phụ thuộc nội dung CK, đối
soát chính xác hơn. Nhưng VCB không bắt buộc VA, và VA thêm việc quản lý vòng đời (cấp,
thu hồi, giới hạn số VA theo gói). Để ngỏ đường nâng cấp nếu thực tế khách nhập sai nhiều.

## Consequences

### Tích cực
- Đối soát tự động: bỏ được cú bấm tay của admin cho đơn CK qua QR.
- Giảm đặt ảo ở phần khách chọn trả trước + tự nhả tồn kho sau 60 phút.
- Tiền vào thẳng TK shop, không qua trung gian giữ tiền.
- `order_payments` cho audit trail đầy đủ (raw payload, reference code, thời điểm).
- Chi phí gần 0 cho MVP (gói FREE), 200–500đ/giao dịch khi scale.
- `payment_status` dẫn xuất → hết nguy cơ lệch dữ liệu.

### Tiêu cực / rủi ro phải chấp nhận

**1. Giao credentials internet banking cho bên thứ ba (rủi ro lớn nhất).**
Docs SePay ghi rõ: phải "điền thông tin đăng nhập internet banking", hoặc OAuth *tuỳ
ngân hàng* ([tài khoản ngân hàng](https://developer.sepay.vn/vi/sepay-webhooks/tai-khoan-ngan-hang)).
Không có tài liệu nào xác nhận SePay dùng **API chính thức của ngân hàng** cho mọi bank.
Giảm thiểu:
- Dùng **TK riêng chỉ để nhận tiền thuê**, không phải TK chính của shop.
- Bật mọi lớp bảo mật bank cho phép (hạn mức chuyển ra thấp, thông báo mọi giao dịch).
- Kiểm tra VCB có hỗ trợ OAuth không — nếu có, ưu tiên OAuth thay vì nhập credentials.

**2. Phụ thuộc uptime SePay.** SePay down → không nhận được xác nhận tự động. Giảm thiểu:
job đối soát định kỳ + admin vẫn đánh dấu tay được (cùng đi qua `PaymentMatcher`).

**3. Đặt ảo chỉ giảm một phần** vì `none` vẫn được phép.

**4. Cần cron scheduler trên server** cho job tự huỷ + đối soát — hiện **chưa có**
(bopcamping-ybsm còn open). Đây là **blocker cho production**, không phải cho dev.

**5. Thay đổi golden path** — `tech-strategy.md` phải cập nhật (bỏ dòng "chưa tích hợp
cổng thanh toán", thêm SePay vào bảng Data/Integrations).

### Điểm chưa xác minh được (không suy diễn — cần kiểm thực tế)
1. HMAC-SHA256 có sẵn ở **mọi gói** (kể cả FREE) hay không — docs không nói theo gói.
   Changelog không nhắc HMAC dù trang Xác thực mô tả chi tiết → **test thực tế trước khi
   dựa vào nó ở production**. Fallback: API Key + IP whitelist.
2. Con số cụ thể các hạn mức Test mode (số webhook, số giao dịch mô phỏng/ngày).
3. Mapping chính xác bậc giao dịch ↔ giá gói STARTUP, và định nghĩa chính thức
   "1 giao dịch" (chỉ tiền vào, hay cả ra) → confirm với SePay trước khi cam kết chi phí.
4. Số tiền trong QR động có bị app VCB khoá không cho sửa hay không → test thực tế.

## References

- [Bắt đầu nhanh](https://developer.sepay.vn/vi/sepay-webhooks/bat-dau-nhanh)
- [Tích hợp webhook](https://developer.sepay.vn/vi/sepay-webhooks/tich-hop-webhook)
- [Xác thực webhook](https://developer.sepay.vn/vi/sepay-webhooks/xac-thuc)
- [Xử lý lỗi / retry](https://developer.sepay.vn/vi/sepay-webhooks/xu-ly-loi)
- [Cấu hình mã thanh toán](https://developer.sepay.vn/vi/sepay-webhooks/cau-hinh-ma-thanh-toan)
- [Tài khoản ngân hàng](https://developer.sepay.vn/vi/sepay-webhooks/tai-khoan-ngan-hang)
- [Tạo QR code](https://developer.sepay.vn/vi/tien-ich-khac/tao-qr-code)
- [Test mode](https://developer.sepay.vn/vi/tien-ich-khac/test-mode)
- [API v2 danh sách giao dịch](https://developer.sepay.vn/vi/sepay-api/v2/giao-dich/danh-sach)
- [Địa chỉ IP](https://developer.sepay.vn/vi/dia-chi-ip)
- [Bảng giá](https://sepay.vn/bang-gia.html)

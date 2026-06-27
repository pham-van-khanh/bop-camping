# Design Spec — Checkout thanh toán 2 tầng (Cọc + Phí thuê)

- **Beads epic:** bopcamping-ust · **Giai đoạn 1:** bopcamping-452
- **Branch:** `feature/checkout-2tier-payment` (không merge cho tới khi user test OK)
- **Nguồn:** thiết kế BA (ảnh user gửi 26/06/2026) — đã điều chỉnh theo ràng buộc dự án.

## 1. Bối cảnh & ràng buộc

Dự án **chỉ COD, chưa tích hợp cổng thanh toán** (`tech-strategy.md`). Thiết kế gốc giả định trả tiền
online (Credit Card/E-wallet). **Quyết định (user chốt):** thu phí cọc/tiền trả trước bằng
**chuyển khoản thủ công**, admin xác nhận đã nhận. Không tích hợp cổng ở giai đoạn này.

## 2. Hai chi phí

| Chi phí | Ý nghĩa | Thu khi nào |
|---|---|---|
| **Phí Cọc** (`deposit_total`) | Giữ chỗ + đảm bảo đồ không hỏng/mất | Trả trước qua CK (cả 2 option) |
| **Phí Thuê** (`total_price − discount_total`) | Tiền sử dụng dịch vụ theo ngày | Tuỳ option: trả trước CK **hoặc** COD khi nhận |

## 3. Hai option checkout

| Option | Trả trước (CK) | Trả khi nhận (COD) |
|---|---|---|
| **`full`** — Trả toàn bộ trước | Cọc + Phí thuê (− giảm giá) | 0 |
| **`deposit`** — Trả cọc trước | Cọc | Phí thuê (− giảm giá) |

- `prepayAmount(full)`  = `deposit_total + (total_price − discount_total)` = `amount_due`
- `prepayAmount(deposit)` = `deposit_total`
- `codAmount(full)` = 0 · `codAmount(deposit)` = `total_price − discount_total`

## 4. Mô hình dữ liệu (orthogonal: xử lý ⟂ thanh toán)

Giữ nguyên **trạng thái xử lý** (`orders.status`): `pending → confirmed → renting → returned / cancelled`
(đang dùng cho tồn kho `activeStatuses`, filter & thống kê admin — không đụng để tránh rủi ro).

Thêm **lớp thanh toán** (migration mới):

| Cột | Kiểu | Ý nghĩa |
|---|---|---|
| `payment_option` | enum/string `full`\|`deposit`, nullable | Lựa chọn lúc checkout (null = đơn cũ) |
| `deposit_paid_at` | timestamp nullable | Admin đánh dấu đã nhận cọc |
| `rental_paid_at`  | timestamp nullable | Admin đánh dấu đã nhận phí thuê (full: lúc CK; deposit: khi shipper thu COD) |

## 5. Nhãn gộp hiển thị (map sang từ ngữ thiết kế gốc)

Tính từ (`status`, `payment_option`, `deposit_paid_at`, `rental_paid_at`):

| Điều kiện | Nhãn hiển thị (≈ thiết kế gốc) |
|---|---|
| status=pending, chưa trả cọc | Chờ chuyển khoản cọc *(Pending Payment)* |
| đã có deposit_paid_at, chưa đủ | Đã nhận cọc *(Deposit Paid)* |
| full + đã đủ tiền (deposit+rental) | Đã thanh toán đủ *(Payment Confirmed)* |
| status=confirmed | Đã xác nhận · đang xử lý *(Confirmed & Processing)* |
| confirmed + option=deposit + chưa trả thuê | Đã nhận cọc · chờ COD *(Admin Confirmed – COD Pending)* |
| status=renting | Đang cho thuê / đã giao *(Delivered)* |
| status=returned | Đã trả |
| status=cancelled | Đã huỷ |

## 6. Luồng (chuyển khoản thủ công)

**Option full:** đặt đơn (`pending`) → khách CK cọc+thuê → admin gọi confirm + đánh dấu *đã nhận đủ*
(`deposit_paid_at`, `rental_paid_at`) → `confirmed` → giao (`renting`) → trả (`returned`).

**Option deposit:** đặt đơn (`pending`) → khách CK cọc → admin gọi confirm + đánh dấu *đã nhận cọc*
(`deposit_paid_at`) → `confirmed` → book ship; shipper thu phí thuê (COD) → admin đánh dấu
*đã nhận phí thuê* (`rental_paid_at`) khi giao → `renting` → `returned`.

Từ chối bất kỳ lúc nào → `cancelled` (refund cọc nếu đã nhận — xử lý ngoài hệ thống ở GĐ1).

## 7. UI

**Checkout (Cart):** radio chọn option; hiện rõ *Trả trước (CK)* vs *COD khi nhận*; khối thông tin
chuyển khoản (số TK/chủ TK/nội dung = mã đơn) lấy từ `config/shop.php` (env, không phải secret).

**Admin Orders:** thêm cột/badge *Hình thức* (full/deposit) + *Thanh toán* (nhãn gộp mục 5);
nút **Đã nhận cọc** / **Đã nhận phí thuê**; giữ nút đổi trạng thái xử lý + Huỷ.

## 8. Phạm vi theo giai đoạn

- **GĐ1 (branch này):** mục 4–7 + test. Thu tiền = CK thủ công, admin đánh dấu.
- **GĐ sau:** email hoá đơn ghi rõ Cọc/Phí thuê + hình thức; tự động hoá refund; edge cases
  (huỷ sau khi cọc, COD từ chối, hỏng đồ, không nhận hàng); (tuỳ chọn) cổng thanh toán.

## 9. Edge cases (ghi nhận — xử lý GĐ sau)
Huỷ sau khi đã CK cọc → chính sách hoàn? · COD bị từ chối → cho trả online lại? ·
Đồ hỏng → giữ/hoàn cọc? · Khách không nhận → xử lý cọc?

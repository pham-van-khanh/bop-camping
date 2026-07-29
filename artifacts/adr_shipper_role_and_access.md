# ADR — Role shipper: mô hình quyền, đăng nhập và giới hạn dữ liệu

**Ngày:** 2026-07-29 · **Trạng thái:** Accepted · **Quyết định bởi:** chủ shop (yêu cầu 28–29/07/2026)
**Reversibility:** One-Way Door (mức trung bình) — đụng vào lớp xác thực/uỷ quyền; gỡ được nhưng phải xoá tài khoản + cột + middleware.

## 1. Bối cảnh

Trang lịch giao (`/admin/lich-giao`, bopcamping-641t) hiện chỉ admin xem được, và shipper phải dùng chung tài khoản admin của chủ shop. Chủ shop yêu cầu:

1. Có **tài khoản shipper riêng** — vẫn **phải đăng nhập** (dứt khoát KHÔNG có link công khai không cần login, vì rò dữ liệu khách).
2. Admin **gán shipper cho từng đơn**, sắp thứ tự đi giao.
3. Shipper **đánh dấu đã giao / đã thu**.

Hiện trạng xác thực trong repo:

| Vai | Cách đăng nhập | Nơi kiểm quyền |
|---|---|---|
| Khách | SĐT + tên + **OTP email**, không mật khẩu | — |
| Admin | SĐT + **mật khẩu** tại `/admin/login` ([AuthController](app/Http/Controllers/Admin/AuthController.php)) | `users.is_admin` qua [EnsureAdmin](app/Http/Middleware/EnsureAdmin.php), alias `admin` ([bootstrap/app.php:25](bootstrap/app.php:25)) |

Tất cả đều là 1 bảng `users` + guard `web` mặc định. Không có bảng roles/permissions, không dùng gói RBAC.

## 2. Các phương án

| # | Phương án | Ưu | Nhược |
|---|---|---|---|
| A | **`users.is_shipper` boolean + middleware `EnsureShipper`** | Giống hệt quy ước `is_admin` đang có; ít code; không thêm dependency; test đơn giản | Thêm cờ thứ 2 → phải nêu rõ luật khi 1 user vừa admin vừa shipper |
| B | `users.role` enum ('customer','shipper','admin') | 1 nguồn chân lý cho vai | Phải refactor **mọi** chỗ đang đọc `is_admin` (middleware, shared props, UserController, seeder, ~10 test) — rủi ro cao, không đem lại giá trị mới ngay |
| C | Gói RBAC (spatie/laravel-permission) | Mở rộng vô hạn | Quá nặng cho 3 vai của 1 shop; trái `tech-strategy.md` (không thêm dependency ngoài golden path) |
| D | Guard + bảng `shippers` riêng | Tách hẳn khỏi user khách | Trùng lặp logic auth, thêm guard/provider, không cần thiết |

## 3. Quyết định

**Chọn A.**

1. **Cột**: thêm `users.is_shipper` (boolean, default false, index). `is_admin` không đổi.
2. **Luật vai** (viết trong `User`): `isShipper()` = `is_shipper`; admin **không** tự động là shipper (admin muốn tự đi giao thì bật thêm cờ shipper cho chính mình). Một user có thể mang cả hai cờ; khi đó vào được cả 2 khu vực.
3. **Đăng nhập**: route **riêng** `/shipper/dang-nhap` (SĐT + mật khẩu), controller `Shipper\AuthController`, throttle `10,1` theo IP+SĐT. Sai creds → thông báo chung, không tiết lộ tài khoản có tồn tại.
   - **KHÔNG** dùng chung form với `/admin/login`: bead `bopcamping-vo4` dự định thay login admin bằng HTTP Basic Auth, dùng chung sẽ vỡ. Tách route giúp 2 việc độc lập nhau.
   - Đăng nhập thành công phải `session()->regenerate()`; nếu tài khoản không có `is_shipper` → `Auth::logout()` + lỗi.
   - Tài khoản khách (không có mật khẩu thật) không thể lọt vào vì `Auth::attempt` cần mật khẩu khớp; shipper luôn do admin tạo kèm mật khẩu.
4. **Uỷ quyền**: middleware `EnsureShipper` (alias `shipper`) bọc mọi route `/shipper/*`, kiểm `is_shipper`, không phải thì redirect `shipper.login`.
5. **Tạo/sửa tài khoản shipper**: trong trang `/admin/users` đã có (đang chỉ quản admin) — thêm tab "Shipper", dùng lại `store/update/destroy` với validate hiện tại (`phone` regex + unique, `password` min 6). Chỉ admin làm được.

### Giới hạn dữ liệu shipper (data minimization — bắt buộc)

Shipper **chỉ** đọc được:

- Đơn được gán **cho chính mình** (`pickup_shipper_id` hoặc `return_shipper_id` = user id), và chỉ trong **ngày đang xem**.
- Của mỗi đơn: mã đơn, tên khách, SĐT, địa chỉ, danh sách món + số lượng, giờ đã chốt, ghi chú shipper, **số tiền phải thu / phải hoàn của đơn đó**.

> Số tiền là **bắt buộc** cho shipper — thu COD là việc của họ. Đây là điều chỉnh so với nhãn phương án ban đầu ("không thấy tiền"): shipper thấy tiền **của đơn mình giao**, nhưng không thấy doanh thu/thống kê/danh sách khách/CRUD gì khác.

Shipper **không** đọc được: dashboard, thống kê doanh thu, danh sách khách hàng, sản phẩm/voucher/khuyến mãi, đơn của shipper khác, đơn ngày quá khứ ngoài phạm vi ngày xem, email khách.

Shipper **chỉ** ghi được: chuyển trạng thái `confirmed → renting` (đã giao) và `renting → returned` (đã thu) **trên đơn được gán cho mình**. Không sửa giờ, không sửa tiền, không huỷ đơn, không đổi lịch.

Việc đánh dấu tái dùng đúng luồng trạng thái sẵn có (`OrderObserver` vẫn gửi mail cho khách như admin bấm) — không thêm cột "đã giao" song song, tránh 2 nguồn chân lý.

## 4. Hệ quả

**Tích cực**: không thêm dependency; mô hình quyền giống cái đang có nên người sau đọc là hiểu; khu vực shipper tách route/middleware nên phạm vi rò dữ liệu bị chặn ở 1 chỗ; bopcamping-vo4 (Basic Auth cho admin) không bị ảnh hưởng.

**Tiêu cực / phải chấp nhận**:
- Hai cờ boolean (`is_admin`, `is_shipper`) — nếu sau này có vai thứ 4 thì phải làm phương án B thật. Ghi vào đây để người sau biết đường.
- Mật khẩu shipper do admin đặt tay → phải yêu cầu tối thiểu 6 ký tự (theo quy ước hiện có) và nhắc chủ shop không dùng mật khẩu chung. Rủi ro mật khẩu yếu được **chấp nhận có ý thức**, giống ghi chú ở `bopcamping-vo4`.
- `HandleInertiaRequests` phải chia sẻ thêm `auth.user.is_shipper` để FE điều hướng — thêm 1 field vào shared props.

## 5. Bị loại — link công khai không cần login

Chủ shop đã bỏ hẳn phương án link token xem lịch không cần đăng nhập. Lý do giữ trong ADR để không ai đề nghị lại: link kiểu đó là **bearer token trong URL**, bị chuyển tiếp qua Zalo/tin nhắn là bất kỳ ai cũng đọc được **tên, SĐT, địa chỉ nhà** của toàn bộ khách trong ngày; không thể thu hồi từng người; log/history của trình duyệt và ứng dụng chat đều lưu lại.

## 6. Liên quan

- [prd_shipper_delivery_ops.md](artifacts/prd_shipper_delivery_ops.md) — yêu cầu đầy đủ của đợt này.
- [adr_admin_basic_auth.md](artifacts/adr_admin_basic_auth.md) — hướng đổi login admin (bead `bopcamping-vo4`), cố tình không dùng chung với shipper.
- [system_design_admin_user_management.md](artifacts/system_design_admin_user_management.md) — trang `/admin/users` sẽ nhận thêm tab Shipper.
- `.claude/rules/security.md` — Broken Access Control (OWASP A01), CWE-639 (IDOR): mọi truy vấn của shipper phải kẹp theo `shipper_id` của chính họ, không nhận `order_id` tuỳ ý.

# Security audit — khu vực shipper (bopcamping-zzhm)

**Ngày:** 2026-07-29 · **Phạm vi:** nhánh `feature/shipper-ops` (S1–S8) · **Chuẩn:** OWASP Top 10 2021, `.claude/rules/security.md`
**Lý do audit riêng:** đợt này mở **một cửa mới vào dữ liệu khách** (tên, SĐT, địa chỉ nhà, số tiền) cho người ngoài bộ máy quản trị.

## 1. Bề mặt tấn công mới

| Đường | Middleware | Ai vào được |
|---|---|---|
| `GET /shipper/dang-nhap`, `POST` (login) | — (+ throttle 10,1) | công khai |
| `POST /shipper/dang-xuat` | — | công khai (chỉ huỷ phiên) |
| `GET /shipper`, `GET /shipper/lich-giao` | `EnsureShipper` | user có `is_shipper` |
| `PATCH /shipper/don/{order}/da-giao`, `/da-thu` | `EnsureShipper` (+ throttle 60,1) | user có `is_shipper` |
| `PATCH /admin/lich-giao/don/{order}/shipper` | `EnsureAdmin` (+ 60,1) | admin |
| `POST /admin/lich-giao/gan-tat-ca` | `EnsureAdmin` (+ 30,1) | admin |
| `POST /admin/lich-giao/gui-email` | `EnsureAdmin` (+ 20,1) | admin |

Đã đối chiếu bằng `php artisan route:list --path=shipper --json`: **không có route `/shipper/*` nào thiếu middleware** ngoài 3 route đăng nhập/đăng xuất (cố ý).

## 2. Kết quả rà theo OWASP

| Hạng mục | Kết quả | Bằng chứng |
|---|---|---|
| **A01 Broken Access Control** | Pass | Middleware trên toàn nhóm; uỷ quyền theo bản ghi ở `authorizeLeg()`; test: `ShipperMarkLegTest` (5/9 case là case quyền), `ShipperAccessTest` |
| **IDOR (CWE-639)** | Pass | Đổi `{order}` sang đơn của shipper khác / đơn chưa gán → **404** (không 403, không tiết lộ đơn tồn tại). Được gán lượt THU không mở quyền cho lượt GIAO |
| **A01 — leo quyền qua mass assignment** | Pass | `is_shipper` chỉ set trong `Admin\UserController` (sau `EnsureAdmin`); `ProfileController` dùng `$request->validated()` chỉ có `name`/`email`; không có `create($request->all())` nào trong repo |
| **A01 — rò dữ liệu quản trị sang shipper** | Pass (đã thêm test) | Badge `pending_orders`/`pending_reviews`/`pending_feedback` = `null` với shipper (đã có guard `is_admin`, giờ có test khoá) |
| **A01 — dữ liệu khách quá mức cần thiết** | Pass (đã thêm test) | `row()` không trả `customer_email`; test khẳng định email khách không xuất hiện trong HTML trang shipper |
| **A07 Auth Failures** | Pass có ghi chú | Throttle 10,1 cho login; `session()->regenerate()` sau khi vào; đúng creds nhưng không phải shipper → `Auth::logout()` ngay; thông báo lỗi **chung** cho cả 3 trường hợp (không dò được tài khoản tồn tại) — có test |
| **A07 — mật khẩu yếu** | **Rủi ro đã chấp nhận** | Tối thiểu 6 ký tự theo quy ước sẵn có của trang admin; admin đặt tay. Ghi trong [adr_shipper_role_and_access.md](artifacts/adr_shipper_role_and_access.md) mục 4 |
| **A01 — thu hồi quyền** | Pass | Tắt vai/xoá tài khoản có hiệu lực **ngay request kế tiếp** (middleware đọc cờ mỗi lần); các lượt sắp tới tự về "chưa gán" — test trong `AdminShipperUsersTest` |
| **A03 Injection** | Pass | Toàn bộ qua Eloquent/query builder; `orderByRaw` chỉ nội suy **tên cột từ hằng số trong service**, không nhận input người dùng |
| **A04 Insecure Design** | Pass | Đánh dấu đã giao/thu đi qua đúng luồng `status` sẵn có → không có nguồn chân lý thứ hai; không thêm cột "đã giao" song song |
| **A09 Logging Failures** | Pass | Đổi vai/xoá tài khoản ghi `Log::info('admin.user.role_changed' / '.deleted')` kèm actor + target |
| **Data routing ra ngoài** | Pass | **Không** gửi địa chỉ/SĐT khách sang API thứ ba nào: nút "Chỉ đường" chỉ là link `google.com/maps` mở trên máy shipper; không dùng Zalo OA/ZNS |
| **A02 Cryptographic Failures** | Pass | Mật khẩu cast `hashed`; không log mật khẩu; mail lịch chỉ gửi tới email của chính shipper |

## 3. Phát hiện & xử lý

| # | Mức | Phát hiện | Xử lý |
|---|---|---|---|
| 1 | Low (latent) | `authorizeLeg()` so sánh `===` giữa id đơn và id user. Nếu driver DB trả id dạng chuỗi (cấu hình PDO khác), phép so sánh luôn sai → **khoá cả shipper đúng**. Fail-closed nên không rò dữ liệu, nhưng là lỗi chức năng chỉ xuất hiện trên MySQL (test chạy sqlite nên không bắt được) | **Đã sửa**: ép `(int)` cả hai bên + chặn tường minh trường hợp `null` |
| 2 | Medium | `composer audit`: **4 advisory** của `guzzlehttp/guzzle` (<7.15.1) — rò URI fragment qua `Referer` khi redirect; không giữ scope cookie host-only; cookie phản hồi không giới hạn (DoS); `Proxy-Authorization` gửi tới origin. Là dependency **gián tiếp** (laravel/framework, aws-sdk-php), **có trước** đợt này | Ghi bead `bopcamping-j1vw` (P2). Chưa nâng vì user chưa muốn cập nhật lúc phát hiện — cần chạy full test sau khi nâng |
| 3 | Info | Trang shipper hiển thị **số tiền cần thu / hoàn cọc** | Đúng thiết kế, chủ shop xác nhận 29/07: shipper là người thu COD nên buộc phải thấy. Không thấy doanh thu/thống kê/danh sách khách |

Không có phát hiện Critical hoặc High.

## 4. Đã kiểm bằng test (không chỉ đọc code)

`ShipperAuthTest` (7) · `ShipperAccessTest` (9) · `ShipperMarkLegTest` (9) · `AdminShipperAssignmentTest` (9) · `AdminShipperUsersTest` (11) · `ShipperScheduleMailTest` (8) · `SendShipperDailyScheduleTest` (5) · `ShipperAssignmentSchemaTest` (5) — trong đó **~20 case là case quyền/rò dữ liệu**.

## 5. Việc còn treo cho lần sau

- `bopcamping-j1vw` — nâng guzzle ≥ 7.15.1 (mục 3 #2).
- `bopcamping-vo4` — đổi login admin sang HTTP Basic Auth; login shipper cố tình tách route riêng nên không xung đột.
- `bopcamping-ybsm` — cron trên server: thiếu cron thì email lịch 06:00 không chạy (nút gửi tay vẫn được).
- Khi nào thêm vai thứ 4 thì chuyển từ 2 cờ boolean sang `role` enum — xem ADR mục 4.

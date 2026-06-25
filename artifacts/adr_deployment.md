# ADR: Triển khai Production cho BopCamping

- **Trạng thái:** Accepted
- **Ngày:** 2026-06-25
- **Liên quan:** `bopcamping-0uh`, [tech-strategy.md](../.claude/rules/tech-strategy.md), [deploy_runbook.md](deploy_runbook.md)

## Bối cảnh

BopCamping là monolith **Laravel 12 + Inertia + React (TypeScript)**, một shop duy nhất,
cho thuê đồ camping theo ngày, thu cọc, thanh toán COD. Đăng nhập khách bằng OTP gửi
qua email. App đã chạy ổn ở môi trường dev (SQLite) và cần đưa lên production.

tech-strategy.md đã định hướng prod = **VPS Linux (Nginx + PHP-FPM 8.3 + MySQL 8)** và
ghi rõ "quyết định phương thức deploy khi gần ra mắt, ghi vào ADR". ADR này chốt phương án.

### Ràng buộc nghiệp vụ quan trọng

1. **Mail chạy qua queue** (OTP đăng nhập, xác nhận đơn, mời đánh giá). Production **bắt
   buộc** có một worker `queue:work` chạy nền liên tục, nếu không mail sẽ nằm chờ mãi và
   không bao giờ gửi → khách không đăng nhập được.
2. **Ảnh sản phẩm** lưu ở `storage/app/public` qua symlink `public/storage` → cần
   `php artisan storage:link` và phân quyền ghi cho `storage/`.
3. **Không** có cron scheduler: mail mời đánh giá được kích hoạt bởi `OrderObserver` khi
   đơn chuyển trạng thái `returned`, không cần `schedule:run`. (Nếu sau này thêm tác vụ
   định kỳ — vd dọn OTP hết hạn — sẽ cần thêm cron, xem mục Hệ quả.)
4. **Không** thanh toán online (chỉ COD) → không cần webhook/cổng thanh toán.

## Quyết định

### 1. Hạ tầng: VPS Linux tự quản

Một VPS Ubuntu 24.04 LTS (tối thiểu 1GB RAM, khuyến nghị 2GB) chạy toàn bộ stack:

| Thành phần | Lựa chọn | Lý do |
|------------|----------|-------|
| Web server | **Nginx** | Reverse proxy + phục vụ static asset đã build |
| PHP | **PHP-FPM 8.3** | Khớp bản dev, Laravel 12 cần ≥ 8.2 |
| Database | **MySQL 8** | Theo tech-strategy; migrations đã kiểm tra tương thích |
| Queue worker | **Supervisor** giám sát `php artisan queue:work` | Giữ worker sống 24/7, tự restart khi crash |
| SSL/TLS | **Let's Encrypt (certbot)** | HTTPS miễn phí, tự gia hạn |
| Build asset | **Node 20 + Vite** (`npm run build`) | Build trên server hoặc CI rồi rsync |

**Vì sao VPS tự quản (không phải Forge / PaaS):**

- Theo đúng golden path của tech-strategy → không lệch chuẩn.
- Quy mô 1 shop, traffic thấp → 1 VPS nhỏ thừa sức, chi phí ~150–250k/tháng.
- Toàn quyền cấu hình, không phụ thuộc nền tảng bên thứ ba.
- Đánh đổi: phải tự cấu hình server lần đầu (đã có runbook hướng dẫn từng lệnh) và tự
  lo bảo trì/cập nhật bảo mật OS. Chấp nhận được với quy mô hiện tại.

### 2. Database: SQLite (dev) → MySQL 8 (prod)

Giữ migrations tương thích cả hai (đã kiểm tra: không dùng kiểu cột riêng SQLite).
Production chạy `php artisan migrate --force`. **Không** chạy `db:seed` đầy đủ vì
`DatabaseSeeder` tạo Test User demo; chỉ seed danh mục/sản phẩm + tài khoản admin một
cách có kiểm soát (xem runbook).

### 3. Queue, Cache, Session: giữ driver `database`

Không đưa Redis vào ở giai đoạn này — driver `database` đủ cho quy mô 1 shop, giảm một
thành phần phải vận hành. Có thể nâng lên Redis sau nếu traffic tăng (ghi ADR mới).

### 4. Email: SMTP thật

Production dùng SMTP thật (Gmail App Password hoặc Brevo/Resend free tier). Secrets nằm
trong `.env` trên server, **không commit**. Dev tiếp tục dùng `MAIL_MAILER=log`.

### 5. Cấu hình production an toàn

- `APP_ENV=production`, `APP_DEBUG=false` (không lộ stack trace).
- `APP_KEY` sinh mới trên server (`php artisan key:generate`).
- HTTPS bắt buộc: `SESSION_SECURE_COOKIE=true`, `SESSION_DOMAIN` = domain thật.
- Cache config/route/view cho production (`config:cache`, `route:cache`, `view:cache`).
- **Đổi mật khẩu admin mặc định** (`AdminUserSeeder` đang hardcode `admin`).

## Phương án đã cân nhắc & loại

- **Laravel Forge + VPS:** đỡ cực phần cấu hình/queue/SSL/deploy, nhưng tốn thêm ~12$/tháng
  và thêm phụ thuộc. Để dành cho khi cần quản nhiều site hoặc deploy thường xuyên.
- **PaaS (Railway/Render):** lên sóng nhanh nhưng lệch tech-strategy, cần chỉnh app (queue
  worker dạng process riêng, storage ephemeral phải chuyển sang S3) và chi phí khó dự đoán.
- **Shared hosting (cPanel):** rẻ nhưng thường không cho chạy queue worker thường trú và
  thiếu quyền cấu hình → không phù hợp app cần queue.

## Hệ quả

**Tích cực:** chi phí thấp, toàn quyền, đúng chuẩn dự án, dễ debug vì stack đồng nhất dev/prod.

**Tiêu cực / cần lưu ý:**
- Tự chịu trách nhiệm vá bảo mật OS, backup DB, theo dõi uptime.
- **Backup:** cần thiết lập dump MySQL định kỳ + backup thư mục `storage/app/public`
  (ảnh sản phẩm). Chưa tự động hoá ở giai đoạn này — ghi TODO trong runbook.
- **Nếu thêm tác vụ định kỳ** (dọn `email_otps` hết hạn, nhắc đơn...) → phải thêm một
  dòng cron gọi `php artisan schedule:run` mỗi phút. Hiện chưa cần.
- Single point of failure (1 VPS). Chấp nhận được với SLA của shop nhỏ; nâng cấp HA sau
  nếu cần (ADR mới).

## Tài liệu thực thi

Các bước dựng server + deploy chi tiết (lệnh copy-paste) nằm ở
[deploy_runbook.md](deploy_runbook.md).

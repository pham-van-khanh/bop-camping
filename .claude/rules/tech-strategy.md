# Tech Strategy — Golden Paths (BopCamping)

This is the **SINGLE SOURCE OF TRUTH** for technology choices on BopCamping
(website cho thuê đồ camping — 1 shop, thuê theo ngày, cọc, COD).

## Compliance

1. **Follow This File**: Use the technologies listed below — do not introduce others.
2. **No Deviations**: Do not suggest alternatives unless explicitly instructed.
3. **Latest Stable**: Use the latest stable patch within each pinned major version.

## Golden Path (the whole stack)

| Layer | Choice | Notes |
|-------|--------|-------|
| Language (backend) | **PHP 8.3** | Laravel 12 cần ≥ 8.2. Bản brew `php` ở `/opt/homebrew/bin`. |
| Framework | **Laravel 12** | Monolith, không tách API riêng. |
| Auth scaffold | **Laravel Breeze** (Inertia + React + TypeScript) | Admin login Breeze (SĐT+mật khẩu). Khách login **SĐT + tên + email**, xác thực **OTP 6 số qua email** (OtpService + bảng `email_otps`; OTP chỉ lần đầu/khi email chưa verify). Khách không dùng mật khẩu. |
| FE ↔ BE bridge | **Inertia.js** | SPA mượt, không dựng REST API thủ công. |
| UI | **React 18 + TypeScript** | Component, strict typing — tránh `any`. |
| Styling | **Tailwind CSS** | Theme tông **be / màu đất Naturehike** (xem KE_HOACH.md). |
| Component library | **shadcn/ui** | Button, Card, Dialog, Calendar (chọn ngày thuê)... |
| Build tool | **Vite** | Đi kèm Laravel/Breeze. |
| Node runtime | **Node 20 (LTS)** | Quản lý bằng **nvm** (`nvm alias default 20`); React/Vite cần ≥ 20. |

## Data

| Component | Choice | Notes |
|-----------|--------|-------|
| Database (dev) | **SQLite** | Nhẹ, không cần cài thêm. File `database/database.sqlite`. |
| Database (prod) | **MySQL 8** | Chuyển khi deploy. Giữ migration tương thích cả hai. |
| ORM / Query | **Eloquent + Query Builder** | Luôn dùng prepared statements (mặc định của Eloquent). |
| File/Ảnh sản phẩm | **Laravel Storage — disk `media`** | Dev: local disk (`storage/app/public` + `php artisan storage:link`). Prod (tuỳ chọn): S3 (hoặc S3-compatible) qua `league/flysystem-aws-s3-v3`, chuyển bằng `MEDIA_DISK=s3` + `AWS_*` trong `.env` — không cần đổi code. Xem `artifacts/adr_s3_media_storage.md`. |
| Email (OTP đăng nhập) | **Laravel Mail (SMTP)** | Cấu hình `MAIL_*` trong `.env` (KHÔNG commit secret). Dev có thể dùng `MAIL_MAILER=log`. Prod: SMTP thật (vd Gmail app-password). |

## Tooling & Quality Gates

| Component | Choice | Lệnh |
|-----------|--------|------|
| Package manager (PHP) | **Composer** | `composer install` |
| Package manager (JS) | **npm** | `npm install` |
| PHP formatter | **Laravel Pint** | `./vendor/bin/pint` |
| JS/TS lint (quality gate) | **ESLint + Prettier** | `npm run lint` — CHỈ kiểm, KHÔNG sửa file |
| JS/TS auto-format | **ESLint `--fix`** | `npm run lint:fix` — tự sửa; chạy trước khi commit nếu `lint` báo lỗi format |
| Type check | **TypeScript (`tsc`)** | `npx tsc --noEmit` |
| Test (backend) | **PHPUnit** (Laravel mặc định) | `php artisan test` |
| Test (component React) | **Vitest + @testing-library/react** (jsdom) | `npm test` |

> Tất cả phải pass trước khi commit: `php artisan test` · `npm test` · `npx tsc --noEmit` · `npm run lint` · `./vendor/bin/pint --test` · `npm run build`.

**Lint — `lint` vs `lint:fix` (đọc kỹ, đây là quality gate)**

`npm run lint` **không có `--fix`**: nó chỉ báo lỗi, không đụng vào file. Đây mới là
quality gate đúng nghĩa — lint pass nghĩa là code trong repo thật sự đúng format.
Khi cần sửa, chạy `npm run lint:fix` rồi **xem lại diff trước khi commit**.

TUYỆT ĐỐI không thêm `--fix` lại vào script `lint`. Trước 04/08/2026 script này có
`--fix`, hậu quả: mỗi lần chạy gate tự viết lại hàng loạt file (thực đo 75–77 file),
nên "lint pass" chỉ vì công cụ vừa sửa hộ chứ không phải vì code đúng — và mọi thay
đổi nhỏ đều đẻ ra diff khổng lồ. Toàn bộ `resources/js` + `tests/js` đã được format
một lần ở commit `chore(format)` riêng (bopcamping-26st) để diff về sau sạch.

Quy ước biến không dùng: đặt tên bắt đầu bằng `_` (vd `_u`) để `@typescript-eslint/no-unused-vars`
bỏ qua — dùng cho tham số phải giữ lại vì chữ ký hàm (API deprecated), không phải để
giấu code chết. Code chết thì xoá.

**Test component React** — cấu hình ở `vitest.config.ts`, test đặt trong `tests/js/`.
Truy vấn theo vai trò/nhãn (`getByRole`), mock ranh giới ngoài (Inertia `usePage`,
framer-motion), không mock logic đang kiểm. jsdom **không** kiểm được layout thật
(chồng lấn, z-index) — việc đó vẫn phải đo trên trình duyệt.
Xem `artifacts/adr_frontend_component_testing.md`.

## Dev Commands

```bash
composer run dev          # chạy gộp: artisan serve + queue + vite
php artisan serve         # backend http://localhost:8000
npm run dev               # Vite dev server
php artisan migrate:fresh --seed   # reset + seed dữ liệu mẫu
```

## Deployment (chốt sau khi MVP xong)

- **Dev**: chạy local (SQLite).
- **Prod (dự kiến)**: VPS Linux (Nginx + PHP-FPM 8.3 + MySQL 8), build asset bằng `npm run build`.
  Quản lý deploy có thể dùng Laravel Forge hoặc script thủ công — **quyết định khi gần ra mắt**, ghi vào ADR.
- Secrets: file `.env` trên server (KHÔNG commit). Không có secret nào nằm trong code.

## Out of Scope (hiện tại)

- Không làm app mobile → không cần API tách rời, không cần React Native/Swift/Kotlin.
- Không thanh toán online (chỉ COD) → chưa tích hợp cổng thanh toán.
- Không marketplace nhiều shop → mô hình 1 shop duy nhất.

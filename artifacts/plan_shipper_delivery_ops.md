# Plan — Vận hành giao nhận (shipper, gán đơn, in/xuất, thông báo)

**PRD:** [prd_shipper_delivery_ops.md](artifacts/prd_shipper_delivery_ops.md) · **ADR:** [adr_shipper_role_and_access.md](artifacts/adr_shipper_role_and_access.md), [adr_pdf_generation.md](artifacts/adr_pdf_generation.md)
**Ngày:** 2026-07-29 · **Loại:** Large (~9–11 ngày sau khi bỏ in/PDF/CSV) · **Nhánh:** `feature/shipper-ops` tách từ `feature/delivery-schedule` (đợt này refactor code của nhánh đó; nó đang trên staging chờ test nên chưa có ở `feat/scaffold-laravel`)

> Điều kiện tiên quyết: `feature/delivery-schedule` (giờ đã chốt + lịch tháng) đã merge vào `feat/scaffold-laravel` — đợt này build lên trên nó.

## 1. Hiện trạng (đã khảo sát)

| Thành phần | Vị trí | Dùng lại được gì |
|---|---|---|
| Vai admin | [EnsureAdmin.php](app/Http/Middleware/EnsureAdmin.php), alias tại [bootstrap/app.php:25](bootstrap/app.php:25) | Khuôn mẫu 1:1 cho `EnsureShipper` |
| Login admin | [Admin/AuthController.php](app/Http/Controllers/Admin/AuthController.php) | `Auth::attempt(phone+password)` + `session()->regenerate()` + logout guard `web` |
| CRUD user | [Admin/UserController.php:113](app/Http/Controllers/Admin/UserController.php:113) | `store/update/updateRole/destroy` + validate `phone` regex/unique, `password` min 6, chặn tự đổi quyền mình |
| Shared props | [HandleInertiaRequests.php:44](app/Http/Middleware/HandleInertiaRequests.php:44) | Thêm `is_shipper` vào `auth.user` |
| Lịch giao | [Admin/DeliveryScheduleController.php](app/Http/Controllers/Admin/DeliveryScheduleController.php), [DeliverySchedule.tsx](resources/js/Pages/Admin/DeliverySchedule.tsx) | `ordersOf()`, `row()`, `monthDays()` — tách thành service dùng chung cho web admin / trang shipper / mail |
| Kéo-thả | [MediaGallery.tsx:216](resources/js/Components/admin/MediaGallery.tsx:216) | `Reorder.Group` / `Reorder.Item` của framer-motion + `persistOrder` gọi route reorder |
| Mail hàng loạt | [SendPickupReminders.php](app/Console/Commands/SendPickupReminders.php), [routes/console.php](routes/console.php) | Khuôn command + `Schedule::command(...)->dailyAt()` + cờ idempotent |
| Mail | `app/Mail/*` (ShouldQueue) + component `x-mail.brand` | Khuôn `ShipperScheduleMail` |
| Chuyển trạng thái | [OrderController::updateStatus](app/Http/Controllers/Admin/OrderController.php:395) + [OrderObserver](app/Observers/OrderObserver.php) | Shipper "đã giao/đã thu" đi đúng luồng này để mail khách vẫn gửi |

**Chưa có:** vai shipper, gán đơn, khu vực `/shipper/*`, mail lịch cho shipper.

## 2. Thiết kế

### 2.1 Schema — 1 migration cho `users`, 1 cho `orders`

```php
// 2026_07_30_000001_add_is_shipper_to_users_table.php
$table->boolean('is_shipper')->default(false)->index()->after('is_admin');

// 2026_07_30_000002_add_shipper_assignment_to_orders.php
$table->foreignId('pickup_shipper_id')->nullable()->after('schedule_confirmed_at')
      ->constrained('users')->nullOnDelete();
$table->foreignId('return_shipper_id')->nullable()->after('pickup_shipper_id')
      ->constrained('users')->nullOnDelete();
```
`User`: `$fillable` += `is_shipper`, cast boolean, scope `shippers()`, `isShipper()`.
`Order`: `$fillable` += 4 cột; relation `pickupShipper()`, `returnShipper()` (`BelongsTo` User).

### 2.2 Vai + đăng nhập shipper

- `app/Http/Middleware/EnsureShipper.php` — copy `EnsureAdmin`, đổi `is_shipper`, redirect `shipper.login`. Alias `'shipper'` trong `bootstrap/app.php`.
- `app/Http/Controllers/Shipper/AuthController.php` — `showLogin/login/logout`; `login` throttle `10,1`; sai creds → *"Số điện thoại hoặc mật khẩu không đúng."*; đúng creds nhưng không `is_shipper` → `Auth::logout()` + lỗi chung.
- Routes:
```php
Route::get('/shipper/dang-nhap', [ShipperAuthController::class, 'showLogin'])->name('shipper.login');
Route::post('/shipper/dang-nhap', [ShipperAuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/shipper/dang-xuat', [ShipperAuthController::class, 'logout'])->name('shipper.logout');
Route::middleware(['shipper'])->prefix('shipper')->name('shipper.')->group(function () {
    Route::get('/lich-giao', [ShipperScheduleController::class, 'index'])->name('schedule');
    Route::patch('/don/{order}/da-giao', [ShipperScheduleController::class, 'markDelivered'])->name('orders.delivered')->middleware('throttle:60,1');
    Route::patch('/don/{order}/da-thu', [ShipperScheduleController::class, 'markCollected'])->name('orders.collected')->middleware('throttle:60,1');
});
```
- `HandleInertiaRequests`: `auth.user.is_shipper`.

### 2.3 Service dùng chung — `app/Services/DeliveryScheduleService.php`

Rút logic khỏi `Admin\DeliveryScheduleController` (đang có `ordersOf`/`row`/`monthDays`) thành service để **web admin, trang shipper, mail lịch** dùng đúng 1 nguồn:

```php
legOrders(string $leg /* pickup|return */, Carbon $date, ?int $shipperId = null, bool $onlyAssigned = false): Collection
monthDays(Carbon $month, ?int $shipperId = null): array
row(Order $o, string $leg): array   // shape dùng chung (thêm shipper_id, shipper_name, sort)
```
Sắp xếp: `ORDER BY {leg}_time IS NULL, {leg}_time, code` — theo giờ đã chốt, chưa chốt xuống cuối (chạy đúng cả sqlite + MySQL).

**Two Hats:** bước rút service là **refactor thuần** — không đổi hành vi, test `AdminDeliveryScheduleTest` hiện có phải xanh y nguyên, commit riêng trước khi thêm tính năng.

### 2.4 Admin gán shipper + sắp thứ tự

| Route | Method | Việc |
|---|---|---|
| `admin/orders/{order}/shipper` | PATCH | Gán/bỏ 1 lượt: `leg` (pickup\|return) + `shipper_id` (nullable, phải là user `is_shipper`) |
| `admin/lich-giao/gan-tat-ca` | POST | Gán tất cả đơn **chưa có shipper** của (ngày, lượt) |

Chặn: `is_parent`, status `returned|cancelled`, `shipper_id` không phải shipper → lỗi validate.
FE: `select` shipper trên card + bộ lọc shipper (Tất cả / từng người / Chưa gán) trên header trang lịch. **Không kéo-thả** — chủ shop bỏ (29/07/2026), thứ tự theo giờ đã chốt là đủ.

### 2.5 Trang shipper

`resources/js/Pages/Shipper/Schedule.tsx` + layout tối giản `resources/js/Layouts/ShipperLayout.tsx` (chỉ tên + đăng xuất, KHÔNG nav admin).
- Props: `date`, `date_label`, `today`, `prev_date`, `next_date`, `pickups[]`, `returns[]` — chỉ đơn của chính mình (`onlyAssigned = true`, `shipperId = auth id`).
- Card: số thứ tự lớn, giờ chốt, tên, `tel:`, địa chỉ, nút **Chỉ đường** → `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(address)}` (`target="_blank" rel="noreferrer"`), món, tiền thu/hoàn, ghi chú shipper, nút Đã giao / Đã thu (có `confirm`).
- `markDelivered`: `abort_unless($order->pickup_shipper_id === $request->user()->id, 403)` + `status === 'confirmed'`; đổi `status = renting`.
- `markCollected`: `return_shipper_id` khớp + `status === 'renting'`; đổi `status = returned`.
- Điều hướng ngày: chỉ trong khoảng `[hôm nay − 2, hôm nay + 14]`.

### 2.7 Thông báo

- `app/Mail/ShipperScheduleMail.php` (ShouldQueue) + `resources/views/emails/shipper_schedule.blade.php` — bảng theo thứ tự, giờ, khách, SĐT, địa chỉ, tiền thu/hoàn, ghi chú.
- `POST admin/lich-giao/gui-email` — gửi cho shipper đang lọc (hoặc từng shipper có đơn nếu "Tất cả"); bỏ qua email `@bopcamping.local` và báo lại cho admin.
- `app/Console/Commands/SendShipperDailySchedule.php` (`shipper:send-daily-schedule`) + `Schedule::command(...)->dailyAt('06:00')`; idempotent bằng cache key `shipper-schedule-sent:{shipper}:{date}` (không thêm cột).
- Nút **Chat Zalo**: `https://zalo.me/<phone>` mở tab mới. Không API.

## 3. Phân rã task (thứ tự thực thi)

| # | Bead | Task | Ngày | Chặn bởi |
|---|---|---|---|---|
| S1 | `bopcamping-4gy0` | Refactor: rút `DeliveryScheduleService` (hành vi KHÔNG đổi, test cũ xanh y nguyên) | 0.5 | — |
| S2 | `bopcamping-xdvx` | Schema: `users.is_shipper` + 4 cột gán/thứ tự trên `orders` + model/relation | 0.5 | — |
| S3 | `bopcamping-lsch` | Vai + đăng nhập shipper: `EnsureShipper`, `Shipper\AuthController`, routes, shared prop, trang login | 1.5 | S2 |
| S4 | `bopcamping-2xf6` | Admin quản lý tài khoản shipper (tab Shipper trong `/admin/users`, cảnh báo khi xoá/tắt vai người đang có đơn) | 1.5 | S2 |
| S5 | `bopcamping-yc7d` | Admin gán shipper + gán cả ngày + lọc theo shipper (bỏ kéo-thả) | 2 | S1, S2 |
| S6 | `bopcamping-w2yl` | Trang `/shipper/lich-giao` + nút Chỉ đường + Đã giao / Đã thu (kèm test IDOR) | 2.5 | S3, S5 |
| S8 | `bopcamping-5r5m` | Email lịch cho shipper (nút gửi tay + command 06:00) + nút Chat Zalo | 1.5 | S5 |
| S9 | `bopcamping-zzhm` | Review bảo mật (OWASP A01 / IDOR / rò dữ liệu khách) + full gates + merge/push | 1 | S4, S6, S7, S8 |

Epic: `bopcamping-hae4`. Việc mở đầu (không bị chặn): **S1** và **S2**.

Song song được: S1‖S2 → S3‖S4 → S5 → S6‖S8 → S9. **S7 (in/PDF/CSV) đã bị bỏ 29/07/2026.**

## 4. Test cần viết

**Feature (PHP)**
- `ShipperAuthTest`: shipper login được; user thường/khách bị chặn; sai creds không tiết lộ tồn tại; throttle.
- `ShipperAccessTest`: shipper vào `/admin/*` → redirect; admin vào `/shipper/*` (không có cờ shipper) → redirect; guest → login.
- `ShipperScheduleTest`: chỉ thấy đơn được gán cho mình; **không** thấy đơn của shipper khác; đánh dấu Đã giao/Đã thu đúng chuyển trạng thái + mail khách vẫn queue; **đổi id đơn của người khác → 403** (IDOR); sai trạng thái → chặn.
- `AdminShipperAssignmentTest`: gán đúng cột theo lượt; gán cả ngày không ghi đè đơn đã gán; thứ tự theo giờ chốt; chặn đơn cha/đã trả/đã huỷ; `shipper_id` không phải shipper → lỗi.
- `AdminShipperUsersTest`: CRUD tài khoản shipper; cảnh báo khi xoá người đang có đơn tương lai.
- `ShipperScheduleMailTest` + `SendShipperDailyScheduleTest`: gửi đúng người, nội dung đúng thứ tự, chạy 2 lần không gửi trùng, email placeholder bị bỏ qua.

**Component (vitest)**: `ShipperScheduleCard.test.tsx` (nút Đã giao chỉ hiện đúng trạng thái, link Chỉ đường encode địa chỉ đúng, xác nhận trước khi đổi) · `ShipperAssign.test.tsx` (chọn shipper → patch đúng route/payload).

Test phải collation-safe (chạy cả sqlite `:memory:` và MySQL) theo `CLAUDE.md`.

## 5. Rủi ro triển khai

| Rủi ro | Mức | Xử lý |
|---|---|---|
| Rút service làm lệch hành vi lịch giao hiện tại | Trung bình | Two Hats: refactor riêng 1 commit, test cũ **không được sửa** mà vẫn phải xanh |
| Rò dữ liệu khách qua `/shipper/*` | **Cao** | Query luôn kẹp `shipper_id`; test IDOR; S9 review bảo mật riêng trước merge |
| Email 06:00 không chạy vì thiếu cron | Trung bình | Nút gửi tay luôn có; nêu rõ phụ thuộc `bopcamping-ybsm` khi bàn giao |
| Xung đột với `bopcamping-vo4` (Basic Auth admin) | Thấp | Login shipper tách route/controller riêng — không dùng chung form admin |

## 6. Beads

Xem `bd show bopcamping-hae4` (epic) — bảng bead + phụ thuộc ở mục 3.

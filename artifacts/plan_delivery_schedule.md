# Plan — Chốt giờ giao/thu + Lịch giao theo ngày

**PRD:** [prd_delivery_schedule.md](artifacts/prd_delivery_schedule.md) · **Ngày:** 2026-07-28
**Loại:** Medium feature (~5–6 ngày) · **Reversibility:** Two-Way Door
**Nhánh:** `feature/delivery-schedule` tách từ `feat/scaffold-laravel` (theo Workflow trong CLAUDE.md)

## 1. Hiện trạng (đã khảo sát bằng 4 worker song song)

| Thành phần | Vị trí | Ghi chú |
|---|---|---|
| Cột giờ khách xin | [2026_07_24_000005_add_requested_times_and_extra_fee_to_orders.php:18](database/migrations/2026_07_24_000005_add_requested_times_and_extra_fee_to_orders.php:18) | `string(5)` `HH:MM`, nullable — mẫu để copy |
| Model | [Order.php:41](app/Models/Order.php:41) | `$fillable` chứa `requested_*`; không cast (string) |
| Admin controller | [Admin/OrderController.php](app/Http/Controllers/Admin/OrderController.php) | 8 action; `changeDates():260` là mẫu "sửa + gửi mail"; `updateExtraFee():530` là mẫu action nhỏ |
| Map đơn → FE | [Admin/OrderController.php:128](app/Http/Controllers/Admin/OrderController.php:128) `mapOrder()` | Dùng chung index + show — thêm field ở **một** chỗ |
| Route admin đơn | [routes/web.php:100-112](routes/web.php:100) | Pattern `PATCH /orders/{order}/<việc>` + `throttle:30,1` |
| UI dùng chung | [Admin/orderShared.tsx](resources/js/Pages/Admin/orderShared.tsx) | type `Order`, `DetailRow:125`, `RefundControl:138`, `ExtraFeeEditor:204`, `DatesChanger:245`, `OrderDetailPanel` |
| Hiển thị giờ hiện tại | [orderShared.tsx:425](resources/js/Pages/Admin/orderShared.tsx:425) | `DetailRow` read-only "Giờ nhận/trả" |
| Nav admin | [AdminLayout.tsx:30](resources/js/Layouts/AdminLayout.tsx:30) | Mảng item `{href, name, icon}`; `isActive` = `startsWith(href)` |
| Mail đổi lịch | [OrderDatesChangedMail.php](app/Mail/OrderDatesChangedMail.php) + [order_dates_changed.blade.php](resources/views/emails/order_dates_changed.blade.php) | `ShouldQueue`, component `x-mail.brand` / `x-mail.order-facts` / `x-mail.item-list` / `x-mail.button` |
| Mail nhắc nhận đồ | [order_pickup_reminder.blade.php:12,19,39](resources/views/emails/order_pickup_reminder.blade.php:12) | Đang ghi "hệ thống không lưu giờ" → phải sửa |
| Gửi mail status | [OrderObserver.php](app/Observers/OrderObserver.php) | Chỉ nghe `wasChanged('status')` — **không** dùng cho việc này |
| Khách xem đơn | [AccountController.php:136](app/Http/Controllers/AccountController.php:136) · [OrderLookupService::shape](app/Services/OrderLookupService.php) | Hai nguồn shape riêng, cần thêm ở cả hai |
| Test admin đơn | [AdminOrderPaymentMarkTest.php:48](tests/Feature/AdminOrderPaymentMarkTest.php:48) | `User::factory()->create(['is_admin'=>true])` + `actingAs()->patch(route(...))` |
| Test component | [tests/js/ZaloFloatButton.test.tsx](tests/js/ZaloFloatButton.test.tsx) | mock `@inertiajs/react`, `getByRole`, `userEvent` |

**Không có:** role shipper, trang lịch giao, in/export, cột giờ do shop chốt.

## 2. Thiết kế

### 2.1 Migration `database/migrations/2026_07_28_000001_add_confirmed_times_to_orders.php`

```php
Schema::table('orders', function (Blueprint $table) {
    $table->string('confirmed_pickup_time', 5)->nullable()->after('requested_return_time');
    $table->string('confirmed_return_time', 5)->nullable()->after('confirmed_pickup_time');
    $table->string('schedule_note', 255)->nullable()->after('confirmed_return_time');
    $table->timestamp('schedule_confirmed_at')->nullable()->after('schedule_note');
});
// down(): dropColumn 4 cột
```
Nullable ⇒ **không backfill**. `Order::$fillable` thêm 4 tên; `$casts` thêm `schedule_confirmed_at => 'datetime'`.

### 2.2 Backend — action chốt giờ

`Admin/OrderController::updateSchedule()` (đặt ngay sau `updateExtraFee`, cùng phong cách docblock tiếng Việt):

```php
if ($order->is_parent)  → back()->withErrors(['confirmed_pickup_time' => 'Đơn gộp: chốt giờ trên từng đợt (đơn con).']);
if (in_array($order->status, ['returned','cancelled'], true)) → withErrors(...'Đơn đã trả/đã huỷ — không chốt giờ nữa.');

$data = $request->validate([
    'confirmed_pickup_time' => ['nullable','date_format:H:i'],
    'confirmed_return_time' => ['nullable','date_format:H:i'],
    'schedule_note'         => ['nullable','string','max:255'],
]);

// Cùng ngày → giờ thu phải sau giờ giao (so sánh chuỗi HH:MM là đủ)
if ($order->start_date->isSameDay($order->end_date) && $pickup && $return && $return <= $pickup)
    → withErrors(['confirmed_return_time' => 'Giờ thu phải sau giờ giao (đơn trong cùng ngày).']);

$changed = $pickup !== $order->confirmed_pickup_time || $return !== $order->confirmed_return_time;
$order->update([... , 'schedule_confirmed_at' => ($pickup || $return) ? now() : null]);

if ($changed && $order->status !== 'cancelled' && ($email = $order->notifiableEmail()))
    Mail::to($email)->send(new OrderScheduleConfirmedMail($order));

return back()->with('success', "Đơn {$order->code}: đã cập nhật giờ giao/thu");
```

Route ([routes/web.php](routes/web.php) cạnh `orders.fee`):
```php
Route::patch('/orders/{order}/schedule', [AdminOrderController::class, 'updateSchedule'])
    ->name('orders.schedule')->middleware('throttle:30,1');
```

`mapOrder()` thêm 4 field: `confirmed_pickup_time`, `confirmed_return_time`, `schedule_note`, `schedule_confirmed_at` (format `d/m H:i` hoặc null).

### 2.3 Mail `OrderScheduleConfirmedMail`

`app/Mail/OrderScheduleConfirmedMail.php` — copy khung `OrderDatesChangedMail` (ShouldQueue, `SerializesModels`), subject `'Đơn '.$code.' — đã chốt giờ giao/thu — BỐP CAMPING'`, view `emails.order_schedule_confirmed`.

Blade `resources/views/emails/order_schedule_confirmed.blade.php`: `<x-mail.brand variant="green">`, 2 thẻ (GIAO: `$fmtDay(start_date)` + giờ · THU: `$fmtDay(end_date)` + giờ; giờ null → "sẽ liên hệ"), địa chỉ, `<x-mail.order-facts>`, tiền trả khi nhận, `<x-mail.button :href="route('lookup')">`. **Tuyệt đối không** truyền `schedule_note` vào view.

### 2.4 Mail nhắc nhận đồ (sửa)

[order_pickup_reminder.blade.php:12-21](resources/views/emails/order_pickup_reminder.blade.php:12):
```blade
@if ($order->confirmed_pickup_time)
    <div …>Giao lúc <strong>{{ $order->confirmed_pickup_time }}</strong></div>
@else
    <div …>Tụi mình sẽ liên hệ trước khi giao để hẹn giờ.</div>
@endif
```
Sửa cả comment "chỉ ngày, không giờ" (đã lạc hậu) và preheader (thêm giờ nếu có).

### 2.5 Admin UI

**`orderShared.tsx`:**
- type `Order` thêm 4 field.
- `ScheduleEditor({ order })` — theo khuôn `ExtraFeeEditor` (inline card, không modal): 2 × `<input type="time">` + input ghi chú (`maxLength 255`) + nút "Lưu giờ"; `router.patch(route('admin.orders.schedule', order.id), {...}, { preserveScroll: true })`; hiện `errors` inline; `stopPropagation` trên input (card đơn ở list bấm để mở/đóng).
- Trong `OrderDetailPanel`: đổi `DetailRow` cũ thành 2 dòng — **"Khách xin"** (`requested_*`, chỉ khi có) và **"Đã chốt"** (`confirmed_*`, in đậm; null → "chưa chốt"), rồi mount `<ScheduleEditor>` ngay dưới, chỉ khi `!order.is_parent && !['returned','cancelled'].includes(order.status)`.

**`Admin/Orders.tsx`:** trong ô "Ngày thuê" thêm dòng thứ hai `Giao 14:30 · Thu 09:00`, hoặc pill mờ "Chưa chốt giờ" — **không thêm cột mới** để bảng không rộng thêm (đã có 6 cột).

### 2.6 Trang Lịch giao — `/admin/lich-giao`

`app/Http/Controllers/Admin/DeliveryScheduleController.php`:
```php
$date = rescue(fn () => Carbon::parse($request->input('date'))->startOfDay(), Carbon::today());  // ngày lỗi → hôm nay

$base = fn () => Order::query()->where('is_parent', false)->with(['items.product', 'serviceLocation']);

$pickups = $base()->whereDate('start_date', $date)->whereIn('status', ['pending','confirmed'])
    ->orderByRaw('confirmed_pickup_time IS NULL, confirmed_pickup_time, code')->get();   // chạy đúng cả sqlite + MySQL
$returns = $base()->whereDate('end_date', $date)->whereIn('status', ['confirmed','renting'])
    ->orderByRaw('confirmed_return_time IS NULL, confirmed_return_time, code')->get();

Inertia::render('Admin/DeliverySchedule', [
    'date' => $date->toDateString(),
    'date_label' => Str::ucfirst($date->locale('vi')->isoFormat('dddd, DD/MM/YYYY')),
    'prev_date' => $date->copy()->subDay()->toDateString(),
    'next_date' => $date->copy()->addDay()->toDateString(),
    'today' => Carbon::today()->toDateString(),
    'pickups' => $pickups->map($this->row('pickup')),
    'returns' => $returns->map($this->row('return')),
    'stats' => ['pickups' => …, 'returns' => …, 'unscheduled' => …],
]);
```
`row()` trả: `id, code, time, customer_name, customer_phone, customer_address, service_location, session, status, payment_status, amount_due, deposit_total, schedule_note, items[{name, quantity}]`.

Route (trong group `admin`, cạnh orders):
```php
Route::get('/lich-giao', [DeliveryScheduleController::class, 'index'])->name('schedule');
```

**Page `resources/js/Pages/Admin/DeliverySchedule.tsx`** — mobile-first:
- Header: `date_label`, nút ‹ / Hôm nay / › (`router.get(route('admin.schedule'), { date })`, `preserveState`) + `<input type="date">`.
- Chip đếm: `N giao · M thu · K chưa chốt giờ` (chip "chưa chốt" tông cam nếu > 0).
- 2 section "Cần giao" / "Cần thu", mỗi đơn 1 card: giờ cỡ lớn (`text-[20px] font-mono`) hoặc badge cam "Chưa chốt giờ"; mã đơn link `admin.orders.show`; tên; `<a href="tel:…">` SĐT; địa chỉ; cửa hàng; badge "Chờ xác nhận" nếu `pending`; món + số lượng; "Thu khi nhận: … (chưa chuyển/đã cọc)"; `schedule_note` khối riêng nhãn "Ghi chú shipper".
- Empty state: "Không có đơn nào cần giao/thu ngày này."
- Không dùng `<table>`; padding thoáng, target bấm ≥ 44px.

**Nav:** thêm vào mảng `NAV` trong [AdminLayout.tsx:30](resources/js/Layouts/AdminLayout.tsx:30), ngay sau "Đơn thuê": `{ href: '/admin/lich-giao', name: 'admin.schedule', label: 'Lịch giao', icon: … }`.

### 2.7 Khách thấy giờ đã chốt

- [AccountController.php:136](app/Http/Controllers/AccountController.php:136) + [Account.tsx:47,453](resources/js/Pages/Account.tsx:453): thêm `confirmed_*`; ưu tiên hiện "Giờ đã chốt: giao … · thu …", chưa chốt thì giữ dòng "Giờ (mong muốn)" hiện tại.
- [OrderLookupService::shape](app/Services/OrderLookupService.php) thêm `confirmed_pickup_time`/`confirmed_return_time` → hiện trong `OrderLookup.tsx` (và section tra cứu trong `/tai-khoan` dùng chung shape này).

## 3. Các bước triển khai (thứ tự thực thi)

**T1 — Schema + mapping (0.5 ngày)**
1. Migration 4 cột; `php artisan migrate`.
2. `Order::$fillable` + cast `schedule_confirmed_at`.
3. `mapOrder()` + type `Order` trong `orderShared.tsx` (chỉ thêm field, chưa dùng).
4. Test: `AdminOrderShowTest` bổ sung assert 4 field mới có trong props.

**T2 — Action chốt giờ + mail (1 ngày)** — cần T1
1. Viết test trước (TDD, `tests/Feature/AdminOrderScheduleTest.php`) đủ 8 case ở mục 4.
2. `updateSchedule()` + route + `OrderScheduleConfirmedMail` + blade.
3. Chạy test đến xanh; `pint`.

**T3 — UI admin chốt giờ (1 ngày)** — cần T2
1. `ScheduleEditor` trong `orderShared.tsx` + 2 dòng "Khách xin"/"Đã chốt".
2. Dòng giờ trong ô "Ngày thuê" ở `Orders.tsx`.
3. `tests/js/AdminScheduleEditor.test.tsx` (render, đổi giờ → gọi `router.patch` đúng route + payload, hiện lỗi từ `errors`).
4. Verify bằng preview: `/admin/orders` + `/admin/orders/{id}`, chốt giờ thật, xem toast + giá trị sau reload.

**T4 — Trang Lịch giao (1.5–2 ngày)** — cần T1 (không cần T3)
1. Test trước: `tests/Feature/AdminDeliveryScheduleTest.php`.
2. Controller + route + nav.
3. `DeliverySchedule.tsx` mobile-first.
4. Verify preview ở 375px và desktop; kiểm tra `tel:` link, điều hướng ngày.

**T5 — Mail nhắc nhận đồ + khách xem giờ (1 ngày)** — cần T2
1. Sửa `order_pickup_reminder.blade.php` (giờ thật / fallback) + `SendPickupRemindersTest` thêm 2 assert.
2. `AccountController` + `Account.tsx`; `OrderLookupService::shape` + `OrderLookup.tsx`.
3. Bổ sung assert vào `AccountOrdersTest`, `OrderLookupTest`.

**T6 — QA + quality gates + push (0.5 ngày)** — cần T3, T4, T5
1. Full gates: `php artisan test` · `npm test` · `npx tsc --noEmit` · `npm run lint` · `./vendor/bin/pint --test` · `npm run build`.
2. Test tay theo AC ở PRD mục 6 (kèm 1 đơn cha, 1 đơn không email, 1 đơn nửa ngày).
3. Merge vào `develop` → user test stg → merge feature branch vào `feat/scaffold-laravel` → push.

## 4. Test cần viết

**`tests/Feature/AdminOrderScheduleTest.php`** (pattern `AdminOrderPaymentMarkTest`)
1. `admin_chot_gio_giao_va_thu` → DB lưu đúng, `schedule_confirmed_at` không null, `Mail::assertQueued(OrderScheduleConfirmedMail::class)`.
2. `gio_khach_xin_khong_bi_ghi_de` → `requested_*` giữ nguyên.
3. `gio_sai_dinh_dang_bi_chan` (`25:00`) → `assertSessionHasErrors`.
4. `don_cung_ngay_gio_thu_phai_sau_gio_giao` → error.
5. `don_cha_bi_chan` / `don_da_tra_bi_chan` → error.
6. `xoa_gio_ve_null` → cột null, `schedule_confirmed_at` null.
7. `sua_moi_ghi_chu_khong_gui_mail` → `Mail::assertNothingQueued()`.
8. `don_khong_co_email_hop_le_khong_gui_mail` (`…@bopcamping.local`) → không mail, không lỗi.

**`tests/Feature/AdminDeliveryScheduleTest.php`**
1. Render `Admin/DeliverySchedule`, mặc định hôm nay.
2. Đơn đúng ngày vào đúng nhóm giao/thu; đơn cha + `cancelled` + `returned` bị loại.
3. Sắp theo giờ, đơn chưa chốt xuống cuối (assert thứ tự `code` trong props).
4. `?date=` ngày khác đổi danh sách; `?date=abc` → fallback hôm nay, status 200.
5. `stats.unscheduled` đúng.
6. Non-admin → redirect `admin.login`.

**`tests/Feature/SendPickupRemindersTest.php`** (bổ sung): đơn có `confirmed_pickup_time` → mail render chứa `14:30`, không chứa "để hẹn giờ".

**`tests/js/AdminScheduleEditor.test.tsx`**: render giá trị hiện có; đổi giờ + bấm Lưu → `router.patch` được gọi với route và payload đúng; `errors` hiển thị.

Lưu ý collation-safe (CLAUDE.md): test chạy được cả SQLite và MySQL — không dùng cú pháp SQL riêng, `orderByRaw('col IS NULL, col, code')` đã kiểm cả hai.

## 5. Rủi ro triển khai

| Rủi ro | Mức | Xử lý |
|---|---|---|
| `orderByRaw` khác nhau giữa 2 DB | Thấp | `col IS NULL` là SQL chuẩn, có test assert thứ tự — chạy test cả 2 driver theo hướng dẫn CLAUDE.md |
| Card lịch giao tràn ngang trên mobile | Trung bình | Không dùng `<table>`; verify thật ở 375px bằng preview (jsdom không đo layout — theo `adr_frontend_component_testing`) |
| Mail không gửi khi test tay | Thấp | Nhớ `php artisan queue:work` (mail là `ShouldQueue`) — hoặc `composer run dev` |
| Đơn cha/con làm lệch danh sách lịch giao | Thấp | Query luôn `is_parent = false`; con là đơn đầy đủ nên vào lịch bình thường |
| Sửa blade nhắc nhận đồ làm hỏng mail đang chạy hằng ngày | Thấp | Nhánh `@if/@else` giữ nguyên nhánh cũ; test cả 2 nhánh |

## 6. Beads

| ID | Việc | Chặn bởi |
|---|---|---|
| `bopcamping-641t` | [Epic] Chốt giờ giao/thu + lịch giao cho shipper | — |
| `bopcamping-n7bh` | T1 · Schema 4 cột + mapOrder + type | — |
| `bopcamping-5xir` | T2 · updateSchedule + mail xác nhận giờ | `bopcamping-n7bh` |
| `bopcamping-mwjd` | T3 · UI admin chốt giờ (ScheduleEditor + list) | `bopcamping-5xir` |
| `bopcamping-rtkh` | T4 · Trang /admin/lich-giao mobile-first | `bopcamping-n7bh` |
| `bopcamping-2ded` | T5 · Mail nhắc nhận đồ dùng giờ thật + khách xem giờ | `bopcamping-5xir` |
| `bopcamping-5wen` | T6 · QA + quality gates + merge/push | `bopcamping-mwjd`, `bopcamping-rtkh`, `bopcamping-2ded` |

Đường tới hạn: T1 → T2 → T3 → T6. T4 chạy song song ngay sau T1; T5 song song với T3.

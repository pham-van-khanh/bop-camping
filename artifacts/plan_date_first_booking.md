# Plan — Đặt lịch trước, chọn đồ sau (date-first booking)

**Ngày:** 2026-07-30 · **PRD:** `artifacts/prd_date_first_booking.md` · **Nhánh:** `feature/date-first-booking`

Ước lượng 4–5 ngày. Thứ tự dưới đây là **thứ tự phụ thuộc** — T1 chặn mọi thứ còn lại.

---

## T1 — `AvailabilityService`: batch availability (chặn T2, T3)

**File:** `app/Services/AvailabilityService.php`

Thêm 2 method public, **không sửa** method per-product nào đang có:

```php
/** @param Collection<int,Product> $products (nên loadMissing('serviceLocations'))
 *  @return array<int, array{by_location: array<int,int>, best: int}> */
public function availabilityMatrix(Collection $products, Carbon $start, Carbon $end): array

/** @return array<int,int> [product_id => available] */
public function availableQuantitiesFor(Collection $products, Carbon $start, Carbon $end, ?ServiceLocation $location = null): array
```

**Thuật toán `availabilityMatrix` — 1 query duy nhất:**

1. `buffer[productId]` = `bufferAt($locId)` cho từng kho mở của sp; `maxBuffer` = max toàn tập.
2. Một query `OrderItem::join('orders')`:
   - `whereIn('order_items.product_id', $ids)`
   - `whereIn('orders.status', Order::activeStatuses())`
   - overlap với cửa sổ **nới rộng** `[start − maxBuffer, end]`, dùng `COALESCE(DATE(order_items.start_date), DATE(orders.start_date))` như `bookedQuantity()`
   - `select`: `product_id`, `orders.service_location_id`, ngày COALESCE start/end, `quantity`
3. Cộng trong PHP theo **buffer riêng từng sp**: row tính vào `booked[pid][locId]` khi
   `itemStart <= end` **và** `itemEnd >= start − buffer[pid][locId]`.
4. Đơn `service_location_id = NULL` (dữ liệu cũ) → cộng vào **mọi** kho của sp đó (khớp `bookedQuantity()` dòng 60).
5. `by_location[locId] = max(0, pivotStock(locId) − booked)`; `best = max(by_location)`.
6. Sp **không có kho open nào** (legacy): fallback nhánh global — `products.quantity` − booked, buffer = `maxBufferAcrossLocations()`. `by_location = []`, `best` = số global đó.

`availableQuantitiesFor` = wrapper: `$location` có → `by_location[$location->id] ?? 0`; null → `best`.

**Tests — `tests/Feature/AvailabilityBatchTest.php`:**
- **Invariant (quan trọng nhất):** dựng ~6 sp × 2 kho, buffer khác nhau, đơn active + cancelled, đơn NULL kho, đơn có ngày per-item và đơn chỉ có ngày cấp đơn → assert `availableQuantitiesFor()[id] === availableQuantity($p, …)` cho **mọi** sp × mọi kho **và** nhánh `$location = null` so với `best`.
- Sp không kho nào → fallback global đúng.
- Tập rỗng → `[]`, không query.
- **Query count:** `DB::listen` / `assertQueryCount` — batch với 20 sp phải ra **cùng số query** như với 2 sp.

**Gate:** `php artisan test --filter=AvailabilityBatchTest` xanh, `./vendor/bin/pint`.

---

## T2 — Controller: listing nhận `start`/`end`

**File:** `app/Http/Controllers/Shop/ProductController.php` (`index()`), `ComboController.php` (`index()`)

- Parse `start`/`end`. Helper dùng chung (đặt cạnh nhau, DRY — cân nhắc trait hoặc method riêng trong controller cha nếu đã có):
  `validRange(Request): ?array{start: Carbon, end: Carbon}` — trả `null` nếu sai format / `end < start` / `start < hôm nay` / `> 30 ngày` (FR-4).
- Có range → `availabilityMatrix()` **một lần** cho tập sp đang render; gắn `available` + `in_range` vào `shape()`.
- Combo: gom **tất cả** product của mọi combo → gọi `availabilityMatrix` **một lần**, rồi `comboAvailable = min(intdiv(available, qty))` per-location trong PHP. Không N×M query.
- Sort: `in_range` desc trước, rồi `sort` hiện tại.
  > ⚠️ Sort trong PHP chỉ đúng vì listing **chưa phân trang** (`get()`). Khi thêm phân trang phải chuyển sang SQL hoặc đổi cách — ghi vào bead follow-up.
- Props thêm: `filters.start`, `filters.end`, `range_summary: { days, unavailable_count }`.

**Tests:** `tests/Feature/ProductListingDateFilterTest.php`, `ComboListingDateFilterTest.php`
- `available`/`in_range` đúng khi có đơn chồng lịch.
- Sort đẩy `in_range` lên trước, giữ thứ tự phụ theo `sort`.
- Ngày bẩn (sai format · `end < start` · quá khứ · > 30 ngày) → props không có ngày, **không** 422.
- `vi-tri` + ngày kết hợp → tính đúng kho đó.
- Combo `in_range` = min theo item.
- **Query count không tăng theo số sản phẩm.**
- Collation-safe: không để dữ liệu nhiễu chứa từ khoá có dấu.

---

## T3 — FE: `RentalDatePicker` + trang chủ

**File mới:** `resources/js/Components/site/RentalDatePicker.tsx`

Wrap `DateRangeCalendar` (`resources/js/Components/site/DateRangeCalendar.tsx`) + select địa điểm + nút Xác nhận.
- Props: `variant: 'hero' | 'compact'`, `serviceLocations`, `initialStart`, `initialEnd`, `initialLocation`, `targetPath` (`/thiet-bi` hoặc `/combos`), `preserveParams`.
- `unavailable` truyền `new Set()` — trang chủ không tô ngày hết hàng (FR-1).
- Xác nhận `disabled` tới khi đủ `start` + `end`.
- Xác nhận → `router.get(targetPath, { ...preserveParams, start, end, 'vi-tri' })`.

**`resources/js/Pages/Welcome.tsx`:** thêm section dưới hero, trên `BiomeHero` (~dòng 117). `service_locations` đã có trong props.

**Test:** `tests/js/RentalDatePicker.test.tsx` (Vitest + testing-library, mock Inertia `router`)
- Nút Xác nhận disabled khi chưa đủ range, enabled khi đủ.
- Xác nhận gọi `router.get` đúng path + query.
- Giữ `cat`/`q`/`sort` đang có khi ở variant `compact`.
- Truy vấn theo `getByRole` / nhãn, không theo class.

---

## T4 — FE: listing hiện trạng-thái + thanh đổi ngày

**File:** `resources/js/Pages/Products.tsx`, `resources/js/Pages/Combos.tsx`

- Thanh `RentalDatePicker variant="compact"` phía trên bộ lọc.
- Chip "Đang xem: 12–14/08 · 3 ngày ✕" → bỏ `start`/`end`, giữ filter khác.
- Card `in_range === false`: `opacity-50`, badge "Hết hàng", nút thêm giỏ disable.
- Card available + `available <= 3`: nhãn "Còn N".
- Cập nhật type `ProductResource` (thêm `available?`, `in_range?`) — **không** dùng `any`.

---

## T5 — FE: prefill trang chi tiết

**File:** `resources/js/Pages/ProductDetail.tsx`, `resources/js/Pages/ComboDetail.tsx`

Đọc `start`/`end` từ query (Inertia `usePage().props.ziggy?.query` hoặc `URLSearchParams`), prefill lịch, **ưu tiên hơn** `cartSuggestedRange()` (`resources/js/lib/cart.ts`). Khách sửa được tự do — không lock.

---

## Quality gates (tất cả phải xanh trước commit)

```bash
php artisan test
npm test
npx tsc --noEmit
./vendor/bin/pint --test
npm run build
```

> `npm run lint` hiện **không dùng được** làm gate do Prettier drift toàn repo (`bopcamping-vbz1`). Chỉ chạy Prettier trên file mình sửa.

## Điểm cần xem lại về sau (tạo bead follow-up)

1. Sort `in_range` trong PHP sẽ sai khi `/thiet-bi` thêm phân trang.
2. "Còn N" ở chế độ "Tất cả" là **max qua các kho** — có thể lệch so với kho khách chọn sau.
3. Lịch trang chủ chưa tô ngày hết hàng — nếu về sau muốn, cần endpoint availability tổng hợp toàn shop theo ngày.

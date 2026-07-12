# Per-store Stock Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans (inline). Steps dùng checkbox `- [ ]`.

**Goal:** Tồn kho + availability theo từng cửa hàng (Vinh/Hà Nội); đơn gắn store; admin nhập số theo store + đổi store đơn; không tính khả dụng xuyên cửa hàng.

**Architecture:** Tồn kho lưu ở cột `quantity` của pivot `product_service_location` (nguồn chân lý). `AvailabilityService` nhận thêm tham số `ServiceLocation`. `orders.service_location_id` ghi store; checkout resolve store (khách chọn hoặc auto-gán store đủ cả giỏ). `products.quantity` giữ làm tổng hiển thị (= SUM per-store).

**Tech Stack:** Laravel 12 · Inertia · React/TS · Eloquent pivot withPivot.

**Spec:** `artifacts/design_spec_per_store_stock.md`

## Global Constraints

- Branch `feature/per-store-stock`; merge `develop` (stg) → test → `feat/scaffold-laravel`.
- Gates trước merge: `php artisan test` · `./vendor/bin/pint --test` · `npx tsc --noEmit` · `npm run build`.
- Migration tương thích SQLite + MySQL. Availability LUÔN per-store, không cộng A+B.
- Không dùng `any` TS. Mỗi task ≥1 commit.

---

### Task 1: Schema + Model (pivot quantity, orders.service_location_id)

**Files:**
- Create: `database/migrations/2026_07_12_000001_add_quantity_to_product_service_location.php`
- Create: `database/migrations/2026_07_12_000002_add_service_location_to_orders.php`
- Modify: `app/Models/Product.php` (serviceLocations withPivot), `app/Models/Order.php` (serviceLocation relation + fillable)
- Test: `tests/Feature/PerStoreStockTest.php` (tạo, mở rộng dần)

**Interfaces:**
- Produces: pivot `product_service_location.quantity` (uint default 0); `orders.service_location_id` (nullable FK), `orders.location_auto_assigned` (bool default false).
- Produces: `Product::serviceLocations()` có `->withPivot('quantity')`; `Order::serviceLocation(): BelongsTo`.

- [ ] **Migration 1** — pivot quantity + backfill dồn `products.quantity` vào store đầu (sort_order):

```php
public function up(): void {
    Schema::table('product_service_location', fn (Blueprint $t) => $t->unsignedInteger('quantity')->default(0));
    // Backfill: mỗi product dồn toàn bộ quantity cũ vào store phục vụ đầu tiên (sort_order).
    foreach (DB::table('products')->get() as $p) {
        $firstPivotId = DB::table('product_service_location as psl')
            ->join('service_locations as sl', 'sl.id', '=', 'psl.service_location_id')
            ->where('psl.product_id', $p->id)
            ->orderBy('sl.sort_order')->orderBy('sl.id')
            ->value('psl.service_location_id');
        if ($firstPivotId) {
            DB::table('product_service_location')
                ->where('product_id', $p->id)->where('service_location_id', $firstPivotId)
                ->update(['quantity' => $p->quantity]);
        }
    }
}
public function down(): void {
    Schema::table('product_service_location', fn (Blueprint $t) => $t->dropColumn('quantity'));
}
```
(import `DB`, `Blueprint`, `Schema`.)

- [ ] **Migration 2** — orders columns + backfill active orders:

```php
public function up(): void {
    Schema::table('orders', function (Blueprint $t) {
        $t->foreignId('service_location_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        $t->boolean('location_auto_assigned')->default(false)->after('service_location_id');
    });
    // Backfill: đơn cũ -> store phục vụ đầu tiên chung của các món (best-effort).
    foreach (DB::table('orders')->whereIn('status', ['pending','confirmed','renting'])->get() as $o) {
        $locId = DB::table('order_items as oi')
            ->join('product_service_location as psl', 'psl.product_id', '=', 'oi.product_id')
            ->join('service_locations as sl', 'sl.id', '=', 'psl.service_location_id')
            ->where('oi.order_id', $o->id)->where('sl.status', 'open')
            ->orderBy('sl.sort_order')->orderBy('sl.id')
            ->value('psl.service_location_id');
        if ($locId) DB::table('orders')->where('id', $o->id)->update(['service_location_id' => $locId]);
    }
}
public function down(): void {
    Schema::table('orders', function (Blueprint $t) {
        $t->dropConstrainedForeignId('service_location_id');
        $t->dropColumn('location_auto_assigned');
    });
}
```

- [ ] **Product.php**: `serviceLocations()` thêm `->withPivot('quantity')`. Helper `stockAt(int $locationId): int` = `(int) optional($this->serviceLocations->firstWhere('id',$locationId))->pivot->quantity ?? 0` (load nếu cần).
- [ ] **Order.php**: thêm `service_location_id`, `location_auto_assigned` vào `$fillable`; cast `location_auto_assigned` bool; `serviceLocation(): BelongsTo` → ServiceLocation.
- [ ] Test `PerStoreStockTest::test_pivot_stores_quantity_per_location` (tạo product sync 2 store với pivot quantity khác nhau, đọc lại đúng) + `test_migration_backfill` (bỏ qua — kiểm thủ công). Chạy `php artisan migrate`, test pass.
- [ ] Commit `feat(stock): pivot quantity per-store + orders.service_location_id`.

---

### Task 2: AvailabilityService per-store + StoreResolver

**Files:**
- Modify: `app/Services/AvailabilityService.php`
- Create: `app/Services/StoreResolver.php`
- Test: mở rộng `PerStoreStockTest`

**Interfaces:**
- Consumes: Task 1 pivot + `Order::serviceLocation`.
- Produces:
  - `AvailabilityService::availableQuantity(Product, Carbon, Carbon, ServiceLocation $location): int`
  - `AvailabilityService::availableByLocations(Product, Carbon, Carbon): array` → `[locationId => available]` cho store phục vụ.
  - `AvailabilityService::comboAvailable(Combo, Carbon, Carbon, ServiceLocation $location): int`
  - `AvailabilityService::unavailableDates(Product, Carbon, Carbon, ServiceLocation $location): array`
  - `StoreResolver::resolveForCart(Collection $lines, Carbon $start, Carbon $end, ?int $chosenLocationId): array{location: ServiceLocation, auto: bool}` — throws `\RuntimeException` với message tiếng Việt khi không store nào đủ.

- [ ] **availableQuantity** đổi chữ ký thêm `ServiceLocation $location`; tồn = `$product->stockAt($location->id)`; booked lọc thêm `->whereHas('order', fn($q)=>$q->where('service_location_id',$location->id)->...)`. `isAvailable` truyền tiếp `$location`.
- [ ] **availableByLocations**: loop `$product->serviceLocations` (open) → map id → availableQuantity tại store đó.
- [ ] **comboAvailable / comboInsufficientItems / nextComboWindow / checkCart / unavailableDates**: thêm `ServiceLocation $location`, truyền xuống availableQuantity. `checkCart` nhận thêm `$location`.
- [ ] **StoreResolver::resolveForCart**: 
  - Gom nhu cầu `[productId|start|end => qty]` từ lines (product + combo bung) như OrderController.
  - Nếu `$chosenLocationId` != null → chỉ xét store đó; đủ hết → `{location, auto:false}`, thiếu → throw.
  - Nếu null → duyệt các store `open` mà PHỤC VỤ mọi sản phẩm trong giỏ; store nào đủ toàn bộ nhu cầu → ứng viên; chọn store tổng khả dụng lớn nhất (tie sort_order) → `{location, auto:true}`; không có → throw "Khoảng này chưa cơ sở nào còn đủ cả giỏ — đổi ngày hoặc liên hệ shop nhé.".
- [ ] Test: `test_available_is_per_store` (A=5,B=0 → availableQuantity B=0, A=5); `test_booking_at_a_does_not_reduce_b` (đơn ở A không giảm B); `test_resolver_autopicks_store_with_stock`; `test_resolver_throws_when_no_single_store_fills_cart`; `test_combo_available_per_store`. Chạy pass.
- [ ] Commit `feat(stock): AvailabilityService per-store + StoreResolver auto-gán`.

---

### Task 3: Admin — nhập số lượng theo store

**Files:**
- Modify: `app/Http/Controllers/Admin/ProductController.php` (index payload, store/update validate+sync)
- Modify: `resources/js/Pages/Admin/Products.tsx` (ô số lượng theo store)
- Test: `tests/Feature/AdminProductStockTest.php`

**Interfaces:**
- Consumes: pivot quantity.
- Produces: payload sản phẩm admin thêm `stocks: {location_id: quantity}[]`; validate `stocks.*.quantity integer|min:0`, chỉ store trong `service_location_ids`.

- [ ] Backend index(): mỗi product trả `stocks` = `serviceLocations->map(fn($l)=>['service_location_id'=>$l->id,'quantity'=>$l->pivot->quantity])`. Bỏ phụ thuộc hiển thị vào `quantity` đơn (giữ trả `quantity` = tổng cho cột hiện có).
- [ ] Backend store()/update(): bỏ rule `quantity` bắt buộc (hoặc để optional); thêm `stocks` array validate. Sync: `serviceLocations()->sync()` với `[$id => ['quantity'=>$q]]` cho store đã tick; set `$product->quantity = array_sum($q)` rồi save. Tách helper `syncStocks(Product $p, array $data): void`.
- [ ] FE Products.tsx: form thay input "Số lượng" bằng: dưới mỗi store đã tick ở "Vị trí phục vụ", hiện ô number "Số lượng tại đây" (state `stocks: Record<number, number|''>`). Gửi `stocks` = mảng `{service_location_id, quantity}` cho store đã tick. Hiển thị cột "Kho" ở bảng = tổng (giữ `quantity`).
- [ ] Test: lưu 2 store số khác nhau → pivot đúng + `products.quantity`=tổng; store bỏ tick → pivot xoá; quantity âm → 422. Chạy pass + `tsc`.
- [ ] Commit `feat(admin): nhập tồn kho theo cửa hàng cho sản phẩm`.

---

### Task 4: Shop backend — availability theo store

**Files:**
- Modify: `app/Http/Controllers/Shop/ProductController.php` (show + availability endpoint)
- Modify: `app/Http/Controllers/Shop/CartController.php` (refresh trả available theo store nếu có location)
- Modify: `resources/js/types/product.ts`
- Test: `tests/Feature/ShopPerStoreTest.php`

**Interfaces:**
- Produces: prop `show`: `stock_by_location: [{id, name, slug, quantity, available}]` (available theo tồn tĩnh — client fetch động). Endpoint `/thiet-bi/{slug}/kha-dung?start&end&location_id?` → `{available}` (1 store) hoặc `{by_location: {id: n}}` (không truyền location_id).

- [ ] show(): thêm `stock_by_location` từ `availableByLocations` + tên store. `unavailable_dates` — hiện tính global; đổi: trả theo store phục vụ đầu (hoặc bỏ nếu FE tính theo store chọn — giữ đơn giản: trả map `unavailable_by_location`). YAGNI: giữ `unavailable_dates` cho store mặc định (store đầu) + để FE refetch theo store chọn qua endpoint.
- [ ] availability(): thêm optional `location_id`; có → trả `{available}` tại store đó (dùng `availableQuantity`); không → `{by_location}` map tất cả store phục vụ.
- [ ] CartController.refresh(): mỗi dòng có `location_id` → tính available tại store đó; giữ backward khi null (trả available store đầu hoặc bỏ — dùng: nếu null, min? → KHÔNG cộng; trả available store đầu phục vụ). Chốt: refresh nhận `loc` theo dòng, tính đúng store.
- [ ] types/product.ts: thêm `stock_by_location?: {id:number;name:string;slug:string;quantity:number;available:number}[]`.
- [ ] Test: show trả stock_by_location 2 store; endpoint location_id lọc đúng; endpoint không location trả by_location map. Pass.
- [ ] Commit `feat(shop): endpoint + props availability theo cửa hàng`.

---

### Task 5: Shop FE — hiển thị 2 store + chọn cơ sở

**Files:**
- Modify: `resources/js/Pages/ProductDetail.tsx`
- Modify: `resources/js/lib/cart.ts` (CartLine.location_id + conflict theo store chọn)

**Interfaces:**
- Consumes: `stock_by_location`, endpoint `?location_id`.
- Produces: `CartLine.location_id?: number | null`.

- [ ] State `storeId: number|null`. Nếu `stock_by_location.length > 1`: khối "Chọn cơ sở gần bạn" — mỗi store 1 nút (tên + "còn N" theo availByLoc động). Chọn → set storeId, fetch availability `?location_id=storeId` cho lịch/nút. 1 store → auto set storeId = store đó, không hiện nút.
- [ ] Availability fetch hiện tại (`/kha-dung`) thêm `&location_id=${storeId}` khi có store; hiện "còn N" theo store chọn. Chưa chọn (nhiều store) → hiện cả 2 số từ by_location, nút "Thêm vào giỏ" vẫn cho (location_id=null).
- [ ] buildLine gắn `location_id: storeId`. cart.ts: `cartCommonLocations`/`locationConflict` đổi sang so theo `location_id` đã chọn (2 dòng khác store đã chọn → conflict; null không xung đột).
- [ ] `tsc` + build + xem mắt. Commit `feat(shop): trang sản phẩm chọn cơ sở + tồn kho 2 cửa hàng`.

---

### Task 6: Checkout — resolve store + lưu đơn

**Files:**
- Modify: `app/Http/Controllers/Shop/OrderController.php`
- Test: `tests/Feature/OrderPerStoreTest.php`

**Interfaces:**
- Consumes: `StoreResolver`, `availableQuantity(...,$location)`.

- [ ] Validate thêm `items.*.location_id nullable|integer|exists:service_locations,id` + `combos.*.location_id` (giỏ 1 store → lấy location_id chung, null nếu chưa chọn).
- [ ] Bỏ block "giao vị trí phục vụ" cũ (dòng ~100-113) → thay bằng `StoreResolver::resolveForCart(lines, startDate, endDate, chosenLocationId)`; chosenLocationId = location_id đầu tiên không null trong lines (nếu có 2 khác nhau → 422 "giỏ chỉ 1 cơ sở"). Bắt `RuntimeException` → back withErrors.
- [ ] Kiểm kho: `availableQuantity($product, $s, $e, $resolved['location'])`.
- [ ] Order::create thêm `service_location_id => $resolved['location']->id`, `location_auto_assigned => $resolved['auto']`.
- [ ] Test: đặt chọn store A trừ A không đụng B; không chọn → auto-gán store đủ + cờ auto=true; A hết B còn → auto vào B; cả 2 thiếu → 422; 2 dòng khác store → 422. Pass.
- [ ] Commit `feat(checkout): gắn cửa hàng cho đơn + kiểm tồn theo store`.

---

### Task 7: Admin đơn hàng — hiện store + đổi store

**Files:**
- Modify: `app/Http/Controllers/Admin/OrderController.php` (payload + action changeLocation)
- Modify: `routes/web.php` (PATCH /admin/orders/{order}/location)
- Modify: `resources/js/Pages/Admin/Orders.tsx`
- Test: mở rộng `OrderPerStoreTest`

- [ ] index/show payload: thêm `service_location` (id, name) + `location_auto_assigned`.
- [ ] `changeLocation(Request, Order)`: validate `service_location_id exists`; kiểm store đích còn đủ MỌI món của đơn trong khoảng (availableQuantity tại store đích, +cộng lại phần chính đơn này đang chiếm nếu cùng store — đơn giản: vì đổi sang store khác nên đơn chưa chiếm store đích, check thẳng ≥ qty từng món); đủ → update service_location_id (+ location_auto_assigned=false), thiếu → back withErrors liệt kê món thiếu.
- [ ] Route trong group admin: `Route::patch('/orders/{order}/location', [AdminOrderController::class,'changeLocation'])->name('orders.location')`.
- [ ] FE Orders.tsx: hiện badge store + nhãn "Khách chọn"/"Hệ thống gán"; dropdown đổi store (PATCH). Toast theo flash.
- [ ] Test: đổi sang store đủ hàng OK + cờ auto=false; đổi sang store thiếu bị chặn; guest chặn. Pass + tsc.
- [ ] Commit `feat(admin): hiển thị + đổi cửa hàng cho đơn`.

---

### Task 8: Combo + Cart suggestion theo store (quét nơi còn dùng global)

**Files:**
- Modify: `app/Http/Controllers/Shop/ComboController.php`, `app/Http/Controllers/Shop/ProductController.php` (suggestionAvailability), `CartController.php` (suggestion)
- Test: mở rộng

- [ ] Grep `availableQuantity(` + `comboAvailable(` — mọi caller phải truyền store. ComboController show/alternatives, ProductController suggestionAvailability, CartController suggestion/refresh: nhận `location_id` (query/param), truyền vào. Combo detail hiện 2 store nếu combo phục vụ 2 store (giống sản phẩm) — YAGNI: combo lấy store theo `commonOpenLocations`, nếu >1 hiện chọn cơ sở như sản phẩm; nếu phức tạp, GĐ này combo chỉ tính theo store chọn ở giỏ (mặc định store đầu) + ghi chú.
- [ ] Đảm bảo KHÔNG còn nơi nào gọi availableQuantity thiếu store (build fail nếu thiếu — chữ ký bắt buộc). Chạy toàn bộ test.
- [ ] Commit `feat(stock): cập nhật combo/gợi ý theo cửa hàng`.

---

### Task 9: Quality gates + stg

- [ ] `php artisan test` toàn bộ · `pint --test` · `tsc` · `npm run build` pass.
- [ ] Preview: admin nhập tồn 2 store; trang sản phẩm chọn cơ sở; đặt đơn ở A/B; admin đổi store.
- [ ] Merge `develop` push (stg) → user test → merge `feat/scaffold-laravel` → `bd close`.

## Self-review

- **Spec coverage:** A→T1 · B→T2 · C→T4/T5 · D→T5/T6 · E→T3/T7 · F→mỗi task có test. Combo/callers→T8. Đủ.
- **Chữ ký nhất quán:** `availableQuantity(Product,Carbon,Carbon,ServiceLocation)` dùng thống nhất T2–T8; `resolveForCart(...): {location,auto}`; `stock_by_location` shape thống nhất T4/T5.
- **Rủi ro:** đổi chữ ký `availableQuantity` là breaking — T2 phải sửa HẾT caller cùng lúc (grep ở T8) kẻo build đỏ giữa chừng; chấp nhận T2 tạm đỏ tới khi T8 xong, hoặc sửa caller ngay trong T2. → **Quyết định:** trong T2 sửa luôn tất cả caller (thêm store mặc định tạm) để test xanh từng task; T8 tinh chỉnh store thật cho combo/gợi ý.

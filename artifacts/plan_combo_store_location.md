# Plan — Gán vị trí store cho từng combo

**Ngày:** 2026-07-31 · **PRD:** `artifacts/prd_combo_store_location.md` · **Nhánh:** `feature/combo-store-location`

Ước lượng 5–6 ngày. T1 chặn T2/T4; T6 độc lập nên làm song song được.

---

## T1 — Pivot + backfill + Model (chặn T2, T4)

**File:** migration mới `database/migrations/*_create_combo_service_location_table.php`, `app/Models/Combo.php`

```php
Schema::create('combo_service_location', function (Blueprint $table) {
    $table->foreignId('combo_id')->constrained()->cascadeOnDelete();
    $table->foreignId('service_location_id')->constrained()->cascadeOnDelete();
    $table->primary(['combo_id', 'service_location_id']);
});
```

**Backfill (trong cùng migration):** với mỗi combo, gán tập `commonOpenLocations()` đang tính ra. Tập rỗng → gán **tất cả kho đang mở**. Viết bằng query thô (`DB::table`) chứ không gọi Model — migration không được phụ thuộc code app có thể đổi sau. Logic giao: lấy `product_service_location` join `service_locations` (`status=open`) của mọi món con rồi đếm — kho nào xuất hiện đủ số món phân biệt của combo thì thuộc tập giao.

**Model `Combo`:** 4 method theo PRD FR-2. **Xoá** `commonOpenLocations()` và sửa 4 chỗ gọi (`AccountController:283`, `CartController:128`, `CartController:316`, `Shop/ComboController:207`) sang `openLocations()`.

> ⚠️ `openLocations()` trả cùng dạng `[{slug, name}]` như `commonOpenLocations()` để 4 chỗ gọi và FE không phải đổi type.

**Tests — `tests/Feature/ComboStoreLocationTest.php`:**
- Backfill: combo có kho chung → gán đúng tập đó; combo **không** có kho chung → gán tất cả kho đang mở (KHÔNG rỗng).
- `assignableLocationIds()`: kho mà mọi món con đều được gán; món chưa gán kho X → X không có trong tập. **Không** phụ thuộc tồn (combo mọi món tồn 0 vẫn ra đủ kho — neo đúng ca prod, PRD R2).
- `stockAtLocation()`: trả đúng `[productId => qty]`, món không phục vụ ở kho đó → 0.
- `openLocations()` loại kho `status=coming`.

**Gate:** `php artisan test --filter=ComboStoreLocationTest`, `pint`.

---

## T2 — Admin: validate + sync + props

**File:** `app/Http/Controllers/Admin/ComboController.php`

- `validated()` thêm `service_location_ids` → `required|array|min:1`, `service_location_ids.*` → `integer|exists:service_locations,id`.
- Validate **nghiệp vụ** (sau `validate()`): mỗi id phải ∈ `assignableLocationIds` tính từ **items đang gửi lên** (chưa lưu). Sai → `ValidationException::withMessages` nêu tên món + tên kho. Tính bằng query trên `product_service_location`, không dựng Model tạm.
- `store`/`update`: `$combo->serviceLocations()->sync($data['service_location_ids'])` trong cùng `DB::transaction` với items.
- `index()` props thêm:
  - `service_locations` — kho `status=open`, `[id, name, slug]`
  - `location_stock` — `{ locationId: { productId: qty } }` cho **toàn bộ** sản phẩm (1 query trên pivot, prod 11 sản phẩm)
  - mỗi combo trong `combos` thêm `service_location_ids`
- `products` prop thêm `service_location_ids` mỗi sản phẩm (để FE biết món nào phục vụ kho nào mà tính assignable ngay lúc gõ).

**Tests:** `tests/Feature/AdminComboStoreLocationTest.php`
- Lưu combo kèm kho → pivot đúng.
- **Từ chối** kho mà có món con không phục vụ, message nêu tên món.
- **Chấp nhận** kho mà mọi món con tồn 0 (ca prod — không được chặn theo tồn).
- Thiếu `service_location_ids` → 422.
- `update` thay đổi tập kho → sync đúng, không sót dòng cũ.
- Props có `location_stock` và `service_location_ids`.

---

## T3 — Admin UI (cần T2 để có props)

**File mới:** `resources/js/Pages/Admin/combo/ComboLocationPicker.tsx`
**Sửa:** `resources/js/Pages/Admin/Combos.tsx` (đang 615 dòng — chỉ nhúng component, không nhồi logic)

Props: `locations`, `locationStock`, `products` (kèm `service_location_ids`), `items` (đang chọn), `value: number[]`, `onChange`.

- Chip kho: enable khi mọi món trong `items` có kho đó trong `service_location_ids`. Disabled → `title` + dòng lý do *"Đệm hơi: không phục vụ tại Hà Nội"*.
- Bảng "Món tại kho này" cho từng kho ĐÃ tích: liệt kê món `locationStock[loc][product] > 0` kèm số tồn.
- Món tồn 0 → một dòng vàng gom lại: *"2 món đang hết hàng tại kho này: Bàn gấp, Bếp BBQ"*. **Không** chặn lưu.
- `useEffect`: `items` đổi làm kho đang tích thành không assignable → bỏ tích kho đó + `emit(EVENTS.toast, ...)`.

**Test:** `tests/js/ComboLocationPicker.test.tsx`
- Chip kho có món chưa phục vụ → `disabled` + hiện lý do.
- Bảng chỉ liệt kê món tồn > 0; món tồn 0 xuất hiện ở dòng cảnh báo, KHÔNG ở bảng.
- Combo mọi món tồn 0 → chip vẫn **enable** (neo quyết định #2).
- Thêm món làm kho hết hợp lệ → kho tự bỏ tích, gọi `onChange` không còn id đó.
- Truy vấn theo `getByRole`/nhãn, không theo className.

---

## T4 — Phía khách (cần T1)

**File:** `app/Http/Controllers/Shop/ComboController.php`, `app/Services/AvailabilityService.php`

- `index()`: `?vi-tri=X` → thêm `whereHas('serviceLocations', fn ($q) => $q->whereKey($activeLocation->id))`. Hiện `vi-tri` chỉ dùng để tính khả dụng, chưa lọc.
- `comboQuantitiesFor()`: khi `$location === null`, tập kho quét đổi từ "mọi kho xuất hiện trong matrix món con" sang **kho được gán của combo** (∩ open). Combo 0 kho → 0 (không bán được), KHÔNG fallback toàn cục.
  > Giữ nguyên công thức `max` qua kho của `min` qua món (đã sửa ở `bopcamping-jyxi`) — chỉ đổi *tập kho* được quét.
- `show()` + 3 chỗ còn lại: dùng `openLocations()` (đã đổi ở T1).

**Tests:** `tests/Feature/ComboCustomerLocationTest.php`
- `/combos?vi-tri=X` chỉ trả combo được gán X.
- Combo gán 1 kho: `available` tính theo đúng kho đó, không lấy kho khác dù món con còn hàng ở đó.
- Combo 0 kho → `available = 0`.
- `locations` trong props chi tiết/giỏ/tài khoản = kho được gán, không phải giao món con.
- **Query count** không tăng theo số combo (giữ NFR của `bopcamping-j91m`).

---

## T5 — Checkout validation (cần T1 + T4)

**File:** `app/Http/Controllers/Shop/OrderController.php`

Sau khi `StoreResolver` chốt `$resolved['location']`: mỗi combo trong `$comboLines` phải có location đó ∈ `openLocations()`. Sai → `RuntimeException` message nêu tên combo + tên kho, dùng đúng đường xử lý lỗi hiện có của `resolveForCart`.

> Đơn không gắn kho (`location = null`, dữ liệu cũ/legacy) → **bỏ qua** check này, không chặn.

**Tests:** bổ sung vào `ComboCustomerLocationTest`
- Combo chỉ bán ở Vinh + giỏ chốt Hà Nội → **từ chối**, message nêu tên combo.
- Khớp kho → đặt được, `order.service_location_id` đúng.
- Đơn `location = null` → không bị chặn.

---

## T6 — Chặn bế tắc "locations rỗng" (độc lập, làm song song được)

**File:** `resources/js/lib/cart.ts`

- `locationConflict()`: `newLocations` rỗng hiện trả `conflict:false` (dòng 157) → đổi thành **conflict** (món không bán được ở đâu cả).
- `cartHasLocationConflict()`: dòng 166 đang loại dòng có `locations` rỗng khỏi phép tính → đổi thành coi dòng rỗng là mâu thuẫn.

**Đã kiểm (31/07/2026):** không sản phẩm nào rỗng kho — dev **0/9**, prod **0/11** (backfill của migration `2026_06_26_000010` đã phủ hết). Nên đổi `locations` rỗng thành conflict **không chặn oan** sản phẩm nào. Nếu về sau ai thêm sản phẩm mà bỏ trống kho thì T6 sẽ chặn nó — đó là hành vi **mong muốn** (món không bán được ở đâu thì không nên vào giỏ).

**Test:** `tests/js/cartLocationConflict.test.ts`
- Dòng có `locations` rỗng → `locationConflict` báo conflict.
- Giỏ chứa dòng rỗng → `cartHasLocationConflict` = true.
- Ca cũ (có kho chung) vẫn không conflict — không phá hành vi hiện tại.

---

## Quality gates (tất cả phải xanh trước commit)

```bash
php artisan test
npm test
npx tsc --noEmit
./vendor/bin/pint --test
npm run build
```

> `npm run lint` toàn repo không dùng được làm gate (Prettier drift — `bopcamping-vbz1`). Chỉ chạy `npx eslint <file mình sửa>`.

## Verify tay trước khi deploy

DB dev có **0 combo** và tồn pivot phần lớn = 0 → phải seed để test được (xem `artifacts/qa_report_2026-07-30_date_first_booking.md` mục "Môi trường test"; backup DB trước, restore sau).

Kịch bản: gán kho cho combo → khách ở kho khác không thấy → thêm vào giỏ bị chặn với toast → đổi kho thì thấy lại.

## Điểm cần xem lại về sau (tạo bead follow-up)

1. Tồn 0 trên prod (8/11 sản phẩm) làm bảng "Món tại kho này" gần như trống — thông tin đúng nhưng ít giá trị tới khi shop nhập tồn (`bopcamping-ry4u`).
2. Chưa có tồn riêng cho combo: 2 combo dùng chung một món vẫn cạnh tranh cùng tồn sản phẩm. Đúng thiết kế hiện tại, nhưng cần ADR nếu sau này muốn giữ hàng theo combo.

# Plan — Tính năng Thuê theo Combo

> **Artifact:** `plan_combo.md` · **Nguồn:** [prd_combo.md](prd_combo.md) · **ADR:** [adr_combo_data_model.md](adr_combo_data_model.md)
> **Ngày:** 2026-07-02 · **Phasing theo PRD mục 9:** P1 → P4, mỗi phase một branch riêng + Beads issue riêng.

## Nguyên tắc xuyên suốt

1. **Tồn kho combo KHÔNG viết mới** — chỉ mở rộng `AvailabilityService` với `comboAvailable()`, bên trong gọi lại `availableQuantity()` per-product (PRD 5.1, AC-10). Grep codebase không được có công thức overlap thứ hai.
2. Đơn combo **bung thành `order_items` per-product** (PRD mục 4) — logic chồng lịch hiện tại tự đúng, không cần cơ chế giữ chỗ riêng.
3. Test viết **trước** khi implement (Feature test cho availability/pricing/detection từng phase).
4. Quality gates mỗi phase: `php artisan test` · `./vendor/bin/pint --test` · `npx tsc --noEmit` · `npm run build`.
5. Migration tương thích cả SQLite (test/dev cũ) và MySQL 8 (dev qua Docker/prod).

---

## P1 — Nền tảng dữ liệu + AvailabilityService + Admin CRUD (US-06, US-07)

**Branch:** `feature/combo-p1` · **Phụ thuộc:** —

### 1. Migrations (5 file, prefix `2026_07_02_1000xx`)

| File | Nội dung |
|---|---|
| `create_combos_table` | `id, name, slug (unique), description (text null), combo_price decimal(12,0), deposit decimal(12,0) null, suitable_for unsignedTinyInteger null, is_active bool default true, sort_order unsignedInteger default 0, timestamps` |
| `create_combo_items_table` | `id, combo_id FK cascadeOnDelete, product_id FK cascadeOnDelete, quantity unsignedInteger, timestamps` + `UNIQUE(combo_id, product_id)` |
| `create_combo_images_table` | `id, combo_id FK cascadeOnDelete, path, sort_order default 0, type enum(image,video) default image, timestamps` — bảng riêng, KHÔNG polymorphic (xem ADR-3) |
| `add_combo_fields_to_order_items_table` | `combo_id FK null nullOnDelete, combo_group_uuid uuid null (index), allocated_price decimal(12,0) null, allocated_deposit decimal(12,0) null` — dùng ở P2, tạo sẵn ở P1 vì P1 = toàn bộ schema combo |
| `add_applicable_to_combos_to_vouchers_table` | `applicable_to_combos bool default false` (PRD mục 7) |

`product_accessories` **KHÔNG** nằm ở P1 — PRD xếp nó vào P3.

### 2. Models

- `Combo`: fillable/casts đủ cột; `items()` (hasMany, with product), `images()` (orderBy sort_order), `scopeActive`; helper `sumIndividualPrice(): int` (Σ giá lẻ × qty — tính runtime, không lưu, PRD 5.2), `savingsAmount()`, `savingsPercent()`; `Combo::hideForProduct(Product $p): int` — single source cho US-07 (ẩn mọi combo active chứa product + ghi `Log::info`).
- `ComboItem`, `ComboImage`: model mỏng, quan hệ belongsTo.
- `OrderItem`: thêm fillable/casts cho 4 cột mới + quan hệ `combo()`.
- `Voucher`: thêm `applicable_to_combos` vào fillable/casts.

### 3. AvailabilityService (mở rộng — KHÔNG sửa hàm cũ)

```php
comboAvailable(Combo $combo, Carbon $start, Carbon $end): int
// = min( intdiv(availableQuantity(product_i), quantity_i) ), combo rỗng → 0
isComboAvailable(Combo $combo, Carbon $start, Carbon $end, int $needed = 1): bool
```

### 4. Admin CRUD combo

- `Admin/ComboController`: `index` (bảng: tên, số món, giá combo, tổng giá lẻ, % tiết kiệm server-tính, trạng thái) · `store`/`update` (validate items array, product distinct + exists, quantity ≥ 1; **warning override** khi `combo_price ≥ sum_individual` — chặn bằng validation error trừ khi gửi `confirm_over_price = true`, PRD 5.2) · `destroy` (xoá ảnh storage + combo, items cascade) · `storeImage`/`destroyImage` (mirror ProductController, có chặn IDOR).
- Routes trong nhóm `admin.` + throttle như các CRUD khác.
- US-07 backend: `Admin/ProductController@update` (khi status → hidden) và `@destroy` gọi `Combo::hideForProduct()`; payload index thêm `combo_names` để FE hiện cảnh báo.
- FE: `Pages/Admin/Combos.tsx` (theo pattern Products.tsx: bảng + modal form với product-picker kèm quantity, preview tổng giá lẻ & % tiết kiệm live, upload ảnh/video ở hàng expand) + mục nav "Combo" trong `AdminLayout.tsx` + cảnh báo combo trong dialog ẩn/xoá sản phẩm ở `Products.tsx`.

### 5. Tests (viết trước)

- `ComboAvailabilityTest`: min across items; `intdiv` khi quantity item > 1; đơn chồng lịch trừ đúng; đơn cancelled không trừ; ngày kề nhau không chồng; combo rỗng → 0; không âm.
- `AdminComboTest`: tạo/sửa/xoá combo (kèm sync items); non-admin bị chặn; over-price cần confirm; upload/xoá ảnh + IDOR; **US-07**: ẩn product → combo chứa nó tự ẩn; xoá product → combo tự ẩn + combo_items cascade.

### Deliverables P1
Toàn bộ schema + models + `comboAvailable()` + trang Admin Combos chạy được end-to-end (tạo combo trong admin), tests xanh, gates pass, push branch.

---

## P2 — Public pages + checkout combo (US-01, US-02, US-05, Case 4)

**Branch:** `feature/combo-p2` · **Phụ thuộc:** P1

- `Shop/ComboController`: `/combos` (danh sách + date-picker chung, badge % tiết kiệm, còn/hết qua `comboAvailable`), `/combos/{slug}` (gallery, bảng so sánh giá, check realtime, Case 4: món nào hết + khoảng 30 ngày tới còn đủ + sản phẩm thay thế cùng danh mục — chỉ tham khảo).
- Checkout: giỏ hàng nhận combo item; khi đặt, bung combo → N `order_items` cùng `combo_group_uuid`, phân bổ `allocated_price`/`allocated_deposit` theo tỷ lệ giá lẻ, làm tròn 100₫, món cuối nhận dư (PRD 5.3, AC-3) — logic phân bổ đặt trong service riêng (`ComboPricingService` hoặc method trong Combo), test số học kỹ.
- Validate checkout combo qua `isComboAvailable` (AC-2, AC-4).
- Homepage section "Combo tiết kiệm" (3–4 combo theo `sort_order`).
- Giỏ hàng: nhóm items theo `combo_group_uuid`, khối mở rộng được.
- Voucher: đơn hỗn hợp — voucher thường chỉ tính trên phần lẻ; flag `applicable_to_combos` mở rộng (AC-8).
- Tests: pricing allocation (tổng khớp từng đồng), double-booking combo (AC-2), chiếm tồn kho chéo lẻ↔combo (AC-4), voucher × combo (AC-8).

## P3 — "Thường thuê cùng" (US-03, US-08, Case 2)

**Branch:** `feature/combo-p3` · **Phụ thuộc:** P1

- Migration `create_product_accessories_table` (`UNIQUE(product_id, related_product_id)`).
- Admin: tab "Thường thuê cùng" trong form sản phẩm.
- Trang sản phẩm: section gợi ý (chỉ món còn hàng trong khoảng ngày đang chọn — qua AvailabilityService, AC-9) + nút "Thêm tất cả vào giỏ"; banner "thuộc combo X" ưu tiên hơn gợi ý lẻ (PRD 5.6).
- Tests: AC-9, banner ưu tiên combo, admin CRUD accessories.

## P4 — Cart detection + upsell + analytics (US-04, US-09, Case 3)

**Branch:** `feature/combo-p4` · **Phụ thuộc:** P1 + P2 + P3

- Service `ComboSuggestionService`: match giỏ vs combo active (khớp đủ / superset / thiếu 1 món — PRD 5.4), chỉ gợi ý khi `comboAvailable ≥ 1` và thực sự rẻ hơn sau voucher; nhiều combo khớp → chọn tiết kiệm nhất, tối đa 1 gợi ý.
- Convert 1 click (giữ khoảng ngày, thay items lẻ bằng combo); đổi ngày trong giỏ → re-check ngay (ADR-5).
- Event log `combo_suggestion_shown` / `combo_suggestion_converted` (bảng events hoặc log có cấu trúc — chốt khi làm) → dashboard widget: top combo theo lượt thuê + convert rate.
- Tests: 3 tình huống match, ràng buộc không gợi ý khi hết hàng/không rẻ hơn (AC-5, AC-6), event ghi đúng.

---

## Beads mapping

| Phase | Issue | Dep |
|---|---|---|
| P1 | `combo P1 — schema + AvailabilityService + admin CRUD` | — |
| P2 | `combo P2 — public pages + checkout` | P1 |
| P3 | `combo P3 — product accessories + gợi ý trang sản phẩm` | P1 |
| P4 | `combo P4 — cart detection + upsell + dashboard` | P1, P2, P3 |

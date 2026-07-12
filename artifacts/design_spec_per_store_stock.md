# Design Spec — Quản lý tồn kho theo cửa hàng (per-store stock)

- **Ngày:** 2026-07-12
- **Trạng thái:** Đã duyệt thiết kế (hội thoại), chờ review spec
- **Branch:** `feature/per-store-stock` từ `feat/scaffold-laravel`
- **Vấn đề:** Shop có 2 cửa hàng (Vinh, Hà Nội) ở xa nhau, mỗi nơi giữ số lượng khác nhau. Cần tồn kho + availability **theo từng cửa hàng**; đặt ở A trừ kho A, đặt ở B trừ kho B; **không tính khả dụng xuyên cửa hàng** (tránh hứa món không giao kịp). Điều chuyển kho là thao tác chỉnh tay có kế hoạch, không phải fulfill real-time.

> Đây là thay đổi vào **logic cốt lõi** (AvailabilityService — single source of truth). Mọi nơi tính "còn hàng" đều phải đi qua hàm per-store duy nhất.

## Quyết định đã chốt (từ brainstorming)

1. **Trang sản phẩm hiện tồn kho cả 2 store** ("Vinh: còn 3 / Hà Nội: còn 0"), có title "Chọn cơ sở gần bạn". Khách **được phép không chọn**.
2. **Mọi đơn luôn có cửa hàng ngay khi đặt.** Khách chọn → dùng store đó. Khách không chọn → **hệ thống tự gán** store còn đủ cả giỏ. Trừ kho ngay, không overbook. Admin đổi store sau (theo địa chỉ) nếu store đích còn hàng.
3. **Chỉnh kho gọn:** số lượng theo store sửa trực tiếp trong form sản phẩm; KHÔNG sổ nhập/xuất/điều chuyển có lịch sử (ngoài phạm vi).
4. **Di trú dữ liệu cũ:** dồn toàn bộ `products.quantity` hiện tại vào **store phục vụ đầu tiên** (theo `sort_order`), store còn lại = 0; admin chỉnh lại số thật sau. Sản phẩm chỉ phục vụ 1 store → tự đúng.

## A. Mô hình dữ liệu

### Tồn kho theo store — pivot `product_service_location`
- Thêm cột `quantity` (unsignedInteger, default 0). Mỗi dòng pivot = (product, store) → số tồn tại store đó.
- **Nguồn chân lý** cho tồn kho. Sản phẩm KHÔNG có dòng pivot ở store nào = không phục vụ ở đó (khác với "phục vụ nhưng đang hết").
- `Product::serviceLocations()` giữ nguyên quan hệ, bổ sung `->withPivot('quantity')`.

### `products.quantity` — đổi vai thành TỔNG (denormalized, chỉ để hiển thị)
- Không còn dùng cho availability. Mỗi khi admin lưu tồn per-store → cập nhật `products.quantity = SUM(pivot.quantity)`.
- Giữ để các chỗ hiển thị "tổng còn X bộ" / cảnh báo sắp hết không vỡ; nhưng ý nghĩa giờ là "tổng toàn hệ thống" (không phải khả dụng theo store).

### `orders` — thêm 2 cột
- `service_location_id`: `foreignId->nullable()->constrained()` (nullable để backfill đơn cũ an toàn; đơn mới luôn set).
- `location_auto_assigned`: `boolean, default false` — true khi hệ thống tự gán store (khách không chọn) → admin biết đơn nào cần review theo địa chỉ.
- Backfill migration: đơn cũ → gán store phục vụ đầu tiên (theo sort_order) trong số store chung của các món; không suy ra được thì để null (chỉ đơn active mới thực sự cần — admin xử lý số ít).
- `Order::serviceLocation()` belongsTo.

## B. Logic availability (AvailabilityService)

### `availableQuantity(Product, Carbon $start, Carbon $end, ServiceLocation $location): int`
- **Bắt buộc truyền store** (bỏ biến thể toàn cục — mọi chỗ gọi đều biết store, hoặc lặp theo store).
- Công thức:
  ```
  tồn_tại_store = pivot.quantity(product, location)   // 0 nếu không có dòng pivot
  đã_đặt = SUM(OrderItem.quantity)
     WHERE product_id = product
       AND order.service_location_id = location.id
       AND order.status IN [pending, confirmed, renting]
       AND order.start_date <= end AND order.end_date >= start
  return max(0, tồn_tại_store − đã_đặt)
  ```
- **Không bao giờ cộng tồn giữa các store.**

### `availableByLocations(Product, $start, $end): array<location_id, int>`
- Helper trả khả dụng theo TỪNG store phục vụ (dùng cho trang sản phẩm hiện 2 số).

### `comboAvailable(Combo, $start, $end, ServiceLocation $location): int`
- Thêm store; min các món tại store đó.

### `unavailableDates(Product, $start, $end, ServiceLocation $location)`
- Thêm store; ngày hết = đã_đặt tại store ≥ tồn tại store.

## C. Trang sản phẩm (khách)

- Controller `show()` trả `stock_by_location`: `[{location_id, name, slug, quantity, available_static}]` cho các store phục vụ (available theo ngày fetch động như hiện tại).
- Endpoint `/thiet-bi/{slug}/kha-dung` thêm query `?location_id=` → trả khả dụng store đó. Không truyền → trả map tất cả store phục vụ `{by_location: {id: available}}`.
- UI:
  - Sản phẩm phục vụ **>1 store**: khối "Chọn cơ sở gần bạn" — mỗi store 1 nút hiện "Vinh · còn N" / "Hà Nội · còn N" (theo khoảng ngày đã chọn; chưa chọn ngày thì hiện tồn tĩnh). Chọn store nào → lịch/giá/nút thêm giỏ theo store đó.
  - Sản phẩm **1 store**: hiện thẳng store đó (không cần nút chọn), tự set store cho dòng giỏ.
  - Khách không bấm chọn ở sản phẩm nhiều store → dòng giỏ mang `location_id = null` (chưa chọn), checkout tự gán.

## D. Giỏ + Checkout

### Giỏ (client, `cart.ts`)
- `CartLine` thêm `location_id?: number | null` (store khách chọn cho dòng đó).
- Ràng buộc **1 store/giỏ**: nếu giỏ đã có dòng chốt store A, thêm dòng chốt store B → popup xung đột (tái dùng cơ chế `locationConflict` hiện có, đổi từ "vị trí phục vụ chung" sang "store đã chọn"). Dòng chưa chọn store (null) không gây xung đột.

### Checkout (`OrderController::store`)
1. Xác định store của đơn:
   - Có dòng đã chọn store → đó là store đơn (mọi dòng phải cùng store hoặc null).
   - Không dòng nào chọn → **auto-pick**: tìm store mà MỌI món trong giỏ còn đủ số cho khoảng ngày; nhiều store thỏa → chọn store tổng khả dụng lớn hơn (tie-break sort_order). Không store nào thỏa → lỗi 422 "Khoảng này không cơ sở nào còn đủ cả giỏ, đổi ngày hoặc liên hệ shop nhé".
2. Validate tồn kho: gọi `availableQuantity(..., $store)` cho từng (món, khoảng) — gộp nhu cầu lẻ + combo như hiện tại.
3. Lưu `order.service_location_id = store.id` + cờ `location_auto_assigned` (để admin biết đơn nào hệ thống tự gán) — lưu cờ này trong `note`/cột phụ? → dùng cột `location_auto_assigned` boolean cho rõ.
4. Trừ kho: giữ mô hình động hiện tại (đơn active gắn store làm giảm availability store đó) — không cần cột "đã trừ".

## E. Admin

### Form sản phẩm (`Admin/Products.tsx` + `Admin/ProductController`)
- Giữ nguyên khối chọn **"Vị trí phục vụ"** (tick store) như hiện tại; mỗi store **đã tick** hiện thêm 1 ô number **"Số lượng tại đây"** ngay cạnh. Bỏ ô "Số lượng" đơn lẻ cũ.
- Store được tick nhưng để trống số = 0 (phục vụ nhưng đang hết). Store không tick = không phục vụ (không tạo dòng pivot).
- Payload: `service_location_ids` (mảng id đã tick, giữ nguyên) + `stocks` = map `{service_location_id: quantity}`. Validate `stocks.*` `integer|min:0`; chỉ nhận quantity cho store nằm trong `service_location_ids`.
- Lưu: `serviceLocations()->sync()` với pivot `['quantity' => ...]` theo store đã tick; sau đó `products.quantity = SUM(quantity)`.

### Màn đơn hàng (`Admin/Orders` + `Admin/OrderController`)
- Hiện store của đơn + nhãn "Khách chọn" / "Hệ thống gán".
- Nút **đổi store**: PATCH kiểm tra store đích còn đủ toàn bộ món của đơn trong khoảng ngày (dùng availableQuantity trừ chính đơn này ra) → đổi hoặc báo thiếu.

## F. Testing

- `AvailabilityService`: available theo store (A=5/B=0 → đặt B fail, A ok); không cộng xuyên store; combo per-store.
- Checkout: khách chọn store → trừ đúng store; auto-gán khi không chọn (chọn store đủ hàng); lỗi khi không store nào đủ cả giỏ; 2 store — đặt A không ảnh hưởng B.
- Admin: lưu quantity per-store cập nhật tổng; đổi store đơn có kiểm tồn; đổi sang store thiếu hàng bị chặn.
- Migration: dồn quantity cũ vào store đầu; backfill order location.

## Quality gates
`php artisan test` · `./vendor/bin/pint --test` · `npx tsc --noEmit` · `npm run build`

## Ngoài phạm vi
- Sổ nhập/xuất/điều chuyển có lịch sử (audit trail).
- Nhiều hơn 2 store / phân quyền admin theo store.
- Giao hàng liên store / gộp tồn khi 1 store thiếu.

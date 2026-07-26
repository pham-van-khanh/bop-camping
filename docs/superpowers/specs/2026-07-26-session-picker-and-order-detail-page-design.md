# Thiết kế: Chọn buổi (sáng/chiều/cả ngày) + Màn hình đơn riêng cho admin

Ngày: 2026-07-26
Nhánh nền: `develop` (= `feature/staging-all`, gộp buffer/half-day/extra-fee/product-edit/parent-child/hours)
Beads liên quan: kế thừa n6mr (giờ khách chọn), jrh8 (giá nửa ngày), sẽ tạo bead mới cho A + B.

## Bối cảnh & vấn đề

Hiện trang sản phẩm cho khách chọn **giờ nhận/trả tự do** (`<input type=time>`, n6mr) khi thuê đúng 1 ngày, lưu vào `orders.requested_pickup_time/return_time`. Giá nửa ngày (jrh8) áp qua `products.early_return_discount_pct` khi `days===1` + `is_half_day`.

Chủ shop muốn thay bằng mô hình **3 buổi rõ ràng** — *Buổi sáng · Buổi chiều · Cả ngày* — khớp giờ mở/đóng cửa đặt trong admin, giá tự cập nhật theo buổi, và hiện rõ ở checkout. Đồng thời, mỗi đơn ở admin cần **màn hình riêng** để chứa nhiều thông tin thay vì gói trong danh sách.

## Quyết định đã chốt (với chủ shop)

1. **Giá buổi** = giá ngày × (1 − `early_return_discount_pct` của SP). Tái dùng field sẵn có. Cả ngày = giá đầy đủ.
2. **Ranh giới buổi** = thêm setting `site_settings.session_split_hour` (mặc định 14). Sáng = mở→split, Chiều = split→đóng.
3. **Thay hẳn** ô chọn giờ tự do bằng 3 buổi.
4. **Chỉ áp dụng khi thuê đúng 1 ngày** (`start === end`). Nhiều ngày = cả ngày, không chọn buổi.
5. **Danh sách đơn admin** vẫn giữ **đổi trạng thái nhanh**; mọi thao tác khác chuyển vào màn chi tiết.
6. Làm **cả A (chọn buổi) + B (màn đơn riêng)** trong cùng spec, triển khai tuần tự A → B.

---

## Phần A — Chọn buổi ở trang sản phẩm

### Bám theo ADR đã chốt (`artifacts/adr_pricing_models.md`)

ADR mục 2 **giữ INVARIANT**: giờ/buổi KHÔNG tham gia tính tồn kho — **mọi lượt khoá TRỌN NGÀY**. "Nửa ngày" = khoá cả ngày, chỉ **giảm giá trả sớm** (`early_return_discount_pct`), KHÔNG giải phóng capacity. Spec này **tuân thủ tuyệt đối** điều đó.

**Hệ quả cho lo ngại vệ sinh/quay vòng:**
- **Trường hợp A (ADR mục 4)** — khách chọn "buổi chiều" online: vì khoá trọn ngày, hệ thống chỉ chào unit **CÒN TRỐNG cả ngày** (vd cái ghế thứ 2), KHÔNG bao giờ chào một unit đã có đơn hôm đó. ⇒ Không có rủi ro giao đồ chưa vệ sinh. Tự chạy, không build gì thêm về tồn kho.
- **Trường hợp B (ADR mục 6)** — cho thuê lại CHÍNH cái vừa về trong ngày ("đồ chỉ lau qua" = `buffer_days = 0`): **admin tự bấm `returned` sau khi lau xong, rồi TỰ TẠO đơn buổi chiều**. KHÔNG mở cửa sổ chiều tự động cho khách online. Đồ `buffer_days > 0` (lều/túi ngủ): **cấm quay vòng trong ngày**.
- `session_split_hour` **chỉ để hiển thị khung giờ + phân biệt sáng/chiều cho GIÁ**, không tạo suất turnaround. Kiểm soát vệ sinh vẫn hoàn toàn là `buffer_days` (theo ngày) trong `AvailabilityService`.

### Nguyên tắc: tái dùng đường half-day sẵn có, không phá dữ liệu cũ

Buổi sáng/chiều = một dạng "half-day" (ưu đãi trả sớm) đã có. Nên **KHÔNG thêm logic giá/tồn kho mới**; chỉ thêm nhãn `session` để phân biệt sáng/chiều (cùng `is_half_day`) và suy ra giờ hiển thị.

**Sai lệch DUY NHẤT so với ADR** (ADR mục 3 nói "không thêm enum session" — trong bối cảnh *không làm tồn kho theo buổi*): ta thêm cột `orders.session` nhưng **thuần HIỂN THỊ + phân biệt giờ sáng/chiều**, KHÔNG dùng cho tồn kho. Cần nó vì yêu cầu mới của chủ shop là 3 lựa chọn có tên + khung giờ riêng, mà `is_half_day` (boolean) không phân biệt được sáng vs chiều. Giữ đúng tinh thần ADR: không có `session_price`, `session_buffer_minutes`, không nhánh tồn kho theo buổi.

### Mô hình dữ liệu

- **Migration mới** `add_session_to_orders`: cột `orders.session` — `string` nullable, giá trị `morning` | `afternoon` | `full` | `null`. `null` = thuê nhiều ngày (dùng khung mặc định, không phải nửa ngày).
- **Setting mới**: `site_settings.session_split_hour` — cột `integer`, mặc định 14. `SiteSetting` lưu **dạng cột** (singleton), nên: migration `add_session_split_hour_to_site_settings` thêm cột `integer default 14`, cộng thêm `'session_split_hour' => 14` vào `$attributes` và `'session_split_hour' => 'integer'` vào `$casts`. Admin sửa ở trang "Cài đặt shop" (thêm 1 input, cạnh giờ mở/đóng).
- **GIỮ NGUYÊN** `orders.requested_pickup_time`, `orders.requested_return_time`, `orders.is_half_day` — **server suy ra** từ `session` + settings, không bỏ cột (tránh migration phá dữ liệu + admin display hiện có vẫn chạy).

### Bảng suy diễn (server-authoritative)

Với `pickup_hour=P` (mở), `return_hour=R` (đóng), `session_split_hour=S`:

| `session` | requested_pickup_time | requested_return_time | `is_half_day` | earlyPct áp dụng |
|---|---|---|---|---|
| `morning` | `P:00` | `S:00` | true | `early_return_discount_pct` |
| `afternoon` | `S:00` | `R:00` | true | `early_return_discount_pct` |
| `full` | `P:00` | `R:00` | false | 0 |
| `null` (nhiều ngày) | `null` | `null` | false | 0 |

→ Giá tự đúng: `RentalPricingService::priceLine` đã áp `earlyReturnPct` khi `days===1 && is_half_day`. Không đổi service.

### Luồng dữ liệu

1. **`CartLine` (resources/js/lib/cart.ts)**: BỎ `requested_pickup_time`, `requested_return_time`, `half_day` free-form. THÊM `session?: 'morning' | 'afternoon' | 'full' | null`. Giữ `early_return_pct` (để mirror giá client-side).
2. **Client `lineRent`**: half-day khi `session` là morning/afternoon và `halfDayEligible` → áp `early_return_pct`; ngược lại như cũ. Cả ngày/nhiều ngày = giá thường.
3. **ProductDetail.tsx**: state `session` (mặc định `'full'`). Khi `start===end` hiện 3 nút; `buildLine` set `session`. Khi nhiều ngày → `session=null`, ẩn picker. Bỏ `pickupTime/returnTime` state + `<input type=time>`.
4. **Checkout payload (Cart.tsx → OrderController)**: mỗi item mang `session`. BỎ gửi `requested_pickup_time/return_time/half_day` từ client.
5. **`OrderController::store` validate**: `items.*.session` → `nullable|in:morning,afternoon,full`. Bỏ validate `requested_pickup_time/return_time` (không còn nhận từ client).
6. **`OrderSplitter`**: đọc `line['session']`. Trong `groupByRange`, gom `session` cho nhóm cùng ngày. Thêm helper `sessionToTimes(?string $session): array{pickup:?string, return:?string, half_day:bool}` đọc `SiteSetting` (P/R/S). Áp guard: `session` chỉ hợp lệ khi `start===end`; nhóm nhiều ngày ép `session=null`, `half_day=false`, times=null. Set `orders.session`, `requested_pickup_time`, `requested_return_time`, `is_half_day` từ đó. `earlyPct` trong `buildItems` lấy theo `half_day` như hiện tại.

**Bảo mật/đúng đắn:** client chỉ gửi `session` (enum). Server tự tính giờ + half_day + % → không tin client về giá/giờ. Đây là "single source of truth".

### UI trang sản phẩm (khi `start===end`)

- 3 nút chọn (radio-style), CSS đẹp tông be: **Buổi sáng (P–S h) · Buổi chiều (S–R h) · Cả ngày (P–R h)** — giờ hiển thị lấy từ `site` props (đã expose `pickup_hour/return_hour`, thêm `session_split_hour`).
- Chọn buổi → giá dòng cập nhật ngay (mirror `early_return_pct`).
- Dòng ghi chú dưới picker: *"Muốn giờ nhận/trả khác? Liên hệ Zalo"* → link `zaloUrl(1)` (đã có helper). Chỉ hiện link nếu có Zalo cấu hình.
- Thuê nhiều ngày: ẩn 3 nút, hiển thị khung mặc định P–R (giống PickupReturnNote hiện tại), `session=null`.

### Checkout & Cart hiển thị

- Mỗi dòng giỏ + dòng checkout: badge/nhãn buổi ("Buổi sáng · 8h–14h") + giá đã áp ưu đãi. Cả ngày hiện "Cả ngày · 8h–20h".
- Cart.tsx + `toCheckoutItems` mang `session`. Component nhãn buổi dùng chung (helper `sessionLabel(session, P, S, R)`).

### Admin xem đơn

- Chi tiết đơn hiện nhãn buổi + giờ (đã có sẵn khối "Giờ khách chọn"; đổi sang hiển thị buổi + giờ suy ra).

---

## Phần B — Màn hình riêng cho mỗi đơn (admin)

### Route & controller

- Thêm `Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show')`.
- `AdminOrderController::show(Order $order)`: eager-load items/product, con (nếu parent), cha (nếu child), location, envelope thanh toán → `Inertia::render('Admin/Orders/Show', [...])`. Tái dùng đúng props detail mà `Orders.tsx` đang dựng inline.

### Frontend

- **Tách file**: `resources/js/Pages/Admin/Orders.tsx` (905 dòng) → giữ **danh sách** (table gọn: mã, khách, ngày, **buổi**, tiền, trạng thái) + **dropdown đổi trạng thái nhanh** (giữ `orders.update`). Bấm dòng/nút "Xem" → `router.visit(route('admin.orders.show', id))`.
- **`Admin/Orders/Show.tsx` (mới)**: gom toàn bộ khối chi tiết + action đang nằm trong Orders.tsx:
  - Thông tin khách, ngày/buổi, danh sách món (kèm ngày riêng từng món), tổng/cọc/phụ phí/hoàn.
  - Đơn cha ↔ con: parent liệt kê link các con; con có link về cha.
  - Actions: đổi trạng thái, đổi ngày (`orders.dates`), đổi vị trí (`orders.location`), thanh toán (`orders.payment`), phụ phí (`orders.fee`), hoàn (`orders.refund`).
- Tách các sub-component detail hiện có (nếu đang inline) ra để tái dùng, tránh Show.tsx phình to. Mục tiêu: mỗi file một trách nhiệm rõ.

### Không đổi

- Logic controller các action (status/dates/location/payment/fee/refund) giữ nguyên; chỉ đổi nơi render (list → detail page). Không đổi hành vi backend.

---

## Kiểm thử

### A — chọn buổi
- **OrderSplitterSessionTest**: gửi `session=morning` (1 ngày) → order có `is_half_day=true`, `requested_pickup_time='08:00'`, `requested_return_time='14:00'`, giá = ngày×(1−pct). `afternoon` → `14:00`–`20:00`. `full` → không half_day, giá đầy đủ. Nhiều ngày + `session=morning` (client cố gửi) → server ép `session=null`, không half_day (guard).
- **OrderController validate**: `session` ngoài enum → `assertSessionHasErrors('items.0.session')`.
- Cập nhật `RequestedTimesExtraFeeTest`: đổi từ gửi `requested_pickup_time` sang `session`; phụ phí (extra-fee) giữ nguyên.
- Client: mirror giá `lineRent` với `session` (nếu có test JS; nếu không, kiểm bằng feature test tổng tiền order).

### B — màn đơn
- **AdminOrderShowTest**: admin GET `orders.show` → 200, thấy mã đơn + món. Non-admin → redirect login. Parent hiện link con; child hiện link cha.
- Smoke: các action (status/dates/fee/refund) vẫn chạy từ trang detail (route không đổi nên test cũ vẫn xanh).

### Quality gates
`php artisan test` (toàn bộ, mục tiêu ≥ số test hiện tại) · `./vendor/bin/pint --test` · `npx tsc --noEmit` · `npm run build`. Verify browser: chọn buổi đổi giá, checkout hiện buổi, màn đơn admin mở riêng.

---

## Ngoài phạm vi (YAGNI)

- **Trường hợp B — quay vòng buổi chiều CHÍNH món vừa về (`buffer_days=0`): KHÔNG làm đợt này** (chủ shop chốt "chưa cần"). Không thêm công cụ admin tạo đơn tay. ⇒ "Buổi chiều" online chỉ là lựa chọn giờ + giá; luôn khoá trọn ngày (chỉ dùng được unit còn trống cả ngày — Trường hợp A). Để lại như hướng tương lai (ADR mục 10).
- Không hỗ trợ chọn buổi cho thuê nhiều ngày.
- Không thêm cổng thanh toán / không đụng tồn kho theo giờ (mọi lượt thuê vẫn khoá trọn ngày — INVARIANT giữ nguyên).
- Không đổi logic buffer giặt/phơi, voucher, combo.

## Rủi ro

- **Migration `session` trên develop dùng chung SQLite**: chuyển nhánh khác schema phải `migrate:fresh --seed`.
- **Tách Orders.tsx 905 dòng**: rủi ro sót action khi port. Giảm bằng cách port từng khối + chạy test smoke các action sau mỗi bước.
- **Dữ liệu đơn cũ** có `requested_pickup_time` nhưng `session=null`: admin detail cần fallback hiển thị giờ thô nếu `session` null nhưng có time (đơn tạo trước khi có session).

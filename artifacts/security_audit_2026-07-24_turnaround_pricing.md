# Security Audit — Turnaround Buffer & Half-day Pricing

- **Ngày:** 2026-07-24
- **Phạm vi:** thay đổi của 3 tính năng đã merge `develop`:
  - Tách màn sản phẩm (`bopcamping-o4kw`)
  - Đệm quay vòng buffer per-kho + giờ giao/trả (`bopcamping-s1ij`)
  - Giá nửa ngày early-return + `is_half_day` (`bopcamping-jrh8`, Part A+B)
- **Phương pháp:** STRIDE + trace data-flow (grep) trên các entry point mới/đổi.
- **Kết luận:** **KHÔNG có lỗ hổng Critical/High/Medium.** 3 ghi chú Informational/Low (không chặn merge).

---

## 1. Bề mặt tấn công (entry points thay đổi)

| Entry point | Loại | Auth |
|---|---|---|
| `POST /dat-hang` (`items.*.half_day`) | Public (khách) | Không cần (khách vãng lai) |
| `GET /thiet-bi/{slug}/kha-dung` | Public | Không |
| `GET/PUT /admin/products*`, `/admin/products/{id}/sua`, `/admin/products/create` | Admin | `admin` middleware ✓ |
| `PUT /admin/settings` (`pickup_hour`/`return_hour`) | Admin | `admin` middleware ✓ |
| Shared prop `site` (giờ giao/trả), product resource + `cart.refresh` (`early_return_pct`) | Public read | — |

Không thêm dependency mới → không cần quét CVE lần này.

## 2. STRIDE

### Spoofing — OK
Không đổi cơ chế auth. Route admin mới (`products.create`, `products/{id}/sua`) nằm trong `Route::middleware(['admin'])` (routes/web.php:90) → khách không truy cập được (đã có test `non_admin_cannot_see_create_or_edit_page`).

### Tampering — OK (điểm rủi ro cao nhất, đã phòng thủ)
**Giá nửa ngày không thể bị client thao túng:**
- Client chỉ gửi `items.*.half_day` = **boolean** (validate `nullable|boolean`). KHÔNG gửi được % giảm.
- Server áp % từ **DB sản phẩm**: `OrderSplitter.php:138` `$earlyPct = ($halfDay && $days===1) ? (int) $product->early_return_discount_pct : 0`.
- `RentalPricingService::priceLine()` clamp `min(100, max(0, $earlyReturnPct))`.
- **Guard cùng ngày:** `OrderSplitter.php:116` ép `half_day = half_day && start===end` → không giảm được đơn nhiều ngày dù client cố gửi. (test: `multi_day_ignores_half_day_flag`.)
- Combo KHÔNG nhận early-return (vòng combo không truyền `earlyPct`) — test `combo_lines_get_no_early_return...`.

**Cấu hình admin được validate + clamp 2 lớp:**
- `buffers.*`: `integer|min:0|max:30` (validate) + `min(30,max(0,...))` khi sync pivot.
- `early_return_discount_pct`: `sometimes|integer|min:0|max:50` (store+update).
- `pickup_hour`/`return_hour`: `sometimes|integer|min:0|max:23`.

**Không mass-assignment từ input không tin cậy:** `$base` của checkout (OrderController.php:144) chỉ gồm field khách đã validate; `is_half_day` do server tính trong OrderSplitter, KHÔNG lấy từ request. Không có `Order::create($request->all())` / `->update($request->all())` ở luồng này (grep sạch).

### Repudiation — OK
`orders.is_half_day` + `order_items.duration_discount_percent` (lưu % đã áp) → có vết cho đối soát. Đủ cho mô hình shop nhỏ.

### Information Disclosure — OK
`early_return_discount_pct` (product resource, `cart.refresh`) và `pickup_hour/return_hour` (shared `site`) lộ ra công khai — **đúng chủ đích** (khách thấy "−X%" và khung giờ), không phải dữ liệu nhạy cảm. Không lộ PII/secret.

### Denial of Service — OK
Không có vòng lặp vô hạn mới. `unavailableDates` vẫn giới hạn ≤90 ngày (cũ); buffer bị chặn ≤30 (validate) / ≤255 (cột `unsignedTinyInteger`). `subDays($buffer)` bị chặn. Giỏ vẫn `items max:50 / combos max:20` (cũ).

### Elevation of Privilege — OK
Không có IDOR mới: `products/{id}/sua` dùng route-model-binding trong nhóm `admin`. Không thêm đường vòng phân quyền.

### Injection (SQLi/XSS) — OK
- SQL: `AvailabilityService` dùng `whereRaw('... >= ?', [$start->copy()->subDays($buffer)->toDateString()])` — **tham số hoá**; buffer là int (Carbon), không nối chuỗi.
- XSS: các field mới đều là số/boolean, render qua React (auto-escape). Không có HTML thô.

## 3. Findings

| # | Mức | Mô tả | Khuyến nghị |
|---|---|---|---|
| F1 | Info/Low | `is_half_day` (Order) và `early_return_discount_pct` (Product) nằm trong `$fillable`. Hiện KHÔNG có đường mass-assign từ input không tin cậy (đã verify), nhưng là rủi ro tiềm ẩn nếu về sau ai đó viết `Order::create($request->all())`. | Giữ pattern "mảng field tường minh" như hiện tại. Không cần sửa ngay. |
| F2 | Info | `early_return_discount_pct` lộ công khai (product/cart). | Chấp nhận — dữ liệu khách-thấy, không nhạy cảm. |
| F3 | Info | Giá nửa ngày là **tầng giá** (giảm net), nằm NGOÀI trần voucher `max_discount_percent_per_order` — giống bậc giảm dài ngày. Stack với voucher: xấu nhất ≈ 25% gross (−50% nửa ngày rồi −50% voucher trên net). | Đúng thiết kế, admin kiểm soát cả 2 %. Ghi nhận để không bất ngờ khi đối soát doanh thu. |

## 4. Kết luận
Bề mặt thay đổi nhỏ, phòng thủ tốt: **client chỉ điều khiển ý định (boolean), server quyết định giá từ DB + validate/clamp/guard**. Không có finding Critical/High/Medium → **không tạo GitHub issue**. F1–F3 chỉ là ghi chú, không chặn phát hành.

> Handoff: nếu chốt siết F1, chuyển `/architect` cân nhắc bỏ `is_half_day` khỏi `$fillable` (dùng `forceFill` trong OrderSplitter). Hiện KHÔNG bắt buộc.

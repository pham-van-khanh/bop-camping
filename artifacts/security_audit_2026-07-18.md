# Security Audit — Giảm giá thuê dài ngày (duration discount)

- **Ngày:** 2026-07-18
- **Phạm vi:** `feature/duration-discount` (bopcamping-e36e) vs `feat/scaffold-laravel`
- **Kết luận:** ✅ **KHÔNG có lỗ hổng Critical/High.** 1 Low (đã vá), 1 Low (informational).
- **Phương pháp:** STRIDE + trace data-flow (Grep) trên toàn bộ điểm vào thay đổi.

## Bề mặt tấn công đã thay đổi

| Điểm vào | Quyền | Input từ client |
|---|---|---|
| `POST /dat-hang` (checkout) | Public | product_id, quantity, dates, voucher_codes |
| `PATCH /admin/orders/{o}/dates` (đổi lịch) | Admin | start_date, end_date |
| `PUT /admin/promotion/duration-tiers` (CRUD bậc) | Admin | tiers[]{min_days, discount_percent, is_active} |
| Inertia shared prop `durationTiers` | Public (đọc) | — |

## STRIDE

### S — Spoofing / Authentication
- Route CRUD bậc + đổi lịch nằm trong `Route::middleware(['admin'])`. Test `non_admin_cannot_update_tiers` / `non_admin_cannot_change_dates` xác nhận redirect `admin.login`. ✅

### T — Tampering (TRỌNG TÂM: ép giảm giá)
- **Client KHÔNG điều khiển được giá/giảm giá.** Checkout chỉ validate `product_id, quantity(1–99), dates, voucher_codes`; KHÔNG nhận `price`, `subtotal`, `discount`, `duration_discount_percent`. Server tính 100% qua `RentalPricingService` (đọc bậc từ DB + `product.price_per_day` phía server). → **CWE-602 (client-side enforcement of security) KHÔNG tồn tại.** ✅
- Đổi lịch: client chỉ gửi `start_date/end_date` (validated); server tái tính giá theo `price_per_day/allocated_price` đã snapshot. ✅
- `durationTiers` shared prop chỉ để HIỂN THỊ; sửa ở client không ảnh hưởng — server là chân lý. ✅
- Trần giảm 25% được **tái áp trên NET** khi đổi lịch (từ review trước) → không thể lách để giảm âm/vượt tổng. ✅
- Eloquent parameterized toàn bộ — không SQLi (CWE-89). ✅ CSRF: web middleware mặc định. ✅

### R — Repudiation / Audit
- `order_items.duration_discount_percent` snapshot % đã áp → dấu vết kiểm toán cho từng đơn. ✅
- Thay đổi bậc ở admin không ghi audit-log (ai đổi, khi nào) — **nhưng đồng nhất với `PromotionSetting` hiện có**, không phải regression. (Info)

### I — Information Disclosure
- `durationTiers` (min_days + %) là thông tin marketing công khai (hiện trên trang sản phẩm). Không PII, không nhạy cảm. ✅

### D — Denial of Service
- **[LOW — ĐÃ VÁ]** `updateDurationTiers` nhận `tiers[]` không giới hạn số phần tử → payload mảng khổng lồ (delete + insert N dòng). Admin-only nên rủi ro thấp; đã thêm `max:50` (CWE-770).
- **[LOW — Info]** Shared prop `durationTiers` chạy 1 query mỗi request (mọi trang). Ảnh hưởng hiệu năng nhẹ, không phải lỗ hổng; có thể cache nếu cần.
- `RentalPricingService` cache bậc theo request; `tierPercentForDays` duyệt danh sách nhỏ — không N+1 trong hot path. ✅

### E — Elevation of Privilege
- Mọi mutation (CRUD bậc, đổi lịch) sau `admin` middleware; checkout public nhưng không thao túng được giá. ✅

## Checklist

- [x] Authn/Authz — admin middleware trên route mutation
- [x] Input validation — trace Grep: không field giá/giảm từ client; bậc validate min/max/distinct
- [x] Secrets — không thêm secret nào
- [x] Dependencies — **không thêm dependency mới** (composer/npm không đổi) → không CVE mới
- [x] Data encryption — không dữ liệu nhạy cảm mới
- [x] Audit logging — snapshot % trên order_item

## Findings & severity

| # | Severity | Mô tả | CWE | Trạng thái |
|---|----------|-------|-----|-----------|
| 1 | Low | `tiers[]` không giới hạn kích thước (DoS payload, admin-only) | CWE-770 | ✅ Đã vá (`max:50`) |
| 2 | Low (info) | Shared prop `durationTiers` query mỗi request | — | Chấp nhận / cache sau nếu cần |
| 3 | Info | Không audit-log thay đổi bậc (đồng nhất PromotionSetting) | CWE-778 | Chấp nhận (không regression) |

## Ghi chú
- `DurationDiscountTier` dùng `$guarded = []` (mass-assignable) nhưng CHỈ tạo từ input admin đã validate (field tường minh); đồng nhất `PromotionSetting`. Không có đường khai thác.
- Không cần tạo GitHub issue (không có Critical/High).

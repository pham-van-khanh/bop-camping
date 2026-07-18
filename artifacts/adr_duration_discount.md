# ADR: Giảm giá thuê dài ngày (Duration-based rental discount)

- **Trạng thái:** Proposed (chờ duyệt → hand off `/swarm-execute`)
- **Ngày:** 2026-07-18
- **Tác giả:** Architect (Claude) + chủ shop
- **Liên quan:** `PromotionSetting`, `AvailabilityService`, checkout (`Shop/OrderController`), `CartController`, `ComboPricingService`, đổi lịch (`Admin/OrderController::changeDates` + `rescaleDiscount`), `ProductDetail.tsx`, `DateRangeCalendar.tsx`, trang `/admin/promotion`.

---

## 1. Bối cảnh & yêu cầu

Chủ shop muốn khuyến khích thuê dài ngày bằng **giảm giá theo bậc số ngày**:

- Thuê **≥ 5 ngày** → giảm **20%**
- Thuê **≥ 10 ngày** → giảm **30%**
- Các mốc ngày + % do **admin cấu hình** (thêm/sửa/xoá bậc).
- **Hiển thị ở phần chọn lịch** và **giá thay đổi trực tiếp** khi khách chọn khoảng ngày.

### Quyết định đã chốt với chủ shop (2026-07-18)

| # | Câu hỏi | Chốt |
|---|---------|------|
| D1 | Bản chất giảm giá | **Điều chỉnh GIÁ THUÊ** (giá/ngày hiệu lực thấp hơn), KHÔNG tính vào trần voucher 25%; voucher áp **thêm** trên giá đã giảm. |
| D2 | Ranh giới bậc | **Bậc thang theo ngày tối thiểu**: lấy bậc có `min_days` lớn nhất mà `days ≥ min_days`; dưới ngưỡng thấp nhất = 0%; trên bậc cao nhất giữ % cao nhất. |
| D3 | Phạm vi | **Per-dòng** — xét bậc theo số ngày của TỪNG dòng thuê. |
| D4 | Combo | **Có áp** cho combo (theo số ngày của dòng combo). |

---

## 2. Nguyên tắc kiến trúc

1. **Giảm giá dài ngày là quy tắc GIÁ, không phải khuyến mãi.** Nó nằm ngoài `discount_breakdown` và ngoài trần `max_discount_percent_per_order` (25%). Điều này (a) tránh xung đột 30% > 25%, (b) khớp yêu cầu "giá thay đổi", (c) giữ nguyên ngữ nghĩa "trần voucher = chống lạm voucher".

2. **Layering giá (thứ tự tính, single source of truth):**
   ```
   gross  = price_per_day × qty × days                      (giá gốc)
   net    = round(gross × (1 − tier% / 100))                (sau giảm dài ngày)  ← subtotal lưu DB
   total_price = Σ net (tất cả dòng)                        (phí thuê của đơn)
   voucher/referral/email_bonus áp trên total_price, kẹp ≤ 25% × total_price
   amount_due = total_price + deposit_total − discount_total
   ```
   Giảm dài ngày nằm ở **tầng 1 (giá)**; voucher/cap ở **tầng 2 (khuyến mãi)** — độc lập, không chồng trần.

3. **Một nguồn tính giá duy nhất:** thêm `RentalPricingService` gói toàn bộ logic bậc + tính net. Mọi nơi (checkout, giỏ, combo, đổi lịch) gọi cùng service. Client mirror qua bậc được truyền xuống bằng Inertia shared prop (không hardcode %).

4. **Snapshot khi đặt đơn:** `%` bậc được **chốt vào order_item** lúc tạo đơn (như `price_per_day`), nên admin đổi bậc sau này KHÔNG làm lệch đơn cũ.

5. **Cọc không bị giảm.** Duration discount chỉ tác động phí thuê; `deposit_total` giữ nguyên (đúng như voucher hiện tại).

---

## 3. Mô hình dữ liệu

### 3.1. Bảng mới `duration_discount_tiers`

| Cột | Kiểu | Ghi chú |
|-----|------|---------|
| `id` | bigint PK | |
| `min_days` | unsignedInteger, **unique** | Ngày tối thiểu để hưởng bậc (inclusive). |
| `discount_percent` | decimal(5,2) | 0–100. |
| `is_active` | boolean, default true | Tắt bậc mà không xoá. |
| `timestamps` | | |

- **Chọn bậc:** trong các tier `is_active`, sắp xếp `min_days` giảm dần, lấy tier đầu tiên có `days ≥ min_days`. Không có → 0%.
- **Vì sao bảng riêng (không phải cột JSON trên `PromotionSetting`):** số dòng thay đổi, cần validate `min_days` unique + `percent` hợp lệ, dễ seed/test, và khớp pattern CRUD bảng admin đã có (giống `vouchers`). (Trade-off: xem §9.)

### 3.2. Cột thêm vào `order_items`

| Cột | Kiểu | Ghi chú |
|-----|------|---------|
| `duration_discount_percent` | decimal(5,2), default 0 | Snapshot % bậc đã áp cho dòng. Đơn cũ = 0 → net == gross (không đổi). |

- `subtotal` vẫn lưu **net** (sau giảm) → `total_price`, `amount_due`, trần voucher đều đúng tự động, không phải sửa downstream.
- **Giá gốc (gross)** suy ra được = `price_per_day × quantity × days` (hoặc `allocated_price × days` cho combo) → dùng để hiển thị "giá gốc gạch ngang".

> Không thêm dòng vào `discount_breakdown` (đó là hệ khuyến mãi mức đơn, bị trần). Minh bạch per-dòng đủ nhờ `duration_discount_percent`.

---

## 4. Logic tính giá — `App\Services\RentalPricingService`

```
tierPercentForDays(int $days): float
    → % của bậc áp dụng (0 nếu không có bậc nào). Đọc tier active, cache theo request.

priceLine(int $perDayAmount, int $qty, int $days): array{gross:int, percent:float, net:int}
    gross   = $perDayAmount * $qty * $days
    percent = tierPercentForDays($days)
    net     = (int) round($gross * (1 - percent/100))
```

- **Lẻ:** `perDayAmount = product.price_per_day`, `qty = quantity`.
- **Combo (tổng đơn):** `perDayAmount = combo.combo_price`, `qty = 1` (mỗi combo-instance) → net cho tổng.
- **Combo (dòng hiển thị):** `perDayAmount = allocated_price`, `qty = quantity` — cùng `percent` (đồng nhất trên combo-instance để Σ dòng == net combo).
- Service là nơi DUY NHẤT biết công thức. `tierPercentForDays` cũng expose ra client (§6).

---

## 5. Điểm tích hợp phía server

| Nơi | Thay đổi |
|-----|----------|
| **Checkout** `Shop/OrderController::store` | Thay `subtotal = ppd×qty×days` bằng `RentalPricingService::priceLine(...)`; lưu `subtotal = net` + `duration_discount_percent = percent`. Combo: tổng đơn dùng `combo_price` qua service; mỗi dòng combo lưu `duration_discount_percent` như nhau. |
| **Giỏ hàng** `CartController::index/refresh` | Trả về giá net theo bậc để giỏ hiển thị đúng khi khách đã chọn ngày. |
| **Đổi lịch** `Admin/OrderController::changeDates` | ⚠️ Đang linear-scale `net × newDays/oldDays` — **SAI** khi bậc đổi theo ngày. Sửa: với mỗi dòng, tính lại `gross' = perDay × qty × newDays` → `priceLine` → net' + snapshot percent' mới. `total_price = Σ net'`. Sau đó `rescaleDiscount` (voucher/cap) chạy trên `total_price` mới như hiện tại. |
| **`rescaleDiscount`** | Giữ nguyên logic tầng-2 (voucher %/cố định + re-cap 25%) — chỉ đổi ở chỗ `newTotal` giờ là tổng net đã áp bậc. |
| **Combo** `ComboPricingService` | Không đổi phân bổ; service giá gọi bậc lên trên kết quả allocate. |

---

## 6. Hiển thị & client

### 6.1. Truyền bậc xuống client
Thêm `durationTiers: [{ minDays, percent }]` (active, sort giảm dần) vào **Inertia shared props** (`HandleInertiaRequests::share`) — dùng ở product detail, giỏ, checkout, modal đổi lịch. Kèm helper TS dùng chung `lib/pricing.ts`:
```
durationTierPercent(days, tiers): number
netSubtotal(gross, days, tiers): number
```
(Mirror server — chấp nhận trùng logic ở client cho preview trực tiếp, nhưng **cùng nguồn cấu hình** = tiers từ prop, không hardcode.)

### 6.2. Trang chọn lịch (`ProductDetail.tsx`, cạnh `DateRangeCalendar`)
- **Bảng bậc luôn hiện:** "Thuê ≥5 ngày −20% · ≥10 ngày −30%".
- Khi đã chọn ngày và đạt bậc:
  - "Tạm tính" hiện **giá gốc gạch ngang → giá net**, kèm badge "Đang giảm 20% (thuê 7 ngày)".
  - Còn `X ngày nữa để lên bậc −30%` (gợi ý nâng bậc) — optional, tăng chuyển đổi.
- Cùng cách hiển thị ở **giỏ**, **tóm tắt checkout**, và **preview modal đổi lịch** (admin thấy net mới đúng).

### 6.3. Admin `/admin/promotion`
Thêm khối **"Giảm giá thuê dài ngày"**: bảng sửa được (min_days, %, bật/tắt) + thêm/xoá dòng + Lưu. Validate: `min_days` unique & ≥1, `percent` 0–100. Sắp xếp hiển thị theo `min_days`.

---

## 7. Tương tác với hệ giảm giá hiện có

- **Voucher/referral/email_bonus:** tính trên `total_price` = tổng **net** (sau bậc). Trần 25% cũng theo net. ⇒ Giảm dài ngày và voucher **cộng dồn** nhưng ở 2 tầng khác nhau; tổng có thể > 25% (đúng chủ ý — bậc dài ngày không thuộc trần).
- **Ví dụ:** 1 lều 120k/ngày, thuê 10 ngày. gross=1.200k, bậc −30% → net=840k. Voucher 10% (≤ trần 25% của 840k) → −84k. amount_due = 840k − 84k + cọc.
- **`rescaleDiscount` (vừa build):** tầng-2 không đổi; chỉ nhận `newTotal` là net mới. Test đổi lịch phải cập nhật để phản ánh net theo bậc.

---

## 8. Edge cases & validation

- **Trùng `min_days`:** unique index + validate admin.
- **`percent` ngoài [0,100]:** reject.
- **days < bậc thấp nhất:** percent = 0 (giá gốc).
- **Không có tier active:** toàn bộ = 0% (hành vi như hiện tại — an toàn).
- **Làm tròn:** `round()` mỗi dòng (server PHP `round`, client `Math.round`) — nhất quán từng dòng, tổng = Σ net.
- **Đơn cũ:** `duration_discount_percent` default 0 → không đổi.
- **Combo có giá ưu đãi sẵn:** bậc áp TIẾP trên `combo_price` (chủ shop đã đồng ý chồng) — ghi rõ để tránh hiểu lầm "giảm 2 lần".
- **Cọc:** không giảm.

---

## 9. Trade-off — nơi lưu cấu hình bậc

| Tiêu chí | Bảng riêng `duration_discount_tiers` (chọn) | Cột JSON trên `PromotionSetting` |
|----------|---------------------------------------------|----------------------------------|
| CRUD/validate từng dòng | ✅ dễ (unique index, rule) | ⚠️ validate mảng thủ công |
| Số dòng động | ✅ | ✅ |
| Query/seed/test | ✅ rõ ràng | ⚠️ parse JSON |
| Migration đơn giản | ⚠️ +1 bảng | ✅ +1 cột |
| Khớp pattern hiện có | ✅ giống `vouchers` | ✅ giống settings khác |

→ **Chọn bảng riêng** (đổi lấy 1 migration bảng) vì rõ ràng + testable + extensible (sau này có thể per-category).

---

## 10. Rủi ro

| Rủi ro | Mức | Giảm thiểu |
|--------|-----|-----------|
| Giá client ≠ server (trùng logic) | Trung bình | Cùng `tiers` từ prop; test parity; server là nguồn chân lý (revalidate lúc checkout). |
| Đổi lịch quên áp bậc → tính sai | Cao | Sửa `changeDates` dùng `RentalPricingService`; thêm test bậc-đổi-theo-ngày. |
| Chồng giảm với combo gây "giảm 2 lần" hiểu lầm | Thấp | Chủ shop đã đồng ý; hiển thị rõ "giảm dài ngày" tách khỏi giá combo. |
| Tổng giảm quá sâu (bậc + voucher) | Thấp | Đúng chủ ý; nếu cần có thể thêm trần tổng-tầng-2 riêng sau. |
| Lệch làm tròn cộng dồn nhiều dòng | Thấp | Làm tròn per-dòng, không làm tròn lại ở tổng. |

---

## 11. Migration & tương thích ngược

1. `create_duration_discount_tiers_table` (+ seed 2 bậc mẫu 5→20%, 10→30% để demo, có thể tắt).
2. `add_duration_discount_percent_to_order_items` (default 0).
3. Đơn hiện có: percent=0 → net=gross, không đổi. `total_price` cũ vẫn khớp.

---

## 12. Kế hoạch test

- **Unit `RentalPricingService`:** chọn bậc đúng (biên 4/5/9/10/20/21 ngày), percent=0 khi không bậc, làm tròn.
- **Feature checkout:** subtotal net + snapshot percent đúng; combo áp bậc; voucher áp trên net; trần 25% theo net.
- **Feature đổi lịch:** kéo dài qua ngưỡng bậc → net giảm đúng bậc mới (KHÔNG linear-scale); rút ngắn dưới ngưỡng → mất bậc; voucher/cap tầng-2 vẫn đúng.
- **Admin:** CRUD tier, validate unique/percent.
- **Client (build/tsc + browser):** preview giá gốc→net, badge bậc, khớp DB sau đặt/đổi lịch.
- Collation-safe, chạy được cả sqlite lẫn MySQL.

---

## 13. Phân rã triển khai (đề xuất beads)

1. **Epic:** Giảm giá thuê dài ngày.
2. Migration + model `DurationDiscountTier` + seed.
3. Migration `order_items.duration_discount_percent`.
4. `RentalPricingService` + unit test (nguồn chân lý).
5. Tích hợp checkout + giỏ + combo (server) + test.
6. Sửa `changeDates` áp bậc khi đổi lịch + test (tích hợp với `rescaleDiscount`).
7. Shared prop `durationTiers` + `lib/pricing.ts` (client helper).
8. UI product detail (bảng bậc + giá gốc→net + badge) + giỏ + checkout summary + preview đổi lịch.
9. Admin `/admin/promotion`: CRUD bậc + validate.
10. QA: browser verify + quality gates.

> Handoff: sau khi duyệt ADR → `/swarm-execute` theo thứ tự 2→10 (mục 4 là chốt chặn cho 5,6).

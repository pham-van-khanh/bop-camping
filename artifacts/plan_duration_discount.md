# Plan: Giảm giá thuê dài ngày (duration discount)

- **Epic:** `bopcamping-e36e`
- **Thiết kế:** [artifacts/adr_duration_discount.md](adr_duration_discount.md) (đã chốt 4 quyết định)
- **Quy mô:** ~4–6 ngày · **Reversibility:** Two-way door (tắt bằng `is_active=false`; cột `duration_discount_percent` default 0 → đơn cũ không đổi). ADR đã có → đủ artifact.
- **Branch:** `feature/duration-discount` (từ `feat/scaffold-laravel`, theo quy trình stg).

## Nguyên tắc chốt chặn
- **`RentalPricingService` là NGUỒN CHÂN LÝ** giá net theo bậc — build + test TRƯỚC mọi tích hợp (task 3 chặn 4,5,6).
- Client mirror qua `durationTiers` (Inertia shared prop) + `lib/pricing.ts`; **không hardcode %**.
- `order_items.subtotal` = **net** (sau bậc); `duration_discount_percent` snapshot khi đặt.
- Giảm dài ngày là tầng GIÁ (ngoài trần voucher 25%); voucher/cap tầng 2 chạy trên net.

## Sơ đồ phụ thuộc
```
T1 (tiers table+model+seed) ─┐
T2 (order_items column)      ─┼─▶ T3 (RentalPricingService + unit test)
                                   ├─▶ T4 (checkout + combo server)
                                   ├─▶ T5 (đổi lịch: áp bậc + rescale) ── (cần T4 xong về DB shape)
                                   └─▶ T6 (shared prop + lib/pricing.ts client) ─▶ T7 (UI product/cart/checkout/preview)
T1 ─▶ T8 (admin CRUD bậc /admin/promotion)
T4,T5,T7,T8 ─▶ T9 (QA browser + quality gates)
```

## Tasks (right-sized, kèm acceptance)

### T1 — Bảng `duration_discount_tiers` + model + seed  *(~0.5d)*
- Migration: `min_days` unsignedInt **unique**, `discount_percent` decimal(5,2), `is_active` bool default true, timestamps.
- Model `DurationDiscountTier` (guarded=[], casts). Seed demo: (5, 20), (10, 30) — có thể để `is_active` tùy.
- **AC:** migrate:fresh --seed chạy; unique index chặn min_days trùng; test model đọc/ghi.

### T2 — Cột `order_items.duration_discount_percent`  *(~0.25d)*
- Migration add decimal(5,2) default 0; thêm vào `OrderItem::$fillable` + cast.
- **AC:** đơn cũ percent=0; net=gross khi 0.

### T3 — `RentalPricingService` (SINGLE SOURCE) + unit test  *(~1d)* — **chốt chặn**
- `tierPercentForDays(int $days): float` — tier active, min_days lớn nhất ≤ days; cache theo request.
- `priceLine(int $perDay, int $qty, int $days): array{gross,percent,net}` — net=round(gross×(1−%/100)).
- **AC (unit):** biên 4/5/9/10/20/21 ngày ra đúng bậc; không tier→0%; làm tròn; qty>1 đúng.

### T4 — Tích hợp checkout + combo (server)  *(~1d)* — depends T2,T3
- `Shop/OrderController::store`: dùng `priceLine` cho dòng lẻ (perDay=price_per_day) và combo (tổng đơn perDay=combo_price; mỗi dòng combo perDay=allocated_price, cùng %). Lưu `subtotal=net` + `duration_discount_percent`.
- Voucher/referral/email_bonus/cap **không đổi** (chạy trên total_price=Σnet).
- **AC (feature):** checkout đơn ≥5/≥10 ngày ra net đúng bậc; combo áp bậc; voucher áp trên net; trần 25% theo net; cọc không đổi.

### T5 — Đổi lịch áp bậc  *(~0.75d)* — depends T3,T4
- `Admin/OrderController::changeDates`: **bỏ linear-scale**; mỗi dòng tính lại `gross'=perDay×qty×newDays` → `priceLine` → net' + snapshot %'. `total_price=Σnet'`. `rescaleDiscount` chạy sau trên total mới (giữ nguyên logic tầng-2).
- **AC:** kéo dài qua ngưỡng → net giảm đúng bậc mới (KHÔNG linear); rút ngắn dưới ngưỡng → mất bậc; voucher/cap tầng-2 vẫn đúng; cập nhật test `AdminOrderRescheduleTest` cho tương tác bậc.

### T6 — Shared prop `durationTiers` + `lib/pricing.ts`  *(~0.5d)* — depends T1,T3
- `HandleInertiaRequests::share`: `durationTiers=[{minDays,percent}]` (active, sort desc).
- `lib/pricing.ts`: `durationTierPercent(days,tiers)`, `netSubtotal(gross,days,tiers)` — mirror server.
- **AC:** tsc pass; prop có ở mọi trang; helper khớp server (test parity thủ công hoặc vitest nếu có).

### T7 — UI hiển thị  *(~1d)* — depends T6
- `ProductDetail.tsx`: bảng bậc cạnh `DateRangeCalendar`; khi đạt bậc: giá gốc gạch ngang → net + badge "Đang giảm X%". (Optional: "còn Y ngày để lên bậc").
- Giỏ (client localStorage) + tóm tắt checkout + **preview modal đổi lịch** (`Admin/Orders.tsx` DatesChanger) dùng cùng helper.
- **AC:** browser: chọn ngày → giá đổi đúng; badge đúng; khớp DB sau đặt/đổi lịch.

### T8 — Admin CRUD bậc  *(~0.75d)* — depends T1
- `/admin/promotion`: khối "Giảm giá thuê dài ngày" — bảng sửa (min_days, %, bật/tắt) + thêm/xoá + Lưu. Đơn giản nhất: `update` nhận `tiers:[]` và **sync toàn bảng**. Validate: min_days unique & ≥1, percent 0–100.
- **AC:** thêm/sửa/xoá/tắt bậc; validate trùng min_days & % ngoài [0,100]; hiển thị sort theo min_days.

### T9 — QA + quality gates  *(~0.5d)* — depends T4,T5,T7,T8
- `php artisan test` · `pint --test` · `npx tsc --noEmit` · `npm run build` xanh.
- Browser verify: product page (giá đổi), checkout (net+voucher), đổi lịch (bậc theo ngày mới), admin CRUD.
- Collation-safe (sqlite + MySQL). Regression: đơn cũ percent=0 không đổi.

## Rủi ro & lưu ý (từ ADR §10)
- **Đổi lịch quên áp bậc** → T5 bắt buộc dùng service + test.
- **Client/server lệch** → cùng nguồn tiers + parity test; server revalidate lúc checkout là chân lý.
- **Chồng giảm combo** → chủ shop đã đồng ý; UI ghi rõ "giảm dài ngày" tách khỏi giá combo.

## Handoff → /swarm-execute
- `bd ready` trả T1, T2 (không phụ thuộc) trước; T3 mở khoá sau T1+T2; T8 mở khoá sau T1.
- Thứ tự khuyến nghị: T1‖T2 → T3 → (T4, T6) → (T5, T7, T8) → T9.
- Mỗi task commit riêng; merge feature branch → develop test stg → feat/scaffold-laravel.

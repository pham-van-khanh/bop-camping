# Plan: Đơn cha / đơn con — tách theo khoảng ngày thuê

- **Epic:** `bopcamping-wtuv` · **Thiết kế:** [artifacts/adr_parent_child_orders.md](adr_parent_child_orders.md)
- **Quy mô:** Large (2+ tuần) · **Reversibility:** One-Way Door (cao — đổi schema + mô hình đơn; ADR đã duyệt).
- **Branch:** `feature/parent-child-orders` (từ `feat/scaffold-laravel`).

## Nguyên tắc chốt chặn
- **T1 schema/model** chặn tất cả. **T2 OrderSplitter** là lõi checkout, chặn T3–T9.
- Đơn 1 khoảng → **đơn thường như cũ** (không cha) — mọi task phải giữ đường này không đổi.
- Cha `is_parent=true` (không items); con `parent_id` set. Danh sách/đếm/nhắc lịch phải **ẩn cha hoặc ẩn con đúng ngữ cảnh** (không nhân đôi).

## Sơ đồ phụ thuộc
```
T1 (schema+model+scope) ──▶ T2 (OrderSplitter+checkout) ──▶ T3 (voucher cha+phân bổ) ──▶ T4 (huỷ con recompute)
                        │                              ├──▶ T6 (admin cha/con)
                        │                              ├──▶ T7 (lifecycle con: reminder/đổi lịch/cọc)
                        │                              └──▶ T9 (mail cấp cha)
                        ├──▶ T5 (giỏ tự điền ngày — FE)
                        └──▶ T8 (sweep: account/lookup/dashboard/stats/badge/eligibility)
(T2,T3,T4,T6,T7,T8,T9) ─▶ T10 (QA + browser + gates)
```

## Tasks (right-sized, kèm acceptance)

### T1 — Schema + model + scope *(~1d)* — chốt chặn
- Migration: `orders.parent_id` (nullable FK→orders, **restrict** on delete — không nullOnDelete), `is_parent` bool default false.
- Order model: `parent()`, `children()`, scope `topLevel()` (parent_id null → gồm đơn thường + cha, ẩn con), helper `aggregateStatus()` (suy trạng thái cha từ con).
- **AC:** migrate:fresh; quan hệ đọc được; `topLevel()` không trả đơn con; đơn cũ = đơn thường.

### T2 — `OrderSplitter` + checkout *(~2d)* — depends T1 — lõi
- Service `OrderSplitter`: nhận nhóm cart theo (start,end). 1 nhóm → 1 đơn thường (giữ nguyên logic hiện tại). ≥2 nhóm → tạo cha + N con (con: items+ngày+net theo bậc+cọc+status pending+code `cha-i`; cha: tổng+envelope+is_parent).
- Refactor `Shop/OrderController::store` gọi service; giữ availability check per-con (per-item đã có).
- **AC:** đơn 1 khoảng KHÔNG đổi (test cũ xanh); đơn 2 khoảng → 1 cha + 2 con, tổng cha = Σ con, cọc con đúng, ngày con đúng, mã cha/con đúng; tồn kho check từng con.

### T3 — Voucher trên tổng cha + phân bổ con *(~1.5d)* — depends T2
- Voucher/referral/email-bonus chạy trên **tổng thuê cha** (cap 25% trên tổng cha) → discount_total + breakdown ở cha.
- Phân bổ ∝ tiền thuê con (dồn dư con cuối) → `discount_total` + breakdown note mỗi con.
- **AC:** Σ discount con == discount cha; cap đúng; đơn thường vẫn như cũ; test bất biến + phân bổ + làm tròn.

### T4 — Huỷ 1 con → tính lại voucher *(~1d)* — depends T3
- Huỷ con → recompute voucher trên các con còn `active` + phân bổ lại + cập nhật tổng cha (trong transaction). Huỷ cả cha → huỷ hết con.
- **AC:** huỷ 1/2 con → voucher/cap đúng trên con còn lại; tổng cha cập nhật; test.

### T5 — Giỏ tự điền ngày *(~0.75d)* — depends T1 (độc lập FE)
- `lib/cart.cartSuggestedRange()` (khoảng đang dùng / dòng thêm gần nhất). `ProductDetail` prefill start/end từ đó (khách vẫn đổi được).
- **AC:** giỏ có khoảng → sản phẩm mới tự chọn ngày đó; đổi ngày → thành nhóm khoảng mới; tsc/build.

### T6 — Admin cha/con *(~2d)* — depends T2, T3
- Danh sách: gom theo cha (ẩn con), badge "gồm N đợt". Mở cha → khối "Các đợt giao": mỗi con card tái dùng control trạng thái/payment/đổi lịch/hoàn cọc/nhắc. Voucher note cha + phân bổ con.
- **AC:** browser: cha hiện gọn, mở ra thấy từng con thao tác được; đơn thường hiển thị như cũ.

### T7 — Vòng đời cấp con *(~1d)* — depends T2
- `SendPickupReminders`: loại `is_parent`, chạy per-con (mỗi con ngày nhận riêng). `changeDates`/refund/status: xác nhận chạy đúng trên con; loại cha khỏi các thao tác không hợp lệ.
- **AC:** reminder gửi theo ngày từng con; đổi lịch/cọc/status per-con; cha không nhận thao tác đơn lẻ; test.

### T8 — Sweep nơi đọc orders *(~1.5d)* — depends T1
- `AccountController` (list gom cha), `OrderLookupService` (tra mã cha→cha+con, mã con→con), `DashboardController`/`StatsController` (đếm đơn KHÔNG nhân đôi con; doanh thu = con), `HandleInertiaRequests` badge `pending_orders`, `ReviewInviteController` (token theo con), first-order eligibility (Referral/EmailBonus — đếm theo cha/đơn thường, không tính con là đơn riêng).
- **AC:** mỗi nơi đếm/hiển thị đúng; test cho từng nơi trọng yếu (stats, account, lookup, eligibility).

### T9 — Mail cấp cha *(~0.75d)* — depends T2
- `OrderPlacedMail`/admin mail: 1 email cấp cha liệt kê các con + khoảng ngày + tiền từng đợt (đơn thường giữ mail hiện tại).
- **AC:** đơn 2 khoảng → 1 mail xác nhận liệt kê 2 đợt; đơn thường → mail như cũ; test queued.

### T10 — QA + browser + gates *(~1d)* — depends T2,T3,T4,T6,T7,T8,T9
- `php artisan test` · pint · tsc · build. Browser E2E: checkout giỏ 2 khoảng → cha/con; admin thao tác con; giỏ tự điền ngày. Regression đơn thường + đơn cũ.
- **AC:** tất cả gate xanh; browser verify; collation-safe.

## Rủi ro (từ ADR §10)
- **Nhân đôi đơn con** ở list/đếm → scope `topLevel()` + rà kỹ T8.
- **Voucher phân bổ / huỷ con** lệch → test bất biến Σ + recompute (T3,T4).
- **Query ngầm giả định 1 đơn = 1 khoảng** → T8 sweep là task rủi ro nhất, cần review.

## Handoff → /swarm-execute
- `bd ready` → T1 (và T5 sau T1). Thứ tự: T1 → T2 → (T3, T6, T7, T9, T8 song song tuỳ) → T4 → T10.
- Mỗi task commit riêng; branch → develop (stg test kỹ vì lõi) → feat/scaffold-laravel.

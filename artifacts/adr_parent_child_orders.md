# ADR: Đơn cha / đơn con — tách theo khoảng ngày thuê

- **Trạng thái:** Proposed (chờ duyệt → plan → execute)
- **Ngày:** 2026-07-18
- **Liên quan:** `orders`, `order_items`, checkout (`Shop/OrderController`), giỏ (`lib/cart`, `ProductDetail`, `Cart`), `AvailabilityService` (đã per-item), khuyến mãi (`VoucherService`/`ReferralService`/`EmailBonusService`), admin `Orders.tsx`, mail, tra cứu/tài khoản, nhắc lịch (`SendPickupReminders`), đổi lịch/hoàn cọc.
- **Kế thừa:** bopcamping-u1nb (ngày thuê riêng từng món) — vẫn dùng, mỗi con là 1 khoảng nên ngày món = ngày con.

## 1. Bối cảnh & vấn đề

Đơn nhiều khoảng ngày (A 10–12, B 13–15) gộp thành 1 đơn envelope 10–15 khiến admin **không biết ngày nào giao/thu món nào** — mỗi món có vòng đời giao/nhận riêng nhưng đơn chỉ có 1 trạng thái + 1 cọc. Chủ shop chốt hướng **tách đơn theo khoảng ngày** với mô hình **đơn cha + đơn con**.

## 2. Quyết định đã chốt (chủ shop, 2026-07-18)

| # | Nội dung | Chốt |
|---|----------|------|
| D1 | Cọc / trạng thái giao-thu / nhắc lịch | **Theo đơn CON** (mỗi con 1 vòng đời độc lập). Cha gom khách + voucher + tổng. |
| D2 | Khi nào tạo cha | **Chỉ khi giỏ có ≥2 khoảng ngày.** 1 khoảng → đơn thường như hiện tại (không cha). |
| D3 | Voucher | Tính trên **tổng tiền thuê của cha**, rồi **phân bổ theo tỉ lệ** tiền thuê xuống từng con; note rõ ở admin. |
| D4 | Giỏ tự điền ngày | Sản phẩm mở sau **mặc định chọn ngày đã có trong giỏ**; khách đổi ngày = cố ý → tách sang con khác. |

## 3. Mô hình dữ liệu

Thêm tự-tham-chiếu trên `orders`:

| Cột (mới) | Kiểu | Ghi chú |
|-----------|------|---------|
| `parent_id` | nullable FK → orders.id (nullOnDelete? **KHÔNG** — xem §8 huỷ) | Con trỏ về cha. Cha: null. Đơn thường: null. |
| `is_parent` | boolean default false | Đánh dấu dòng cha (container, KHÔNG có order_items). |

**Đơn CHA (container):**
- `is_parent=true`, `parent_id=null`, **không có order_items**.
- Giữ: thông tin khách, `service_location_id` (giỏ 1 cơ sở), `discount_breakdown` + `discount_total` (voucher/referral/email-bonus tính trên TỔNG thuê), `total_price` = Σ tiền thuê con (net, trước voucher), `deposit_total` = Σ cọc con, `start_date`=min / `end_date`=max (envelope, chỉ để hiển thị).
- **Không** trạng thái giao/thu, **không** payment/nhắc lịch (những cái đó ở con). Trạng thái cha = **suy ra** để hiển thị (mọi con đã trả → "hoàn tất"; có con đang thuê → "đang thuê"; …). Admin thao tác trên con.

**Đơn CON:**
- `parent_id=<cha>`, `is_parent=false`, có order_items (1 khoảng ngày duy nhất → ngày món = ngày con).
- Giữ: `start_date/end_date` (khoảng của con), order_items + `total_price` (net sau giảm dài ngày của con), `deposit_total` (cọc các món của con), `discount_total` (**phần voucher phân bổ** cho con), `status` + `payment_status` + `pickup_reminder_sent_at` (RIÊNG), `code` riêng.
- `amount_due(con)` = tiền thuê con − voucher phân bổ + cọc con → **tiền COD thu tại đợt giao của con này**.

**Mã đơn:** cha `BOP-XXXX`; con `BOP-XXXX-1`, `BOP-XXXX-2` (hậu tố theo thứ tự ngày). Dễ đọc, thấy ngay quan hệ.

**Đơn thường (1 khoảng):** `parent_id=null, is_parent=false` — y như hiện tại, không đổi.

## 4. Luồng checkout

```
1. Gom cart lines theo (start,end) → các nhóm khoảng ngày.
2. 1 nhóm  → tạo 1 đơn thường (như hiện tại). HẾT.
3. ≥2 nhóm → tạo CHA + N CON:
   a. Với mỗi nhóm: tạo 1 con (order_items nhóm đó, tính net theo bậc dài ngày,
      total_price con, deposit con, status=pending, code cha-i).
   b. Kiểm tồn kho từng con theo khoảng của nó (AvailabilityService per-item — đã có).
   c. total thuê cha = Σ total con. Chạy voucher/referral/email-bonus trên TỔNG này
      (VoucherService, cap 25% trên tổng cha) → discount_total cha + breakdown.
   d. Phân bổ discount cha xuống con ∝ total_price con (đồng cuối dồn vào con cuối
      để Σ khớp từng đồng). Lưu discount_total mỗi con + breakdown note "phân bổ".
   e. Cha: total_price=Σcon, deposit_total=Σcon, start=min, end=max.
4. Mail xác nhận: 1 email cấp CHA, liệt kê các con + khoảng ngày + tiền từng đợt.
```

## 5. Giỏ tự điền ngày (D4) — frontend

- `lib/cart` thêm hàm `cartSuggestedRange()` → khoảng ngày "đang dùng" của giỏ (nếu mọi dòng cùng khoảng → khoảng đó; nếu đã lẫn nhiều khoảng → khoảng của dòng thêm gần nhất).
- `ProductDetail`: khi mount, nếu giỏ có sẵn khoảng → **prefill** `start/end` = khoảng đó (khách vẫn đổi được). Đổi ngày = cố ý → khi thêm vào giỏ sẽ thành nhóm khoảng mới → checkout tách con.
- Không ép buộc, chỉ mặc định — tôn trọng "nếu khách đổi thì đó là ý của họ".

## 6. Admin (Orders.tsx)

- **Danh sách**: đơn cha hiện 1 dòng (mã cha, khách, envelope ngày, tổng, badge "gồm N đợt"). Đơn con KHÔNG hiện rời ở danh sách gốc (tránh trùng) — chỉ hiện trong cha khi mở rộng.
- **Mở rộng cha**: khối "Các đợt giao" — mỗi con 1 card: khoảng ngày, thiết bị, tiền thuê con, **voucher phân bổ −Zđ**, cọc con, trạng thái giao/thu (nút đổi per-con), nhắc lịch, đổi lịch, hoàn cọc — tái dùng UI đơn hiện có ở cấp con.
- **Voucher note (D3)**: cấp cha hiện "Voucher −X trên tổng Y (Z%)"; mỗi con hiện "đã gồm giảm phân bổ −Zđ · COD đợt này = …".
- Đơn thường (không cha): hiển thị y như hiện tại.

## 7. Các nơi khác

- **Nhắc lịch giao** (`SendPickupReminders`): chạy trên **đơn CON** (mỗi con có ngày nhận + reminder riêng) — bỏ qua cha.
- **Đổi lịch / hoàn cọc / trạng thái**: thao tác trên **con** (mỗi con 1 khoảng → `changeDates` chạy như đơn thường).
- **Tra cứu / tài khoản**: tra theo mã cha → hiện cha + các con; hoặc tra mã con ra con. Danh sách tài khoản gom theo cha.
- **AvailabilityService**: không đổi (đã đếm theo ngày món; con là 1 khoảng).

## 8. Edge case & quyết định

| Tình huống | Xử lý |
|-----------|-------|
| **Huỷ 1 con** (không huỷ cả cha) | Con → `cancelled`; **tính lại voucher trên tổng các con còn active** + phân bổ lại (giữ cap đúng); cập nhật tổng cha. KHÔNG nullOnDelete parent_id. |
| **Huỷ cả cha** | Huỷ tất cả con. |
| **Còn 1 con active** (các con khác huỷ) | Vẫn giữ cấu trúc cha/con (không tự "gộp ngược") — đơn giản, nhất quán. |
| **Đổi lịch 1 con** trùng khoảng con khác | Cho phép (2 con khác món); tồn kho check per-con. |
| **Duration discount** | Theo item/ngày của con — không đổi. |
| **Đơn cũ** | `parent_id=null,is_parent=false` — không ảnh hưởng. |

## 9. Migration & tương thích ngược

1. `add_parent_id_is_parent_to_orders` — `parent_id` nullable FK, `is_parent` bool default false. Đơn cũ mặc định null/false → đơn thường.
2. Không cần backfill (không có đơn cha cũ).
3. Roll-out: tính năng chỉ kích hoạt khi giỏ ≥2 khoảng — đơn 1 khoảng chạy y hệt hiện tại.

## 10. Rủi ro

| Rủi ro | Mức | Giảm thiểu |
|--------|-----|-----------|
| Phân bổ voucher lệch đồng khi làm tròn | TB | Dồn dư vào con cuối; test bất biến Σcon.discount == cha.discount. |
| Huỷ con → voucher/cap sai | Cao | Tính lại voucher trên con còn active trong transaction; test. |
| Admin/tra cứu/mail nhân đôi đơn con | TB | Danh sách gốc lọc `is_parent OR parent_id IS NULL` (ẩn con); con chỉ trong cha. |
| Nhắc lịch gửi nhầm cấp cha | TB | Query reminder loại `is_parent=true`. |
| Query "đơn" rải rác giả định 1 order = 1 khoảng | Cao | Rà mọi nơi đọc orders (dashboard, stats, account, lookup) — sửa để hiểu cha/con. Cần sweep + test. |

## 11. Phân rã (đề xuất, sẽ chi tiết ở plan)

1. Migration `orders.parent_id/is_parent` + quan hệ model (`parent()`, `children()`) + scope ẩn con ở list.
2. Checkout: gom nhóm khoảng → tạo cha+con + phân bổ voucher (service `OrderSplitter`).
3. Voucher: chạy trên tổng cha + allocate ∝ con (+ test bất biến, cap, huỷ-con recompute).
4. Frontend giỏ tự điền ngày (`cartSuggestedRange` + ProductDetail prefill).
5. Admin: danh sách gom cha + card "các đợt giao" per-con (tái dùng control status/payment/reschedule/refund).
6. Nhắc lịch / đổi lịch / hoàn cọc / trạng thái ở cấp con.
7. Tra cứu / tài khoản / mail xác nhận cấp cha.
8. Rà sweep dashboard/stats (đếm đơn, doanh thu) hiểu cha/con.
9. QA + browser + quality gates.

> Handoff: duyệt ADR → `/swarm-plan` chi tiết hoá + tạo beads → `/swarm-execute`.

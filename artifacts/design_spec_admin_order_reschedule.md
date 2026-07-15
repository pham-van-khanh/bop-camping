# Design Spec — Admin: đổi lịch đơn thuê

- **Beads:** bopcamping-5hjm
- **Ngày:** 2026-07-15
- **Phạm vi:** admin đổi ngày nhận/trả của đơn `pending`/`confirmed`. Đơn `renting` (gia hạn ngày trả) là nghiệp vụ khác — ngoài phạm vi.

## Quyết định nghiệp vụ (đã chốt với user)
1. Chỉ đơn **pending + confirmed**; `start_date ≥ hôm nay`, `end_date ≥ start_date`.
2. Số ngày đổi → **tự tính lại tiền thuê**; **cọc + giảm giá giữ nguyên** (admin tự cân nhắc). UI preview giá mới trước khi lưu.
3. **Gửi email** báo khách lịch mới (layout brand); khách không email hợp lệ → bỏ qua.

## Backend
- Route `PATCH /admin/orders/{order}/dates` → `Admin\OrderController@changeDates` (name `admin.orders.dates`).
- **Kiểm tồn kho khoảng mới** tại store của đơn qua `AvailabilityService::availableQuantity` (single source): gộp nhu cầu theo product như `changeLocation`; nếu **khoảng mới chồng khoảng cũ** thì `avail += qty` của chính đơn (availableQuantity đã trừ nó). Thiếu món nào trả lỗi rõ "tên SP (avail/needed)".
- **Tính lại tiền tuyến tính** (đúng cho cả dòng lẻ và dòng combo): `subtotal_mới = round(subtotal_cũ × ngày_mới ÷ ngày_cũ)`, `days = ngày_mới`; `orders.total_price = Σ subtotal`. Không đụng `deposit_total`, `discount_total`, `allocated_*`.
- **Re-arm nhắc lịch:** `start_date` đổi → `pickup_reminder_sent_at = null` (command daily sẽ gửi lại cho ngày mới).
- **Mail:** `OrderDatesChangedMail` (ShouldQueue) + view `emails/order_dates_changed` dùng `x-mail.brand` (variant green) — hiện lịch cũ → mới + item + order-facts.

## Frontend (resources/js/Pages/Admin/Orders.tsx)
- Hàng mở rộng đơn: nút **"Đổi lịch"** (chỉ pending/confirmed) mở modal:
  - 2 `input[type=date]` (nhận/trả), min = hôm nay.
  - Preview: "N ngày · tiền thuê mới X đ" — scale client-side từ subtotal hiện có (chỉ hiển thị; server là source of truth).
  - Lưu → `router.patch(admin.orders.dates)`; lỗi validate/tồn kho hiện trong modal.

## Test (Feature)
- Guard: đơn `renting`/`returned`/`cancelled` bị từ chối.
- Chặn khi đơn khác chồng lịch làm thiếu hàng; **cho phép** dịch ngày trong khoảng tự chồng (không tự chặn mình).
- Giá: 2 ngày → 3 ngày thì subtotal & total_price scale đúng; cọc + giảm giá không đổi.
- `pickup_reminder_sent_at` bị reset khi đổi ngày.
- Mail queued khi có email; không gửi khi email tạm `@bopcamping.local`.
- `start_date` quá khứ bị từ chối.

## Quality gates
`php artisan test` · `./vendor/bin/pint --test` · `npx tsc --noEmit` · `npm run build`.

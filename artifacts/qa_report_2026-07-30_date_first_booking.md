# QA Report — Đặt lịch trước, chọn đồ sau (date-first booking)

**Ngày:** 2026-07-30 · **Nhánh:** `feature/date-first-booking` (đã merge vào `develop`)
**Phạm vi:** epic `bopcamping-0ark` (T1–T5) · **PRD:** `artifacts/prd_date_first_booking.md`

## Kết luận

Chức năng **chạy đúng** trên dữ liệu thật, kể cả các ca biên khó (đệm quay vòng, tồn theo kho, ngày riêng từng món). Tìm được **2 lỗi**, cả hai đều KHÔNG nằm trong code của epic này nhưng đều do epic làm lộ ra:

| # | Bead | Mức | Vấn đề |
|---|---|---|---|
| QA-1 | `bopcamping-jyxi` | **Cao** | Giỏ báo còn 6 khi checkout chỉ cho 4 — cart refresh dùng tồn toàn cục |
| QA-2 | `bopcamping-k3pc` | Trung bình | Lịch không mở ở tháng của khoảng ngày đã prefill |

## Môi trường test

DB dev có 9 sản phẩm nhưng **8/9 có tồn pivot = 0 ở cả hai kho** → mọi thứ hiện "Hết hàng", không test được gì. Đã seed tạm (tồn theo kho + 2 combo + 1 đơn khoá lịch), test xong **restore về đúng backup** (`/tmp/bopcamping-dev-backup.sqlite`). DB dev hiện nguyên trạng: 0 combo QA, 0 đơn QA, tồn pivot như cũ.

Fixture: lều Cloud-Up 2 khoá sạch tồn Vinh (2/2) trong 10–12/09, đệm Vinh 1 ngày.

## Đã kiểm và ĐẠT

### 1. Batch ↔ per-product khớp tuyệt đối trên dữ liệu thật

Đây là rủi ro lớn nhất của epic (hai đường tính tồn cùng hiển thị cho khách trong một phiên).

| Khoảng | Listing (batch) Vinh / HN | Endpoint chi tiết (per-product) |
|---|---|---|
| 10–12/09 | 0 / 1 | `{1:0, 2:1}` ✅ |
| 13–14/09 (trong đệm) | 0 / 1 | `{1:0, 2:1}` ✅ |
| 14–15/09 (ngoài đệm) | 2 / 1 | `{1:2, 2:1}` ✅ |

Biên đệm quay vòng chính xác: đơn trả 12/09 + đệm Vinh 1 ngày → chặn tới 13/09, mở lại 14/09. Hà Nội đệm 0 → không bị chặn.

### 2. FR-4 — URL bẩn không làm vỡ trang (12/12 ca)

Tất cả trả **HTTP 200**, `filters.start/end` rỗng, `range_summary` null:
ngày quá khứ · end trước start · `2026-02-30` · quá 30 ngày · chữ · `start[]=` (array) · SQL injection (`' OR 1=1--`) · XSS (`<script>`) · năm 7 chữ số · `vi-tri` không tồn tại · `vi-tri[]=` (array) · chuỗi rỗng.

Guard `is_string()` chặn array; regex `^\d{4}-\d{2}-\d{2}$` + so lại chuỗi sau `format()` chặn ngày không tồn tại.

### 3. Listing

- Món đặt được xếp trước, món hết hàng mờ 0.5 + badge "Hết hàng", `1 thiết bị hết hàng trong khoảng này` đếm đúng.
- Badge tồn theo **khoảng ngày** chứ không phải tổng kho (Ba lô: hiện 14 = tồn Vinh, không phải 22 = `quantity`).
- `?vi-tri=` + ngày tính đúng kho; không chọn kho → max qua các kho đang mở.
- Đổi danh mục / sort / địa điểm **giữ nguyên** khoảng ngày.
- "Bỏ lọc ngày" giữ `cat`/`q`/`sort`/`vi-tri` (lỗi này đã phát hiện và vá trong lúc làm epic, có test hồi quy).

### 4. Trang chi tiết (prefill)

- Nhãn "Ngày thuê 14/09 → 16/09" lấy đúng từ query; "Còn đủ 2 bộ cho khoảng này" khớp tồn Vinh.
- Chọn cơ sở hoạt động; chọn Vinh → lịch tô **10, 11, 12, 13/09** hết hàng + disabled, khớp `unavailable_by_location[Vinh]` (có cả ngày đệm).
- Chưa chọn cơ sở → không tô ngày nào: đúng hành vi chủ ý sẵn có.

### 5. `/combos`

2 combo render đúng: combo còn hàng "Còn 2 bộ" (= min qua món con) xếp trước, combo nghẽn "Hết trong khoảng này" mờ 0.5, header "1 combo hết hàng".

### 6. Nút back

Bỏ lọc ngày → back → khoảng ngày và trạng thái card trở lại đầy đủ.

### 7. A11y (audit DOM thủ công)

Không lỗi: `section` có `aria-label`, mọi button có tên đọc được, toggle có `aria-expanded`, `<select>` có `<label for>`, card hết hàng có `aria-label` mô tả và **vẫn là link vào được** (để khách đổi ngày). Trạng thái hết hàng **không chỉ dựa vào màu** — có badge chữ.

## Lỗi tìm được

### QA-1 (Cao) — Giỏ báo còn nhiều hơn checkout cho phép · `bopcamping-jyxi`

`CartController.php:151` gọi `availableQuantity($p, start, end)` **không truyền kho** → nhánh tồn toàn cục `products.quantity`. Dòng 157 tương tự cho combo. Nhưng checkout (`StoreResolver::shortfall`) yêu cầu **một kho** đủ cả giỏ, không cộng xuyên kho.

Ghế gấp Helinox (Vinh 4, Hà Nội 2, `quantity` 6), khoảng 20–22/09:

| Nơi | Nói còn |
|---|---|
| Listing `/thiet-bi` | **4** ✅ khớp checkout |
| Cart refresh | **6** ❌ |
| `StoreResolver` | đặt 4 → CHO · đặt 5, 6 → TỪ CHỐI |

Khách nhét 6 bộ vào giỏ rồi bị chặn ở checkout bằng thông báo chung chung. Trước epic này listing hiện `quantity` = 6 nên "khớp" với giỏ (cùng sai); giờ listing đúng nên chỗ lệch mới lộ ra.

**Vá:** dùng `availabilityMatrix()['best']` / `comboQuantitiesFor()` cho `stock` trong cart refresh — đúng bằng số checkout đáp ứng được, và giảm N query xuống 1. Cả hai method đã có sẵn từ T1.

### QA-2 (Trung bình) — Lịch không mở ở tháng đã prefill · `bopcamping-k3pc`

`DateRangeCalendar.tsx:28` khởi tạo `view` = tháng hiện tại, bỏ qua prop `start`. Khách chọn 14–16/09 ở trang chủ, vào trang chi tiết thấy nhãn đúng nhưng **mở lịch ra là tháng 7 và 8 trống trơn** → tưởng mất lựa chọn; phải bấm mũi tên 2 lần mới thấy.

State prefill **đúng** (lật sang tháng 9 thì 14/16 xanh đậm, 15 trong khoảng) — chỉ sai tháng khởi tạo.

**Vá:** khởi tạo `view` từ `start` khi có và không ở quá khứ. Cải thiện cho cả 5 chỗ đang dùng.

## Giới hạn của lần QA này

- **Lighthouse không chạy được:** Browser pane bị ẩn trong phiên này, và Inertia render client-side nên HTML tĩnh không có nội dung để audit. Đã thay bằng audit DOM thủ công (mục 7) — bao được nhãn/ARIA/không-chỉ-dựa-màu, **không** bao được tương phản màu và thứ tự tab đầy đủ. Nên chạy Lighthouse trên trình duyệt thật trước khi lên production.
- **Animation không đo được:** pane ẩn làm `requestAnimationFrame` bị throttle nên `framer-motion` treo giữa animation (`opacity` mắc ở 0–0.25). Screenshot có thể bắt đúng lúc đang dở. Đã xác minh bằng DOM/dữ liệu thay vì ảnh. Không phải lỗi sản phẩm.
- **Chưa test:** đặt đơn thật end-to-end qua form checkout (mới chỉ gọi `StoreResolver` trực tiếp); đa người dùng đặt đồng thời cùng khoảng ngày; `/combos` trên dữ liệu production thật.

## Test tự động (không đổi)

736 test PHP · 52 test JS · `tsc` sạch · `pint` pass · build OK.

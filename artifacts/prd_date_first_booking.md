# PRD — Đặt lịch trước, chọn đồ sau (date-first booking)

**Ngày:** 2026-07-30
**Trạng thái:** Approved (user chốt 5 quyết định trong phiên brainstorming 2026-07-30)
**Beads:** epic `bopcamping-<epic>` (xem `bd show`)

## 1. Vấn đề

Hôm nay khách vào trang chủ chỉ có đường dẫn duy nhất là "xem sản phẩm" → `/thiet-bi` liệt kê **toàn bộ** thiết bị, không biết món nào còn rảnh vào ngày khách định đi. Khách phải mở từng trang chi tiết, chọn ngày, rồi mới biết món đó hết hàng — lặp lại cho từng món. Ngày thuê là **ràng buộc mạnh nhất** của mô hình cho thuê theo ngày, nhưng lại được hỏi **cuối cùng**.

## 2. Mục tiêu

Hỏi ngày **ngay ở trang chủ**, rồi đưa khách sang trang thiết bị đã biết trước món nào đặt được trong khoảng đó.

**Ngoài phạm vi:** không đổi cấu trúc giỏ hàng (ngày vẫn theo từng dòng giỏ), không đổi checkout, không làm bộ lọc ngày cho trang chi tiết sản phẩm.

## 3. Người dùng & kịch bản

**Khách thuê lẻ (persona chính).** "Cuối tuần này tôi đi Ba Vì 2 đêm, shop còn gì cho tôi thuê?"

1. Vào trang chủ → thấy dải "Bạn đi ngày nào?" ngay dưới hero.
2. Chọn khoảng ngày trên lịch 2 tháng; tùy chọn chọn địa điểm nhận đồ.
3. Bấm **Xác nhận** → sang `/thiet-bi?start=…&end=…&vi-tri=…`.
4. Danh sách hiện **toàn bộ** thiết bị, món đặt được xếp trước; món hết hàng trong khoảng đó bị làm mờ + badge "Hết hàng", nút thêm giỏ disable.
5. Bấm vào món → trang chi tiết, lịch **đã prefill** khoảng ngày đó; khách sửa được nếu muốn.

## 4. Quyết định đã chốt

| # | Quyết định | Chọn | Lý do |
|---|---|---|---|
| 1 | Món hết hàng trên listing | **Hiện hết, làm mờ + badge "Hết hàng"**, món available xếp trước | Khách thấy shop CÓ món đó → có thể đổi ngày, thay vì tưởng shop không bán |
| 2 | Địa điểm trong module đặt lịch | **Có, tùy chọn** (mặc định "Tất cả") | Tồn thật ở pivot theo kho. Không chọn → lấy **max qua các kho đang mở**; chọn rồi → tính đúng kho đó |
| 3 | Phạm vi khoảng ngày | **URL query là source of truth**, nhẹ nhàng prefill xuống trang chi tiết | Share/F5/back đều đúng; không đụng cấu trúc giỏ per-line → không xung đột `bopcamping-wtuv` |
| 4 | Vị trí trên trang chủ | **Dải riêng ngay dưới hero** (trên `BiomeHero`) | Không phá `HeroSlideshow` + `HomeServingPanel` đang có; mobile xếp dọc tự nhiên; test được |
| 5 | Phạm vi trang | **Cả `/thiet-bi` và `/combos`** | Đỡ lệch trải nghiệm; `comboAvailable()` đã có nên tái dùng được |

## 5. Yêu cầu chức năng

### FR-1 — Module đặt lịch trang chủ

> **Sửa 31/07/2026:** ban đầu làm thành *dải riêng dưới hero*. Chủ shop chốt lại: đưa lên
> **banner** thành một ô đặt lịch, bấm mở **popup**; dải dưới hero đã **bỏ** để không có hai
> chỗ chọn ngày trên cùng một trang.

- **Ô đặt lịch trên banner** (thay nút "Tra cứu đơn của tôi" cũ): nhãn "NGÀY NHẬN – NGÀY TRẢ / Chọn ngày đi". Tra cứu đơn vẫn còn ở nav header (khách vãng lai), footer, giỏ và trang tài khoản.
- Bấm → **popup** `RentalDateModal`: rộng 920px trên PC (to hơn dải cũ 720px), lịch dùng `size="lg"` (ô ngày 40px thay vì 30px). Đóng bằng ESC / backdrop / nút ×, chặn scroll trang nền, `role="dialog"` + `aria-modal`.
- Trong popup: `DateRangeCalendar` (tái dùng), select địa điểm (tùy chọn), nút **Xác nhận**.
- Nút Xác nhận **disabled** tới khi có đủ `start` **và** `end`.
- Lịch **không** tô ngày hết hàng — chưa có sản phẩm nào để tính. Chỉ chặn ngày quá khứ. Chú giải "Hết hàng" cũng được ẩn ở đây (`showUnavailableLegend={false}`) vì hiện ô màu cho trạng thái không bao giờ xảy ra là nói sai.
- Xác nhận → điều hướng `/thiet-bi?start=&end=[&vi-tri=]`.

### FR-2 — Listing theo ngày (`/thiet-bi`, `/combos`)
- Nhận `start`, `end` (`Y-m-d`) từ query; tái dùng `vi-tri` sẵn có cho địa điểm (**không** thêm param mới).
- Mỗi item có thêm `available: number` và `in_range: boolean`.
- Sắp xếp: `in_range` desc **trước**, rồi tới `sort` hiện tại (`pop`/`low`/`high`).
- Món `in_range === false`: mờ (`opacity-50`), badge "Hết hàng", nút thêm giỏ disable.
- Món available hiện thêm "Còn N" khi `N ≤ 3` (khan hiếm nhẹ); không hiện khi dư nhiều.
- Thanh ngày **compact** ngay trên trang để đổi ngày tại chỗ; chip "Đang xem: 12–14/08 · 3 ngày ✕" để bỏ filter.
- Giữ nguyên `cat` / `q` / `sort` / `vi-tri` khi đổi ngày.

### FR-3 — Prefill trang chi tiết
- `ProductDetail` / `ComboDetail` đọc `start`/`end` từ query, prefill lịch, **ưu tiên hơn** `cartSuggestedRange()`.
- Khách sửa được tự do. Không lock.

### FR-4 — Ngày không hợp lệ → bỏ qua, không lỗi
Sai format · `end < start` · `start` trong quá khứ · range > **30 ngày** → **bỏ qua filter ngày**, render như chưa chọn. Không 422, không exception. Trang không bao giờ vỡ vì URL bẩn.

## 6. Yêu cầu phi chức năng

### NFR-1 — Availability phải là O(1) query bất kể số sản phẩm
`/thiet-bi` **không phân trang** (`get()` tại `ProductController::index()`). Gọi `availableQuantity()` trong vòng lặp = N query, cộng thêm combo thì N×M. Phải có method batch trong `AvailabilityService`.

### NFR-2 — Batch không được là logic thứ hai
`bookedQuantity()` dùng **buffer quay vòng riêng theo từng sản phẩm/kho** (`AvailabilityService.php:49`) nên không gộp được thành một `SUM`. Batch lấy rows rồi cộng trong PHP theo buffer riêng.

**Invariant bắt buộc (test bằng property):** với mọi sản phẩm,
`availableQuantitiesFor([...])[id] === availableQuantity($product, …)`.
Batch chỉ là tối ưu I/O — **không** được trở thành nguồn chân lý thứ hai (CLAUDE.md: single source of truth cho tồn kho).

### NFR-3 — Test collation-safe
Chạy đúng trên cả sqlite (LIKE so byte) và MySQL `utf8mb4_unicode_ci`. Không để dữ liệu nhiễu chứa từ khoá có dấu trong `name`/`description`.

## 7. Đo lường thành công

- Tỉ lệ khách vào trang chủ rồi tới `/thiet-bi` **có** `start`/`end` trong URL.
- Giảm số lần khách mở trang chi tiết rồi thoát vì hết hàng.
- Query count trên `/thiet-bi` **không tăng theo số sản phẩm** (test khẳng định).

## 8. Rủi ro

| Rủi ro | Giảm thiểu |
|---|---|
| Batch lệch per-product ở ca biên (buffer, đơn NULL kho, ngày per-item) | Test invariant so sánh batch vs per-product trên chính các ca đó |
| "Còn N" ở chế độ "Tất cả" là max qua các kho → khách chọn kho khác thấy ít hơn | Khi chưa chọn kho, hiện nhãn trung tính; số chính xác ở trang chi tiết (đã có `by_location`) |
| Sort trong PHP sẽ sai khi thêm phân trang về sau | Ghi rõ trong plan là điểm phải xem lại; hiện chưa phân trang nên đúng |

## 9. Liên quan

- `artifacts/plan_date_first_booking.md` — các bước triển khai
- `artifacts/design_spec_per_store_stock.md` — tồn theo kho (pivot)
- `artifacts/adr_turnaround_buffer.md` — đệm quay vòng
- `bopcamping-u1nb` — ngày riêng từng `order_item`
- `bopcamping-wtuv` — đơn cha/con (không xung đột: giỏ vẫn per-line)

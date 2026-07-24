# ADR — Bậc giá nửa ngày & quay vòng trong ngày (Half-day pricing & same-day turnaround)

- **Trạng thái:** Accepted (chờ implement)
- **Ngày:** 2026-07-24
- **Beads:** `bopcamping-jrh8` (Phase 3 — phụ thuộc Phase 1 turnaround `bopcamping-s1ij`)
- **Liên quan:** `app/Services/AvailabilityService.php`, `app/Services/RentalPricingService.php` (nơi tính giá thuê hiện có — single source of truth), `products`, `product_service_location`, `orders`, `order_items`, `site_settings`.
- **Bổ sung cho:** [`adr_turnaround_buffer.md`](./adr_turnaround_buffer.md) — ADR này **giữ nguyên INVARIANT mục 2** của file đó (giờ không vào tính tồn kho).

> **Đã khớp codebase (2026-07-24):** so với bản chốt gốc, tài liệu điều chỉnh 2 điểm cho khớp code thật, KHÔNG đổi quyết định:
> 1. Giá tính trong **`RentalPricingService`** (đã tồn tại, là nguồn chân lý tính giá thuê + bậc giảm dài ngày) — không tạo `PricingService` mới. Ưu đãi trả sớm là một tầng cộng thêm ở đây.
> 2. Cờ `is_half_day` đặt ở **`orders`** (đúng như "gắn ở cấp đơn" của bản chốt; khớp với `start_date/end_date` vốn nằm ở cấp đơn). Tiền lệ: `duration_discount_percent` per-order_item + `DurationDiscountTier` đã có — `early_return_discount_pct` per-product noi theo pattern này.

---

## 1. Bối cảnh / Vấn đề

ADR Turnaround đã chốt: tồn kho tính theo **ngày**, giờ giấc chỉ để hiển thị + phụ phí thủ công. Trên nền đó nảy ra hai câu hỏi về **giá** và **quay vòng**:

1. **Cho thuê ngắn trong ngày tính tiền thế nào?** Khách chỉ dùng vài giờ (vd 8h–12h) mà tính đủ giá cả ngày thì thiệt cho khách; nhưng chia đôi giá thì lỗ cho shop, vì món **vẫn bị khoá trọn ngày** trong tồn kho (INVARIANT).
2. **Có nên cho một món ra tiền 2 lượt trong cùng một ngày không?** (Món lau nhanh như ghế/bàn: sáng 1 khách, chiều 1 khách.)

> **Ghi chú lịch sử:** Từng cân nhắc một mô hình "theo buổi" (session) tự động — tồn kho tính đến mức buổi, khách đặt buổi chiều online. **Đã loại ở giai đoạn này** vì rủi ro vận hành với mô hình **giao tận nơi** + **ít đồ** (xem mục 6 và mục 7). Giữ lại như hướng tương lai có điều kiện (mục 10).

## 2. Quyết định

**Giai đoạn này chỉ triển khai mô hình theo ngày.** Không có mô hình buổi tự động. Cụ thể:

- **Nửa ngày = khoá CẢ NGÀY, giảm giá nhẹ.** Đơn trả sớm trong ngày được giảm (mặc định **10%**, nhập được theo từng sản phẩm). Đây là **ưu đãi trả sớm**, không phải "bán nửa phần" — vì món vẫn khoá trọn ngày, không giải phóng capacity nào.
- **Gắn cờ "nửa ngày" ở cấp đơn** (`orders.is_half_day`). Cờ này để admin **thấy ngay đơn nào sẽ trả vào trưa** → là tín hiệu cân nhắc quay vòng (mục 6).
- **Bậc nửa ngày chỉ áp cho đơn CÙNG NGÀY.** Đơn nhiều ngày **luôn** tính theo số ngày, không có nửa ngày ở giữa.
- **Quy ước đếm ngày:** `start=01 → end=03` = **3 ngày** (đếm số ngày món ra khỏi kho, khớp mô hình tồn kho). Web và cửa hàng offline phải dùng chung.
- **Quay vòng trong ngày (Trường hợp B, mục 6):** CHỈ cho món `buffer_days = 0`; admin bấm `returned` **sau khi đã lau xong**; **admin tự tạo đơn buổi chiều**, không mở cửa sổ chiều cho khách đặt online tự động.
- **(Tùy chọn) van an toàn:** một cờ "tạm ngừng nhận đơn" per-product — chỉ để **đóng**, không bao giờ để mở vượt trần tồn.

> **INVARIANT (giữ nguyên từ ADR Turnaround mục 2):** Giờ giấc KHÔNG tham gia tính tồn kho; mọi lượt khoá **trọn ngày**. Giai đoạn này **không có ngoại lệ nào** (khác với bản nháp session trước đó).

### 2.1 Bảng bậc giá (chốt)

Số liệu ví dụ: giá gốc 100.000đ/ngày, ưu đãi trả sớm −10%.

| Bậc thuê | Nhận → Trả | Ngày khoá kho | Cách tính giá | Ví dụ |
| --- | --- | --- | --- | --- |
| Nửa ngày (trả sớm) | 8h → 12h, **cùng ngày** | 1 | Giá ngày − % (nhập/món) | 90.000đ |
| Cả ngày | 8h → 20h, 1 ngày | 1 | Giá ngày × 1 | 100.000đ |
| Nhiều ngày | 8h ngày 01 → 20h ngày N | N | Giá ngày × N | 300.000đ (3 ngày) |
| Nhận sớm / trả muộn ngoài khung | vẫn trong ngày đã đặt | như trên | + phụ phí admin nhập tay | + phụ phí |

## 3. Thiết kế — Schema

```php
// migration: add_early_return_discount_to_products
$table->unsignedTinyInteger('early_return_discount_pct')->default(0); // 0 = không giảm; validate 0..50

// (tùy chọn) van an toàn per-product
$table->boolean('accepting_orders')->default(true); // false = tạm ngừng nhận đơn món này
```

```php
// migration: add_half_day_flag_to_orders  (cấp ĐƠN — khớp start_date/end_date vốn ở orders)
$table->boolean('is_half_day')->default(false); // true = trả sớm trong ngày, đã giảm giá
```

Cờ ở **cấp đơn**; % giảm đã áp được phản ánh trong `order_items.subtotal` từng dòng (mỗi món dùng `early_return_discount_pct` của chính nó — mirror cách `duration_discount_percent` per-item hoạt động). Không cần cột mới trên `order_items` cho giai đoạn này.

Không thêm `enum session`, `session_price`, `session_buffer_minutes` — mô hình buổi tự động **không** triển khai giai đoạn này.

## 4. Thiết kế — Tính tồn kho

**Giữ nguyên toàn bộ ADR Turnaround.** Không thêm nhánh nào. Cụ thể:

- **Trường hợp A — cái thứ 2 còn trong kho:** đã tự chạy nhờ tồn theo ngày. 2 ghế, sáng thuê 1 → tồn `2 − 1 = 1`, khách khác vẫn đặt được cái còn lại cho ngày đó (kể cả buổi chiều). **Không cần build gì thêm**, không cần toggle.
- **Trường hợp B — chính cái sáng quay vòng:** dựa vào trạng thái đơn, xem mục 6.
- Đơn `is_half_day = true` vẫn khoá trọn ngày trong `availableQuantity()` — cờ chỉ để hiển thị + tính giá, **không** đổi tồn.

## 5. Tính tiền (`RentalPricingService`)

Ưu đãi trả sớm là một **tầng cộng thêm** vào service tính giá hiện có (`priceLine(perDay, qty, days)` → `{gross, percent, net}`). Với đơn cùng ngày (`days = 1`), bậc giảm dài ngày `tierPercentForDays(1) = 0` nên không xung đột; ta nhân thêm hệ số trả sớm của từng món:

- **Đơn cùng ngày, trả sớm (`orders.is_half_day = true`):** `giá_ngày × (1 − early_return_discount_pct/100)` cho từng dòng.
- **Đơn cùng ngày, cả ngày:** `giá_ngày`.
- **Đơn nhiều ngày:** `giá_ngày × N` với bậc giảm dài ngày như hiện tại. **Không** áp ưu đãi trả sớm.
- **Phụ phí ngoài khung:** cộng `extra_fee` admin nhập tay (ADR Turnaround Phase 2).

Triển khai: thêm tham số/hàm cho early-return trong `RentalPricingService` (vd `priceLine(..., int $earlyReturnPct = 0)` chỉ áp khi `days === 1 && is_half_day`), giữ mọi caller khác không đổi hành vi.

## 6. Quay vòng trong ngày (Trường hợp B) — quy trình & bẫy

Cơ chế: `returned` không nằm trong `activeStatuses()` → bấm `returned` là đơn **rớt khỏi tính tồn ngay**, món trống lại. Dùng đúng thì quay vòng được; dùng sai thì hỏng.

**Luồng đúng (chỉ món `buffer_days = 0`):**

1. Đồ sáng về → admin kiểm + lau.
2. **Lau xong** → bấm `returned`.
3. Món trống lại → admin **tự tạo đơn buổi chiều** cho khách (đặt cọc xác nhận thủ công + Zalo như quy trình hiện tại).

**Bẫy 1 — bấm `returned` quá sớm.** Bấm lúc đồ *vừa về nhưng chưa lau* → hệ thống coi món sẵn sàng ngay, có thể nhận đơn khi đồ chưa xử lý. Quy ước: `returned` = "đã về **và** lau xong, sẵn sàng cho thuê lại" (khớp ADR Turnaround mục 5).

**Bẫy 2 — món có buffer.** Với món `buffer_days > 0` (lều…), bấm `returned` buổi trưa sẽ **xoá luôn cả buffer phơi** → hệ thống tưởng lều khô, cho thuê chiều, **giao lều còn ướt**. Chốt chặn: **chỉ món `buffer_days = 0` mới được quay vòng trong ngày.** Món có buffer: cấm quay vòng, dù đã bấm `returned` cũng không cho thuê lại trong khoảng phơi.

**Vì sao admin tự tạo đơn chiều, không mở online tự động:** trạng thái `returned` bật đúng lúc là thao tác tay dễ quên / dễ bấm sớm; với giao tận nơi, sai một nhịp là trễ hẹn khách đã trả tiền. Admin tạo đơn = có người xác nhận "giờ chắc chắn giao được", an toàn hơn để máy tự chào cửa sổ trống ra ngoài.

**Cảnh báo đo lường:** dùng `returned` làm công tắc "mở cho chiều" khiến **thời điểm bấm `returned` lệch** khỏi ý nghĩa gốc (đánh dấu đơn kết thúc). Thống kê sau này ("đồ thường về lúc mấy giờ", "tỉ lệ trả trễ") sẽ nhiễu. Không phải vấn đề bây giờ, nhưng ghi lại để sau khỏi ngạc nhiên.

## 7. Alternatives đã cân nhắc

| Phương án | Vì sao loại |
| --- | --- |
| **Giá theo giờ thuần (per-hour)** | Mặc cả giờ lẻ; toán qua đêm vỡ trận (6h ngày 01 → 12h ngày 03 = 54h, ra số kỳ cục); chống lại độ phân giải ngày. |
| **Nửa ngày = 50% giá ngày** | Lỗ: món vẫn khoá cả ngày; khách khôn sẽ luôn chọn nửa ngày rồi giữ tới tối. |
| **Mô hình buổi tự động (session)** cho mọi món | Với giao tận nơi + ít đồ: sáng trả trễ → không giao kịp cho khách chiều đã đặt = mất uy tín, nặng hơn cái lợi doanh thu lượt hai. Hoãn (mục 10). |
| **Để hệ thống tự mở cửa sổ chiều cho khách đặt online** | Phơi rủi ro turnaround ra cho khách; phụ thuộc thao tác tay đúng-thời-điểm. Chọn admin tự tạo đơn thay thế. |
| **Toggle "cho thuê tiếp" vượt trần tồn** | Overbook. Van an toàn chỉ được phép **đóng**, không mở quá tồn. |
| **`early_return_discount_pct` toàn shop** | Không phản ánh khác biệt món; chủ shop chọn nhập per-product. |

## 8. Hệ quả

**Tích cực:** khách thuê ngắn có giá hợp lý mà shop không lỗ; **gỡ bớt** so với bản nháp session (bỏ enum buổi, giá buổi, đệm chuyển buổi, nhánh tồn theo buổi) — chỉ thêm 1 cờ nửa ngày + 1 cột % giảm; giữ nguyên INVARIANT và toàn bộ tính tồn của ADR Turnaround; Trường hợp A chạy sẵn không tốn công. **Cần lưu ý:** Trường hợp B phụ thuộc kỷ luật vận hành (bấm `returned` đúng lúc, chỉ áp món buffer=0) → phải ghi rõ trong UI admin; thời điểm `returned` bị lệch làm nhiễu thống kê sau này.

## 9. Kế hoạch triển khai (Phase 3 — bead `bopcamping-jrh8`)

1. Migration: `early_return_discount_pct` (+ tùy chọn `accepting_orders`) vào `products`; `is_half_day` vào `orders`.
2. `RentalPricingService`: áp ưu đãi trả sớm cho đơn cùng ngày `is_half_day` (mục 5), giữ caller khác không đổi.
3. Checkout: đơn cùng ngày hiện lựa chọn "trả sớm trong ngày (−%)"; set `orders.is_half_day`. Đơn nhiều ngày không hiện bậc này.
4. Admin: ô `early_return_discount_pct` mỗi sản phẩm; danh sách đơn **hiển thị cờ nửa ngày** để thấy đơn trả trưa; nút tạo đơn thủ công cho lượt chiều; (tùy chọn) công tắc `accepting_orders`.
5. UI admin ghi rõ quy ước: bấm `returned` **sau khi lau xong**; **chỉ quay vòng trong ngày với món không có buffer**.
6. **Test (bắt buộc):**
   - Không hồi quy: bỏ mọi cờ mới ⇒ hành vi y hệt ADR Turnaround.
   - Nửa ngày: đơn cùng ngày áp `early_return_discount_pct`; đơn nhiều ngày KHÔNG áp.
   - `is_half_day` **không** làm thay đổi `availableQuantity()` (vẫn khoá trọn ngày).
   - Trường hợp A: 2 ghế, sáng thuê 1 → còn 1, khách khác đặt được ngày đó.
   - Trường hợp B: món buffer=0, bấm `returned` → món trống lại trong ngày, tạo được đơn chiều.
   - **Chốt chặn buffer:** món buffer>0, bấm `returned` giữa khoảng phơi → **không** cho thuê lại trong khoảng đó.
   - Chạy được cả SQLite lẫn MySQL, collation-safe (CLAUDE.md).

## 10. Tương lai (có điều kiện) — mô hình theo buổi

Chưa build. Chỉ mở lại khi hết điều kiện rủi ro hiện tại, tức khi có **tồn dư để gánh trả trễ**, hoặc có thêm kênh **khách tự đến lấy** (bỏ mắt xích giao chéo thành phố). Khi đó cân nhắc, theo thứ tự ưu tiên an toàn:

- **Session chỉ mở khi tồn ≥ 2:** chỉ nhận khách chiều khi món còn cái thứ hai để gánh nếu sáng trả trễ; tự bật khi nhập thêm hàng.
- **Session admin bật tay từng ca:** không cho đặt buổi chiều online; admin tự thêm lượt khi thấy an toàn.

> Handoff artifact: file này (`artifacts/adr_pricing_models.md`), đọc kèm `artifacts/adr_turnaround_buffer.md`.

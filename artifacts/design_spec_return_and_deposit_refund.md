# Design Spec — Lượt trả + hoàn cọc có kiểm đồ

- **Ngày:** 2026-08-07
- **Epic:** bopcamping-c9aw (Hình thức giao nhận 4 mô hình + hoàn cọc)
- **Thay thế:** phần "cam kết 24h" của bopcamping-ud3l (T3) và phần "lượt trả ghi chữ tự do" của bopcamping-o64l (T4)
- **Trạng thái:** đã chốt với chủ shop 07/08/2026

## 1. Mục đích

Hai mục tiêu của chủ shop, giải bằng cùng một thiết kế:

1. **Kiểm được đồ trước khi hoàn cọc** — cọc thu và hoàn **bằng chuyển khoản**, nên tiền nằm ở tài khoản shop và chỉ chủ shop chuyển lại được. Cần biết đơn nào đang giữ tiền của ai, đã kiểm chưa, hoàn bao nhiêu.
2. **Tối ưu chi phí vận hành** — Nghệ An phải thuê xe ngoài. Rẻ nhất là khách tự đến lấy, tự mang trả.

Đòn bẩy nối hai mục tiêu: **tốc độ hoàn cọc khác nhau theo hình thức giao nhận, và đó là sự thật vận hành.** Khách tự mang trả thì kiểm tại quầy, hoàn ngay. Cho khách thấy điều đó ở checkout thì một phần khách tự chọn đường rẻ — không cần giảm giá, không cần biết trước phí ship.

## 2. Vì sao bỏ "cam kết 24h"

Bead T3 định hứa hoàn cọc trong 24h. Chủ shop vận hành thực tế là **1–2 tiếng**. Hứa 24h là hứa mức tệ nhất cho mọi khách trong khi làm tốt hơn nhiều — tự bôi xấu mình.

Thay bằng lời hứa **theo hình thức khách chọn**, kèm khung giờ làm việc. Đồ thu sau giờ làm thì hoàn sáng hôm sau — nói thẳng, vì "1–2 tiếng" lúc 8h tối là bịa.

## 3. Hiện trạng (đã kiểm chứng trong code)

| Đã có | Chưa có |
|---|---|
| `delivery_method` ∈ `self_pickup`\|`ship` — khách chọn lượt GIAO ở checkout | Lượt TRẢ có cấu trúc (đang nằm trong `schedule_note` dạng chữ) |
| `collected_at`/`collected_by` — ghi qua `Order::stampAction()` từ cả admin lẫn app shipper | Mốc **kiểm đồ** |
| `deposit_refund_status` ∈ `pending`\|`refunded`, `deposit_refund_note`, `deposit_refunded_at`/`_by` | **Số tiền hoàn thực tế** — trừ cọc xong là mất dấu |
| App shipper có nút "đã hoàn cọc" | — nút này **sai vai**: shipper không chuyển khoản từ TK shop được |
| `SiteSetting.pickup_hour = 8`, `return_hour = 20` | — dùng luôn làm khung giờ làm việc, không đẻ cài đặt mới |

## 4. Dữ liệu

### 4.1 Cột mới trên `orders`

| Cột | Kiểu | Ghi chú |
|---|---|---|
| `return_method` | `string` nullable | `self_return` (khách tự mang trả) \| `shop_collect` (Bốp đến thu). Trống lúc khách đặt; admin chốt khi gọi xác nhận. |
| `deposit_refund_amount` | `unsignedInteger` nullable | Số tiền **thực hoàn**. Trừ bao nhiêu = `deposit_total − amount` (suy ra, không lưu hai chỗ). |
| `inspected_at` | `timestamp` nullable | Mốc kiểm đồ. |
| `inspected_by` | `foreignId` nullable → `users` | Người kiểm. |

`inspected_at`/`inspected_by` đặt tên theo đúng khuôn `stampAction()` sẵn có, và thêm `'inspected' => 'Đã kiểm đồ'` vào `Order::TRACKED_ACTIONS` → `actionLog()` tự hiện dòng mới, không sửa gì thêm.

**Checkout KHÔNG hỏi thêm câu nào.** `return_method` do admin chốt lúc gọi — đúng cách chủ shop đang làm, chỉ khác là bấm nút thay vì gõ chữ.

### 4.2 Trạng thái hoàn cọc: 2 → 4

`Order::REFUND_STATUSES` mở rộng:

| Giá trị | Nghĩa | Ai gỡ |
|---|---|---|
| `pending` | Đã thu đồ, **chưa kiểm** | Người kiểm (admin, hoặc shipper báo về) |
| `awaiting_customer` | Kiểm rồi, **có vấn đề, đang thoả thuận** | Admin, sau cuộc gọi/Zalo |
| `ready` | Chốt xong số tiền, **chờ chuyển khoản** | Admin, mở app ngân hàng |
| `refunded` | Đã chuyển | xong |

Dữ liệu cũ hợp lệ nguyên trạng (`pending`, `refunded` đều còn trong tập mới) — migration không cần backfill.

### 4.3 Chuyển trạng thái hợp lệ

| Từ → Đến | Ai làm | Điều kiện |
|---|---|---|
| `pending` → `ready` | Admin, hoặc shipper lượt trả | Kiểm đồ OK. Stamp `inspected`. |
| `pending` → `awaiting_customer` | Admin, hoặc shipper lượt trả | Kiểm thấy vấn đề, bắt buộc có ghi chú. Stamp `inspected`. |
| `pending` → `refunded` | **Chỉ admin** | Nút tắt "Kiểm xong, hoàn đủ". Stamp `inspected` + hoàn đủ cọc. |
| `awaiting_customer` → `ready` | **Chỉ admin** | Khách đã đồng ý mức trừ. |
| `awaiting_customer` → `refunded` | **Chỉ admin** | Đồng ý và chuyển luôn. |
| `ready` → `refunded` | **Chỉ admin** | Bắt buộc có `deposit_refund_amount`. |
| bất kỳ → `pending` | **Chỉ admin** | Sửa sai — xoá `deposit_refunded_at`/`_by`/`amount`. Giữ nguyên hành vi reset sẵn có. |

**Bất biến:**

- Mọi chuyển trạng thái đi qua **một lối vào duy nhất** trên model (mở rộng từ `markRefunded()` hiện tại). Không controller nào tự ghi `deposit_refund_status`.
- Vào `refunded` **bắt buộc** có `deposit_refund_amount`, với `0 ≤ amount ≤ deposit_total`. Vào `ready` thì **nên** có (đó chính là nghĩa của `ready`: số tiền đã chốt) nhưng không bắt buộc — shipper báo OK là chốt hoàn đủ, admin vẫn sửa được trước khi chuyển.
- Chỉ thao tác được khi **đã thu cọc** (`deposit_paid_at` khác null) **và** đơn ở `returned`. Không có ngoại lệ: muốn báo kiểm thì phải bấm "đã thu đồ" trước — đúng thứ tự đời thực (thu đồ rồi mới kiểm), và nhờ vậy mọi đơn đang chờ hoàn cọc đều có `collected_at` để đếm thời gian giữ và để lọt vào ô lọc.
- **Đơn cha** không hoàn cọc — làm trên từng đơn con (giữ nguyên luật sẵn có).
- Shipper **không bao giờ** đặt được `refunded`.

## 5. Màn admin

Không thêm màn mới.

**Ô lọc mới trên trang đơn hàng.** Dải lọc hiện có (`Chờ xác nhận 3 · Đang thuê 5 · …`, tính trong `OrderController::index`) thêm một ô: **"Đang giữ cọc (n)"**.

Điều kiện lọc: `status = returned` **AND** đã thu cọc **AND** `deposit_refund_status ≠ refunded`.

Mỗi dòng hiện thêm: số tiền đang giữ, **đã giữ bao lâu** (tính từ `collected_at`), đang kẹt ở bước nào.

Tô màu theo thời gian giữ: **vàng > 3 giờ, đỏ > 24 giờ** (giờ thực). Chấp nhận việc đơn thu buổi tối sẽ vàng qua đêm — chỉ là màu, không phải cảnh báo sai.

**Popup trên từng đơn** (không phải màn riêng) với ba hành động:

| Nút | Làm gì |
|---|---|
| Kiểm xong, hoàn đủ | `pending → refunded`, amount = `deposit_total` |
| Có vấn đề | `pending → awaiting_customer`, bắt buộc ghi lý do |
| Đã chuyển khoản | `→ refunded`, nhập số tiền thực trả |

Đơn `delivery_method = ship` nhắc rõ: *"đơn này Bốp giao — nhớ báo phí và nhập vào Phí phát sinh"* (giữ từ T4, dùng `extra_fee` + `extra_fee_note` sẵn có).

Chốt `return_method` bằng một nút trên màn đơn, dùng khi gọi xác nhận.

## 6. App shipper

Đổi nút **"đã hoàn cọc"** → **"Báo kiểm đồ: OK / Có vấn đề"**.

- OK → `pending → ready`, stamp `inspected`
- Có vấn đề → `pending → awaiting_customer` + ghi chú, stamp `inspected`

Chỉ shipper được gán **lượt trả** của đúng đơn đó làm được (giữ nguyên luật chống IDOR sẵn có). Nút chỉ hiện sau khi đã bấm "đã thu đồ" (đơn ở `returned`). Endpoint hoàn cọc cũ của shipper **bỏ**.

## 7. Khách thấy gì

**Checkout** — thay câu hứa chung bằng bảng so sánh:

| Hình thức | Cọc về tay khách |
|---|---|
| Tự mang trả tại kho | **Ngay lúc trả** — kiểm đồ cùng nhân viên |
| Bốp đến thu | Sau khi kiểm xong, **trong giờ làm 8h–20h** |

Giờ đọc từ `SiteSetting.pickup_hour`/`return_hour` — không hardcode, không đẻ cài đặt mới.

**Trang tra cứu đơn + trang tài khoản** — bước cuối hiện nay gộp một cục *"Đã trả · hoàn cọc"* (`OrderLookupService::buildTimeline`). Tách thành:

*Đã thu đồ* → *Đang kiểm đồ* → *Đã hoàn cọc 800.000₫*

Có trừ thì hiện **số tiền thực hoàn + lý do** (`deposit_refund_note`), không chỉ nói qua Zalo.

**Email khi trả đồ** — thêm dòng: đã nhận đồ, đang kiểm, hoàn trong giờ làm.

**Trang chính sách** — mô tả đúng luồng này, bỏ mọi câu "24h".

## 8. Kiểm chứng

- Chuyển trạng thái đúng đường; nhảy sai bị chặn (vd `pending → refunded` bằng đường shipper)
- Vào `refunded` mà thiếu `deposit_refund_amount` → chặn
- Hoàn thiếu: `amount` lưu đúng số, `deposit_total − amount` ra đúng mức trừ
- Nút tắt "kiểm xong hoàn đủ" → `amount = deposit_total` + có stamp `inspected`
- Shipper báo kiểm được; shipper **không** đặt được `refunded`
- Shipper không được gán lượt trả → 404
- Đơn chưa thu cọc **không** lọt vào ô lọc "Đang giữ cọc"
- Đơn cha không hoàn cọc được
- Reset về `pending` xoá sạch mốc + amount
- Dữ liệu cũ `pending`/`refunded` vẫn đúng sau migration
- Timeline khách hiện đúng bước + đúng số tiền

## 9. Thứ tự làm

1. Cột mới + trạng thái + lối vào duy nhất trên model (+ test)
2. Popup + ô lọc "Đang giữ cọc" + nút chốt `return_method` trên admin
3. App shipper: đổi nút báo kiểm, bỏ endpoint hoàn cọc
4. Phần khách: checkout, timeline, email, trang chính sách

## 10. Ngoài phạm vi (cố ý)

| Không làm | Vì sao |
|---|---|
| Biên bản kiểm đồ có ảnh | Chủ shop đang thoả thuận qua Zalo, ảnh gửi Zalo. Xây khi tranh chấp nhiều lên — trạng thái `awaiting_customer` đã chừa chỗ nối. |
| Khách bấm đồng ý mức trừ trên web | Khách gật qua điện thoại rồi; bắt vào web bấm nữa là bước thừa. |
| Cột "nơi kiểm" (tại chỗ / tại kho) | Suy được từ ai stamp `inspected` và lúc nào. |
| Số tài khoản khách | Lấy từ sao kê, đang chạy được. Khách nhờ người khác chuyển hộ là ca hiếm → ghi vào ô ghi chú. |
| Bảng giá đền theo món | Chủ shop chọn thoả thuận từng ca, không chiếu bảng. |
| Phí ship hiển thị ở checkout | Chủ shop chốt: không ra được mức cố định, còn tuỳ địa điểm giao → vẫn báo qua điện thoại. |
| Nhắc tự động qua email khi sắp quá hạn | Chưa yêu cầu. Ô lọc + tô màu đã đủ để không quên. |

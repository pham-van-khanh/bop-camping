# PRD — Bán sản phẩm mới trên BopCamping

> **Artifact:** `prd_product_sales.md` · **Trạng thái:** Draft v1 — chờ chủ shop duyệt · **Ngày:** 2026-08-01
> **Nguồn chân lý kỹ thuật:** [`adr_sales_inventory_and_order_model.md`](./adr_sales_inventory_and_order_model.md) (QĐ-1…QĐ-12).
> PRD này **không được mâu thuẫn** với ADR đó; chỗ nào ADR còn thiếu thì ghi ở mục 9, không tự chốt.
> **Nguồn định hướng nghiệp vụ:** [`pr_faq_product_sales.md`](./pr_faq_product_sales.md)
> **Bước tiếp theo:** Design Spec → `plan_product_sales.md` → Beads issues

---

## 1. Tóm tắt

BopCamping mở mảng **bán đồ camping mới** trên cùng website đang cho thuê. Bốn điều chủ shop đã chốt và
chi phối toàn bộ tài liệu này:

1. **Thuê = đồ CŨ, bán = đồ MỚI** — hai kho tách biệt vật lý.
2. **Đơn thuê và đơn mua tách riêng** — không trộn chung giỏ.
3. **Thanh toán đơn mua: COD hoặc chuyển khoản**, khách chọn.
4. **Chỉ ĐỔI, không hoàn tiền.** Hàng đổi về được làm sạch và chuyển sang kho CHO THUÊ.

Điểm bán hàng cốt lõi: khách **thuê thử** → trả đồ → hệ thống **tự tặng voucher** chỉ dùng được cho
**đơn mua**.

---

## 2. Mục tiêu & phi mục tiêu

### 2.1 Mục tiêu

| # | Mục tiêu | Cách biết là đạt |
|---|---|---|
| G1 | Khách mua được đồ mới trên web, giao toàn quốc | Đặt được đơn mua end-to-end, COD hoặc chuyển khoản |
| G2 | Bắc cầu thuê → mua | Voucher tự sinh khi trả đồ thuê, chỉ dùng cho đơn mua, đo được tỷ lệ dùng |
| G3 | Chủ shop biết mảng bán lãi hay lỗ | Dashboard tách doanh thu thuê / bán + lãi gộp hàng bán |
| G4 | Kho bán có sổ cái lần ra được | Mọi biến động kho bán có một dòng `stock_movements`; `sale_quantity` = `SUM(delta)` |
| G5 | **Không làm hỏng mảng thuê** | 8 test file tồn kho thuê xanh nguyên; `AvailabilityService` không đổi một dòng |

> G5 là tiêu chí **chặn phát hành**. Ba mục tiêu kia không đạt thì lùi ngày ra mắt; G5 không đạt thì
> không được merge.

### 2.2 Phi mục tiêu — KHÔNG làm ở phiên bản này

Ghi rõ để không phình phạm vi. Mọi mục dưới đây nếu ai đó đề xuất giữa chừng thì trả lời là "không, đã
chốt ở PRD".

| Không làm | Vì sao |
|---|---|
| **Trộn giỏ thuê + giỏ mua trong một đơn** | Hai loại đơn khác nhau ở gần như mọi thuộc tính (ngày trả, cọc, đơn vị giao, phạm vi, chính sách hoàn). Chủ shop đã chốt tách riêng. |
| **Bán combo** | Combo hiện là cơ chế của mảng thuê. Bán combo kéo theo phân bổ giá vốn theo món — chưa đáng làm. |
| **Đặt trước hàng chưa về (pre-order)** | Đơn mua giữ chỗ tồn từ `pending` (QĐ-4); pre-order phá vỡ giả định "còn hàng mới cho đặt". |
| **Chuyển kho THUÊ sang sổ cái** | Phải viết lại màn hình tồn kho admin đang chạy ổn + 8 test file đang bảo vệ. Rủi ro không tương xứng (QĐ-3). |
| **Hoàn tiền đơn mua** | Chính sách chỉ-đổi-không-hoàn. Không xây luồng refund, không xây trạng thái `refunded`. |
| **Tự động cộng hàng đổi về vào `product_service_location.quantity`** | `syncStocks()` dùng `sync()` sẽ ghi đè im lặng (QĐ-12). Admin nhập tay, hệ thống chỉ nhắc. |
| **Bán hàng cũ/thanh lý qua kênh này** | Làm mờ thông điệp "đồ bán là đồ mới". |
| **Tích điểm / hạng thành viên cho đơn mua** | Giai đoạn sau. |

---

## 3. Người dùng & tình huống sử dụng

| Vai | Tình huống | Điều họ cần |
|---|---|---|
| **Khách mới, mua thẳng** | Chưa từng thuê, tìm thấy sản phẩm qua tìm kiếm/Google | Thấy giá bán rõ ràng, biết còn hàng không, đặt được ngay, biết phí ship trước khi xác nhận, biết chính sách chỉ-đổi-không-hoàn **trước** khi bấm đặt |
| **Khách đã thuê, quay lại mua** | Đã thuê 2–3 lần, biết mình hợp món nào, vừa nhận voucher sau khi trả đồ | Tìm lại đúng sản phẩm đã thuê, thấy voucher của mình, áp được vào đơn mua, không phải đăng nhập kiểu khác |
| **Admin nhập hàng bán** | Nhận lô hàng mới về kho Vinh hoặc Hà Nội | Ghi số lượng nhập theo **từng cơ sở**, ghi giá vốn, và biết chắc con số đó không bị màn hình khác ghi đè |
| **Admin xử lý đơn mua** | Mỗi sáng mở một hàng đợi việc | Thấy đơn thuê và đơn mua trong **cùng một danh sách**, lọc được theo loại đơn, đẩy trạng thái đúng nhánh của từng loại |
| **Admin xử lý đổi hàng** | Khách gửi trả món cũ, shop gửi món mới | Ghi được hai chiều (bán ra −1, thuê về +1) vào sổ cái, và được **nhắc** rằng còn N món đổi về chưa nhập kho thuê |

---

## 4. User story & tiêu chí nghiệm thu

Mọi AC dưới đây phải viết được thành test. AC nào không kiểm chứng được bằng máy thì ghi rõ `[thủ công]`.

### US-01 — Trang sản phẩm hiển thị đúng vai trò của sản phẩm

*Là khách, tôi muốn nhìn một trang sản phẩm là biết ngay món này thuê được, mua được, hay cả hai.*

- **AC-01** — Sản phẩm `rentable = true` và `sellable = true`: trang chi tiết hiện **cả hai** khối — giá thuê/ngày kèm chọn ngày, và giá bán kèm nút "Thêm vào giỏ mua".
- **AC-02** — Sản phẩm `rentable = true`, `sellable = false`: **không** hiện giá bán, **không** hiện nút thêm giỏ mua ở bất kỳ đâu (trang chi tiết, danh sách, trang chủ).
- **AC-03** — Sản phẩm `rentable = false`, `sellable = true`: **không** hiện giá thuê, **không** hiện date-picker, **không** xuất hiện trong danh sách cho thuê. `price_per_day = 0` không được render ra màn hình dưới dạng "0₫/ngày".
- **AC-04** — Lưu sản phẩm với `sellable = true` mà `sale_price` trống hoặc ≤ 0 → validation lỗi, không lưu.
- **AC-05** — Lưu sản phẩm với `rentable = true` mà `price_per_day` ≤ 0 → validation lỗi, không lưu.
- **AC-06** — Lưu sản phẩm với cả `rentable = false` và `sellable = false` → validation lỗi ("ít nhất một trong hai").
- **AC-07** — Đánh giá (review) của sản phẩm hiển thị **chung** cho cả khối thuê và khối mua; không tách hai danh sách review.

### US-02 — Giỏ mua tách riêng giỏ thuê

*Là khách, tôi muốn giỏ mua và giỏ thuê không lẫn vào nhau.*

- **AC-08** — Giỏ mua lưu ở khoá localStorage `bop_sale_cart_v1`, độc lập hoàn toàn với giỏ thuê. Xoá giỏ mua không ảnh hưởng giỏ thuê và ngược lại.
- **AC-09** — Route giỏ mua (`/gio-hang`) và route giỏ thuê (`/gio-thue`) là hai trang riêng; header hiện hai badge số lượng riêng.
- **AC-10** — Giỏ mua chứa dữ liệu hỏng (không phải mảng, JSON lỗi) → trang **vẫn render bình thường** với giỏ rỗng, không trắng trang. Áp dụng cho **cả hai** giỏ.
  > Phụ thuộc bắt buộc: vá `bopcamping-gccu` trước (QĐ-11).
- **AC-11** — Thêm một sản phẩm vào giỏ mua không tạo dòng nào trong giỏ thuê, kể cả khi sản phẩm đó `rentable = true`.

### US-03 — Đặt đơn mua

*Là khách, tôi muốn đặt đơn mua và nhận hàng toàn quốc.*

- **AC-12** — Đặt đơn mua thành công tạo một bản ghi `orders` với `kind = 'sale'`, `status = 'pending'`, `start_date = NULL`, `end_date = NULL`.
- **AC-13** — Mỗi dòng `order_items` của đơn mua có `unit_price = sale_price` tại thời điểm đặt (snapshot), `days = NULL`, và `subtotal = quantity × unit_price × COALESCE(days, 1)`.
- **AC-14** — Đổi `sale_price` của sản phẩm sau khi đơn đã đặt **không** làm đổi `unit_price` hay tổng tiền của đơn cũ.
- **AC-15** — Đơn mua nhận mã `BOP-XXXXXX` cùng dãy với đơn thuê, không trùng.
- **AC-16** — Đơn mua **không** có tiền cọc: `deposit_total = 0` và màn hình không hiện dòng cọc.
- **AC-17** — Chính sách "chỉ đổi, không hoàn tiền" hiển thị ở bước xác nhận đơn, khách phải nhìn thấy trước khi bấm đặt. `[thủ công + snapshot test]`
- **AC-18** — Đơn mua **không** xuất hiện trong `DeliveryScheduleService` (lịch giao nhận theo `start_date`), và **không** xuất hiện trong app shipper.
- **AC-19** — Timeline tra cứu đơn (`/tra-cuu`) của đơn mua hiện các mốc của nhánh mua (`pending → confirmed → shipping → delivered`), không hiện mốc "Đang thuê"/"Đã trả".

### US-04 — Hết hàng bán thì chặn đặt

*Là khách, tôi không muốn đặt được món đã hết rồi bị gọi điện xin lỗi.*

Công thức chốt tại QĐ-4:

```
Còn bán được (sản phẩm P, cơ sở S)
  = sale_quantity(P, S) − Σ số lượng dòng đơn MUA tại S có status ∈ {pending, confirmed, shipping}
```

- **AC-20** — `sale_quantity = 1`, khách A đặt 1 cái và đơn đang ở `pending` → khách B thấy sản phẩm **hết hàng bán** và không đặt được. Đây là điểm **cố ý lệch** với đơn thuê (đơn thuê `pending` không giữ chỗ) — phải có comment trong code và test khẳng định.
- **AC-21** — Đơn của khách A chuyển sang `cancelled` → khách B lập tức đặt được (tồn được nhả).
- **AC-22** — Đơn của khách A chuyển sang `delivered` → tồn **không** được nhả (hàng đã đi vĩnh viễn), và sổ cái có dòng `pool='sale', delta=-1, reason='sale'`.
- **AC-23** — Số "còn bán được" hiển thị ở trang sản phẩm và số dùng để validate ở bước đặt đơn là **cùng một hàm**; grep codebase không tìm thấy công thức thứ hai.
- **AC-24** — Đặt số lượng vượt "còn bán được" → chặn ở bước đặt đơn với thông báo rõ số còn lại, không tạo đơn.
- **AC-25** — Việc giữ chỗ tồn kho bán **không** đi qua `AvailabilityService`; grep xác nhận `AvailabilityService` không đọc `sale_quantity` và không đọc `orders.kind`.

### US-05 — Chọn COD hoặc chuyển khoản

*Là khách, tôi muốn chọn cách trả tiền phù hợp với mình.*

- **AC-26** — Bước xác nhận đơn mua có hai lựa chọn `payment_method` — `cod` và chuyển khoản — và lựa chọn của khách được lưu đúng vào đơn.
- **AC-27** — Chọn chuyển khoản → sau khi đặt, khách thấy thông tin chuyển khoản của shop; đơn ở `pending` và **chưa** được đẩy sang `shipping` cho tới khi admin đánh dấu đã nhận tiền.
- **AC-28** — Chọn COD → đơn đi tiếp bình thường, không yêu cầu thanh toán trước.
- **AC-29** — **KHÔNG có ngưỡng bắt buộc chuyển khoản** (chốt 2026-08-01). Khách luôn thấy **cả hai** lựa chọn COD và chuyển khoản, ở mọi giá trị đơn. Không thêm cột ngưỡng, không có logic ẩn lựa chọn COD. Test: đơn 50.000đ và đơn 50.000.000đ đều hiện đủ hai lựa chọn.

### US-06 — Admin nhập hàng bán, ghi sổ cái

*Là admin, tôi nhận lô hàng mới và muốn ghi vào hệ thống theo từng cơ sở.*

- **AC-30** — Màn hình nhập hàng cho phép chọn sản phẩm + cơ sở + số lượng + ghi chú; lưu xong tạo một dòng `stock_movements` với `pool='sale'`, `delta = +số lượng`, `reason='import'`, `user_id` = admin thao tác.
- **AC-31** — Sau khi nhập, `product_service_location.sale_quantity` của đúng cơ sở đó tăng đúng bằng số nhập; cơ sở khác **không** đổi.
- **AC-32** — `product_service_location.quantity` (kho THUÊ) **không** đổi một chút nào sau thao tác nhập hàng bán.
- **AC-33** — Nhập số lượng ≤ 0 → validation lỗi.
- **AC-34** — Có lệnh artisan đối soát: chạy lên thì báo mọi cặp (sản phẩm, cơ sở) có `sale_quantity ≠ SUM(delta) WHERE pool='sale'`. Chạy trên dữ liệu đúng thì báo 0 sai lệch.

### US-07 — Admin xem sổ cái kho bán

- **AC-35** — Màn hình sổ cái liệt kê `stock_movements` lọc được theo sản phẩm, cơ sở, `reason`, khoảng ngày; mỗi dòng hiện delta có dấu, lý do, đơn liên quan (link), người thao tác, thời điểm.
- **AC-36** — Dòng sổ cái **không sửa được, không xoá được** qua giao diện. Sai thì ghi một dòng `reason='adjust'` bù trừ.
- **AC-37** — Tổng `SUM(delta)` hiển thị ở cuối bộ lọc khớp đúng với `sale_quantity` khi lọc theo (sản phẩm, cơ sở, `pool='sale'`) không giới hạn ngày.

### US-08 — Admin sửa giá bán / giá vốn

- **AC-38** — Form sản phẩm admin có ô `sale_price` và `cost_price`; `cost_price` **không** xuất hiện ở bất kỳ response nào của phía khách (kiểm bằng test: response trang sản phẩm công khai không chứa khoá `cost_price`).
- **AC-39** — `cost_price` để trống được (nullable), nhưng khi trống thì lãi gộp của sản phẩm đó không tính được — dashboard phải hiện cảnh báo "N sản phẩm chưa có giá vốn" thay vì tính lãi bằng 0.
- **AC-40** — Sửa `sale_price` không ảnh hưởng `unit_price` của các `order_items` đã tạo (xem AC-14).

### US-09 — Vòng đời đơn mua

*Là admin, tôi muốn đẩy đơn mua qua đúng các bước của nó.*

Nhánh trạng thái (QĐ-6):

```
Dùng chung : pending → confirmed → … → cancelled
Chỉ THUÊ   : confirmed → renting → returned
Chỉ MUA    : confirmed → shipping → delivered → [exchanging → exchanged]
```

- **AC-41** — Trên đơn `kind='sale'`, danh sách nút chuyển trạng thái **không** chứa `renting` / `returned`. Trên đơn `kind='rental'`, **không** chứa `shipping` / `delivered` / `exchanging`.
- **AC-42** — Chuyển trạng thái sai nhánh qua HTTP request trực tiếp (bỏ qua UI) → server từ chối, đơn không đổi.
- **AC-43** — Huỷ đơn mua ở `pending` hoặc `confirmed` → `status='cancelled'`, tồn được nhả, sổ cái ghi dòng `reason='cancel'` nếu trước đó đã có dòng trừ.
- **AC-44** — Danh sách đơn admin có **bộ lọc loại đơn** (tất cả / thuê / mua) và nhãn trạng thái hiển thị đúng theo `kind`.
- **AC-45** — Mail báo trạng thái có nhánh riêng cho `shipping` / `delivered`; đơn mua không nhận mail có chữ "trả đồ".
- **AC-46** — Đơn mua **không** có đơn cha/con; `aggregateStatus()` không được gọi trên đơn `kind='sale'`.

### US-10 — Đổi hàng

- **AC-47** — Đơn mua ở `delivered` chuyển sang `exchanging` được; từ `exchanging` chuyển sang `exchanged` được. Không có nhánh nào dẫn về `returned`.
- **AC-48** — Khi đơn chuyển sang `exchanged`, hệ thống ghi **hai** dòng sổ cái: `pool='sale', delta=-1, reason='exchange_out'` và `pool='rental', delta=+1, reason='exchange_in'`.
- **AC-49** — `product_service_location.quantity` (kho THUÊ) **không** tự tăng khi đổi hàng. Admin thấy dải nhắc *"Có N món đổi về chưa nhập kho thuê"*, N tính từ số dòng `reason='exchange_in'` chưa được đánh dấu đã nhập.
- **AC-50** — Sau khi admin nhập tay vào form sản phẩm và đánh dấu đã xử lý, N giảm đúng; dải nhắc biến mất khi N = 0.

### US-11 — Voucher "thuê rồi mua"

- **AC-51** — Đơn thuê chuyển sang `returned` → nếu `PromotionSetting` bật chương trình, hệ thống tạo một voucher với `source='rental_to_sale'`, `applicable_order_kind='sale'`, hạn dùng theo cấu hình.
- **AC-52** — Chương trình tắt → không sinh voucher nào. Bật/tắt không ảnh hưởng voucher đã phát.
- **AC-53** — Một đơn thuê `returned` chỉ sinh **tối đa một** voucher, kể cả khi đơn bị đổi trạng thái qua lại nhiều lần (idempotent).
- **AC-54** — Voucher `applicable_order_kind='sale'` áp vào đơn thuê → **bị từ chối**.
- **AC-55** — **Mọi voucher đang tồn tại trong DB** sau migration có `applicable_order_kind='rental'`; áp bất kỳ voucher cũ nào vào đơn mua đều **bị từ chối**. Đây là test bất biến, không phải test tính năng.
- **AC-56** — Voucher xuất hiện trong mục voucher của tài khoản khách, có ghi rõ "chỉ dùng cho đơn mua" và ngày hết hạn.
- **AC-57** — Voucher hết hạn không áp được; thông báo nói rõ lý do là hết hạn.

### US-12 — Dashboard tách doanh thu

- **AC-58** — Dashboard hiện **hai con số riêng**: doanh thu thuê (đơn `kind='rental'`, `status='returned'`) và doanh thu bán (đơn `kind='sale'`, `status='delivered'`). Không có ô nào gộp hai con số thành một tổng duy nhất mà không ghi chú.
- **AC-59** — Doanh thu thuê sau khi ra mắt tính năng bán **bằng đúng** doanh thu thuê tính theo công thức cũ trên cùng bộ dữ liệu. Đây là test chống hồi quy số tiền.
- **AC-60** — Lãi gộp hàng bán = `Σ (unit_price − cost_price) × quantity` trên các dòng của đơn `kind='sale'`, `status='delivered'`. Sản phẩm thiếu `cost_price` bị loại khỏi phép tính và đếm vào cảnh báo ở AC-39.
- **AC-61** — Trang thống kê (`StatsController`) áp dụng đúng cách tách như dashboard.

### US-13 — Job tự huỷ đơn mua treo

- **AC-62** — Đơn `kind='sale'`, `status='pending'`, tạo cách đây quá `N` giờ (mặc định 48, cấu hình được) → job chuyển sang `cancelled` và nhả tồn.
- **AC-63** — Đơn `kind='sale'` ở `confirmed` hoặc bất kỳ trạng thái nào khác `pending` → job **không** đụng vào.
- **AC-64** — Đơn `kind='rental'` ở `pending` quá N giờ → job **không** đụng vào. Job này chỉ dành cho đơn mua.
- **AC-65** — Mỗi lần huỷ, job ghi log có mã đơn, thời điểm tạo đơn, ngưỡng áp dụng. Chạy job hai lần liên tiếp không tạo hai dòng huỷ cho cùng một đơn.
- **AC-66** — Khách nhận mail báo đơn bị huỷ do quá hạn xác nhận, có hướng dẫn đặt lại.

---

## 5. Bất biến bắt buộc

Đây là các mệnh đề **luôn đúng** sau khi epic hoàn thành. Mỗi mệnh đề phải có ít nhất một test giữ. Vi
phạm bất kỳ mệnh đề nào = chặn merge, không thương lượng.

| # | Bất biến | Cách kiểm |
|---|---|---|
| **INV-01** | `AvailabilityService` **không đổi một dòng nào**. Kho bán không đi qua service này. | `git diff` trên file phải rỗng; grep xác nhận service không đọc `sale_quantity` / `orders.kind` |
| **INV-02** | `products.quantity` **vẫn chỉ là tổng kho THUÊ** = `SUM(product_service_location.quantity)`. Không bao giờ cộng `sale_quantity` vào. | Test: nhập 10 hàng bán → `products.quantity` không đổi |
| **INV-03** | `product_service_location.sale_quantity` **luôn** bằng `SUM(delta) WHERE pool='sale'` cho đúng cặp (sản phẩm, cơ sở). | Test bất biến chạy sau mọi thao tác: nhập, bán, huỷ, đổi. Cộng thêm lệnh artisan đối soát (AC-34) |
| **INV-04** | **8 test file tồn kho thuê xanh nguyên**, không sửa một dòng nào trong các file đó. | Chạy đủ bộ; `git diff` trên 8 file phải rỗng. *(Danh sách 8 file cần ADR chốt đích danh — xem mục 9)* |
| **INV-05** | **Không voucher cũ nào tự dưng dùng được cho đơn mua.** Mọi voucher tồn tại trước migration mang `applicable_order_kind='rental'`. | AC-55; thêm test trên dữ liệu migration thật |
| **INV-06** | Đơn mua **không bao giờ** lọt vào lịch giao nhận, app shipper, hay báo cáo theo khoảng ngày thuê. | AC-18; test truy vấn `DeliveryScheduleService` với dataset lẫn hai loại đơn |
| **INV-07** | `start_date` / `end_date` của đơn mua **luôn NULL**, không nhét ngày giả. | AC-12; constraint ở tầng ứng dụng |
| **INV-08** | Doanh thu thuê tính theo công thức mới **bằng đúng** công thức cũ trên cùng dữ liệu. | AC-59 |
| **INV-09** | Kho bán **giữ chỗ từ `pending`**, kho thuê **không**. Sự lệch này là cố ý. | AC-20 + comment trong code + test có tên nói rõ "cố ý lệch" |
| **INV-10** | Sửa sản phẩm trong admin **không bao giờ** làm mất `sale_quantity` của cơ sở nào. | Xem EC-02 |

> Ghi chú cho người triển khai: INV-09 là chỗ dễ bị "sửa cho nhất quán" nhất. Nếu ai đó thấy hai chỗ
> xử lý `pending` khác nhau và định gộp lại, đọc QĐ-4 trước.

---

## 6. Ca biên & xử lý lỗi

### EC-01 — Hai khách cùng mua cái cuối cùng

**Tình huống:** `sale_quantity = 1`. Khách A và khách B bấm đặt gần như đồng thời. Nếu chỉ đọc tồn rồi
ghi đơn, cả hai đơn đều lọt → bán vượt tồn, đúng cái mà QĐ-4 sinh ra để tránh.

**Xử lý:** phép kiểm "còn bán được" và phép tạo đơn phải nằm trong **cùng một transaction**, có khoá
dòng `product_service_location` của cặp (sản phẩm, cơ sở) trước khi tính. Kiểm lại tồn **bên trong**
transaction, không tin số đã đọc ở màn hình.

- **AC-67** — Test chạy hai lượt đặt song song trên tồn = 1 → đúng **một** đơn thành công, đơn còn lại
  nhận lỗi "vừa hết hàng", `sale_quantity` không âm.
- **AC-68** — `sale_quantity` **không bao giờ** âm ở bất kỳ thời điểm nào (cột UNSIGNED + kiểm ở tầng
  ứng dụng để báo lỗi đẹp thay vì lỗi DB).

### EC-02 — Admin sửa sản phẩm làm mất dòng pivot (cái bẫy nguy hiểm nhất)

**Tình huống:** `syncStocks()` dùng `sync()`. Sản phẩm P đang có hàng bán ở Hà Nội (`sale_quantity = 8`).
Admin mở form sản phẩm để sửa mô tả, bỏ tick cơ sở Hà Nội (vì kho **thuê** ở đó đã hết), bấm lưu.
`sync()` **xoá luôn dòng pivot** → mất trắng `sale_quantity = 8`, và mất im lặng, không ai biết.

Đây chính là mục 4 dòng 15 và QĐ-12 của ADR.

**Xử lý bắt buộc:**
- `syncStocks()` phải **bảo toàn** `sale_quantity` khi ghi lại pivot.
- Dòng pivot có `sale_quantity > 0` **không được xoá** dù cơ sở không được tick cho kho thuê. Trường hợp
  đó ghi `quantity = 0` và giữ nguyên `sale_quantity`.
- Nếu admin thực sự muốn bỏ hàng bán ở cơ sở đó thì phải đi qua màn hình sổ cái (ghi `reason='adjust'`
  hoặc `writeoff`), không phải qua form sản phẩm.

- **AC-69** — Sản phẩm có `quantity=5, sale_quantity=8` tại Hà Nội. Admin lưu sản phẩm mà **không** tick
  Hà Nội → dòng pivot **vẫn tồn tại**, `quantity=0`, `sale_quantity=8` nguyên vẹn.
- **AC-70** — Admin đổi `quantity` kho thuê ở Vinh từ 3 → 7 → `sale_quantity` ở Vinh không đổi.
- **AC-71** — Sau mọi thao tác của AC-69/AC-70, INV-03 vẫn đúng (`sale_quantity` = `SUM(delta)`).

### EC-03 — Phí vận chuyển KHÔNG nằm trong hệ thống

**Chốt 2026-08-01:** chủ shop **tự book vận chuyển ngoài hệ thống**. Web không tính cước, không gọi API
hãng, không lưu mã vận đơn, không cần cân nặng sản phẩm.

**Xử lý:**
- Tổng tiền đơn mua = **tiền hàng**, không có dòng phí vận chuyển.
- Phí ship (nếu thu) shop báo qua điện thoại — đúng cách mảng cho thuê đang làm ở bước
  *"Báo tổng chi phí (gồm phí giao nhận nếu có)"*.
- Muốn ghi lại phí đã thu thì dùng `orders.extra_fee` + `extra_fee_note` **đã có sẵn**, không thêm cột.
- Trạng thái `shipping` vẫn giữ nguyên nghĩa "đang trên đường tới khách" — ai book không quan trọng.

- **AC-72** — Đơn mua tạo xong **không** có trường phí vận chuyển nào; tổng tiền = Σ tiền hàng.
- **AC-73** — Không có lời gọi HTTP ra ngoài nào trong luồng đặt đơn mua (test khẳng định).

### EC-05 — Các ca biên khác cần xử lý

| Ca | Xử lý | AC |
|---|---|---|
| Voucher hết hạn giữa lúc khách đang ở bước xác nhận | Kiểm lại hạn voucher **tại thời điểm tạo đơn**, không tin giá trị đã tính ở màn hình. Hết hạn → báo lỗi và tính lại tổng tiền. | **AC-77** |
| Khách áp voucher rồi bỏ hết sản phẩm khỏi giỏ | Voucher không bị đánh dấu đã dùng khi đơn chưa tạo. | **AC-78** |
| Đổi sang món **rẻ hơn** | **Chưa chốt** (mục 8, câu 1). Bản này **không** cho chọn món đổi có giá thấp hơn qua giao diện; ca đó admin xử lý tay ngoài hệ thống cho tới khi chủ shop quyết. | **AC-79** — giao diện đổi hàng chặn chọn món rẻ hơn, hiện thông báo "liên hệ shop" |
| Job huỷ đơn chạy trùng nhau (hai tiến trình) | Job có khoá chống chạy chồng; huỷ đơn là thao tác idempotent. | **AC-80** |
| `sale_quantity` lệch sổ cái do lỗi nào đó | Lệnh artisan đối soát báo rõ từng cặp lệch và **không tự sửa** — sửa tay có ghi `reason='adjust'`. | AC-34 |
| Migration `orders.status` từ ENUM sang VARCHAR trên dữ liệu thật | Sao lưu trước; migration có bước kiểm số dòng trước/sau; `down()` chạy được. | **AC-81** |

---

## 7. Đo lường

Nguồn: PR-FAQ mục 5. **Mọi con số mục tiêu dưới đây là GIẢ ĐỊNH để thảo luận — chưa có dữ liệu thật,
cần chủ shop xác nhận hoặc thay bằng số của shop.** Phần "cách đo" thì không phải giả định.

### 7.1 Chỉ số chính — bắc cầu thuê → mua

| Chỉ số | Cách đo | Mục tiêu |
|---|---|---|
| Tỷ lệ chuyển đổi thuê → mua | % khách có ≥1 đơn thuê `returned`, sau đó phát sinh đơn mua `delivered` trong 90 ngày | **GIẢ ĐỊNH: 5–10%** trong 3 tháng đầu |
| Tỷ lệ dùng voucher sau thuê | % voucher `source='rental_to_sale'` được dùng trước khi hết hạn | **GIẢ ĐỊNH: 15–25%** |

### 7.2 Chỉ số kinh doanh

| Chỉ số | Cách đo | Mục tiêu |
|---|---|---|
| Doanh thu bán/tháng | `SUM` đơn `kind='sale'`, `status='delivered'` | — |
| Tỷ trọng doanh thu bán | doanh thu bán ÷ tổng doanh thu | **GIẢ ĐỊNH: 10–20%** giai đoạn đầu |
| Giá trị đơn mua trung bình (AOV) | doanh thu bán ÷ số đơn `delivered` | Chưa có mốc |
| Lãi gộp hàng bán | `Σ (unit_price − cost_price) × quantity` | Đo được từ AC-60 |
| **Biên lợi nhuận thực** | lãi gộp − phí vận chuyển − phí hoàn hàng − giá trị voucher đã dùng − chi phí xử lý đổi trả | **Đo tay giai đoạn đầu** — hệ thống chưa ghi đủ chi phí vận hành |

### 7.3 Chỉ số rủi ro (quan trọng ngang chỉ số tăng trưởng)

| Chỉ số | Cách đo | Ngưỡng báo động |
|---|---|---|
| Tỷ lệ đơn COD bị từ chối nhận | đơn COD `cancelled` sau khi đã `shipping` ÷ tổng đơn COD | **GIẢ ĐỊNH: >5%** |
| Tỷ lệ đổi hàng | đơn đạt `exchanged` ÷ đơn `delivered` | **GIẢ ĐỊNH: >10%** |
| Tỷ lệ đơn mua bị job tự huỷ | đơn huỷ bởi job ÷ tổng đơn mua tạo ra | Chưa có mốc — theo dõi từ tháng đầu. Cao bất thường nghĩa là ngưỡng 48h sai hoặc luồng xác nhận chậm |
| Tồn kho bán quá X ngày | từ sổ cái: hàng nhập chưa bán sau X ngày | X **chưa chốt** |
| Số món đổi về chưa nhập kho thuê | đếm dòng `reason='exchange_in'` chưa xử lý | Nên luôn về 0 trong vòng vài ngày |

### 7.4 Chỉ số vận hành

- Thời gian trung bình từ `pending` → `shipping` (đo được từ lịch sử trạng thái).
- Số giờ nhân sự/tuần cho đơn mua và đổi trả — **đo tay**, hệ thống không biết.
- Số lần API cước vận chuyển lỗi/tuần (từ log EC-03) — cao thì phải đổi cách tính cước.

---

## 8. Câu hỏi mở

Chép nguyên mục 7 của ADR. **Không tự trả lời thay chủ shop.** Chưa có câu trả lời thì chưa code phần
liên quan.

1. **Đổi sang món rẻ hơn thì xử lý chênh lệch thế nào?** Không hoàn tiền? Cấp voucher? Chính sách "chỉ
   đổi không trả" chưa nói rõ chiều này.
2. **Thời hạn được đổi là bao lâu** kể từ ngày nhận, và điều kiện gì (còn nguyên seal? đã dùng?).
3. **Đơn mua có được tính cho chương trình giới thiệu không?** Hiện `conversion_trigger_status` chỉ
   nhận 4 trạng thái của đơn thuê.
4. **Voucher thuê→mua**: giảm bao nhiêu, hạn mấy ngày, có yêu cầu giá trị đơn tối thiểu không.
5. **Đơn mua `pending` treo bao lâu thì tự huỷ** (đề xuất 48h).
6. **Khách bùng đơn COD giá trị lớn thì sao?** Có ngưỡng tiền nào bắt buộc chuyển khoản trước không?
   Đây là rủi ro tiền thật lớn nhất của cả tính năng — đơn thuê có tiền cọc làm ràng buộc, đơn mua thì
   không có gì cả.

**Ảnh hưởng tới AC:** câu 1 → AC-79 · câu 4 → AC-51/AC-56 · câu 5 → AC-62 · câu 6 → AC-29.
Bốn AC đó hiện đang triển khai ở dạng **cấu hình được, mặc định an toàn**, chờ số thật.

---

## 9. Ghi chú cho ADR

Những điểm dưới đây là chỗ ADR có vẻ **thiếu hoặc chưa rõ**, phát hiện khi viết PRD. **PRD không tự
sửa ADR** — ghi lại để người viết ADR quyết.

1. **ADR chưa nói THỜI ĐIỂM ghi dòng sổ cái `reason='sale'`.** QĐ-3 liệt kê `reason='sale'` trong danh
   sách giá trị, nhưng không nói dòng đó được ghi khi đơn `pending`, `confirmed`, hay `delivered`.
   Điều này **quan trọng về mặt đúng/sai**: công thức QĐ-4 đã trừ các dòng đơn ở `{pending, confirmed,
   shipping}` khỏi `sale_quantity`. Nếu sổ cái ghi `-1` ngay từ `pending` thì cùng một món bị trừ
   **hai lần**. PRD này giả định (AC-22) sổ cái ghi `-1` khi đơn đạt `delivered` — cần ADR xác nhận.
2. **`adr_sales_shipping_carrier.md` được tham chiếu ở đầu ADR nhưng không tồn tại** trong
   `artifacts/`. Mọi thứ về cước, cân nặng, ai chịu phí hoàn hàng đang không có nguồn chốt (ảnh hưởng
   EC-03, EC-04).
3. **"8 test file tồn kho thuê" chưa được liệt kê đích danh.** Grep `tests/Feature` ra 9 file liên quan
   (`AdminProductStockTest`, `AvailabilityBatchTest`, `AvailabilityBufferTest`, `AvailabilityServiceTest`,
   `CartStockMatchesCheckoutTest`, `ComboAvailabilityTest`, `PerStoreStockTest`, `ProductAvailabilityTest`,
   `ReorderAvailabilityTest`). INV-04 là tiêu chí nghiệm thu nhưng hiện **không kiểm chứng được** vì
   không biết chính xác 8 file nào.
4. **Sổ cái `pool='rental'` là nhật ký hay nguồn chân lý?** QĐ-3 nói kho thuê **không** dùng sổ cái;
   QĐ-12 lại ghi một dòng `pool='rental', reason='exchange_in'`, còn `product_service_location.quantity`
   thì **không** tự cộng. Vậy `SUM(delta) WHERE pool='rental'` sẽ **không** khớp `quantity` — và đó là
   đúng theo thiết kế. Cần ghi rõ trong ADR rằng bất biến "sổ cái = tồn" **chỉ áp dụng cho
   `pool='sale'`", nếu không sẽ có người viết test sai hoặc "sửa cho nhất quán".
5. **Voucher đơn mua và cột `applies_to`.** Bảng `vouchers` có `applies_to ∈ {rental_fee, total}`
   (`2026_06_24_000003`). Với đơn mua thì `rental_fee` vô nghĩa. QĐ-10 thêm `applicable_order_kind`
   nhưng không nói ràng buộc giữa hai cột. Đề nghị ADR chốt: `applicable_order_kind ∈ {sale, both}` ⇒
   `applies_to` bắt buộc `= 'total'`.
6. **Đơn mua được huỷ ở những trạng thái nào?** QĐ-6 vẽ `pending → confirmed → … → cancelled` nhưng
   không nói `shipping` hay `delivered` có huỷ được không. PRD giả định huỷ chỉ ở `pending` /
   `confirmed` (AC-43); ca "khách từ chối nhận hàng COD" đang đo ở mục 7.3 lại là đơn **đã** `shipping`
   bị huỷ — hai chỗ này cần khớp nhau trong ADR.
7. **`payment_method` đã tồn tại trong `orders`** (default `'cod'`, migration `2026_06_21_000004`),
   nhưng `OrderSplitter` hardcode `'cod'` ở 3 chỗ và chưa có luồng chuyển khoản nào chạy thật. Mục 4
   của ADR (danh sách 15 điểm phải rà) **không có dòng nào** về thanh toán. Quan hệ giữa QĐ về COD/
   chuyển khoản và `adr_sepay_qr_payment.md` (Proposed) cần được nói rõ.
8. **Mâu thuẫn giữa hai ADR (không phải lỗi của ADR này).** `adr_sepay_qr_payment.md` viết *"Đơn
   `pending` vẫn tính vào `AvailabilityService`"*. Kiểm code: `Order::activeStatuses()` trả
   `['confirmed','renting']` — tức là **ADR kho/đơn đúng, ADR SePay sai**. Nên sửa ở ADR SePay trước
   khi ai đó dựa vào câu sai đó để thiết kế.

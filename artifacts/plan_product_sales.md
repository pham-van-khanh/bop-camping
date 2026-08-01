# Plan — Tính năng BÁN sản phẩm

- **Ngày:** 2026-08-01
- **Trạng thái:** Draft — chờ chủ shop duyệt artifacts trước khi code
- **Nguồn chân lý:** [`adr_sales_inventory_and_order_model.md`](./adr_sales_inventory_and_order_model.md) ·
  [`prd_product_sales.md`](./prd_product_sales.md) · [`pr_faq_product_sales.md`](./pr_faq_product_sales.md)
- **Vận chuyển:** NGOÀI PHẠM VI (chốt 2026-08-01) — chủ shop tự book hãng.
  [`adr_sales_shipping_carrier.md`](./adr_sales_shipping_carrier.md) chuyển *Deferred*.

---

## 0. Quy mô thật — đọc trước khi bắt đầu

Đây **không phải** một tính năng, mà là **một mô hình kinh doanh thứ hai** chạy song song trong cùng
một hệ thống. Ước lượng thẳng thắn:

| Khối | Số việc | Ước lượng |
| --- | --- | --- |
| P0 Gỡ chặn | 2 | 1–2 ngày |
| P1 Nền dữ liệu | 4 | 5–7 ngày |
| P2 Kho bán (admin) | 2 | 3–4 ngày |
| P3 Mặt tiền khách | 4 | 5–7 ngày |
| P5 Vòng đời đơn mua | 5 | 6–8 ngày |
| P6 Voucher thuê→mua | 2 | 2–3 ngày |
| P7 Đổi hàng | 1 | 2–3 ngày |
| P8 Báo cáo | 1 | 2 ngày |
| P9 QA & ra mắt | 2 | 3–4 ngày |
| **Tổng** | **23** | **≈ 29–40 ngày công** |

Tức khoảng **5–7 tuần** cho một người làm liên tục — giảm từ 6–9 tuần sau khi bỏ vận chuyển.

> **Bỏ vận chuyển đổi được gì:** không còn phụ thuộc bên ngoài nào, không còn API nằm trên đường
> thanh toán của khách, không cần cân nặng/kích thước sản phẩm, và **cả epic không gọi HTTP ra ngoài
> lần nào**. Đây là lần cắt phạm vi hiệu quả nhất của cả kế hoạch: bỏ 4 việc nhưng bỏ được **toàn bộ**
> nhóm rủi ro nặng nhất.

Muốn ngắn hơn nữa thì xem mục 6.

---

## 1. Thứ tự thực hiện

```
P0   T0.1 chủ shop chốt 6 câu ⛔ chặn tất cả
     T0.3 vá bopcamping-gccu  (chặn P3)
       │
P1   T1.1 cột products ──► T1.2 sale_quantity + bẫy sync() ──► T1.3 sổ cái
     T1.4 orders.kind + trạng thái + order_items
       │
       ├──► P2  T2.1 form sản phẩm · T2.2 nhập hàng + sổ cái
       │
       ├──► P3  T3.1 tồn bán ──► T3.2 trang SP
       │        T3.3 giỏ mua ──► T3.4 đặt đơn mua
       │
       ├──► P5  T5.1 trạng thái ──► T5.3 rà FE · T5.4 mail
       │        T5.2 rà backend · T5.5 job tự huỷ
       │
       ├──► P6  T6.1 cờ voucher ──► T6.2 tự sinh khi trả đồ
       │
       └──► P7 đổi hàng ──► P8 báo cáo ──► P9 QA ──► ra mắt
```

**Đường găng:** T0.1 → T1.1 → T1.2 → T1.3 → T3.1 → T3.4 → T9.1.
Không còn phụ thuộc bên ngoài nào sau khi bỏ vận chuyển — tiến độ hoàn toàn nằm trong tay mình.

---

## 2. Chi tiết từng việc

### P0 — Gỡ chặn

#### T0.1 · Chủ shop chốt 6 câu còn treo ⛔ CHẶN TOÀN EPIC
**Năm** câu còn treo ở mục 7 ADR kho (câu 6 về ngưỡng bắt buộc chuyển khoản đã chốt: KHÔNG có ngưỡng,
khách tự chọn COD hay chuyển khoản).

Câu nặng nhất còn lại: **thời hạn và điều kiện được đổi hàng** — nó quyết định vòng đời đơn mua và
việc hàng đổi về có vào được kho thuê hay không.
**Xong khi:** ADR chuyển trạng thái `Accepted`, 5 câu có câu trả lời ghi trong artifact.

#### T0.3 · Vá `bopcamping-gccu` — `getCart()` không kiểm mảng
`resources/js/lib/cart.ts:44` `JSON.parse` xong không kiểm `Array.isArray`, nên một giỏ hỏng làm
`cartCount()` ném lỗi trong `SiteLayout` → **trắng toàn bộ site**, mọi trang.
**Bắt buộc làm trước P3** vì giỏ mua sẽ dùng lại đúng khuôn mẫu này — không vá thì nhân đôi bề mặt lỗi.
**Xong khi:** `getCart()` trả `[]` với JSON hợp lệ nhưng sai hình dạng; có test hồi quy; cân nhắc thêm
error boundary quanh `SiteLayout`.

---

### P1 — Nền dữ liệu

#### T1.1 · Cột mới trên `products`
`rentable`, `sellable` (bool), `sale_price`, `cost_price` (nullable).
**Không** thêm cân nặng/kích thước — chúng chỉ cần cho API cước, mà vận chuyển đã ra ngoài phạm vi.
Validate: `sellable ⇒ sale_price > 0`; `rentable ⇒ price_per_day > 0`; phải bật ít nhất một cờ.
Backfill: toàn bộ sản phẩm cũ `rentable=true, sellable=false` → **hành vi hôm nay không đổi**.
**Xong khi:** test khẳng định sản phẩm cũ vẫn thuê được y như trước; validate chặn được 3 tổ hợp sai.

#### T1.2 · `product_service_location.sale_quantity` + gỡ bẫy `sync()`
Thêm cột. Rồi sửa `syncStocks()` — **đây là bẫy nguy hiểm nhất của epic**: nó dùng `sync()`, nên khi
admin bỏ tick một cơ sở thì dòng pivot bị **xoá**, kéo theo mất luôn `sale_quantity` của cơ sở đó,
và số kho bán biến mất không dấu vết.
**Xong khi:** có test riêng — set kho bán ở Vinh, sửa sản phẩm bỏ tick Vinh rồi tick lại, `sale_quantity`
phải còn nguyên **hoặc** thao tác bị chặn với thông báo rõ. Kiểm bằng mutation.

#### T1.3 · Sổ cái `stock_movements` + `StockLedgerService`
Bảng theo ADR QĐ-3. Service có `record()` chạy trong transaction, cập nhật cache `sale_quantity`
nguyên tử. Thêm lệnh `artisan stock:reconcile` đối soát cache với sổ cái.
**Xong khi:** test bất biến `sale_quantity === SUM(delta WHERE pool='sale')` sau một chuỗi thao tác
ngẫu nhiên (nhập, bán, huỷ, điều chỉnh); lệnh đối soát phát hiện được lệch cố ý gieo vào.

#### T1.4 · `orders.kind`, trạng thái, ngày, `order_items`
- `orders.kind` VARCHAR(8) default `'rental'` — backfill toàn bộ đơn cũ.
- `orders.status`: **ENUM → VARCHAR(20)** + validate tầng ứng dụng.
- `orders.start_date` / `end_date` → **NULLABLE**.
- `order_items`: thêm `unit_price`, backfill `= price_per_day`; `days` → NULLABLE.
⚠️ Migration trên bảng đang có dữ liệu thật. **Sao lưu DB production trước.** Viết `down()` chạy được.
**Xong khi:** toàn bộ 796 test PHP xanh nguyên; `subtotal = quantity × unit_price × COALESCE(days,1)`
đúng cho mọi dòng cũ.
⚠️ Nhớ bỏ hardcode `payment_method => 'cod'` ở `OrderSplitter.php:53,69,85` (đơn mua chọn được COD/CK).

---

### P2 — Kho bán phía admin

#### T2.1 · Form sản phẩm: phần bán
Cờ "Cho thuê" / "Bán", giá bán, giá vốn. Kho bán hiển thị **chỉ đọc** (số thật
đến từ sổ cái), kèm nút "Nhập hàng".
**Xong khi:** tạo được sản phẩm chỉ-bán, chỉ-thuê, và cả hai; giá vốn không lộ ra bất kỳ trang khách nào
(có test khẳng định điều này).

#### T2.2 · Màn nhập hàng + xem sổ cái
Nhập hàng theo cơ sở (ghi `reason='import'`), điều chỉnh tay có bắt buộc ghi lý do, xem lịch sử chuyển
động lọc theo sản phẩm/kho/khoảng ngày.
**Xong khi:** nhập 10 cái → `sale_quantity` = 10 và có đúng 1 dòng sổ cái; điều chỉnh không ghi lý do bị chặn.

---

### P3 — Mặt tiền khách

#### T3.1 · `SaleAvailabilityService` — còn bán được bao nhiêu
```
Còn bán (P, kho S) = sale_quantity(P,S) − Σ số lượng dòng đơn MUA ở S
                     có status ∈ {pending, confirmed, shipping}
```
⚠️ **Cố ý khác đơn thuê**: đơn thuê không giữ chỗ khi `pending`, đơn mua thì có (ADR QĐ-4).
Phải ghi chú trong code và có test nêu rõ lý do, nếu không người sau sẽ "sửa cho nhất quán".
**Xong khi:** test cho tình huống hai khách cùng mua cái cuối — người sau bị chặn.

#### T3.2 · Trang sản phẩm: khối "Mua mới"
Song song khối thuê. Nêu rõ **thuê = đồ đã qua sử dụng, mua = hàng mới**. Nếu khách đã từng thuê món
này thì hiện gợi ý voucher.
**Xong khi:** ba trạng thái (chỉ thuê / chỉ bán / cả hai) hiện đúng; hết hàng bán thì nút mua tắt.

#### T3.3 · Giỏ mua riêng — `lib/saleCart.ts` + `/gio-hang`
Khoá `bop_sale_cart_v1`, độc lập hoàn toàn với giỏ thuê. **Phụ thuộc T0.3.**
Viết `getSaleCart()` có kiểm `Array.isArray` ngay từ đầu.
**Xong khi:** hai giỏ không đụng nhau; giỏ mua hỏng không làm vỡ trang; header hiện hai số riêng.

#### T3.4 · Đặt đơn mua — `SaleOrderController`
Validate, kiểm tồn bán, chọn cơ sở gửi hàng, chọn COD / chuyển khoản, tạo `Order(kind='sale')`.
Dùng lại `AddressPicker` vừa làm xong. Tổng tiền = **tiền hàng**, không có dòng phí vận chuyển.
**Xong khi:** đơn mua lưu đủ mã tỉnh/xã; `deposit_total = 0`; `start_date`/`end_date` NULL;
đặt vượt tồn bị chặn với thông báo tiếng Việt rõ ràng.

---

### ~~P4 — Vận chuyển~~ · BỎ (chốt 2026-08-01)

Chủ shop tự book hãng vận chuyển ngoài hệ thống. Web **không** tính cước, **không** gọi API hãng,
**không** lưu mã vận đơn, **không** cần cân nặng sản phẩm.

Phí ship báo qua điện thoại — đúng cách mảng cho thuê đang làm ở bước *"Báo tổng chi phí (gồm phí giao
nhận nếu có)"*. Muốn ghi lại phí đã thu thì dùng `orders.extra_fee` + `extra_fee_note` **đã có sẵn**,
không thêm cột nào.

Nghiên cứu chọn hãng giữ ở [`adr_sales_shipping_carrier.md`](./adr_sales_shipping_carrier.md) (Deferred)
cho lần sau. Nếu sau này muốn cho khách tra mã vận đơn trên web thì đó là **một cột nullable** — nhỏ,
nhưng cố ý không làm bây giờ.

---

### P5 — Vòng đời đơn mua

#### T5.1 · Trạng thái + màn danh sách đơn
`pending → confirmed → shipping → delivered`, `cancelled`. `NEXT_STATUSES` tách theo `kind`.
Thêm bộ lọc "Đơn thuê / Đơn mua" và nhãn phân biệt rõ trong danh sách.
**Xong khi:** đơn mua không bao giờ hiện nút "Đang thuê"; và ngược lại.

#### T5.2 · Rà 15 điểm bám vào đơn thuê — **phần 1** (backend)
Mục 4 ADR, dòng 1–8: doanh thu, review-invite, referral, lịch giao, app shipper, timeline, gộp trạng thái.
**Xong khi:** đơn mua **không** lọt vào `/admin/lich-giao`, **không** lọt vào app shipper — có test cho từng cái.

#### T5.3 · Rà 15 điểm — **phần 2** (trang khách + FE)
Dòng 9–14: trang tài khoản, tra cứu đơn, mail trạng thái, nhãn FE.
**Xong khi:** `/tra-cuu` hiện đúng timeline đơn mua (không có bước "hoàn cọc").

#### T5.4 · Mail cho đơn mua
Xác nhận đặt, đã gửi hàng, đã giao. Đều `ShouldQueue`. Không có mã vận đơn (ngoài hệ thống).

#### T5.5 · Job tự huỷ đơn mua treo quá hạn
Mặc định 48h, cấu hình được. Ghi log rõ, ghi dòng sổ cái `reason='cancel'` để nhả tồn.
**Xong khi:** test khẳng định chỉ huỷ đơn `pending` quá hạn, tuyệt đối không đụng đơn thuê.

---

### P6 — Voucher "thuê rồi mua"

#### T6.1 · `vouchers.applicable_order_kind` + lọc trong `VoucherService`
Default `'rental'` để **voucher cũ giữ nguyên hành vi**.
**Xong khi:** test khẳng định voucher hiện có **không** dùng được cho đơn mua.

#### T6.2 · Tự sinh voucher khi trả đồ thuê
Bám móc `OrderObserver::updated` (chỗ `ReviewInviteMail` đang dùng). Cấu hình trong `PromotionSetting`.
**Xong khi:** đơn thuê `returned` → sinh đúng 1 voucher; chạy lại observer không sinh trùng.

---

### P7 — Đổi hàng

#### T7.1 · Luồng đổi + hai dòng sổ cái + dải nhắc
`delivered → exchanging → exchanged`. Ghi `exchange_out` (kho bán −1) và `exchange_in` (kho thuê +1).
**Không** tự ghi vào `product_service_location.quantity` — lý do ở ADR QĐ-12. Thay vào đó hiện dải nhắc
*"Có N món đổi về chưa nhập kho thuê"*.
**Xong khi:** đổi hàng xong sổ cái có đúng 2 dòng; kho thuê **chưa** đổi; dải nhắc hiện đúng số.

---

### P8 — Báo cáo

#### T8.1 · Dashboard tách doanh thu + lãi gộp
Hai con số riêng, không gộp. Thêm lãi gộp hàng bán `Σ (giá bán − giá vốn) × số lượng`.
Lý do không gộp: xem ADR QĐ-9 — gộp lại sẽ dẫn tới quyết định kinh doanh sai.
**Xong khi:** doanh thu thuê giữ **nguyên con số cũ** (test hồi quy trên dữ liệu cũ).

---

### P9 — QA & ra mắt

#### T9.1 · QA đầu-cuối + quality gates + checklist staging
Chạy đủ: `php artisan test` · `npm test` · `npx tsc --noEmit` · `./vendor/bin/pint --test` · `npm run build`.
Kiểm trên trình duyệt thật cả hai luồng. Viết checklist cho chủ shop tự nghiệm thu trên staging.

#### T9.2 · Ra mắt
`feature/product-sales` → `develop` (staging) → chủ shop nghiệm thu → `feat/scaffold-laravel` (production).
⚠️ Sao lưu DB production trước khi chạy migration T1.4.

---

## 3. Tiêu chí nghiệm thu toàn epic

1. **Mảng cho thuê không suy suyển.** 796 test PHP + 108 test JS xanh nguyên. **11 test file** đụng tới
   `AvailabilityService` không sửa một dòng.
2. `AvailabilityService` **không thay đổi**.
3. Doanh thu thuê trên dashboard **bằng đúng** con số trước khi làm epic.
4. `sale_quantity` luôn khớp sổ cái — có lệnh đối soát chứng minh.
5. Đơn mua **không** xuất hiện ở lịch giao nhận và app shipper.
6. Voucher hiện có **không** dùng được cho đơn mua.

## 4. Rủi ro lớn nhất

| Rủi ro | Vì sao đáng lo | Giảm thiểu |
| --- | --- | --- |
| Sót một trong 15 điểm ở mục 4 ADR | Sai **âm thầm**: số doanh thu lệch, đơn mua lọt vào lịch shipper | T5.2/T5.3 làm theo danh sách, mỗi dòng một test |
| Bẫy `sync()` xoá `sale_quantity` | Mất số kho không dấu vết | T1.2 có test riêng + mutation |
| Migration T1.4 trên dữ liệu thật | Đổi kiểu cột `status`, cho ngày NULL | Sao lưu trước, `down()` chạy được, thử trên bản sao prod |
| Khách bùng đơn COD giá trị lớn | Mất cước hai chiều, mất hàng | **Chấp nhận như rủi ro kinh doanh** — chủ shop chốt không ép trả trước. Chốt bằng vận hành: bước gọi điện xác nhận trước khi giao (đã có trong luồng) |
| Vốn chết trong kho hàng mới | Tiền thật nằm im | Ngoài phạm vi kỹ thuật; sổ cái + lãi gộp cho chủ shop nhìn thấy sớm |

## 5. Việc KHÔNG làm trong epic này

Trộn giỏ thuê + mua · bán combo · pre-order · hoàn tiền · chuyển kho thuê sang sổ cái ·
**mọi thứ liên quan vận chuyển** (tính cước, API hãng, mã vận đơn, cân nặng sản phẩm, đối soát COD).

---

## 6. Nếu 5–7 tuần vẫn dài — cắt tiếp được gì

Bỏ vận chuyển đã là lần cắt hiệu quả nhất (bỏ 4 việc, bỏ **toàn bộ** nhóm rủi ro nặng nhất, mà vẫn bán
được toàn quốc). Muốn ngắn hơn nữa thì hai lựa chọn còn lại:

| Cắt thêm | Tiết kiệm | Đổi lại |
| --- | --- | --- |
| **P7 đổi hàng** (T7.1) | 2–3 ngày | Vài tháng đầu số ca đổi đếm trên đầu ngón tay; admin xử lý tay ngoài hệ thống. **Khuyến nghị cắt.** |
| **P6 voucher thuê→mua** (T6.1, T6.2) | 2–3 ngày | Mất đòn bẩy "thuê thử rồi mua" — chính là điểm khác biệt của shop. **Không khuyến nghị cắt.** |

Cắt P7: còn **22 việc, ≈ 4,5–6 tuần**.

Dưới mức đó thì bắt đầu cắt vào phần lõi (sổ cái kho, rà 16 điểm đơn thuê) — chỗ đó cắt là mua nợ kỹ
thuật thật, không phải tiết kiệm.

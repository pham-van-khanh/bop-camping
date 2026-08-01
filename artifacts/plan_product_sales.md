# Plan — Tính năng BÁN sản phẩm

- **Ngày:** 2026-08-01
- **Trạng thái:** Draft — chờ chủ shop duyệt artifacts trước khi code
- **Nguồn chân lý:** [`adr_sales_inventory_and_order_model.md`](./adr_sales_inventory_and_order_model.md) ·
  [`adr_sales_shipping_carrier.md`](./adr_sales_shipping_carrier.md) · [`prd_product_sales.md`](./prd_product_sales.md) ·
  [`pr_faq_product_sales.md`](./pr_faq_product_sales.md)

---

## 0. Quy mô thật — đọc trước khi bắt đầu

Đây **không phải** một tính năng, mà là **một mô hình kinh doanh thứ hai** chạy song song trong cùng
một hệ thống. Ước lượng thẳng thắn:

| Khối | Số việc | Ước lượng |
| --- | --- | --- |
| P0 Gỡ chặn | 3 | 2–3 ngày (phần lớn là chờ bên ngoài) |
| P1 Nền dữ liệu | 4 | 5–7 ngày |
| P2 Kho bán (admin) | 2 | 3–4 ngày |
| P3 Mặt tiền khách | 4 | 6–8 ngày |
| P4 Vận chuyển | 3 | 6–9 ngày ⚠️ phụ thuộc bên ngoài |
| P5 Vòng đời đơn mua | 5 | 7–9 ngày |
| P6 Voucher thuê→mua | 2 | 2–3 ngày |
| P7 Đổi hàng | 1 | 2–3 ngày |
| P8 Báo cáo | 1 | 2 ngày |
| P9 QA & ra mắt | 2 | 3–4 ngày |
| **Tổng** | **27** | **≈ 38–52 ngày công** |

Tức khoảng **6–9 tuần** cho một người làm liên tục. Nếu con số này quá lớn so với mong muốn, xem
mục 6 — có một lát cắt nhỏ hơn nhiều mà vẫn bán được hàng.

---

## 1. Thứ tự thực hiện

```
P0 (gỡ chặn)  ──────────────────────────────────────────┐
   T0.1 chủ shop chốt 6 câu ─┐                          │
   T0.2 mở tài khoản hãng VC ─┼──────────────┐          │
   T0.3 vá bopcamping-gccu ───┘              │          │
                              │              │          │
P1 nền dữ liệu ◄──────────────┘              │          │
   T1.1 cột products                          │          │
   T1.2 sale_quantity + bẫy sync()            │          │
   T1.3 sổ cái stock_movements                │          │
   T1.4 orders.kind + trạng thái + order_items│          │
        │                                     │          │
        ├──► P2 kho bán admin (T2.1, T2.2)    │          │
        │                                     │          │
        ├──► P3 mặt tiền khách ◄──────────────┼──────────┘
        │      T3.1 tồn bán  → T3.2 trang SP
        │      T3.3 giỏ mua  → T3.4 checkout
        │                                     │
        ├──► P4 vận chuyển ◄──────────────────┘
        │      T4.1 bảng vùng (fallback) → T4.2 API cước → T4.3 vận đơn
        │
        ├──► P5 vòng đời đơn mua (T5.1 … T5.5)
        │
        ├──► P6 voucher thuê→mua (T6.1, T6.2)
        │
        └──► P7 đổi hàng (T7.1)  ──► P8 báo cáo (T8.1) ──► P9 QA (T9.1, T9.2)
```

**Đường găng:** T0.2 → T4.2. Nếu tài khoản hãng vận chuyển chậm, làm T4.1 (bảng phí theo vùng) trước
và ra mắt bằng nó — vì nó vốn đã là fallback bắt buộc, không phải việc thừa.

---

## 2. Chi tiết từng việc

### P0 — Gỡ chặn

#### T0.1 · Chủ shop chốt 6 câu còn treo ⛔ CHẶN TOÀN EPIC
Sáu câu ở mục 7 của ADR kho. Câu nặng nhất: **khách bùng đơn COD giá trị lớn thì sao** — đơn thuê có
tiền cọc làm ràng buộc, đơn mua không có gì cả, và hàng gửi đi toàn quốc thì mất cả cước hai chiều.
Không trả lời câu này thì không thiết kế được ngưỡng bắt buộc chuyển khoản.
**Xong khi:** ADR chuyển trạng thái `Accepted`, 6 câu có câu trả lời ghi trong artifact.

#### T0.2 · Chọn hãng vận chuyển, mở tài khoản, lấy API key sandbox ⛔ CHẶN P4
Việc bên ngoài, không code được thay. **Xong khi:** có key sandbox trong `.env.example` dạng biến rỗng
(KHÔNG commit key thật), gọi thử được một lệnh báo cước.

#### T0.3 · Vá `bopcamping-gccu` — `getCart()` không kiểm mảng
`resources/js/lib/cart.ts:44` `JSON.parse` xong không kiểm `Array.isArray`, nên một giỏ hỏng làm
`cartCount()` ném lỗi trong `SiteLayout` → **trắng toàn bộ site**, mọi trang.
**Bắt buộc làm trước P3** vì giỏ mua sẽ dùng lại đúng khuôn mẫu này — không vá thì nhân đôi bề mặt lỗi.
**Xong khi:** `getCart()` trả `[]` với JSON hợp lệ nhưng sai hình dạng; có test hồi quy; cân nhắc thêm
error boundary quanh `SiteLayout`.

---

### P1 — Nền dữ liệu

#### T1.1 · Cột mới trên `products`
`rentable`, `sellable` (bool), `sale_price`, `cost_price` (nullable), `weight_grams`,
`length_cm`/`width_cm`/`height_cm` (nullable — cần cho API cước, xem ADR vận chuyển).
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
Cờ "Cho thuê" / "Bán", giá bán, giá vốn, cân nặng + kích thước. Kho bán hiển thị **chỉ đọc** (số thật
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
Validate, kiểm tồn bán, chọn cơ sở gửi hàng, chọn COD / chuyển khoản, tạo `Order(kind='sale')`
+ dòng sổ cái `reason='sale'`. Dùng lại `AddressPicker` vừa làm xong.
**Xong khi:** đơn mua lưu đủ mã tỉnh/xã; `deposit_total = 0`; `start_date`/`end_date` NULL;
đặt vượt tồn bị chặn với thông báo tiếng Việt rõ ràng.

---

### P4 — Vận chuyển (chi tiết ở ADR riêng)

#### T4.1 · Bảng phí ship theo vùng — **fallback bắt buộc**
Dùng `province_code` đã có sẵn trên đơn. Làm **trước** T4.2, vì API hãng nằm trên đường thanh toán của
khách: hãng chết mà không có đường lui thì khách không đặt được hàng.

#### T4.2 · `ShippingQuoteService` — gọi API hãng
Timeout ngắn, cache báo giá, tự rơi về T4.1 khi lỗi. **Phụ thuộc T0.2.**
**Xong khi:** test giả lập API chậm/lỗi/trả rác — cả ba đều rơi về bảng vùng, không ca nào chặn checkout.

#### T4.3 · Mã vận đơn + trạng thái vận chuyển
Giai đoạn 1: admin nhập mã tay (xem lập luận trong ADR vận chuyển).

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
Xác nhận đặt, đã gửi hàng (kèm mã vận đơn), đã giao. Đều `ShouldQueue`.

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
| API hãng vận chuyển chết lúc khách đặt | Khách không mua được | T4.1 làm trước T4.2, fallback bắt buộc |
| Khách bùng đơn COD giá trị lớn | Mất cước hai chiều, mất hàng | **Chưa có lời giải** — chờ T0.1 |
| Vốn chết trong kho hàng mới | Tiền thật nằm im | Ngoài phạm vi kỹ thuật; sổ cái + lãi gộp cho chủ shop nhìn thấy sớm |

## 5. Việc KHÔNG làm trong epic này

Trộn giỏ thuê + mua · bán combo · pre-order · hoàn tiền · chuyển kho thuê sang sổ cái ·
tự tạo vận đơn qua API (giai đoạn 1 nhập tay) · đối soát COD tự động với hãng vận chuyển.

---

## 6. Nếu 6–9 tuần là quá dài — lát cắt nhỏ nhất bán được hàng

Bỏ P4 (vận chuyển qua hãng) và P7 (đổi hàng) ở vòng đầu, **chỉ bán cho khách ở Vinh và Hà Nội, giao
bằng shipper của shop** như đơn thuê đang làm.

Còn lại: P0 + P1 + P2 + P3 + P5 (rút gọn) + P6 + P8 ≈ **17 việc, 3–4 tuần**.

Đổi lại: chưa bán được ra ngoài hai thành phố. Nhưng bán được thật, thu tiền thật, và **biết khách có
mua hay không trước khi bỏ 6–9 tuần**. Phần vận chuyển toàn quốc luôn cắm thêm được sau vì mô hình dữ
liệu ở P1 đã tính sẵn chỗ cho nó.

Đây là lát cắt tôi khuyến nghị.

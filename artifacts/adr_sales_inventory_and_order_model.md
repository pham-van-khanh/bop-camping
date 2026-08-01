# ADR — Mô hình kho & mô hình đơn cho tính năng BÁN sản phẩm

- **Trạng thái:** Proposed — chờ chủ shop duyệt
- **Ngày:** 2026-08-01
- **Liên quan:** `app/Services/AvailabilityService.php`, `app/Http/Controllers/Admin/ProductController.php`,
  `app/Models/Order.php`, `app/Models/Product.php`, `products`, `product_service_location`, `orders`, `order_items`, `vouchers`
- **Vận chuyển:** NGOÀI PHẠM VI — chủ shop tự book (chốt 2026-08-01). [`adr_sales_shipping_carrier.md`](./adr_sales_shipping_carrier.md) chuyển sang *Deferred*, giữ lại cho sau.
- **Kế thừa:** [`adr_pricing_models.md`](./adr_pricing_models.md), [`adr_parent_child_orders.md`](./adr_parent_child_orders.md), [`design_spec_per_store_stock.md`](./design_spec_per_store_stock.md)

---

## 1. Bối cảnh

BopCamping đang là web **cho thuê** đồ camping theo ngày. Chủ shop mở rộng sang **bán** sản phẩm.

Bốn điều chủ shop đã chốt, và chúng thay đổi hoàn toàn mức độ khó:

1. **Cho thuê dùng đồ CŨ, bán dùng đồ MỚI.** Hai kho tách biệt về mặt vật lý.
2. **Đơn thuê và đơn mua tách riêng** — khách không trộn chung một giỏ.
3. **Thanh toán: COD hoặc chuyển khoản** (khách chọn).
3b. **Vận chuyển nằm NGOÀI hệ thống** — chủ shop tự book hãng, web không tính cước, không lưu mã vận đơn.
4. **Chỉ cho ĐỔI, không cho TRẢ tiền. Hàng đổi về sẽ được làm sạch và chuyển sang kho CHO THUÊ.**

Cộng thêm một điểm bán hàng: khách **thuê thử** để trải nghiệm, thích thì **mua mới**; sau khi trả đồ
thuê hệ thống **tự tặng voucher** giảm giá cho đơn mua.

### 1.1 Vì sao điều số 1 là tin cực tốt

Nỗi lo lớn nhất khi thêm tính năng bán vào một hệ thống cho thuê là **kho dùng chung**: bán một cái lều
hôm nay có thể làm vỡ một đơn thuê đã nhận cho tuần sau. Muốn an toàn phải so tồn với **đỉnh cam kết
thuê ở mọi ngày tương lai** — đắt và dễ sai.

Vì chủ shop tách "thuê = đồ cũ / bán = đồ mới", **rủi ro đó biến mất**. `AvailabilityService` —
nguồn chân lý duy nhất cho tồn kho thuê, đang được 11 test file bảo vệ — **gần như không phải sửa**.

Nhưng điều số 4 mở lại một đường nối **một chiều**: hàng đổi về đi từ kho bán **sang** kho thuê.
Chiều này an toàn (chỉ làm tăng tồn thuê, không bao giờ phá đơn đã nhận), nhưng vẫn phải kiểm soát.

### 1.2 Sự thật đã kiểm chứng từ code (không phải phỏng đoán)

| Điều | Sự thật | Nguồn |
| --- | --- | --- |
| Trạng thái giữ chỗ tồn kho | `['confirmed', 'renting']` — **đơn `pending` KHÔNG giữ chỗ** | `app/Models/Order.php:452` |
| Admin lưu sản phẩm | `syncStocks()` dùng `sync()` → **ghi đè** tồn **và xoá** dòng kho không được tick | `Admin/ProductController.php` |
| `products.quantity` | = `SUM(product_service_location.quantity)`, do `syncStocks()` tính lại | như trên |
| Có sổ cái kho không | **Không.** Không có bảng lịch sử, không có chỗ nào trừ tồn vĩnh viễn | quét toàn repo |
| `orders.status` | khai báo `enum(5 giá trị)` trong migration → MySQL prod có ràng buộc thật | `2026_06_21_000004` |
| `orders.start_date` / `end_date` | **NOT NULL** | như trên |
| Chỗ bám vào `'returned'` | **13 nơi** trong `app/` (doanh thu, review-invite, referral, timeline, shipper) | grep |
| Doanh thu | `SUM(total_price − discount_total)` WHERE `status='returned'` AND `is_parent=false` | `Admin/DashboardController.php:24` |
| Voucher | `applies_to` ∈ `{rental_fee, total}`, `source` ∈ 4 giá trị, `applicable_to_combos` bool | `2026_06_24_000003` |

---

## 2. Quyết định

### QĐ-1 — Một bản ghi sản phẩm, hai kho

Không tách "sản phẩm cho thuê" và "sản phẩm bán" thành hai bản ghi. **Một `products` = một model đồ**,
mang cả hai vai.

```
products
  + rentable      BOOLEAN  default true    -- có cho thuê không
  + sellable      BOOLEAN  default false   -- có bán không
  + sale_price    DECIMAL(12,0) nullable   -- giá bán (bắt buộc khi sellable)
  + cost_price    DECIMAL(12,0) nullable   -- giá vốn, chỉ admin thấy (xem QĐ-9)
```

**Lý do:** cả tính năng "thuê thử rồi mua" phụ thuộc vào việc **cùng một trang sản phẩm** cho cả hai
đường. Ảnh, thông số, hướng dẫn dựng, và **đặc biệt là đánh giá** đều dùng chung. Tách hai bản ghi thì
đánh giá của người thuê không đỡ được cho người mua — mà đó chính là đòn bẩy bán hàng mạnh nhất ở đây.

`price_per_day` giữ nguyên NOT NULL; sản phẩm chỉ-bán để `price_per_day = 0` và `rentable = false`.
Validate: `sellable = true` ⇒ `sale_price > 0`; `rentable = true` ⇒ `price_per_day > 0`;
ít nhất một trong hai cờ phải bật.

### QĐ-2 — Kho bán nằm trên pivot theo cơ sở, KHÔNG dùng chung cột với kho thuê

```
product_service_location
    quantity        -- GIỮ NGUYÊN: kho CHO THUÊ (đồ cũ) tại cơ sở này
    buffer_days     -- giữ nguyên
  + sale_quantity   UNSIGNED INT default 0   -- kho BÁN (đồ mới) tại cơ sở này
```

Hai cột riêng, **không bao giờ cộng vào nhau**. `products.quantity` vẫn chỉ là tổng kho thuê — không
đổi ý nghĩa, để 11 test file và mọi màn hình hiện có không bị lệch.

Đặt kho bán theo **cơ sở** (chứ không phải một kho toàn cục) vì shop có hàng ở cả Vinh và Hà Nội; gửi
từ kho gần khách hơn thì cước rẻ hơn thật. Đây cũng là hướng nhất quán với kiến trúc per-store đã dựng.

### QĐ-3 — Sổ cái kho cho phần BÁN; kho THUÊ giữ nguyên cách cũ

```
stock_movements
  id, product_id, service_location_id
  pool        ENUM('sale','rental')
  delta       INTEGER            -- có dấu: +5 nhập hàng, -1 bán
  reason      VARCHAR(24)        -- import | sale | cancel | exchange_out | exchange_in | adjust | writeoff
  order_id    BIGINT nullable    -- đơn gây ra chuyển động
  user_id     BIGINT nullable    -- ai thao tác
  note        VARCHAR nullable
  created_at
  INDEX (product_id, service_location_id, pool)
```

`product_service_location.sale_quantity` là **giá trị cache**, luôn tính lại được bằng
`SUM(delta) WHERE pool='sale'`. Có một test bất biến khẳng định điều đó — đây là lưới an toàn quan
trọng nhất của cả epic.

> ⚠️ **Bất biến này CHỈ áp cho `pool='sale'`.** Các dòng `pool='rental'` (sinh ra khi đổi hàng — QĐ-12)
> là **vết ghi để đối chiếu, KHÔNG phải nguồn chân lý**: chúng cố ý không cộng vào
> `product_service_location.quantity`. Vì vậy `SUM(delta) WHERE pool='rental'` **sẽ không** khớp tồn
> kho thuê, và đó là đúng ý đồ. Test đối soát phải lọc `pool='sale'`, nếu không sẽ báo lệch giả.

**Vì sao phải có sổ cái cho phần bán, trong khi phần thuê không cần:**

Cho thuê là **mượn rồi trả** — tồn kho quay về đúng chỗ cũ, số hiện tại là đủ. Bán là **mất vĩnh viễn**
và tốn tiền vốn thật. Không có sổ cái thì chủ shop không bao giờ trả lời được "tháng này nhập bao
nhiêu, bán bao nhiêu, sao kho lệch 2 cái" — và **không có cách nào lần ra** khi số bị sai.

**Vì sao KHÔNG chuyển luôn kho thuê sang sổ cái ở giai đoạn này:** phải viết lại màn hình sản phẩm
admin đang chạy ổn, và 11 test file tồn kho đang bảo vệ hành vi hiện tại. Rủi ro không tương xứng.
Ghi nhận là việc nên làm sau (mục 6).

### QĐ-4 — Đơn mua GIỮ CHỖ tồn kho ngay từ `pending` (khác đơn thuê — cố ý)

Đơn thuê hiện **không** giữ chỗ khi `pending` (`activeStatuses() = ['confirmed','renting']`). Với thuê
thì chấp nhận được: shop gọi điện xác nhận trong ngày, và nếu trùng thì đổi ngày được.

Với **bán thì không**: hàng gửi đi toàn quốc, hết là hết. Bán vượt tồn nghĩa là phải gọi điện xin lỗi
và huỷ đơn — trải nghiệm tệ hơn hẳn.

```
Còn bán được (P, kho S) = sale_quantity(P,S) − Σ số lượng dòng đơn MUA ở S
                          có status ∈ {pending, confirmed, shipping}
```

Kèm **van an toàn bắt buộc**: một job theo lịch tự huỷ đơn mua để `pending` quá `N` giờ
(mặc định 48h, cấu hình được) và nhả tồn. Không có van này thì đơn ma sẽ khoá kho dần.

**Thời điểm ghi sổ cái — điểm dễ sai nhất, phải làm đúng:**

| Mốc | Sổ cái | Phần "giữ chỗ" ở công thức trên |
| --- | --- | --- |
| Khách đặt (`pending`) | **KHÔNG ghi gì** | đơn được tính vào giữ chỗ |
| `confirmed`, `shipping` | **KHÔNG ghi gì** | vẫn tính vào giữ chỗ |
| `delivered` | ghi `-N`, `reason='sale'` | đơn **thôi** tính vào giữ chỗ |
| `cancelled` (từ bất kỳ đâu trước `delivered`) | **KHÔNG ghi gì** | đơn thôi tính vào giữ chỗ |

Lý do: `sale_quantity` là **hàng đang nằm trong kho thật**. Chừng nào hàng chưa rời kho thì tồn chưa
giảm — nó chỉ đang *bị giữ chỗ*. Nếu ghi sổ cái ngay lúc `pending` thì **cùng một món bị trừ hai lần**:
một lần trong `sale_quantity` và một lần nữa trong tổng giữ chỗ. Phải có test riêng cho đúng ca này.

> Đây là điểm **cố ý lệch** với đơn thuê. Phải ghi rõ trong code và có test, nếu không người sau sẽ
> "sửa cho nhất quán" và tạo ra lỗi bán vượt tồn.

### QĐ-5 — Dùng chung bảng `orders`, phân biệt bằng cột `kind`

```
orders
  + kind  VARCHAR(8) default 'rental'   -- 'rental' | 'sale'
```

**Đã cân nhắc bảng riêng `sale_orders`** và bác bỏ. So sánh:

| Tiêu chí | Chung bảng `orders` | Bảng `sale_orders` riêng |
| --- | --- | --- |
| Màn hình đơn admin | Một hàng đợi việc duy nhất ✅ | Nhân đôi màn hình, nhân viên phải nhớ vào đâu ❌ |
| Tra cứu đơn `/tra-cuu` | Dùng lại nguyên ✅ | Viết lại ❌ |
| Trang tài khoản khách | Một danh sách ✅ | Hai danh sách ❌ |
| Mã đơn `BOP-XXXXXX` | Một dãy, không trùng ✅ | Phải nghĩ cách tránh trùng ❌ |
| Mail, địa chỉ, khách hàng | Dùng lại ✅ | Nhân đôi ❌ |
| Enum trạng thái | Phải mở rộng ❌ | Sạch ✅ |
| Rủi ro lẫn dữ liệu | Phải lọc `kind` ở mọi truy vấn ❌ | Không có ✅ |

Chốt **chung bảng**, vì thực tế vận hành quan trọng hơn độ sạch của schema: shop nhỏ, một người trực,
họ muốn **một danh sách việc phải làm hôm nay**, không phải hai.

Cái giá phải trả là **13 chỗ đang bám vào `'returned'`** và mọi truy vấn đơn đều phải lọc `kind`.
Danh sách đầy đủ nằm ở mục 4 — đây là phần dễ sót nhất của cả epic.

### QĐ-6 — Trạng thái: đổi cột sang VARCHAR, tách nhánh theo `kind`

```
Dùng chung : pending → confirmed → … → cancelled
Chỉ THUÊ   : confirmed → renting → returned
Chỉ MUA    : confirmed → shipping → delivered → [exchanging → exchanged]
```

Đổi `orders.status` từ `ENUM` sang `VARCHAR(20)` + validate ở tầng ứng dụng — giống cách
`payment_status` đang làm. Lý do: mỗi lần thêm trạng thái mà phải `ALTER TABLE` enum trên MySQL là một
lần khoá bảng và một migration dễ hỏng; đây sẽ không phải lần cuối.

`NEXT_STATUSES` phía React phải tách theo `kind`, nếu không admin sẽ thấy nút "Đang thuê" trên đơn mua.

**Huỷ đơn mua — phải nói rõ vì đây chính là ca khách bùng COD:**

- Huỷ được từ `pending`, `confirmed`, **và `shipping`** (khách từ chối nhận hàng lúc shipper tới —
  ca này xảy ra thật và là rủi ro tiền lớn nhất của tính năng).
- Huỷ từ `shipping`: tồn được nhả lại, nhưng hàng đang trên đường về, chưa nằm trong kho. Đánh dấu
  `reason='cancel'` và để admin xác nhận khi hàng về thật.
- **Không** huỷ được từ `delivered` — đã giao rồi thì chỉ còn đường ĐỔI (QĐ-12), theo đúng chính sách
  "chỉ đổi không trả" chủ shop đã chốt.

### QĐ-7 — `start_date` / `end_date` cho NULLABLE, không nhét ngày giả

Đơn mua **không có kỳ thuê**. Hai lựa chọn:

- **Nhét `start = end = ngày đặt`**: không phải sửa schema, nhưng đơn mua sẽ **lọt vào lịch giao nhận**,
  vào các truy vấn theo khoảng ngày, và vào báo cáo — sai âm thầm, rất khó phát hiện.
- **Cho NULL**: phải rà mọi chỗ đọc `start_date`, nhưng chỗ nào quên sẽ **nổ ngay và to** thay vì trả
  số sai.

Chốt **NULLABLE**. Nguyên tắc: thà vỡ ồn ào còn hơn sai im lặng — nhất là với dữ liệu tiền bạc.

### QĐ-8 — `order_items`: thêm `unit_price`, `days` cho NULL

```
order_items
  + unit_price  DECIMAL(12,0)   -- thuê: = price_per_day · mua: = sale_price
    days        → cho NULLABLE  -- mua: NULL
    price_per_day → giữ nguyên cho dòng thuê
```

Backfill `unit_price = price_per_day` cho toàn bộ dòng cũ. Sau đó
`subtotal = quantity × unit_price × COALESCE(days, 1)` đúng cho **cả hai** loại.

Đã cân nhắc cách rẻ hơn — dòng mua để `days = 1`, `price_per_day = giá bán` — và bác bỏ: mọi báo cáo
sau này cộng `price_per_day` sẽ trộn giá/ngày với giá bán đứt mà **không có gì báo là sai**.

### QĐ-9 — Doanh thu tách hai dòng, có giá vốn

- Đơn **thuê** ghi doanh thu khi `returned` (giữ nguyên hôm nay).
- Đơn **mua** ghi doanh thu khi `delivered`.
- Dashboard phải hiện **hai con số riêng**, không gộp.

Gộp một số là sai lệch nghiêm trọng: 10 triệu tiền thuê gần như toàn bộ là lãi (đồ đã có sẵn), còn 10
triệu tiền bán có thể chỉ lãi 1,5 triệu. Nhìn một con số tổng sẽ dẫn tới quyết định kinh doanh sai.

Vì vậy thêm `products.cost_price` và tính **lãi gộp hàng bán** = `Σ (giá bán − giá vốn) × số lượng`.
Không có nó thì màn hình "doanh thu" của phần bán là một con số gây hiểu nhầm.

### QĐ-10 — Voucher "thuê rồi mua": mở rộng hệ voucher sẵn có

```
vouchers
  + applicable_order_kind  VARCHAR(8) default 'rental'   -- 'rental' | 'sale' | 'both'
    source  → thêm giá trị 'rental_to_sale'
```

Mặc định `'rental'` để **toàn bộ voucher đang tồn tại giữ nguyên hành vi** — không có voucher cũ nào
bỗng dưng dùng được cho đơn mua.

Lưu ý cột `applies_to ∈ {rental_fee, total}` đã có sẵn: giá trị `rental_fee` **vô nghĩa với đơn mua**
(đơn mua không có phí thuê). Quy ước: voucher dùng được cho đơn mua **bắt buộc** `applies_to='total'`;
validate chặn tổ hợp `applicable_order_kind ∈ {sale, both}` đi cùng `applies_to='rental_fee'`.

Voucher sinh tự động khi đơn thuê chuyển sang `returned`, bám đúng cái móc mà `ReviewInviteMail` đang
dùng (`OrderObserver::updated`). Cấu hình trong `PromotionSetting`: bật/tắt, loại (%/tiền), giá trị,
số ngày hiệu lực, giá trị đơn tối thiểu.

### QĐ-11 — Giỏ mua tách riêng khỏi giỏ thuê

Khoá localStorage mới `bop_sale_cart_v1`, route `/gio-hang` riêng với `/gio-thue`.

> **Phụ thuộc bắt buộc:** phải vá `bopcamping-gccu` **trước**. Lỗi đó là `getCart()` không kiểm
> `Array.isArray` nên một giỏ hỏng làm **trắng toàn bộ site**. Thêm giỏ thứ hai bằng cùng khuôn mẫu là
> nhân đôi bề mặt lỗi đó lên.

### QĐ-12 — Đổi hàng: ghi sổ cái, nhưng KHÔNG tự cộng vào kho thuê

Luồng đổi: `delivered → exchanging → exchanged`.

Khi hoàn tất đổi, hệ thống ghi **hai** dòng sổ cái:
- `pool='sale', delta=-1, reason='exchange_out'` (món mới gửi đi cho khách)
- `pool='rental', delta=+1, reason='exchange_in'` (món khách trả về, sẽ làm sạch cho thuê)

Nhưng **không tự động ghi vào `product_service_location.quantity`**. Thay vào đó admin thấy một dải
nhắc *"Có N món đổi về chưa nhập kho thuê"* và tự cập nhật qua form sản phẩm như hiện nay.

**Vì sao chịu thêm một bước tay:** `syncStocks()` dùng `sync()` — nó **ghi đè** tồn theo số admin gõ và
**xoá** dòng pivot của kho không được tick. Một phép cộng tự động vào cột đó sẽ bị ghi đè im lặng ngay
lần sau admin sửa sản phẩm, và kho sẽ sai mà không ai biết. Sửa cho đúng nghĩa là viết lại màn hình
tồn kho đang chạy ổn — không đáng đánh đổi ở giai đoạn này, khi số lần đổi hàng mỗi tháng đếm trên đầu
ngón tay.

---

## 3. Những gì KHÔNG đổi

Ghi ra để người triển khai không "tiện tay" đụng vào:

- `AvailabilityService` — mọi hàm, mọi công thức. Kho bán **không** đi qua service này.
- `products.quantity` vẫn chỉ là tổng kho **thuê**.
- Luồng đơn thuê: cọc, hoàn cọc, buffer quay vòng, đơn cha/con, lịch giao nhận, shipper.
- **11 test file** đụng tới `AvailabilityService` phải **xanh nguyên** sau toàn bộ epic — đây là tiêu
  chí nghiệm thu. Danh sách đầy đủ: `AvailabilityBatchTest`, `AvailabilityBufferTest`,
  `AvailabilityServiceTest`, `ComboAvailabilityTest` (Feature + Unit), `ComboCheckoutTest`,
  `PerItemDatesCheckoutTest`, `PerStoreStockTest`, `ProductAccessoryTest`, `ProductAvailabilityTest`,
  `TurnaroundBufferQaTest`.

---

## 4. Danh sách phải rà (phần dễ sót nhất)

Mỗi dòng dưới đây là một chỗ hiện **ngầm giả định mọi đơn đều là đơn thuê**.

| # | Nơi | Đang làm gì | Phải thành |
| --- | --- | --- | --- |
| 1 | `Admin/DashboardController.php:24,26` | doanh thu WHERE `status='returned'` | tách hai dòng theo `kind` |
| 2 | `Admin/StatsController.php:46` | như trên | như trên |
| 3 | `OrderObserver::updated` | gửi `ReviewInviteMail` khi `returned` | thêm nhánh `delivered` cho đơn mua |
| 4 | `ReferralService.php:98` | quy đổi khi `status === conversion_trigger_status` | quyết `delivered` có tính không (mục 7) |
| 5 | `DeliveryScheduleService` | mọi đơn `is_parent=false` theo `start_date` | lọc `kind='rental'` |
| 6 | `Shipper/ScheduleController` | `markDelivered` → `renting` | đơn mua không được lọt vào app shipper |
| 7 | `Order::progress()` (`:389`) | timeline 5 mốc theo thuê | timeline riêng cho đơn mua |
| 8 | `Order::aggregateStatus()` (`:203`) | gộp trạng thái đơn con | đơn mua không có cha/con |
| 9 | `User::reviewableOrderItemId()` (`:150`) | chỉ tính `returned` | thêm `delivered` |
| 10 | `AccountController:65,152` | thống kê + review token theo `returned` | thêm nhánh mua |
| 11 | `OrderStatusMail` | 3 nhánh mail | thêm nhánh cho trạng thái mua |
| 12 | `OrderLookupService` | timeline tra cứu | nhánh mua |
| 13 | `lib/orderStatus.ts` (FE) | `STATUS_LABEL`, `NEXT_STATUSES` | tách theo `kind` |
| 14 | `Admin/OrderController::index` | bộ lọc trạng thái | thêm bộ lọc loại đơn |
| 15 | `syncStocks()` | `sync()` ghi đè + xoá dòng pivot | phải giữ `sale_quantity` khi sync |
| 16 | `OrderSplitter.php:53,69,85` | `payment_method` **hardcode `'cod'`** ở 3 chỗ | đơn mua chọn được COD / chuyển khoản |

> Dòng 15 là **cái bẫy nguy hiểm nhất**: `sync()` xoá dòng pivot của cơ sở không được tick, kéo theo
> mất luôn `sale_quantity` của cơ sở đó. Phải có test riêng cho tình huống này.

---

## 5. Hệ quả

**Tích cực**
- `AvailabilityService` không đổi → phần rủi ro nhất của hệ thống không bị động vào.
- Một hàng đợi đơn duy nhất cho nhân viên.
- Sổ cái kho bán trả lời được câu hỏi tiền bạc mà hôm nay không trả lời được.
- Đánh giá của người thuê đỡ cho người mua — đòn bẩy bán hàng có sẵn, không tốn gì thêm.

**Tiêu cực**
- Bảng `orders` gánh hai vòng đời → mọi truy vấn đơn phải nhớ lọc `kind`. Quên là ra số sai.
- 15 điểm phải rà ở mục 4; sót một điểm là một lỗi âm thầm.
- Đổi hàng còn một bước tay.
- Đơn mua giữ chỗ tồn từ `pending` — lệch với đơn thuê, dễ bị người sau "sửa cho nhất quán".

**Rủi ro cần theo dõi**
- `sale_quantity` cache lệch với sổ cái → phải có lệnh `artisan` đối soát và test bất biến.
- Job tự huỷ đơn `pending` chạy sai có thể huỷ nhầm đơn thật → phải log và test kỹ.
- Đổi `status` từ ENUM sang VARCHAR là migration trên bảng đang có dữ liệu thật → phải sao lưu trước.

---

## 6. Để lại cho sau (cố ý không làm bây giờ)

- Chuyển kho **thuê** sang sổ cái (bỏ hẳn kiểu gõ tay).
- Ghi nhận hao hụt / hỏng / mất cho kho thuê.
- Trộn thuê + mua trong một đơn (chủ shop đã chốt tách riêng).
- Bán combo.
- Đặt trước hàng chưa về (pre-order).

---

## 7. Điều CHƯA CHỐT — cần chủ shop trả lời trước khi code

1. **Đổi sang món rẻ hơn thì xử lý chênh lệch thế nào?** Không hoàn tiền? Cấp voucher? Chính sách "chỉ
   đổi không trả" chưa nói rõ chiều này.
2. **Thời hạn được đổi là bao lâu** kể từ ngày nhận, và điều kiện gì (còn nguyên seal? đã dùng?).
3. **Đơn mua có được tính cho chương trình giới thiệu không?** Hiện `conversion_trigger_status` chỉ
   nhận 4 trạng thái của đơn thuê.
4. **Voucher thuê→mua**: giảm bao nhiêu, hạn mấy ngày, có yêu cầu giá trị đơn tối thiểu không.
5. **Đơn mua `pending` treo bao lâu thì tự huỷ** (đề xuất 48h).
6. ~~Có ngưỡng tiền nào bắt buộc chuyển khoản trước không?~~ — **ĐÃ CHỐT 2026-08-01: KHÔNG.**
   Khách **tự chọn** COD hoặc chuyển khoản, không có ngưỡng nào chặn. Đây là quyết định của chủ shop và
   nó đúng với thị trường Việt Nam: COD là mặc định khách quen dùng, ép trả trước cho đơn giá trị cao
   là ép đúng nhóm khách đáng giá nhất.

   Rủi ro khách bùng đơn COD **vẫn còn**, nhưng được chấp nhận như rủi ro kinh doanh chứ không giải
   bằng phần mềm. Lý do việc đó hợp lý: luồng hiện tại **đã có** bước *"Tụi mình gọi điện xác nhận
   thông tin đơn"* trước khi giao — đơn mua đi qua đúng bước đó, và nó chặn được phần lớn đơn ảo mà
   không làm mất khách thật. Một cột `payment_method` sẵn có là đủ; **không** cần cột ngưỡng, không cần
   logic chặn.

   Nếu sau này số liệu cho thấy tỷ lệ bùng đơn cao, hãy siết bằng **vận hành** trước (gọi xác nhận kỹ
   hơn, chặn số điện thoại xấu) rồi mới nghĩ tới phần mềm.

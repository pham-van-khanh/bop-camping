> # ⛔ DEFERRED — 2026-08-01
>
> Chủ shop chốt **tự book vận chuyển ngoài hệ thống**: web không tính cước, không gọi API hãng,
> không lưu mã vận đơn, không cần cân nặng sản phẩm. Phí ship báo qua điện thoại như mảng cho thuê
> đang làm; muốn ghi lại thì dùng `orders.extra_fee` đã có sẵn.
>
> **KHÔNG triển khai ADR này.** Giữ nguyên vì phần khảo sát vẫn đúng và sẽ cần nếu sau này muốn đưa
> vận chuyển vào hệ thống — đặc biệt là phát hiện: hệ mã địa chỉ của hãng sau sát nhập 07/2025 chưa
> chắc khớp `province_code`/`ward_code`, phải xác minh trước khi chọn hãng.

# ADR: Vận chuyển cho đơn MUA — chọn hãng, tính phí, tạo vận đơn, đối soát COD

**Status**: Proposed — chờ chủ shop duyệt
**Ngày**: 2026-08-01
**Phạm vi**: chỉ đơn **MUA** (bán sản phẩm, giao toàn quốc). Đơn **THUÊ** giữ nguyên
shipper riêng của shop (Vinh, Hà Nội) — ADR này KHÔNG đụng vào luồng đó.
**Liên quan**: `artifacts/adr_shipper_role_and_access.md` (shipper nội bộ),
`artifacts/adr_sepay_qr_payment.md` (thanh toán chuyển khoản — mới Proposed, chưa code),
`artifacts/plan_address_picker.md` (địa chỉ sau sáp nhập).

---

## Bối cảnh

### Cái đã chốt (không bàn lại trong ADR này)

- Đơn mua giao qua **đơn vị vận chuyển bên thứ ba** (GHTK / GHN / Viettel Post), bán toàn quốc.
- Chủ shop chọn **tính phí ship bằng API của hãng** (chính xác theo cân nặng + địa chỉ),
  không dùng bảng phí phẳng làm nguồn chính.
- Thanh toán: **COD** hoặc **chuyển khoản**.

### Hiện trạng hệ thống (đã kiểm chứng từ code, không suy đoán)

| Sự thật | Chi tiết |
|---|---|
| Địa chỉ đơn hàng | Bảng `orders` đã có `province_code`, `ward_code`, `street`, `customer_address` (migration `2026_08_01_100000_add_address_codes_to_orders_table.php`). Cấu trúc **sau sáp nhập 07/2025 — 34 tỉnh, 2 cấp**. Các cột code đều nullable, **không backfill đơn cũ**, và `customer_address` vẫn là nguồn chân lý cho giao nhận. |
| Nguồn danh sách tỉnh/xã | FE gọi `provinces.open-api.vn` qua `resources/js/lib/divisions.ts`. Dữ liệu hành chính **không nằm trong DB này**, không có khoá ngoại. |
| Cân nặng / kích thước sản phẩm | **KHÔNG CÓ cột nào** trong `products`. Phải thêm mới. |
| Phí giao nhận | Chưa có khái niệm phí ship trong hệ thống. Chỉ có `orders.extra_fee` + `extra_fee_note` do admin nhập tay, đã cộng vào tổng ở `Order::…` (dòng 263). |
| Mail | Tất cả là `ShouldQueue`, chạy qua queue worker. |
| Tích hợp bên thứ ba | Chưa có gì ngoài `provinces.open-api.vn` (gọi từ FE). Đây sẽ là **tích hợp server-to-server đầu tiên** của dự án. |

### Vấn đề cốt lõi

Gọi API hãng vận chuyển để báo giá nghĩa là đặt **một dịch vụ ngoài tầm kiểm soát vào
đúng đường thanh toán của khách**. Trước giờ toàn bộ luồng checkout chạy hoàn toàn trong
DB của mình; giờ có một HTTP call đồng bộ mà nếu nó chậm 8 giây thì khách bỏ giỏ, nếu nó
chết thì không ai đặt được hàng. Đây là rủi ro lớn nhất của tính năng này — lớn hơn cả
việc chọn hãng nào.

---

## Các phương án đã cân nhắc

### A. Chọn hãng vận chuyển

Bảng dưới là đánh giá **định tính** dựa trên đặc điểm dịch vụ đã biết. **Cố ý KHÔNG có
số liệu giá cụ thể** — bảng giá thay đổi theo hợp đồng, theo sản lượng, theo thời điểm;
mọi con số ở đây sẽ sai. Chủ shop phải lấy biểu phí thật khi mở tài khoản.

| Tiêu chí | GHTK | GHN | Viettel Post |
|---|---|---|---|
| Mở tài khoản | Online, nhanh, hầu như không cần giấy tờ DN | Online, có tài khoản sandbox riêng | Thường cần ký hợp đồng / qua bưu cục |
| Phủ sóng vùng sâu, miền núi | Tốt ở đô thị + đồng bằng | Tốt ở đô thị + đồng bằng | **Mạnh nhất** — bám hệ thống bưu điện/bưu cục |
| Giá hàng nhẹ (< 2kg) | Thường cạnh tranh nhất | Trung bình | Trung bình |
| Giá hàng cồng kềnh (lều, túi ngủ, ghế) | Kém hơn — nhạy với quy đổi thể tích | Trung bình | Thường dễ chịu hơn với hàng to |
| Chất lượng tài liệu API | Khá, tiếng Việt | **Tốt nhất** — có sandbox công khai, ví dụ đầy đủ | Yếu nhất, tài liệu rời rạc |
| Môi trường test không cần đơn thật | Có (phải xin token) | **Có, công khai** | Không rõ |
| Cách khai địa chỉ trong API | Theo **tên** tỉnh/quận/xã dạng chuỗi | Theo **mã ID riêng của GHN** (province_id / district_id / ward_code) | Theo mã riêng |
| Rủi ro sau sáp nhập 07/2025 | Chuỗi tên lệch → API trả lỗi hoặc sai vùng | Phải map `province_code` của mình sang ID của GHN | Chưa rõ |
| Đối soát COD | Có bảng kê + xuất file | Có bảng kê + xuất file | Có, thiên về quy trình offline |
| Webhook trạng thái | Có | Có | Chưa rõ |

**Ghi thẳng chỗ chưa chắc:**
1. Không hãng nào trong ba hãng này mình đã đọc tài liệu tại thời điểm viết ADR. Toàn bộ
   dòng "chất lượng API" là đánh giá theo tiếng tăm chung, **cần chủ shop / dev xác nhận
   khi mở tài khoản**.
2. **Chưa biết hệ mã địa chỉ của các hãng đã cập nhật theo cấu trúc 34 tỉnh / 2 cấp hay
   chưa.** Đây là giả định nguy hiểm nhất của cả tính năng. Nếu hãng vẫn dùng hệ 3 cấp cũ
   (tỉnh → quận/huyện → phường/xã) thì phải xây một bảng map từ `province_code`/`ward_code`
   mới sang mã của hãng — công việc này có thể lớn hơn toàn bộ phần còn lại.
3. Hệ số quy đổi thể tích (chia 5000 hay 6000) khác nhau theo hãng và theo hợp đồng — **không
   hardcode**, phải đưa vào config và xác nhận bằng văn bản.

**Đánh giá:**

- *GHTK trước*: rẻ nhất cho hàng nhẹ, mở tài khoản dễ nhất. Nhưng khai địa chỉ bằng chuỗi
  tên là điểm chết với dữ liệu sau sáp nhập — sai một dấu, sai một chữ "Phường/Xã" là đơn
  hỏng, mà lỗi kiểu này rất khó phát hiện tự động.
- *Viettel Post trước*: phủ sóng tốt nhất, hợp với khách camping ở vùng núi/ven biển. Nhưng
  tài liệu API yếu nhất — với một dev, tích hợp mù là rủi ro tiến độ lớn.
- *GHN trước*: tài liệu và sandbox tốt nhất, tức là **rút ngắn thời gian tới lần gọi API
  đúng đầu tiên** và cho phép test mà không tạo vận đơn thật. Chi phí phải trả là việc
  map mã địa chỉ — nhưng việc map đó là **công khai, kiểm tra được, viết test được**, khác
  hẳn với việc so chuỗi tên mờ mịt.

### B. Cách tính phí ship

| Phương án | Ưu | Nhược |
|---|---|---|
| B1. Chỉ bảng phí tự cấu hình theo vùng | Không phụ thuộc ai, nhanh, luôn sống | Sai với hàng cồng kềnh và vùng xa; shop lỗ hoặc khách bỏ giỏ vì phí cao vô lý |
| B2. Chỉ API hãng (không fallback) | Chính xác nhất | **API chết = không ai đặt được hàng**. Không chấp nhận được |
| B3. API hãng + bảng phí làm lưới an toàn | Chính xác ngày thường, vẫn bán được khi hãng sập | Phải nuôi hai nguồn số; phải xử lý ca "phí báo khác phí thật" |
| B4. Không báo phí ở checkout, admin gọi điện báo sau | Đơn giản nhất về kỹ thuật | Trải nghiệm tệ, khách không biết tổng tiền trước khi đặt — với COD là lý do bỏ đơn hàng đầu |

### C. Tạo vận đơn

| Phương án | Ưu | Nhược |
|---|---|---|
| C1. Admin tạo trên web hãng, dán mã vào đơn | Không rủi ro kỹ thuật, làm được ngay | Thao tác tay, dễ dán nhầm mã |
| C2. Tự động tạo qua API khi admin bấm nút | Nhanh, ít sai sót nhập liệu | Sai địa chỉ/kích thước → **vận đơn rác đã có thật ở hãng**, phải gọi huỷ, có thể mất phí |
| C3. Tự động tạo ngay khi khách đặt | Nhanh nhất | Tệ nhất — đơn ảo cũng sinh vận đơn thật |

### D. Cập nhật trạng thái vận chuyển

| Phương án | Ưu | Nhược |
|---|---|---|
| D1. Không có gì — khách tự tra trên web hãng | Zero code | Khách phải rời site; admin không biết đơn nào đang kẹt |
| D2. Poll định kỳ | Đơn giản, không cần lộ endpoint ra Internet | Trễ; tốn request; dễ đụng rate limit khi nhiều đơn |
| D3. Webhook | Gần thời gian thực, ít request | Phải lộ endpoint public → thành bề mặt tấn công mới; phải chống giả mạo và replay |
| D4. Webhook + poll làm lưới an toàn | Vừa nhanh vừa không mất event | Nhiều code hơn cả hai |

---

## Quyết định

### 1. Hãng vận chuyển: **GHN trước, kiến trúc mở cho hãng thứ hai**

Chọn **GHN** làm hãng đầu tiên, lý do quyết định là **chất lượng tài liệu + sandbox công
khai**, không phải giá. Với một dev và một shop nhỏ, thứ đắt nhất là thời gian debug mù và
những vận đơn hỏng do sai địa chỉ — sandbox giải quyết đúng hai thứ đó. Giá chênh vài
nghìn đồng/đơn ở giai đoạn đầu (dự kiến < 50 đơn/tháng) không đáng để đánh đổi.

Code **không được gọi thẳng GHN**. Bắt buộc đi qua một interface:

```
app/Services/Shipping/ShippingCarrier.php     (interface: quote / createShipment / cancel / track)
app/Services/Shipping/GhnCarrier.php          (giai đoạn 1)
app/Services/Shipping/FlatRateCarrier.php     (fallback, luôn tồn tại, không gọi mạng)
app/Services/Shipping/ShippingQuoteService.php (điều phối: cache → API → fallback)
```

`ShippingQuoteService` là **single source of truth cho phí ship** — giống cách
`AvailabilityService` là nguồn duy nhất cho tồn kho. Mọi chỗ hiển thị phí (giỏ hàng,
checkout, mail xác nhận, admin) đều gọi cùng hàm này. Không lặp công thức ở FE.

Nếu GHN không đáp ứng (mã địa chỉ chưa cập nhật sau sáp nhập, hoặc giá quá cao cho hàng
cồng kềnh), đổi sang GHTK/Viettel Post chỉ là viết thêm một class implement interface đó.

### 2. API nằm trên đường thanh toán — thiết kế phòng thủ (phần quan trọng nhất)

**Nguyên tắc bất di bất dịch: API hãng vận chuyển KHÔNG BAO GIỜ được phép chặn khách đặt
hàng.** Phí ship sai còn sửa được; đơn không đặt được là mất luôn.

| Tham số | Giá trị chốt | Lý do |
|---|---|---|
| Timeout kết nối | **2 giây** | Quá 2s coi như hãng có vấn đề |
| Timeout tổng | **3 giây** | Ngưỡng khách còn chịu được khi giỏ hàng đang tính lại |
| Retry trên đường checkout | **KHÔNG retry** | Retry nhân đôi thời gian chờ đúng lúc không được phép chờ |
| Retry trong job nền | 3 lần, backoff 5s/30s/2 phút | Job nền chờ thoải mái |
| Cache báo giá | **12 giờ**, key = `carrier + province_code + ward_code + bậc cân nặng + bậc giá trị COD` | Biểu phí gần như không đổi trong ngày |
| Làm tròn cân nặng | Lên bội số **500g** | Tăng tỉ lệ trúng cache, làm tròn LÊN nên không bao giờ báo thiếu |
| Circuit breaker | **5 lỗi liên tiếp trong 60s → mở mạch 5 phút** | Hãng đang sập thì đừng bắt từng khách chờ đủ 3 giây |
| Rate limit gọi ra | Tối đa **60 request/phút**, đếm bằng `RateLimiter` | Tránh bị hãng khoá token |

**Fallback — bảng phí theo vùng.** Bảng `shipping_rate_zones` cấu hình được trong admin,
khoá theo `province_code` (34 tỉnh — dùng đúng cột đã có, không thêm hệ mã thứ hai) và bậc
cân nặng. Vùng đề xuất, chủ shop chốt số:

| Vùng | Gồm | Ghi chú |
|---|---|---|
| Nội tỉnh | Nghệ An | Rẻ nhất |
| Lân cận | Hà Tĩnh, Thanh Hoá, Quảng Trị… | |
| Miền Bắc | Hà Nội và các tỉnh phía Bắc | |
| Miền Trung – Nam | Từ Huế trở vào | |
| Vùng xa / đảo | Các tỉnh miền núi cao, huyện đảo | Cộng phụ phí |

Bảng phí này **luôn phải có dữ liệu** trước khi bật tính năng bán hàng. Chưa cấu hình bảng
phí thì không cho bật bán hàng — đây là điều kiện triển khai, không phải khuyến nghị.

**Có báo cho khách khi rơi vào fallback không? → CÓ, nhưng nhẹ.**
Không bao giờ hiện lỗi kỹ thuật ("không gọi được GHN"). Hiện nguyên văn:

> Phí vận chuyển: 35.000đ *(tạm tính — shop xác nhận lại khi gọi điện chốt đơn)*

Lý do: với COD, khách đã quen việc shop gọi xác nhận. Che giấu hoàn toàn rồi sau đó thu
khác đi mới là thứ làm mất niềm tin. Đơn lưu thêm cột `shipping_fee_source` ∈
`api | table | manual` để admin biết ngay đơn nào cần xác nhận lại, và để đo tỉ lệ fallback.

**Chênh lệch phí báo và phí thật — ai chịu?**

| Mức chênh | Xử lý |
|---|---|
| ≤ 20.000đ **hoặc** ≤ 20% phí đã báo | **Shop chịu.** Không gọi khách, không sửa đơn. |
| Vượt ngưỡng trên | Admin **gọi xác nhận** trước khi tạo vận đơn. Khách đồng ý thì admin sửa phí (ghi `extra_fee_note`); không đồng ý thì huỷ đơn, không tính phí. |

Tuyệt đối **không tự động cộng thêm tiền vào đơn đã chốt**. Ngưỡng 20.000đ/20% là đề xuất
— **chủ shop phải chốt con số thật** dựa trên biên lợi nhuận.

### 3. Cân nặng và kích thước sản phẩm

Thêm vào `products` (migration mới, tất cả **nullable**):

| Cột | Kiểu | Đơn vị |
|---|---|---|
| `weight_grams` | `unsignedInteger` | **gram** (số nguyên, không dùng kg/float) |
| `length_cm`, `width_cm`, `height_cm` | `unsignedSmallInteger` | **cm** (số nguyên) |

Dùng gram và cm vì đó là đơn vị các hãng nhận trực tiếp — không phải quy đổi, không có sai
số dấu phẩy động khi cộng dồn nhiều món.

- **Ai nhập**: admin, ở form sản phẩm, nhóm trường "Vận chuyển". Bắt buộc (validate) chỉ
  khi sản phẩm được đánh dấu bán; sản phẩm chỉ cho thuê không cần.
- **Chưa nhập thì sao — KHÔNG được chặn checkout.** Thứ tự lấy giá trị:
  1. Giá trị của sản phẩm.
  2. Mặc định theo danh mục (`categories.default_weight_grams`).
  3. Mặc định toàn hệ thống trong `site_settings` (`shipping_default_weight_grams`, đề xuất
     **1000g**) — cố tình đặt cao hơn thực tế đa số món, vì báo thừa thì shop được lợi,
     báo thiếu thì shop lỗ.
  Mỗi lần rơi xuống bậc 2 hoặc 3, ghi log cảnh báo + hiện badge **"thiếu cân nặng"** ở danh
  sách sản phẩm admin. Sai số phải nhìn thấy được, không được im lặng.
- **Trọng lượng quy đổi**: `weight_tính_phí = max(tổng cân thật, tổng thể tích / hệ_số)`.
  Hệ số để trong `config/shipping.php`, **không hardcode**, xác nhận theo hợp đồng.
- **Nhiều món trong một đơn**: giai đoạn 1 cộng cân nặng, và khai kích thước của **món to
  nhất**. Đây là heuristic đơn giản và cố ý chấp nhận sai số — admin nhìn thấy và sửa được
  lúc tạo vận đơn. Không xây thuật toán xếp hộp (bin packing) ở giai đoạn này.

### 4. Tạo vận đơn: **giai đoạn 1 admin nhập mã tay**

Chọn C1 trước, C2 sau. Lập luận theo rủi ro chứ không theo tiện lợi:

- Báo giá là thao tác **đọc** — gọi sai thì cùng lắm hiện số sai, sửa được.
- Tạo vận đơn là thao tác **ghi có hậu quả ngoài hệ thống** — gọi sai thì đã có một vận đơn
  thật ở hãng, phải gọi huỷ, có thể mất phí, có thể có shipper thật đến lấy hàng không tồn tại.
- Ở đúng giai đoạn mà **chưa ai chắc mã địa chỉ map đúng**, tự động hoá bước ghi là đặt cược
  vào giả định chưa kiểm chứng.

Giai đoạn 1: admin tạo vận đơn trên web GHN, dán `tracking_code` vào đơn. Hệ thống lưu
`tracking_code`, `carrier`, `tracking_url`, `shipped_at` và hiện link tra cứu cho khách.

Giai đoạn 2 (sau khi đạt điều kiện ở mục 8): nút "Tạo vận đơn" trong admin → đẩy job vào
queue → gọi API. Job phải **idempotent**: dùng `orders.id` làm `client_order_code` gửi
sang hãng, để bấm hai lần không sinh hai vận đơn.

### 5. Cập nhật trạng thái: **webhook là chính, poll làm lưới an toàn** (D4) — nhưng chỉ từ giai đoạn 2

Giai đoạn 1 không có cập nhật tự động; khách bấm link tra cứu sang web hãng. Chấp nhận được
khi còn ít đơn.

Giai đoạn 2, endpoint `POST /webhooks/shipping/ghn`, với các yêu cầu **bắt buộc**:

1. **Xác thực.** Ưu tiên tuyệt đối: nếu hãng có ký HMAC payload thì **dùng chữ ký**, so sánh
   bằng `hash_equals`. **Chưa xác nhận được GHN có ký hay không** — nếu không có, dùng
   URL chứa secret dài (`/webhooks/shipping/ghn/{secret}`, ≥ 32 ký tự ngẫu nhiên trong
   `.env`) **cộng với** allowlist dải IP của hãng nếu hãng công bố. URL bí mật là biện pháp
   yếu hơn chữ ký — phải ghi nhận đây là nợ kỹ thuật, không phải giải pháp tốt.
2. **HTTPS bắt buộc.** Không nhận webhook qua HTTP.
3. **Chống replay.** Bảng `shipping_webhook_events` với **unique index** trên
   `(carrier, event_id)`; hãng không gửi event_id thì dùng
   `sha256(tracking_code + status + timestamp)`. Trùng khoá → trả 200 và bỏ qua.
4. **Không tin thứ tự đến.** So sánh thứ hạng trạng thái; event mang trạng thái "lùi" so với
   trạng thái hiện tại thì ghi log và bỏ qua, không ghi đè.
5. **Trả 200 nhanh (< 1s).** Webhook chỉ ghi bản ghi event rồi dispatch job; toàn bộ nghiệp
   vụ (đổi trạng thái đơn, gửi mail) chạy trong queue. Mail vốn đã là `ShouldQueue` nên
   khớp sẵn với kiến trúc hiện tại.
6. **Webhook KHÔNG được đổi trạng thái thanh toán.** Kể cả payload nói "đã thu COD".
   Tiền chỉ được ghi nhận khi đối soát (mục 6). Đây là ranh giới chống gian lận quan trọng
   nhất: ai đó đoán được URL cũng chỉ làm sai trạng thái giao hàng, không đánh dấu được
   đơn đã trả tiền.
7. Route webhook phải nằm ngoài kiểm tra CSRF (thêm vào `$except`) và có
   `throttle` (đề xuất 120 req/phút).

**Poll làm lưới an toàn**: lệnh `php artisan shipping:reconcile-status` chạy mỗi 30 phút,
chỉ quét các đơn đang giao mà **> 24 giờ không có event nào** — số lượng nhỏ, không đụng
rate limit.

### 6. Đối soát tiền COD

- **Ai**: chủ shop (hoặc admin được uỷ quyền). Không tự động hoá phần ra quyết định.
- **Bao lâu một lần**: **hàng tuần, thứ Hai**, cho các đơn đã giao tuần trước. Bắt buộc đối
  soát xong trước khi chốt sổ tháng. (Chu kỳ hãng chuyển tiền COD — thường vài ngày một
  lần — **cần xác nhận khi mở tài khoản**; nếu hãng chuyển theo chu kỳ khác thì bám theo
  chu kỳ đó.)
- **Cách làm**:
  - *Giai đoạn 1*: thủ công. Admin mở bảng kê của hãng, đối chiếu với danh sách đơn đã giao
    trong `/admin/don-hang`, tick "đã nhận tiền COD" từng đơn.
  - *Giai đoạn 3*: màn hình `/admin/doi-soat-ship` cho upload file bảng kê (CSV) hãng xuất
    ra, hệ thống match theo `tracking_code` và chia làm ba nhóm: **khớp** / **lệch số tiền**
    / **chỉ có ở một bên**.
- **Lệch thì xử lý ra sao**: tạo bản ghi trong `shipping_reconcile_issues` (đơn, số tiền hệ
  thống, số tiền hãng, chênh lệch, ghi chú, trạng thái). **Hệ thống không bao giờ tự sửa số
  tiền đơn theo file của hãng** — chỉ hiện ra để người quyết. Chủ shop khiếu nại với hãng
  hoặc chấp nhận chênh lệch và đóng issue kèm lý do. Mọi thay đổi phải để lại vết.

### 7. Bảo mật

- **Khoá API**: `GHN_TOKEN`, `GHN_SHOP_ID`, `SHIPPING_WEBHOOK_SECRET` để trong `.env`,
  **KHÔNG commit**. Code đọc qua `config/shipping.php`, **không gọi `env()` ngoài file
  config** (vì `config:cache` ở production sẽ làm `env()` trả `null`). Thêm placeholder vào
  `.env.example` với giá trị rỗng.
- **Rate limit**: hai chiều — gọi ra tối đa 60 req/phút (mục 2), webhook vào tối đa
  120 req/phút.
- **Log CÓ ghi**: `order_id`, `province_code`, `ward_code`, cân nặng, phí trả về,
  `shipping_fee_source`, thời gian phản hồi (ms), HTTP status, mã lỗi của hãng.
- **Log KHÔNG ghi**: token / header `Authorization` / secret dưới mọi dạng; số điện thoại
  đầy đủ (mask kiểu `0912***678`); họ tên khách; địa chỉ chi tiết (`street`,
  `customer_address`). Log vận hành chỉ cần đủ để debug định tuyến — không cần PII.
- **Payload webhook thô** lưu vào cột JSON trong `shipping_webhook_events` (để tra khi có
  tranh chấp), **không đẩy vào file log**. Xoá tự động sau **90 ngày**.
- Đây là tích hợp server-to-server đầu tiên của dự án → cập nhật
  `.claude/rules/tech-strategy.md` mục "Out of Scope" và ghi nhận GHN vào danh mục dịch vụ
  ngoài (yêu cầu của `.claude/rules/security.md` — "No Silent External Data Routing").
  Dữ liệu rời hệ thống gồm: tên, SĐT, địa chỉ người nhận, giá trị COD. Phải ghi rõ trong
  chính sách quyền riêng tư của website.

### 8. Phân giai đoạn và điều kiện chuyển giai đoạn

**Giai đoạn 0 — Xác minh giả định (BLOCKING, không viết code trước bước này)**

Mở tài khoản GHN, lấy token sandbox + production, và trả lời dứt điểm 5 câu:

1. Hệ mã địa chỉ của GHN đã theo cấu trúc 34 tỉnh / 2 cấp chưa? Nếu chưa, map từ
   `province_code`/`ward_code` sang mã GHN tốn bao nhiêu công?
2. Hệ số quy đổi thể tích là bao nhiêu?
3. GHN có webhook không, và có ký HMAC không?
4. Chu kỳ chuyển tiền COD và định dạng file đối soát?
5. Biểu phí thật cho các tuyến chính từ Vinh?

Nếu câu 1 trả lời là "phải map thủ công cả 34 tỉnh + hàng nghìn xã" → **quay lại cân nhắc
GHTK/Viettel Post trước khi viết dòng code nào**.

**Giai đoạn 1 — Bán được hàng (làm ngay sau GĐ0)**

- Migration: cân nặng + kích thước cho `products`; các cột ship cho `orders`
  (`shipping_fee`, `shipping_fee_source`, `carrier`, `tracking_code`, `tracking_url`,
  `shipped_at`) — tất cả nullable để không ảnh hưởng đơn thuê.
- Bảng `shipping_rate_zones` + màn hình cấu hình trong admin.
- `ShippingCarrier` interface + `GhnCarrier` (chỉ hàm `quote`) + `FlatRateCarrier` +
  `ShippingQuoteService` với cache, timeout, circuit breaker.
- Hiển thị phí ở giỏ hàng / checkout, có nhãn "tạm tính" khi fallback.
- Admin dán mã vận đơn tay; khách xem link tra cứu.
- Test: `ShippingQuoteServiceTest` phải phủ **API chậm**, **API lỗi 500**, **API trả cấu trúc
  lạ** — cả ba đều phải rơi xuống bảng phí và **không ném exception ra checkout**.

**Điều kiện lên Giai đoạn 2** (phải đạt **tất cả**):

- ≥ 30 đơn mua giao thành công.
- Tỉ lệ báo giá rơi fallback **< 10%** trong 14 ngày liên tiếp.
- Chênh lệch phí báo / phí thật trung bình **< 10%**.
- **0 đơn sai địa chỉ** do map mã, trong 20 đơn gần nhất.

**Giai đoạn 2 — Bớt thao tác tay**

- Nút "Tạo vận đơn" qua API (job idempotent theo `orders.id`).
- Webhook trạng thái + `shipping_webhook_events` + lệnh poll lưới an toàn.
- Mail báo khách khi đơn được giao cho hãng (dùng queue sẵn có).

**Điều kiện lên Giai đoạn 3**: > 50 đơn mua/tháng, hoặc chủ shop mất > 1 giờ/tuần cho đối
soát thủ công.

**Giai đoạn 3 — Quy mô**

- Màn hình đối soát COD bằng upload CSV.
- Hãng thứ hai (`GhtkCarrier` / `ViettelPostCarrier`) + so giá tự động chọn rẻ nhất, hoặc
  cho khách chọn hãng.
- Chính sách freeship theo ngưỡng giá trị đơn.

---

## Hệ quả

### Tích cực

- Khách thấy tổng tiền thật (gồm ship) **trước khi** bấm đặt — với COD đây là yếu tố ảnh
  hưởng trực tiếp tới tỉ lệ bỏ giỏ.
- Bán được toàn quốc mà không cần shipper riêng ngoài Vinh / Hà Nội.
- Phí ship có nguồn duy nhất (`ShippingQuoteService`), đúng nguyên tắc đã áp dụng cho
  `AvailabilityService` — không lặp công thức.
- Dữ liệu `province_code` đã có sẵn từ `AddressPicker` được dùng thật, không chỉ để thống kê.
- Kiến trúc adapter cho phép đổi hãng mà không sửa checkout.

### Tiêu cực

- Thêm một phụ thuộc ngoài vào đường thanh toán — kể cả có fallback, đây vẫn là bề mặt lỗi
  mới chưa từng có trong dự án.
- Admin phải nhập cân nặng/kích thước cho toàn bộ sản phẩm bán; dữ liệu này dễ bị bỏ trống
  và sai lệch âm thầm.
- Thêm endpoint public (webhook) từ giai đoạn 2 — bề mặt tấn công mới.
- Hai nguồn số phí (API + bảng) nghĩa là có ca chênh lệch phải xử lý bằng con người mãi mãi.

### Rủi ro

| Rủi ro | Mức | Giảm thiểu |
|---|---|---|
| Mã địa chỉ hãng chưa cập nhật sau sáp nhập → vận đơn sai vùng | **Cao** | Giai đoạn 0 chặn; test map bằng 20 địa chỉ thật rải khắp 3 miền trước khi bật |
| API chậm/chết lúc cao điểm | Trung bình | Timeout 3s + circuit breaker + bảng phí; đo tỉ lệ fallback hằng ngày |
| Phí báo lệch nhiều so với phí thật → shop lỗ ngầm | Trung bình | Cột `shipping_fee_source` + báo cáo chênh lệch hằng tuần; ngưỡng 20k/20% |
| Token bị lộ qua log hoặc commit nhầm | Trung bình | Chỉ đọc qua config; quét secret trước commit; xoay token ngay nếu nghi ngờ |
| Webhook bị giả mạo (khi không có HMAC) | Trung bình | Secret trong URL + allowlist IP + webhook không đụng được tới trạng thái tiền |
| Cân nặng để trống hàng loạt → phí sai toàn hệ thống | Trung bình | Mặc định cao có chủ đích + badge cảnh báo trong admin |
| Nhầm luồng đơn thuê và đơn mua | Trung bình | Toàn bộ cột ship nullable, chỉ áp dụng cho đơn mua; test hồi quy cho luồng thuê |

---

## Việc phải làm

1. **[GĐ0, BLOCKING]** Chủ shop mở tài khoản GHN, trả lời 5 câu ở mục 8. Ghi kết quả bổ
   sung vào chính ADR này rồi chuyển Status sang Accepted.
2. Chủ shop chốt: ngưỡng chênh lệch shop chịu (đề xuất 20.000đ / 20%), bảng phí theo 5 vùng,
   cân nặng mặc định (đề xuất 1000g).
3. Migration cân nặng/kích thước cho `products` + cột ship cho `orders`.
4. Bảng `shipping_rate_zones` + màn hình cấu hình admin.
5. `ShippingCarrier` interface + `GhnCarrier::quote` + `FlatRateCarrier` +
   `ShippingQuoteService`.
6. Test bắt buộc: API chậm / lỗi 500 / trả cấu trúc lạ → đều rơi fallback, checkout không vỡ.
7. Hiển thị phí + nhãn "tạm tính" ở giỏ hàng và checkout; đưa phí vào mail xác nhận.
8. Trường mã vận đơn + link tra cứu trong admin và trang tra cứu đơn của khách.
9. Cập nhật `.claude/rules/tech-strategy.md` (dịch vụ ngoài mới) và chính sách quyền riêng tư.
10. Tạo epic Beads cho GĐ1, mỗi mục trên là một issue.

---

## Điều chưa chốt

Ghi thẳng ra thay vì tỏ ra chắc chắn:

- **Hãng cuối cùng.** GHN là lựa chọn dựa trên chất lượng tài liệu, **chưa dựa trên giá thật
  và chưa xác minh hệ mã địa chỉ**. Kết quả GĐ0 có thể lật quyết định này.
- **Toàn bộ con số phí.** Không có bảng giá nào trong ADR này vì mình không có dữ liệu đúng.
- **Hệ số quy đổi thể tích** — 5000 hay 6000, tuỳ hãng và hợp đồng.
- **GHN có ký HMAC webhook không.** Ảnh hưởng trực tiếp tới thiết kế bảo mật mục 5.
- **Chu kỳ và định dạng file đối soát COD.**
- Chính sách **freeship theo ngưỡng giá trị đơn** — chưa bàn.
- **Ai chịu phí khi khách từ chối nhận hàng COD** (hàng hoàn về, shop mất phí hai chiều).
  Đây là rủi ro tiền thật của mô hình COD, cần chính sách rõ trước khi bán.
- **Đóng gói**: ai đóng, vật tư gì, chi phí đóng gói tính vào giá bán hay phí ship.
- Đơn mua và đơn thuê **dùng chung bảng `orders` hay tách bảng** — ADR này giả định dùng
  chung với cột nullable, nhưng quyết định đó thuộc về ADR mô hình dữ liệu bán hàng, chưa viết.
- Có cho khách **chọn hãng** hay shop tự chọn — giai đoạn 1 shop tự chọn, sau tính lại.

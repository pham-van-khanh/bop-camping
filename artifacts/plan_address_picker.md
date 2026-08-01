# Plan — Chọn địa chỉ theo cấu trúc sau sát nhập (kèm đường "địa chỉ cũ")

**Ngày:** 2026-08-01 · **Nhánh:** `feature/address-picker`
**API:** https://provinces.open-api.vn — `v1` = trước sát nhập (63 tỉnh, 3 cấp), `v2` = sau sát nhập (34 tỉnh, 2 cấp)

Ước lượng 2–3 ngày.

---

## Quyết định đã chốt

| # | Quyết định | Chọn |
|---|---|---|
| 1 | Đường "địa chỉ cũ" | Khách nhập địa chỉ **cũ** → hệ thống **suy ra** địa chỉ mới → khách **sửa được** |
| 2 | Luồng mặc định | Nhập địa chỉ **MỚI** (2 select). Có nút "Tôi chỉ biết địa chỉ cũ" mở thêm 3 select cũ |
| 3 | Cách lưu | `customer_address` **giữ nguyên là chuỗi** + thêm cột mã |
| 4 | Nguồn dữ liệu | **Gọi API họ trực tiếp từ FE**. KHÔNG tạo bảng, KHÔNG import |

### Vì sao không import vào DB (đã cân rồi mới bỏ)

Bản đầu tôi đề xuất 6 bảng + command import. Đo lại thì không đáng:

- `access-control-allow-origin: *` → browser gọi trực tiếp được, không cần proxy
- Độ trễ ~60ms (họ cache edge `s-maxage=30`); lần đầu ~1.5s do cold
- Lý do duy nhất để import là "API sập thì khách không đặt được" — nhưng đã có **fallback về ô text tự do**, nên rủi ro đó xử lý xong rồi
- Import đổi lại: 6 bảng, **3.321 request** một lần lên API miễn phí của người ta, và dữ liệu **cũ dần** (xã vẫn có thể đổi)

### Vì sao mapping cần select, không cho gõ tay

`from-legacy` khớp theo **TÊN**, bỏ qua tỉnh — đo thật:

```
"Phường Điện Biên"  -> 6 kết quả
"Xã Ba Vì"          -> 2 kết quả
"Phường 1"          -> 51 kết quả  (!)
```

Nhưng response có `source_code` = **mã xã cũ**. Khách chọn xã cũ bằng select → biết mã → lọc `source_code` → chính xác. Đây là lý do đường "địa chỉ cũ" phải là select chứ không phải ô text.

**Map là quan hệ nhiều-nhiều.** Lọc đúng `source_code=19` (Phường Điện Biên, Hà Nội) vẫn ra **4 xã mới** vì xã cũ bị **chia**. Nên "suy ra" không phải lúc nào cũng ra một đáp án — thiết kế phải xử lý cả 3 ca (1 / N / 0).

---

## T1 — Cột mới trên `orders` + nhận/lưu ở checkout

**File:** migration mới, `app/Http/Controllers/Shop/OrderController.php`

4 cột **nullable** (đơn cũ không ảnh hưởng, không backfill):

```php
$table->unsignedInteger('province_code')->nullable();
$table->unsignedInteger('ward_code')->nullable();
$table->unsignedInteger('legacy_ward_code')->nullable();   // chỉ khi khách đi đường "địa chỉ cũ"
$table->string('street')->nullable();                       // số nhà, đường
```

`OrderController::store()` nhận thêm các field trên (đều `nullable|integer` / `nullable|string|max:255`), lưu vào `$base`.

`customer_address` **vẫn là chuỗi do FE ghép** — 8 chỗ đang đọc nó (`Admin/orderShared.tsx`, `Shipper/Schedule.tsx`, `Admin/DeliverySchedule.tsx`, `DeliveryScheduleService` tin Zalo, `AccountController`, ...) **không phải sửa dòng nào**:

```
Số 5 Trần Phú, Phường Ba Đình, Thành phố Hà Nội (trước sát nhập: Phường Điện Biên, Quận Ba Đình)
```

Phần trong ngoặc chỉ xuất hiện khi khách dùng đường "địa chỉ cũ" — để shipper quen tên cũ dễ tìm đường.

> ⚠️ **CỐ Ý không validate `ward_code` ở server.** Không có dữ liệu trong DB nên muốn validate thì phải gọi API bên thứ ba **ngay lúc POST tạo đơn** — đưa dependency vào đúng đường tiền, tệ hơn nhiều. Thay vào đó: chuỗi `customer_address` (thứ khách thấy và shipper dùng) là **nguồn chân lý**; các cột mã chỉ để thống kê sau. Khách sửa mã thì chỉ làm sai ô thống kê của chính đơn họ — không ảnh hưởng tiền, tồn kho hay giao nhận.

**Tests** (`tests/Feature/OrderAddressCodesTest.php`):
- Lưu đơn kèm 4 field → cột đúng
- Không gửi field nào (khách dùng ô text như cũ) → đơn vẫn tạo được, cột null
- `customer_address` lưu đúng chuỗi FE gửi
- `ward_code` không phải số → 422
- Đơn cũ (không có cột) không bị ảnh hưởng — chạy lại test đơn hiện có

---

## T2 — `lib/divisions.ts`: gọi API + cache + lọc mapping

**File mới:** `resources/js/lib/divisions.ts` — thuần logic, không JSX, dễ test.

```ts
const BASE = 'https://provinces.open-api.vn/api';

getProvinces()                  // v2/?depth=1        -> 34 tỉnh
getWards(provinceCode)          // v2/p/{code}?depth=2
getLegacyProvinces()            // v1/?depth=1        -> 63 tỉnh
getLegacyDistricts(provCode)    // v1/p/{code}?depth=2
getLegacyWards(districtCode)    // v1/d/{code}?depth=2
inferNewWards(legacyWardName, legacyWardCode)
```

`inferNewWards()`: gọi `v2/w/from-legacy/?legacy_name=<tên>` rồi **lọc `source_code === legacyWardCode`**. Trả `{ wards, exact }` — `exact = wards.length === 1`.

**Cache `sessionStorage`** cho danh sách tỉnh (v1 + v2) — gần như tĩnh, đỡ gọi lại mỗi lần khách mở giỏ. Không cache mapping (ít lặp).

Mọi hàm **throw có kiểm soát** khi fetch lỗi → component bắt và chuyển sang ô text tự do.

**Tests** (`tests/js/divisions.test.ts`, mock `fetch`):
- Ghép URL đúng cho từng hàm
- `inferNewWards` lọc đúng theo `source_code` (dựng response nhiều `source_code` khác nhau)
- 51 kết quả cùng tên nhưng khác `source_code` → chỉ trả đúng nhóm của mã đã chọn
- `source_code` không khớp gì → trả rỗng
- Cache: gọi 2 lần chỉ `fetch` 1 lần; `sessionStorage` lỗi (Safari private) → vẫn chạy
- fetch trả 500 / JSON rác → throw để component fallback

---

## T3 — `AddressPicker.tsx` + tích hợp `Cart.tsx`

**File mới:** `resources/js/Components/site/AddressPicker.tsx`

**Chế độ mặc định:** ô "Số nhà, đường" + select Tỉnh/Thành → select Xã/Phường (nạp sau khi chọn tỉnh).

**Bấm "Tôi chỉ biết địa chỉ cũ":** hiện thêm 3 select cũ (Tỉnh → Huyện → Xã cũ). Chọn xong xã cũ → `inferNewWards()`:

| Kết quả | Xử lý |
|---|---|
| **1** | Tự điền tỉnh + xã mới. Hiện *"Đã suy ra: Phường X. Sửa nếu chưa đúng."* |
| **N** | Chỉ hiện đúng N ứng viên đó, kèm *"Phường Điện Biên cũ nay thuộc 4 phường mới — chọn giúp phường của bạn."* Có link "xem tất cả xã của tỉnh" để mở full list |
| **0** | Giữ full list, *"Không tra được tự động, bạn chọn giúp."* |

Select **mới luôn sửa được** — suy ra chỉ là gợi ý, không khoá.

**Fallback:** bất kỳ lỗi fetch nào → render **đúng ô text tự do như hiện nay** kèm dòng nhỏ *"Không tải được danh sách địa chỉ, bạn nhập tay giúp nhé."* **Không bao giờ chặn đặt hàng.**

**Tích hợp `Cart.tsx`:** thay ô `address` hiện tại ([Cart.tsx:608](resources/js/Pages/Cart.tsx:608)) bằng `AddressPicker`. Component gọi `onChange({ address, street, province_code, ward_code, legacy_ward_code })`. Điều kiện `canSubmit` hiện đòi `address.trim().length >= 4` — giữ nguyên, chỉ là `address` giờ do picker ghép.

**Tests** (`tests/js/AddressPicker.test.tsx`, mock `lib/divisions`):
- Chọn tỉnh → nạp xã của tỉnh đó
- Ghép `address` đúng định dạng, có/không phần "(trước sát nhập: …)"
- Nút "địa chỉ cũ" mở 3 select
- 1 ứng viên → tự điền + hiện dòng "Đã suy ra"
- N ứng viên → hiện đúng N, có link mở full list
- 0 → full list + dòng giải thích
- Sửa đè gợi ý → `onChange` trả mã mới
- `lib/divisions` throw → về ô text tự do, vẫn gọi `onChange` với `address` khách gõ

---

## Quality gates

```bash
php artisan test && npm test && npx tsc --noEmit && ./vendor/bin/pint --test && npm run build
```

`npm run lint` toàn repo không dùng được làm gate (Prettier drift — `bopcamping-vbz1`); chỉ `npx eslint <file mình sửa>`.

## Verify tay trên trình duyệt

1. Chọn tỉnh → xã → nhập số nhà → đặt đơn → xem `customer_address` trong admin đúng chuỗi
2. Bấm "Tôi chỉ biết địa chỉ cũ" → Hà Nội / Ba Đình / **Phường Điện Biên** → phải ra **4 ứng viên** (ca xã cũ bị chia — đã đo thật)
3. Chặn network tới `provinces.open-api.vn` → form về ô text, vẫn đặt được đơn

## Điểm cần xem lại về sau (bead follow-up)

1. Admin sửa địa chỉ đơn vẫn là ô text — chưa dùng picker (cố ý, tránh lan sang admin ở vòng này).
2. Chưa thống kê đơn theo tỉnh/xã — cột đã có, làm báo cáo sau.
3. Nếu về sau API hay lỗi hoặc muốn chạy offline thì mới cân nhắc import vào DB; hiện chưa cần.

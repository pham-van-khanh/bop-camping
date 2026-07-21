# ADR — Quản lý quay vòng giữa hai lượt thuê (Turnaround: giờ giao/trả + đệm giặt/phơi)

- **Trạng thái:** Accepted (chờ implement)
- **Ngày:** 2026-07-21
- **Beads:** `bopcamping-s1ij` (Phase 1) · Phase 2 sẽ tạo bead riêng
- **Liên quan:** `app/Services/AvailabilityService.php` (single source of truth tồn kho theo ngày), `product_service_location` (tồn kho per-kho), `site_settings`.

---

## 1. Bối cảnh / Vấn đề

Đơn thuê chỉ có `start_date` / `end_date` kiểu `date` (độ phân giải **ngày**). Điều kiện chồng lịch trong `AvailabilityService::availableQuantity()`: `start_A <= end_B AND start_B <= end_A`.

Hai lỗ hổng vận hành:

1. **Không chừa thời gian giặt/phơi sau ngày trả.** Đồ về ngày 3, ngày 4–5 còn ướt/bẩn nhưng mô hình coi là "sẵn sàng" → hệ thống **nhận đơn khi đồ chưa khô**. (Lưu ý: va chạm *cùng ngày* thì mô hình hiện tại đã chặn — ngày trả được coi là "đồ còn ở ngoài".)
2. **Không quản giờ giao/giờ trả.** Khách không biết khung giờ chuẩn; giao/trả ngoài giờ không có chỗ ghi nhận và tính phụ phí.

## 2. Quyết định (đã chốt với chủ shop 2026-07-21)

Giải quyết bằng **mô hình 2 tầng độc lập**, cộng một invariant giữ mô hình tồn kho luôn sạch:

| Tầng | Cơ chế | Vai trò |
|---|---|---|
| **Nhiều ngày** | `buffer_days` **theo từng kho** (pivot `product_service_location`) | *Enforce* chống nhận đơn khi đồ cần phơi lâu chưa khô. |
| **Trong ngày** | Giờ mặc định **8h giao / 20h trả**, lưu ở `site_settings` (cấu hình được) | *Hiển thị / đặt kỳ vọng*. Tạo "cửa sổ đêm" cho đồ lau nhanh. |
| **Ngoài khung** (Phase 2) | Giờ mong muốn của khách + **phụ phí admin nhập tay** trên đơn | Tiện logistics + doanh thu thêm. |

> **INVARIANT (bất biến quan trọng):** *Giờ giấc KHÔNG bao giờ tham gia phép tính tồn kho.* Vì mọi lượt đều chiếm **trọn ngày**, độ phân giải tồn kho vẫn là **ngày**; giờ chỉ là hiển thị + phụ phí thủ công. Điều này giữ availability/calendar/checkout đơn giản như hiện tại.

Quyết định chi tiết:
- **Buffer = per-kho** (không per-product, không global). Lều ở Vinh có thể 2 ngày, Hà Nội 1 ngày.
- **Buffer = thời gian phơi THẬT của món**, KHÔNG hạ theo số lượng tồn. Việc "có 2 lều nên vẫn cho thuê khi 1 cái đang phơi" do **phép tính tồn kho tự lo** (mục 3.2), không phải chỉnh buffer.
- **Giờ 8h/20h cấu hình trong `site_settings`** (đổi giờ theo mùa không cần sửa code).
- **Phụ phí ngoài khung = admin nhập tay** (số tiền + ghi chú), không dùng biểu phí tự động (hợp kiểu thương lượng case-by-case).

## 3. Thiết kế — Phase 1 (làm trước, đóng rủi ro gốc)

### 3.1 Schema

**a) Buffer per-kho** — thêm vào pivot đã có sẵn cột `quantity`:
```php
// migration: add_buffer_days_to_product_service_location
$table->unsignedTinyInteger('buffer_days')->default(0); // 0 = hành vi y hệt hiện tại
```
`Product::serviceLocations()` bổ sung `->withPivot('quantity', 'buffer_days')`. Thêm helper mirror `stockAt()`:
```php
public function bufferAt(int $serviceLocationId): int
{
    $this->loadMissing('serviceLocations');
    $loc = $this->serviceLocations->firstWhere('id', $serviceLocationId);
    return $loc ? (int) $loc->pivot->buffer_days : 0;
}
```

**b) Giờ mặc định** — thêm vào `site_settings`:
```php
$table->unsignedTinyInteger('pickup_hour')->default(8);
$table->unsignedTinyInteger('return_hour')->default(20);
```

### 3.2 Predicate mới (điểm cốt lõi — sửa tối thiểu)

Occupied window của đơn A với sản phẩm P **tại kho X** thành `[A.start, A.end + bufferAt(X)]`. Vì mọi query đã lọc theo `product_id` + kho, `buffer` là **hằng số đã biết** → chỉ cần nới mốc quét, **không cần SQL cộng ngày / cross-join**:

```php
$buffer = $location ? $product->bufferAt($location->id)
                    : $product->maxBufferAcrossLocations(); // nhánh global cũ: lấy max cho an toàn

OrderItem::query()
    ->whereHas('order', function ($q) use ($start, $end, $buffer, ...) {
        $q->whereIn('status', Order::activeStatuses())
          ->where('start_date', '<=', $end)
          ->where('end_date',   '>=', $start->copy()->subDays($buffer)); // ← thay đổi duy nhất
        // ... service_location / excludeOrderId giữ nguyên
    })
    ->where('product_id', $product->id)
    ->sum('quantity');
```

**Buffer lấy theo kho đang HỎI** (không theo kho của đơn chặn) — đúng ngữ nghĩa "món ở kho này phơi mấy ngày".

**Tồn kho × buffer tự kết hợp đúng** (không cần chỉnh tay): `còn = stockAt(X) − (đơn chồng lịch đã tính buffer)`. Vinh 2 lều, buffer 2, A thuê 1 lều ngày 10–12 → ngày 13 còn `2 − 1 = 1` (lều thứ 2 rảnh, B thuê được); nếu chỉ 1 lều → `1 − 1 = 0`, B chờ tới ngày 15.

### 3.3 `unavailableDates()` (tô lịch FE)
Nhận `$product` (+ optional `$location`) → tính `buffer` một lần, nới span mỗi đơn thành `[start, end + buffer]`:
```php
$occupiedEnd = $order->end_date->copy()->addDays($buffer);
if ($cursor->between($order->start_date, $occupiedEnd)) { $booked += ...; }
```

### 3.4 Lan toả tự động (KHÔNG phải sửa thêm)
- **Combo:** `comboAvailable()` / `comboInsufficientItems()` / `nextComboWindow()` gọi `availableQuantity()` từng món con → buffer áp đúng theo từng món tại kho đó.
- **Checkout / giỏ / đổi lịch:** đều qua `availableQuantity()` / `checkCart()`.
- **`excludeOrderId`:** không đổi (đơn tự loại mình; buffer vẫn áp cho đơn khác).

### 3.5 Admin & hiển thị
- Màn tồn kho theo kho: mỗi kho có **2 ô cạnh nhau** — *số lượng* + *số ngày phơi* (`buffer_days`), validate `integer|min:0|max:30`.
- Site settings: 2 ô `pickup_hour` / `return_hour` (`integer|min:0|max:23`).
- FE (trang sản phẩm / checkout / email xác nhận): hiển thị "Nhận từ {pickup_hour}h, trả trước {return_hour}h. Ngoài khung giờ vui lòng liên hệ." Lịch nên phân biệt trực quan ngày "đang giặt/phơi" vs "đang cho thuê" (nice-to-have).

## 4. Thiết kế — Phase 2 (bead riêng, làm sau — không ảnh hưởng tính đúng)

Giao/trả **ngoài khung** (khách muốn nhận 6h hoặc trả 22h):
- **Orders:** thêm `requested_pickup_time` / `requested_return_time` (nullable, mặc định = giờ chuẩn) để admin biết nhu cầu; thêm `extra_fee` (decimal, default 0) + `extra_fee_note` cho phụ phí admin nhập tay.
- **Checkout:** khách có thể chọn/ghi giờ mong muốn; nếu ngoài khung → hiện "shop sẽ liên hệ xác nhận phụ phí".
- **Admin đơn:** thấy giờ mong muốn, nhập `extra_fee` + ghi chú; cộng vào tổng.
- **Không** dùng biểu phí tự động. **Không** đụng availability (INVARIANT mục 2).

## 5. Ràng buộc trạng thái (edge case)
`activeStatuses()` = `pending/confirmed/renting`; `returned`/`cancelled` không chặn tồn.
- Rủi ro chính (chặn **đơn tương lai**) được giải quyết trọn: đơn tương lai luôn active → buffer neo theo `end_date` áp đủ.
- Rò rỉ nhỏ: nếu admin bấm `returned` *ngay lúc nhận đồ* (trước khi phơi) → đơn rớt khỏi tính tồn, buffer mất.
  - **Quy ước Phase 1:** `returned` = "đã về **và** xử lý xong / sẵn sàng cho thuê"; bấm sau khi giặt phơi. Ghi rõ trong UI admin.
  - **Nâng cấp tuỳ chọn (Phase 1.5):** vẫn tính buffer cho đơn `returned` còn trong cửa sổ `end_date + buffer`. Chưa làm nếu quy ước đủ.

## 6. Alternatives đã cân nhắc
| Phương án | Vì sao loại |
|---|---|
| **Global buffer** (1 số cả shop) | Giữ oan đồ khô nhanh bằng đồ lâu khô. |
| **Per-product buffer** (không theo kho) | Không phản ánh khác biệt kho (độ ẩm, năng lực xử lý); chủ shop chọn per-kho vì tồn kho vốn đã per-kho. |
| **Chỉ dùng giờ 8h/20h, bỏ buffer** | Cửa sổ đêm 12h đủ *giặt* nhưng không đủ *phơi* lều dính mưa → không đóng được rủi ro gốc. |
| **Hạ buffer theo số lượng tồn** | Sai — tồn kho đã tự xử lý (mục 3.2); hạ buffer là nói dối hệ thống, dễ quên nâng lại → giao đồ ướt. |
| **Mô hình slot theo giờ** | Viết lại toàn bộ availability/calendar/checkout; quá mức cho 1 shop COD; giờ vốn không cần vào phép tính (INVARIANT). |
| **Đệm hai chiều (cả trước start)** | Sai ngữ nghĩa — giặt/phơi chỉ sau khi đồ về. |

## 7. Hệ quả
**Tích cực:** đóng đúng rủi ro với thay đổi tối thiểu, tập trung tại 1 service; backward-compatible (mọi default = giữ nguyên hành vi); combo/checkout/lịch tự nhất quán; giờ giấc không làm phức tạp tồn kho.
**Cần lưu ý:** giảm khả dụng món có buffer > 0 (đúng chủ đích); phụ thuộc quy ước bấm `returned` đúng thời điểm; FE nên phân biệt ngày giặt/phơi (nice-to-have).

## 8. Kế hoạch triển khai

**Phase 1 (bead `bopcamping-s1ij`)**
1. Migration `add_buffer_days_to_product_service_location` + `pickup_hour`/`return_hour` vào `site_settings`.
2. `Product`: `withPivot('quantity','buffer_days')` + `bufferAt()` + `maxBufferAcrossLocations()`.
3. `AvailabilityService`: sửa predicate ở **cả hai nhánh** `availableQuantity()` + `unavailableDates()`.
4. Admin: ô `buffer_days` cạnh `quantity` theo kho; ô giờ trong site settings; hiển thị khung giờ ở FE.
5. **Test (regression, bắt buộc):**
   - buffer=0 ⇒ không hồi quy (hành vi y hệt hiện tại).
   - buffer=2: A `[10..12]` chặn B ngày 13, 14; cho phép ngày 15.
   - **Tồn × buffer:** Vinh 2 lều buffer 2, A thuê 1 → B vẫn thuê được ngày 13; Vinh 1 lều → B bị chặn.
   - Biên: B bắt đầu đúng `end + buffer` (chặn) vs `+ buffer + 1` (cho phép).
   - Combo dùng buffer từng món con theo kho.
   - `unavailableDates()` tô đúng ngày đệm.
   - Chạy được cả SQLite lẫn MySQL, collation-safe (CLAUDE.md).

**Phase 2 (bead riêng)** — giờ mong muốn + `extra_fee` admin nhập tay (mục 4). Tách khỏi Phase 1.

> Handoff artifact: file này (`artifacts/adr_turnaround_buffer.md`).

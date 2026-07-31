# PRD — Gán vị trí store cho từng combo

**Ngày:** 2026-07-31
**Trạng thái:** Approved (user chốt 4 quyết định trong phiên brainstorming 2026-07-31)
**Epic:** xem `bd show` · **Plan:** `artifacts/plan_combo_store_location.md`

## 1. Vấn đề

Combo hiện **không có** vị trí store riêng. Nó *suy ra* bằng cách lấy **giao** các kho đang mở của mọi món con (`Combo::commonOpenLocations()`). Hệ quả:

- Chủ shop **không quyết định được** combo nào bán ở kho nào. Muốn chỉ bán "Combo BBQ" ở Nghệ An thì không có cách nào, trừ khi đổi cấu hình kho của từng món con — mà việc đó ảnh hưởng cả sản phẩm lẻ.
- Khi gán kho, admin **không thấy** kho đó thực tế có những món gì, còn hàng hay không. Phải mở từng trang sản phẩm để tra.

## 2. Mục tiêu

Cho admin **gán tường minh** một hoặc nhiều store cho từng combo, và khi chọn store thì **thấy ngay** những món con nào có ở store đó.

**Ngoài phạm vi:** không đổi mô hình tồn kho (tồn vẫn thuộc sản phẩm, không có tồn riêng cho combo); không làm giá theo store; không đổi luồng checkout ngoài một validation mới.

## 3. Quyết định đã chốt

| # | Quyết định | Chọn | Lý do |
|---|---|---|---|
| 1 | Điều kiện hiển thị sản phẩm trong bảng | Tồn ban đầu (pivot `quantity`) **> 0** | User xác nhận "setting ban đầu" = số tồn ban đầu mỗi store; bản đầu viết "< 0" là nhầm dấu — tồn validate `min:0` nên `< 0` không khớp gì, danh sách sẽ luôn trống |
| 2 | Cơ sở để **chặn** chọn kho | **Tư cách thành viên pivot** của mọi món con — KHÔNG phải tồn > 0 | Xem mục 6 (rủi ro R2): prod chỉ 3/11 sản phẩm còn tồn, chặn theo tồn sẽ khoá sạch mọi kho của mọi combo |
| 3 | Ảnh hưởng phía khách | **Có** — kho gán quyết định combo bán ở đâu (badge, lọc `/combos`, khả dụng, checkout) | Gán kho mà không ảnh hưởng khách thì vô nghĩa |
| 4 | Combo bắt buộc có kho | **≥ 1 kho** (`min:1`) + backfill có fallback | Combo 0 kho rơi vào lỗ "không ràng buộc" ở giỏ (mục 6, R1) |

## 4. Người dùng & kịch bản

**Chủ shop (admin).** "Bộ BBQ chỉ có ở Nghệ An, đừng cho khách Hà Nội đặt."

1. Mở `/admin/combos`, sửa combo.
2. Thấy dải chip kho. Kho nào có món con **chưa được gán** kho đó thì bị disable kèm lý do: *"Đệm hơi: không phục vụ tại Hà Nội"*.
3. Tích **Nghệ An**. Bảng "Món tại kho này" hiện các món con **còn tồn > 0** ở Nghệ An kèm số tồn; nếu có món tồn 0 thì thêm dòng vàng *"2 món đang hết hàng tại kho này: Bàn gấp, Bếp BBQ"* — chỉ để biết đi nhập hàng, **không** chặn lưu.
4. Lưu. Khách ở Hà Nội không còn thấy combo này.

## 5. Yêu cầu chức năng

### FR-1 — Dữ liệu
- Pivot mới `combo_service_location` (`combo_id`, `service_location_id`, primary key cả hai). **Không** thêm cột tồn — tồn luôn thuộc sản phẩm.
- **Backfill bắt buộc:** mỗi combo hiện có được gán đúng tập `commonOpenLocations()` đang tính ra. Nếu tập đó **rỗng** → gán **tất cả kho đang mở** (đúng cách `product_service_location` đã backfill). Không bao giờ để combo 0 kho.

### FR-2 — Model `Combo`
| Method | Nghĩa |
|---|---|
| `serviceLocations()` | BelongsToMany — kho **được gán** |
| `openLocations()` | kho được gán ∩ `status=open` → **thay** `commonOpenLocations()` |
| `assignableLocationIds()` | kho đang mở mà **mọi** món con đều được gán (cơ sở để enable chip — quyết định #2) |
| `stockAtLocation(int $locId)` | `[productId => qty]` tồn pivot của từng món con tại kho đó (cho bảng hiển thị) |

`commonOpenLocations()` bị **xoá** — nghĩa đã đổi từ "suy ra" sang "được gán", để lại là bẫy. 4 chỗ gọi phải sửa: `AccountController:283`, `CartController:128`, `CartController:316`, `Shop/ComboController:207`.

### FR-3 — Admin
- `service_location_ids`: `required|array|min:1`; mỗi id phải ∈ `assignableLocationIds()` tính từ **tập items đang gửi lên** (không tin FE). Sai → 422 kèm tên món không phục vụ ở kho đó.
- `store`/`update` gọi `serviceLocations()->sync()`.
- Props form thêm `service_locations` (kho đang mở) và `location_stock`: `{ locationId: { productId: qty } }` cho toàn bộ sản phẩm (prod có 11 sản phẩm — nhẹ, không cần endpoint riêng).
- Kho `status=coming` **không** hiện trong picker (khớp cách sản phẩm làm).

### FR-4 — Admin UI
- Chip kho theo pattern `ProductForm.tsx:386`. Kho không assignable → disabled + lý do trên chip.
- Bảng "Món tại kho này": chỉ món **tồn > 0** tại kho đang chọn, kèm số tồn. Món tồn 0 gom vào một dòng cảnh báo vàng.
- Đổi/thêm/bớt món → tính lại ngay; kho vừa hợp lệ thành không hợp lệ thì **tự bỏ tích** + toast.
- Tách component `Admin/combo/ComboLocationPicker.tsx` — `Admin/Combos.tsx` đã 615 dòng, nhồi thêm sẽ vượt 700.

### FR-5 — Phía khách
- `locations` ở 4 chỗ gọi → `openLocations()`.
- `/combos?vi-tri=X` → **lọc** combo được gán X (hiện chưa lọc gì).
- `comboQuantitiesFor()` khi không chọn kho: chỉ quét kho **được gán của combo**, không phải mọi kho của món con.
- **Checkout:** đơn có combo mà kho `StoreResolver` chốt không ∈ kho gán của combo → từ chối, nêu tên combo + kho.

### FR-6 — Chặn bế tắc "combo 0 kho"
`lib/cart.ts`: `locations` **rỗng** hiện được coi là "không ràng buộc" (`locationConflict:157` trả `conflict:false`; `cartHasLocationConflict:166` loại dòng rỗng). Đổi thành **không bán được** → chặn ngay ở bước thêm vào giỏ. Áp cho cả sản phẩm, không riêng combo.

## 6. Rủi ro đã phân tích (dựa trên dữ liệu prod thật, 31/07/2026)

### R1 — Combo 0 kho gây bế tắc ở bước cuối
`locations` rỗng lọt cả 2 chốt của giỏ, rồi bị validation checkout (FR-5) từ chối vì kho phải ∈ tập rỗng → khách kẹt sau khi đã điền hết form. **Giảm thiểu:** FR-1 backfill fallback + FR-3 `min:1` + FR-6 sửa `cart.ts`.

Đã kiểm 31/07: không sản phẩm nào rỗng kho (dev 0/9, prod 0/11) nên FR-6 không chặn oan món nào.

### R2 — Chặn theo tồn sẽ khoá sạch (lý do đổi quyết định #2)
Prod chỉ 3/11 sản phẩm còn tồn (`Bạt Lót Lều` 2, `Tăng Bạt Bé` 1, `Thảm Dã Ngoại` 3). Kiểm 2 combo thật:

```
combo relax     = Bàn gấp(0) + Ghế thư giãn(0)              -> mọi món tồn 0
combo bbq-party = Bàn gấp(0) + Bếp BBQ(0) + Ghế SeaStar(0)  -> mọi món tồn 0
```

Nếu chặn theo tồn > 0: backfill theo membership thì admin **không lưu nổi** combo nào (validation đòi kho ∈ ∅ → 422 vĩnh viễn); backfill theo tồn thì **cả 8 combo mất hết kho** → rơi vào R1. Form sẽ hiện mọi kho disabled cho mọi combo. Tồn 0 ở đây là **trạng thái vận hành bình thường** của shop, không phải lỗi nhập liệu.

### R3 — Chốt chặn phía khách đã có sẵn, không phát sinh lỗi mới
| Tình huống | Khách thấy | Nơi xử lý |
|---|---|---|
| Combo không bán ở kho của giỏ | Chặn lúc thêm, toast rõ | `Cart.tsx:236` (có sẵn) |
| Admin thu hẹp kho khi khách đang có giỏ | `refresh` cập nhật → nút Đặt bị chặn | `Cart.tsx:341` (có sẵn) |
| "Đặt lại" đơn cũ mà combo không còn bán ở kho đó | Chặn lúc thêm, cùng toast | Thoái hoá êm |
| Kho được gán nhưng món hết theo ngày | "Hết trong khoảng này" | Đúng nghiệp vụ |

## 7. Đo lường thành công

- Admin gán được kho cho cả 8 combo prod **ngay từ lần đầu**, không cần nhập tồn trước.
- Không combo nào ở trạng thái 0 kho sau migration.
- Số đơn bị từ chối ở bước checkout vì lệch kho combo = **0** (mọi ca phải bị chặn sớm ở giỏ).

## 8. Liên quan

- `artifacts/plan_combo_store_location.md` — các bước triển khai
- `artifacts/design_spec_per_store_stock.md` — tồn theo kho (pivot sản phẩm)
- `artifacts/adr_combo_data_model.md` — mô hình dữ liệu combo
- `bopcamping-4bh` — epic tồn kho theo cửa hàng

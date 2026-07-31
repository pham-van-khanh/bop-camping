# Checklist test staging — Gán vị trí store cho từng combo

**Ngày:** 2026-07-31 · **Nhánh:** `feature/combo-store-location`
**PRD:** `artifacts/prd_combo_store_location.md` · **Epic:** `bopcamping-daet`

---

## 0. Chuẩn bị trước khi test (5 phút)

Tính năng chỉ thấy được nếu dữ liệu có **≥ 2 cơ sở** và **combo có món phân bố khác nhau giữa các cơ sở**. Nếu mọi sản phẩm đều có ở mọi cơ sở thì không ca nào bị chặn và bạn sẽ không thấy gì đặc biệt.

- [ ] **B1.** Có ít nhất 2 cơ sở `status = đang mở` (Admin → Điểm cắm trại)
- [ ] **B2.** Chọn 1 sản phẩm và **bỏ** một cơ sở khỏi nó (Admin → Sản phẩm → sửa → bỏ tích 1 cơ sở). Ghi lại tên: `___________________`
      → Dùng để kiểm ca "cơ sở bị chặn"
- [ ] **B3.** Chọn 1 sản phẩm khác và đặt **tồn = 0** ở một cơ sở. Ghi lại tên: `___________________`
      → Dùng để kiểm ca "cảnh báo hết hàng nhưng vẫn lưu được"
- [ ] **B4.** Ghi lại tồn hiện tại của các sản phẩm sẽ dùng, để đối chiếu số trên giao diện

> ⚠️ Sau khi deploy, migration tự gán cơ sở cho mọi combo cũ. **Kiểm ngay B5** trước khi làm gì khác.

- [ ] **B5.** Mở Admin → Combo, sửa lần lượt từng combo cũ → phần **"Bán tại cơ sở"** phải **đã có sẵn** cơ sở được tích. **Không combo nào để trống.**
      Nếu có combo trống → **dừng lại, báo ngay** (đó là lỗi backfill, sẽ gây kẹt ở bước đặt hàng)

---

## 1. Admin — gán cơ sở cho combo

Admin → **Combo** → bấm **Sửa** một combo.

- [ ] **1.1** Có mục **"Bán tại cơ sở *"** ngay dưới phần chọn sản phẩm, kèm chú thích *"(chỉ cơ sở phục vụ đủ mọi món)"*
- [ ] **1.2** Chip cơ sở nào combo đang bán thì có **dấu tích xanh**
- [ ] **1.3** Thêm sản phẩm ở **B2** vào combo → cơ sở mà sản phẩm đó không phục vụ phải **xám và bấm không được**
- [ ] **1.4** Ngay dưới chip có **dòng chữ đỏ nêu tên món**, ví dụ: *"Hà Nội: Lều Naturehike Cloud-Up 2 không phục vụ ở đây."*
      → Không có dòng này thì bạn sẽ không hiểu vì sao cơ sở bị chặn
- [ ] **1.5** Bỏ sản phẩm đó ra khỏi combo → cơ sở **mở lại được ngay**, dòng chữ đỏ biến mất
- [ ] **1.6** Tích một cơ sở, rồi **thêm lại** sản phẩm ở B2 → cơ sở đó **tự bỏ tích** kèm thông báo *"Đã bỏ chọn <cơ sở> vì món vừa đổi không phục vụ ở đó."*
- [ ] **1.7** Bỏ hết tích cơ sở rồi bấm **Lưu lại** → báo lỗi *"Combo phải bán ở ít nhất 1 cơ sở."*, **không** lưu được
- [ ] **1.8** Combo mới (bấm **Thêm combo**): chưa chọn sản phẩm thì mọi chip cơ sở **xám**, kèm chữ *"Chọn sản phẩm cho combo trước, rồi mới chọn được cơ sở."*

## 2. Admin — bảng "Món tại cơ sở này"

Vẫn trong form sửa combo, sau khi đã tích một cơ sở.

- [ ] **2.1** Xuất hiện khung **"Món tại &lt;tên cơ sở&gt;"**
- [ ] **2.2** Khung này **chỉ liệt kê món còn hàng** (tồn > 0) ở cơ sở đó, mỗi món kèm số **"còn N"**
- [ ] **2.3** Số "còn N" **khớp** tồn bạn ghi ở **B4** cho đúng cơ sở đó
- [ ] **2.4** Thêm sản phẩm ở **B3** (tồn 0) vào combo → món đó **KHÔNG** nằm trong danh sách trên, mà xuất hiện ở **dòng vàng**: *"N món đang hết hàng tại cơ sở này: … Vẫn lưu được — combo sẽ hiện hết hàng tới khi nhập thêm."*
- [ ] **2.5** ⚠️ **Quan trọng:** với combo ở 2.4, bấm **Lưu lại** vẫn **THÀNH CÔNG**. Tồn 0 chỉ là cảnh báo, **không được chặn lưu**.
      → Nếu bị chặn thì sai: shop đang có combo mà mọi món tồn 0, chặn kiểu đó sẽ không sửa được combo nào
- [ ] **2.6** Tích **2 cơ sở** cùng lúc → hiện **2 khung riêng**, số tồn mỗi khung khác nhau đúng theo từng cơ sở

## 3. Khách — trang Combo

- [ ] **3.1** Mở `/combos` → mỗi thẻ combo hiện badge cơ sở đúng như bạn gán ở Admin
- [ ] **3.2** Vào chi tiết một combo → dòng **"Cho thuê tại: …"** hiện đúng cơ sở đã gán
- [ ] **3.3** Gán một combo **chỉ 1 cơ sở** (ví dụ chỉ Vinh). Mở `/combos?vi-tri=ha-noi` (hoặc bấm lọc cơ sở trên trang) → combo đó **không xuất hiện**
- [ ] **3.4** Mở `/combos?vi-tri=vinh` → combo đó **có xuất hiện**
- [ ] **3.5** Chọn khoảng ngày trên `/combos` → combo còn hàng xếp **trước**, combo hết hàng bị **làm mờ + badge "Hết trong khoảng này"**

## 4. Khách — số "còn" phải theo đúng cơ sở đã gán

Ca quan trọng nhất của phần khách. Cần một combo **chỉ gán 1 cơ sở**.

- [ ] **4.1** Ghi lại: combo `___________`, chỉ bán ở cơ sở `___________`
- [ ] **4.2** Làm cho combo đó **hết hàng tại cơ sở đã gán** (đặt một đơn chiếm hết tồn của một món ở cơ sở đó, hoặc sửa tồn về 0)
- [ ] **4.3** Mở `/combos` có chọn khoảng ngày đó → combo hiện **"Hết trong khoảng này"**
- [ ] **4.4** ⚠️ Dù cơ sở **khác** vẫn còn hàng món đó, combo vẫn phải hiện **hết**.
      → Nếu nó hiện "còn hàng" thì sai: đang lấy tồn của cơ sở mà combo không bán

## 5. Khách — giỏ hàng (chuỗi bảo vệ)

- [ ] **5.1** Thêm vào giỏ một combo chỉ bán ở cơ sở A
- [ ] **5.2** Thử thêm một sản phẩm **chỉ phục vụ cơ sở B** → **bị chặn**, hiện thông báo dạng: *"Giỏ hiện tại đang thuê tại A. "&lt;tên sản phẩm&gt;" chỉ phục vụ tại B nên không thể thêm cùng giỏ. Mỗi đơn chỉ thuê tại một vị trí."*
- [ ] **5.3** **Giỏ tự cập nhật khi admin đổi:** giữ combo trong giỏ, vào Admin đổi combo sang cơ sở khác, rồi **tải lại trang giỏ** → cơ sở của dòng combo đổi theo
- [ ] **5.4** **Ca mất cơ sở chung:** dựng giỏ có combo (cơ sở A) + sản phẩm (cơ sở B) bằng cách thêm khi còn hợp lệ rồi vào Admin đổi cơ sở combo. Tải lại giỏ →
      - hiện cảnh báo *"Một số thiết bị đã đổi vị trí phục vụ nên giỏ không còn cùng một vị trí…"*
      - nút **"Đặt yêu cầu thuê"** bị **mờ / bấm không được**
- [ ] **5.5** Gỡ bớt món không phù hợp → cảnh báo mất, nút đặt **dùng lại được**

## 6. Khách — đặt hàng

- [ ] **6.1** Giỏ chỉ có combo, cơ sở khớp → **đặt được**, đơn tạo thành công
- [ ] **6.2** Mở Admin → Đơn thuê, xem đơn vừa tạo → **cơ sở của đơn đúng** cơ sở đã chọn
- [ ] **6.3** Đơn chỉ có sản phẩm lẻ (không combo) → đặt bình thường, **không bị ảnh hưởng** gì bởi tính năng này

## 7. Không làm hỏng thứ đang chạy (hồi quy)

- [ ] **7.1** Trang chủ: ô đặt lịch trên banner → mở popup → chọn ngày → sang `/thiet-bi` bình thường
- [ ] **7.2** `/thiet-bi` lọc theo ngày và cơ sở vẫn đúng, món hết hàng vẫn mờ + badge
- [ ] **7.3** Thêm **sản phẩm lẻ** vào giỏ → đặt đơn → thành công
- [ ] **7.4** Admin → Combo: thêm/xoá ảnh combo, ẩn/hiện combo, xoá combo vẫn hoạt động
- [ ] **7.5** Admin → Lịch giao: mở **ngày cuối tháng** → vẫn thấy việc của ngày đó
      *(vá riêng đã lên prod, kiểm lại cho chắc)*
- [ ] **7.6** Trang tài khoản → lịch sử đơn → nút **"Đặt lại"** một đơn cũ có combo:
      - nếu combo còn bán ở cơ sở đó → thêm được vào giỏ
      - nếu không còn → bị chặn kèm thông báo rõ (**không** được lỗi trắng trang)

---

## Đã kiểm tự động (bạn không cần làm lại)

| Lớp | Số lượng | Ghi chú |
|---|---|---|
| Test PHP | **780** | gồm backfill 2 nhánh, validate admin, lọc `/combos`, khả dụng theo cơ sở, checkout 3 nhánh |
| Test JS | **75** | gồm 8 test cho picker cơ sở, 8 test cho chốt chặn giỏ |
| Mutation | **15/15 bị bắt** | T1 5/5 · T2 6/6 · T5 4/4 — cố tình phá logic để chắc test không "xanh rỗng" |

**Đã verify trên trình duyệt thật** (localhost, dữ liệu dựng riêng): mục **1, 2** (admin picker: chặn đúng lý do, bảng chỉ hiện món tồn > 0, cảnh báo tồn 0 vẫn lưu được, tự bỏ tích), mục **3.2, 3.3, 3.4, 3.5**, mục **4** (combo chỉ-Vinh ra 0 khi Vinh hết dù Hà Nội còn 5), mục **5.1–5.4** (chặn thêm giỏ, giỏ tự cập nhật, nút đặt bị disable).

**Chưa verify trên trình duyệt** — cần bạn kiểm trên staging: mục **0 (B5 backfill trên dữ liệu thật)**, **6** (đặt đơn thật qua form), **7.6** ("Đặt lại" đơn cũ).

## Điểm cần để ý khi test

1. **`bopcamping-65k9`** — N+1 ở `ComboController::shape()`: trang `/combos` tốn thêm 1 query mỗi combo. Chưa vá (đang xử lý ở session riêng). Không sai kết quả, chỉ chậm hơn. Nếu `/combos` chậm rõ thì là nguyên nhân này.
2. **`bopcamping-ry4u`** — trên staging nếu tồn theo cơ sở chưa nhập thì bảng "Món tại cơ sở này" sẽ gần như trống. Đó là **dữ liệu**, không phải lỗi.
3. Nếu gặp combo **0 cơ sở** ở bất kỳ đâu → báo ngay, đó là ca duy nhất có thể gây kẹt ở bước đặt hàng.

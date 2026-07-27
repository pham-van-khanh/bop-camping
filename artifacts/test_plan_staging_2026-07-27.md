# Kế hoạch test staging trước khi lên production

- **Ngày:** 2026-07-27
- **Môi trường test:** staging — **https://staging.bopcamping.cloud** (nhánh `develop`, bản `7c2c99b`)
- **Đích deploy:** production — bopcamping.com (`feat/scaffold-laravel`)
- **Mục tiêu:** xác nhận toàn bộ tính năng trong bảng tổng hợp chạy đúng, rồi mới deploy 1 lượt.

## Chuẩn bị trước khi test
- [ ] Mở staging bằng trình duyệt ẩn danh (tránh cache).
- [ ] Có tài khoản **admin** để đăng nhập `/admin` (SĐT + mật khẩu admin của shop).
- [ ] Có 1 số điện thoại để đăng nhập **khách** (SĐT + tên + email; OTP gửi qua email lần đầu).
- [ ] Biết ít nhất 1 sản phẩm đang mở bán để test (vd trang Thuê đồ).

> Cách đánh dấu: mỗi bước có ô `[ ]` → tick `[x]` khi đạt. Nếu sai, ghi chú ở cột "Ghi chú/lỗi" cuối mỗi mục.

---

## 1. Chọn buổi ở trang sản phẩm (v7sh) — QUAN TRỌNG NHẤT
Mô hình mới: thuê **đúng 1 ngày** → chọn **Buổi sáng / Buổi chiều / Cả ngày**; giá đổi theo buổi. Thay cho bản "chọn giờ tự do" cũ.

**1.1 Hiển thị & giá**
- [ ] Vào 1 sản phẩm → chọn **1 ngày** (ngày nhận = ngày trả).
- [ ] Hiện 3 nút: **Buổi sáng (8h–14h) · Buổi chiều (14h–20h) · Cả ngày (8h–20h)** (giờ theo cài đặt shop).
- [ ] Sản phẩm có ưu đãi trả sớm → nút sáng/chiều hiện **−X%**.
- [ ] Bấm **Buổi sáng** → "Tạm tính" giảm đúng X% (vd 100.000đ → 90.000đ), có gạch giá gốc.
- [ ] Bấm **Cả ngày** → về giá đầy đủ (không giảm).
- [ ] Chọn **nhiều ngày** (khác ngày) → 3 nút buổi **ẩn**, tính theo số ngày như cũ.

**1.2 Đưa vào giỏ & checkout**
- [ ] Chọn Buổi sáng → **Thêm vào giỏ** → mở Giỏ thuê.
- [ ] Dòng giỏ hiện nhãn **"🕑 Buổi sáng · 8h–14h −X%"** và giá đã giảm.
- [ ] Đặt đơn (điền tên/SĐT) → đặt thành công.

**1.3 Lưu vào đơn (admin thấy)**
- [ ] Vào `/admin` → Đơn thuê → mở đơn vừa đặt.
- [ ] Header + khối chi tiết hiện **Buổi: Buổi sáng · 8h–14h** và **Giờ: nhận 08:00 · trả 14:00**.

**1.4 Khách xem lại**
- [ ] Đăng nhập khách → **Tài khoản** → mở đơn → thấy **Buổi** + **Giờ nhận/trả**.

_Ghi chú/lỗi:_ ______________________________________________

---

## 2. Ô khung giờ riêng dưới "Ngày thuê" + liên hệ Zalo (tinh chỉnh)
- [ ] Ở trang sản phẩm, ngay **dưới ô "Ngày thuê"** có 1 ô riêng, **luôn hiện** (kể cả khi chưa chọn ngày).
- [ ] Ô có 2 dòng: "🕗 Nhận từ 8h, trả trước 20h…" và "⏰ Muốn giờ nhận/trả khác? **Liên hệ Zalo**…".
- [ ] Bấm **Liên hệ Zalo** → mở đúng Zalo của shop (tab mới).

_Ghi chú/lỗi:_ ______________________________________________

---

## 3. Màn hình đơn admin riêng (l8br)
- [ ] `/admin` → Đơn thuê: danh sách gọn (mã, khách, ngày, tiền, trạng thái) + nút đổi trạng thái nhanh.
- [ ] **Bấm 1 dòng đơn** → chuyển sang **màn hình riêng** của đơn đó (URL `/admin/orders/{id}`).
- [ ] Màn chi tiết hiện đủ: khách, thiết bị, thanh toán, ưu đãi, ghi chú.
- [ ] Có nút **← Danh sách đơn** quay lại.
- [ ] Đổi trạng thái ở màn chi tiết (vd Chờ xác nhận → Đã xác nhận) → lưu đúng.
- [ ] Với **đơn gộp (cha)**: màn chi tiết liệt kê **các đợt con**, bấm 1 đợt → mở màn riêng đợt đó; đợt con có link **về đơn gộp**.
- [ ] Đổi trạng thái nhanh **trên danh sách** vẫn hoạt động (không mở chi tiết khi bấm nút).

_Ghi chú/lỗi:_ ______________________________________________

---

## 4. Giá nửa ngày (jrh8)
- [ ] Sản phẩm có **ưu đãi trả sớm** > 0% (đặt ở màn sửa SP nếu cần).
- [ ] Thuê 1 ngày + Buổi sáng/chiều → tiền thuê giảm đúng %, **tiền cọc GIỮ NGUYÊN** (không giảm).
- [ ] Thuê 1 ngày + Cả ngày → **không** giảm.
- [ ] Đơn có **combo**: combo **không** được giảm ưu đãi trả sớm (chỉ sản phẩm lẻ).

_Ghi chú/lỗi:_ ______________________________________________

---

## 5. Phụ phí ngoài khung giờ (h4to)
- [ ] `/admin` → mở 1 đơn (thường hoặc đợt con) → mục **Phụ phí ngoài khung giờ**.
- [ ] Nhập số tiền + ghi chú (vd "giao sớm 6h") → Lưu → **"Trả khi nhận"** tăng đúng bằng phụ phí.
- [ ] Nhập số **âm** → bị chặn (báo lỗi).
- [ ] Với **đơn gộp (cha)**: không cho đặt phụ phí trực tiếp (chỉ trên đợt con).

_Ghi chú/lỗi:_ ______________________________________________

---

## 6. Buffer giặt/phơi per-kho (s1ij)
- [ ] `/admin` → Sản phẩm → sửa 1 SP → đặt **số ngày giặt/phơi (buffer)** cho 1 kho (vd 2 ngày).
- [ ] Đặt 1 đơn thuê SP đó tại kho đó, trả vào ngày X.
- [ ] Thử đặt tiếp SP đó **trong khoảng X+1..X+buffer** → hệ thống báo **hết/không đủ** (đang phơi).
- [ ] Sau khoảng buffer → đặt lại được bình thường.
- [ ] Kho khác (buffer khác) không bị ảnh hưởng lẫn nhau.

_Ghi chú/lỗi:_ ______________________________________________

---

## 7. Giờ giao/trả mặc định của shop (pickup/return + giờ chia buổi)
- [ ] `/admin` → **Cài đặt shop** → có ô **Giờ giao**, **Giờ trả**, **Giờ chia buổi sáng/chiều**.
- [ ] Đổi "Giờ chia buổi" = 12 → Lưu.
- [ ] Quay lại trang sản phẩm (1 ngày) → **Buổi sáng = 8h–12h**, **Buổi chiều = 12h–20h** (cập nhật theo cài đặt).
- [ ] Nhập giá trị ngoài 0–23 → bị chặn.
- [ ] (Nhớ đặt lại **14** sau khi test nếu muốn về mặc định.)

_Ghi chú/lỗi:_ ______________________________________________

---

## 8. Tách màn thêm/sửa sản phẩm (o4kw)
- [ ] `/admin` → Sản phẩm → **Thêm sản phẩm** mở **màn hình riêng** (không phải popup nhỏ).
- [ ] **Sửa** 1 sản phẩm cũng mở màn riêng, điền sẵn dữ liệu.
- [ ] Thêm/sắp xếp **ảnh phụ** hoạt động.
- [ ] Lưu → quay lại danh sách, dữ liệu cập nhật đúng.

_Ghi chú/lỗi:_ ______________________________________________

---

## 9. Ngày thuê riêng từng món (u1nb)
- [ ] Thêm 2 sản phẩm vào giỏ với **2 khoảng ngày khác nhau** (dùng nút "Đặt lại"/chọn ngày cho từng dòng nếu có).
- [ ] Đặt đơn → mỗi món giữ đúng khoảng ngày riêng của nó.
- [ ] Tồn kho tính theo **ngày của từng món** (không khoá dư ngày ngoài khoảng món).

_Ghi chú/lỗi:_ ______________________________________________

---

## 10. Hồi quy — đơn cha/con (wtuv) — đã có trên prod, kiểm tra không vỡ
- [ ] Giỏ có **≥ 2 khoảng ngày** → đặt đơn → sinh **1 đơn gộp (cha) + các đợt con**.
- [ ] Voucher/giảm giá áp trên tổng đơn gộp, phân bổ về con đúng.
- [ ] Huỷ **cả cụm** từ đơn cha hoạt động.
- [ ] Email xác nhận từng đợt gửi đúng (nếu bật).

_Ghi chú/lỗi:_ ______________________________________________

---

## 11. Kiểm tra chung (smoke)
- [ ] Trang chủ, Thuê đồ, Combo, Giới thiệu, Tra cứu đơn, các trang **Chính sách** mở bình thường.
- [ ] Đăng nhập/đăng xuất khách (OTP email) hoạt động.
- [ ] Không có lỗi hiển thị rõ ràng (trang trắng, vỡ giao diện) trên mobile + desktop.

_Ghi chú/lỗi:_ ______________________________________________

---

## Kết luận
- [ ] **Tất cả mục trên PASS** → sẵn sàng deploy production.
- Người test: ____________________  Ngày: __________

### Sau khi bạn xác nhận PASS, deploy 1 lượt lên production
Claude sẽ chạy:
```bash
git checkout feat/scaffold-laravel
git merge --no-ff develop
git push origin feat/scaffold-laravel   # auto-deploy bopcamping.com + chạy 8 migration
```
Sau deploy: kiểm nhanh bopcamping.com (mục 1, 3, 11) để chắc chắn.

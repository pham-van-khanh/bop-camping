# Go-live Production — Deploy & Test lại (tài liệu cuối)

- **Ngày:** 2026-07-27
- **Nguồn:** `develop` (staging, bản `e157009`) → **production** `feat/scaffold-laravel` → bopcamping.com
- **Đã test & PASS trên staging** theo `test_plan_staging_2026-07-27.md` (bao gồm feedback mục 6/7/9B).
- **Chất lượng:** 580 test pass · TypeScript · Pint · build sạch · code-check (0 Critical/High).

---

## A. Tính năng sẽ lên production (lần deploy này)

| Tính năng | Ghi chú |
|---|---|
| Chọn buổi sáng/chiều/cả ngày ở trang SP (v7sh) | thay "giờ tự do"; giá đổi theo buổi |
| Khung giờ buổi 2 cửa sổ có khoảng nghỉ | sáng 8–12, chiều 13–20 (chỉnh ở Cài đặt shop) |
| Giá nửa ngày (jrh8) | buổi sáng/chiều giảm theo % SP; cọc giữ nguyên |
| Phụ phí ngoài khung giờ (h4to) | admin nhập tay trên đơn |
| Buffer giặt/phơi per-kho (s1ij) | chặn ngày phơi sau khi trả |
| **CHỈ khoá tồn khi đã xác nhận** | đơn pending không giữ chỗ |
| Ngày thuê riêng từng món (u1nb) | tồn kho theo ngày từng món |
| Tách màn thêm/sửa SP (o4kw) | ProductForm màn riêng |
| Màn hình đơn admin riêng (l8br) | list → chi tiết; cha↔con |
| Đơn khách hiện buổi/giờ + ô khung giờ dưới ngày thuê | trang SP + Tài khoản |
| Modal "Đặt lại": chọn store + ngày bận theo store + chọn buổi 1 ngày + nới rộng | trang Tài khoản (rộng 1100) |

> Đơn cha/con (wtuv) **đã có sẵn** trên production từ trước.

---

## B. Lệnh deploy (Claude chạy khi bạn xác nhận PASS staging)

```bash
git checkout feat/scaffold-laravel
git pull --rebase
git merge --no-ff develop
git push origin feat/scaffold-laravel   # auto-deploy bopcamping.com
```

**DB:** deploy sẽ chạy **9 migration** (đều thêm cột nullable/default → an toàn với đơn cũ):
`add_dates_to_order_items` · `add_buffer_days...` · `add_pickup_return_hours...` · `add_early_return_discount...` · `add_is_half_day...` · `add_requested_times_and_extra_fee...` · `add_session_to_orders` · `add_session_split_hour...` · `replace_session_split_with_windows`

> Lưu ý: migration cuối thêm `morning_end_hour`/`afternoon_start_hour` và bỏ `session_split_hour` — guard sẵn, chạy đúng trên prod.

---

## C. Kiểm tra NGAY SAU deploy trên **bopcamping.com** (smoke nhanh ~10 phút)

> Mở trình duyệt ẩn danh. Nếu 1 mục fail → xem mục E (rollback).

**C1. Site sống & không vỡ**
- [ ] Trang chủ, Thuê đồ, Combo, Giới thiệu, Tra cứu đơn, trang Chính sách mở bình thường.
- [ ] Không lỗi trang trắng/vỡ giao diện (desktop + mobile).

**C2. Chọn buổi + giá (v7sh) — luồng khách quan trọng nhất**
- [ ] Vào 1 sản phẩm → chọn **1 ngày** → hiện 3 nút **Buổi sáng / Buổi chiều / Cả ngày** (đúng giờ Cài đặt shop).
- [ ] Bấm Buổi sáng (SP có ưu đãi) → giá giảm đúng %; Cả ngày → giá đầy đủ.
- [ ] Ô khung giờ + "Liên hệ Zalo" hiện dưới ô Ngày thuê.
- [ ] Thêm vào giỏ → giỏ hiện nhãn buổi + giá đúng → **đặt đơn thành công**.

**C3. Admin thấy & xử lý đơn (l8br + h4to + confirm-lock)**
- [ ] `/admin` → Đơn thuê → bấm đơn vừa đặt → **màn chi tiết riêng**, hiện **Buổi + Giờ**.
- [ ] Nhập **phụ phí** → "Trả khi nhận" tăng đúng.
- [ ] Đổi trạng thái → **Đã xác nhận** → kiểm 1 lần: đặt trùng ngày/SP đó giờ **bị chặn** (trước xác nhận thì không).

**C4. Cài đặt giờ (khung 2 cửa sổ)**
- [ ] `/admin` → Cài đặt shop → có 4 ô giờ (giao / trả / kết thúc sáng / bắt đầu chiều).
- [ ] Đổi thử (vd sáng 8–12, chiều 13–21) → Lưu → trang SP cập nhật đúng khung giờ.
- [ ] Nhập sai thứ tự (đầu chiều < cuối sáng) → **bị chặn**.

**C5. Tài khoản khách + Đặt lại (9B)**
- [ ] Đăng nhập khách → Tài khoản (màn rộng) → mở đơn → thấy **Buổi + Giờ nhận/trả**.
- [ ] **Đặt lại đơn** → modal rộng: chọn **cửa hàng**, lịch **disable ngày đã hết theo store**, thuê 1 ngày → **chọn buổi** → thêm vào giỏ đúng.

**C6. Hồi quy nhanh (không được vỡ)**
- [ ] Đặt đơn **nhiều khoảng ngày** → tách **đơn cha + con** đúng.
- [ ] Đăng nhập/đăng xuất khách (OTP email) hoạt động.
- [ ] Đơn cũ (trước deploy) mở ở admin & tài khoản vẫn hiển thị bình thường (không lỗi thiếu buổi/giờ).

---

## D. Kết luận
- [ ] **C1–C6 PASS** → deploy thành công, thông báo team.
- Người kiểm: ____________________  Giờ: __________

## E. Rollback (nếu có sự cố nghiêm trọng)
- Auto-deploy đã có **health-check + auto-rollback** (nếu `/up` fail → tự lùi release trước).
- Lùi thủ công: SSH server → `scripts/rollback.sh` (lùi về release trước đó). Migration đều cộng cột nullable nên rollback code an toàn; **không** cần rollback DB.
- Báo Claude kèm mô tả lỗi + ảnh chụp để xử lý.

---

*Sau khi C PASS: đóng các bead v7sh/l8br/o4kw/s1ij/jrh8/h4to/u1nb + gs09/v31n/k9ad.*

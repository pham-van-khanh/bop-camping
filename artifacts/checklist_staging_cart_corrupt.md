# Checklist nghiệm thu — Giỏ hỏng không làm trắng site (bopcamping-gccu)

- **Ngày:** 2026-08-01
- **Nhánh:** `fix/cart-corrupt-whiteout`
- **Nơi test:** staging (`https://staging.bopcamping.cloud`) — **cần merge vào `develop` trước**

---

## 0. Chuẩn bị (1 phút)

Mở trình duyệt trên **máy tính**, vào staging, rồi mở bảng điều khiển:

- **Chrome / Edge:** `F12` → tab **Console**
- **Safari:** bật Develop menu trước (Cài đặt → Nâng cao → *Hiện menu Develop*), rồi `⌥⌘C`

Các bước dưới đều là **dán một dòng vào Console rồi Enter**. Không cần biết code.

> **Không sao nếu làm hỏng:** mọi thứ chỉ nằm trong trình duyệt của bạn, không đụng tới dữ
> liệu shop. Muốn về sạch bất cứ lúc nào thì dán dòng này rồi tải lại trang:
>
> ```
> localStorage.removeItem('bop_cart_v1'); location.reload()
> ```

---

## 1. ⭐ QUAN TRỌNG NHẤT — giỏ hàng bình thường vẫn phải chạy y như cũ

Đây là phép thử đáng giá nhất, và cũng là **rủi ro thật sự của bản vá này**. Bản vá lọc bỏ
các dòng giỏ "trông có vẻ hỏng". Nếu tôi lọc quá tay thì **giỏ thật của khách bị vứt oan** —
tệ hơn cả lỗi ban đầu, vì nó xảy ra với *mọi* khách chứ không chỉ khách có dữ liệu hỏng.

Làm bằng tay như một khách thật, **không dùng Console**:

| # | Việc | Kết quả đúng |
|---|---|---|
| 1.1 | Vào `/thiet-bi`, chọn một sản phẩm, chọn ngày, thêm vào giỏ | Badge trên đầu trang tăng lên 1 |
| 1.2 | Thêm sản phẩm thứ hai, số lượng 2 | Badge thành 3 |
| 1.3 | Thêm một **combo** | Badge tăng đúng, combo hiện trong giỏ |
| 1.4 | Vào `/gio-thue` | Thấy **đủ** các món vừa thêm, giá và cọc đúng |
| 1.5 | Tăng/giảm số lượng, xoá một dòng | Số tiền cập nhật đúng |
| 1.6 | **Tải lại trang** (F5) | Giỏ **còn nguyên**, không mất món nào |
| 1.7 | Đóng hẳn trình duyệt, mở lại vào `/gio-thue` | Giỏ vẫn còn nguyên |
| 1.8 | Đặt một đơn thật tới bước cuối | Đơn tạo được, admin thấy đủ món |

> ❌ **Nếu bất kỳ món nào biến mất** ở bước 1.6 hoặc 1.7 → **báo tôi ngay**, đừng đẩy lên
> production. Đó đúng là kiểu lỗi mà bản vá này có thể gây ra.

---

## 2. Thấy tận mắt lỗi cũ có thật (tuỳ chọn — làm trên **production**)

Bước này để bạn tin rằng lỗi là thật chứ không phải tôi nói suông. Làm trên
`https://bopcamping.vn` (bản **chưa vá**).

**2.1** Dán vào Console rồi Enter:

```
localStorage.setItem('bop_cart_v1', '{"items":[{"product_id":1,"quantity":1}]}')
```

**2.2** Tải lại trang (F5).

**Kết quả đúng của bản CHƯA VÁ:** trang **trắng xoá**. Bấm sang trang khác — Trang chủ,
Thuê đồ, Combo, Giới thiệu — **trang nào cũng trắng**. Đây chính là điều khách gặp phải.

**2.3** Dọn lại cho máy bạn về bình thường:

```
localStorage.removeItem('bop_cart_v1'); location.reload()
```

> Đây là lý do lỗi được xếp P0: khách không hề biết vì sao, và không có cách nào tự sửa
> ngoài việc xoá dữ liệu trang — thao tác mà gần như không khách nào biết làm. Họ chỉ nghĩ
> "web hỏng rồi" và bỏ đi.

---

## 3. Bản đã vá — cùng dữ liệu đó phải chạy bình thường

Làm trên **staging**.

**3.1** Dán **đúng dòng đã làm trắng production** ở bước 2.1, rồi F5.

| Kiểm | Kết quả đúng |
|---|---|
| Trang chủ | **Hiện bình thường**, không trắng |
| `/thiet-bi`, `/combos`, `/gioi-thieu` | Đều bình thường |
| `/gio-thue` | Hiện *"Giỏ thuê đang trống"* |
| Badge giỏ trên đầu trang | Bằng **0**, không phải `NaN` |

**3.2** Thử thêm mấy kiểu dữ liệu hỏng khác — mỗi dòng dán xong thì F5:

```
localStorage.setItem('bop_cart_v1', 'xin chao')
```
```
localStorage.setItem('bop_cart_v1', '42')
```
```
localStorage.setItem('bop_cart_v1', 'null')
```
```
localStorage.setItem('bop_cart_v1', '{khong-phai-json')
```

**Kết quả đúng:** cả 4 lần site đều chạy bình thường, giỏ trống, không trang nào trắng.

---

## 4. Giỏ hỏng MỘT PHẦN — phải giữ lại món lành

Đây là điểm tinh tế nhất: giỏ có cả món tốt lẫn dữ liệu rác thì **không được vứt cả giỏ**.

**4.1** Dán (đã cài sẵn 1 món thật + 4 dòng rác):

```
localStorage.setItem('bop_cart_v1', JSON.stringify([{id:1,name:'Lều thử',cat:'leu',grad:'',price:100000,deposit:200000,qty:2,start:'2026-09-01',end:'2026-09-03'},null,'rác',42,{id:99}]))
```

**4.2** F5 rồi vào `/gio-thue`.

| Kiểm | Kết quả đúng |
|---|---|
| Số món trong giỏ | **Đúng 1 món** |
| Tên món | **Không phải "Lều thử"** mà là tên sản phẩm thật (`id:1` trong dữ liệu trên). Trang giỏ tự làm tươi tên/giá theo server — đúng như thiết kế, không phải lỗi |
| Số lượng | **2** — giữ nguyên, đây mới là thứ phải đúng |
| Ngày thuê | `01/09 → 03/09` — giữ nguyên |
| 4 dòng rác | Biến mất, không gây lỗi gì |
| Trang giỏ | Hiện đầy đủ, có ô chọn địa chỉ |

**4.3** Dọn: `localStorage.removeItem('bop_cart_v1'); location.reload()`

---

## 5. Ô chọn địa chỉ — cùng loại lỗi, đã vá kèm

Phần chọn Tỉnh/Xã có bộ nhớ đệm riêng, dính **đúng kiểu lỗi** như giỏ hàng.

**5.1** Vào `/gio-thue` (có ít nhất 1 món), dán:

```
sessionStorage.setItem('bopcamping.divisions.provinces.v2', '{"khong-phai-mang":true}')
```

**5.2** F5.

| Kiểm | Kết quả đúng |
|---|---|
| Trang giỏ | Hiện bình thường, **không trắng** |
| Ô "Tỉnh / Thành phố" | Bấm vào vẫn bung danh sách 34 tỉnh |
| Gõ `nghe an` | Ra "Tỉnh Nghệ An" |

---

## 6. Trên điện thoại (nhanh)

Không cần Console. Chỉ cần xác nhận **không có gì hỏng thêm**:

- Vào staging bằng điện thoại, thêm 2 món vào giỏ
- Vào trang giỏ, kiểm tra đủ món, chọn được địa chỉ
- Tải lại trang → giỏ còn nguyên

---

## 7. Kết luận

- [ ] Mục 1 **toàn bộ** đạt → giỏ bình thường không bị ảnh hưởng *(bắt buộc)*
- [ ] Mục 3 đạt → dữ liệu hỏng không còn làm trắng site
- [ ] Mục 4 đạt → giữ được món lành
- [ ] Mục 5 đạt → ô địa chỉ an toàn
- [ ] Mục 6 đạt → điện thoại bình thường

**Chỉ cần mục 1 có một dòng sai là DỪNG**, báo tôi trước khi đẩy lên production.

---

## Điều bản vá này KHÔNG giải quyết

Nó chặn **nguyên nhân đã biết** (giỏ và bộ nhớ đệm địa chỉ hỏng), **không** chặn được
**loại lỗi**: bất kỳ lỗi lập trình nào khác trong khung layout dùng chung vẫn có thể làm
trắng cả site y như vậy.

Việc bịt hẳn loại lỗi đó nằm ở `bopcamping-5gkt` — cần bạn quyết hiện thông báo gì cho
khách, và có gửi lỗi về máy chủ để theo dõi không (hiện chưa có hạ tầng thu lỗi phía web).

# Checklist nghiệm thu — Badge "Còn N" nói rõ kho (bopcamping-kvcc)

- **Ngày:** 2026-08-01
- **Nhánh:** `fix/stock-badge-best-location` → đã merge `develop`
- **TRƯỚC khi vá:** `https://bopcamping.vn` (production, chưa có bản vá)
- **SAU khi vá:** `https://staging.bopcamping.cloud`

---

## ⚠️ ĐỌC TRƯỚC — rất có thể bạn sẽ KHÔNG thấy gì khác

Bản vá chỉ hiện tên kho khi **tồn kho hai cơ sở LỆCH nhau**. Nếu mọi sản phẩm trên
production đang có tồn **bằng nhau** ở Vinh và Hà Nội thì badge **giống hệt trước và sau** —
và đó là **đúng**, không phải lỗi.

Theo ghi chú của bạn hôm 30/07 trong `bopcamping-ry4u`, ví dụ *Bạt Lót Lều* có Nghệ An = 1
và Hà Nội = 1 → **bằng nhau**. Nên rất có thể hiện chưa sản phẩm nào lệch.

**Vì vậy mục 1 dưới đây là bước bắt buộc**: kiểm xem có sản phẩm nào lệch không, và nếu
không có thì tự tạo ra một cái để thấy được sự khác biệt.

---

## 1. Tìm (hoặc tạo) một sản phẩm có tồn LỆCH giữa hai cơ sở

Làm trên **staging** trước.

1. Vào `/admin/products`, mở sửa một sản phẩm đang còn hàng.
2. Nhìn phần tồn kho theo cơ sở — hai ô số cho Vinh và Hà Nội.
3. **Ghi lại hai số hiện tại** (để lát nữa trả về đúng như cũ).
4. Đặt lệch nhau hẳn: **Vinh = 3, Hà Nội = 1**. Lưu.

> Sản phẩm này gọi là **[SP-LỆCH]** trong các bước sau.

Rồi chọn thêm một sản phẩm khác, đặt **bằng nhau: Vinh = 2, Hà Nội = 2**. Lưu.

> Gọi là **[SP-ĐỀU]**. Nó dùng để chứng minh bản vá **không** làm rối những món không cần.

---

## 2. TRƯỚC khi vá — xem trên production

Trên `https://bopcamping.vn`:

1. Vào **Thuê đồ** (`/thiet-bi`).
2. Bấm chọn khoảng ngày — ví dụ 3 ngày tới.
3. Để ô cơ sở ở **"Tất cả"** (đừng chọn Vinh hay Hà Nội).
4. Nhìn badge góc trên bên trái mỗi thẻ sản phẩm.

**Ghi lại đúng chữ bạn thấy**, ví dụ:

```
Còn 3 bộ
```

> Con số 3 đó là **của kho nhiều nhất**, không phải con số đúng cho mọi kho. Không có gì
> trên trang nói cho khách biết điều đó — đây chính là vấn đề.

### Chứng minh nó gây hại thật

Vẫn trên production:

1. Vẫn để **"Tất cả"**, thêm **3 cái** [SP-LỆCH] vào giỏ (hoặc món nào badge ghi "Còn 3").
2. Vào giỏ, chọn cơ sở **Hà Nội**.
3. Điền thông tin và **bấm "Đặt yêu cầu thuê"**.
4. Kỳ vọng thấy lỗi: *"Cơ sở bạn chọn không phục vụ đủ các món trong giỏ."*

> ⚠️ **Chú ý bước 2:** ngay khi chọn Hà Nội trong giỏ, con số trong giỏ **vẫn hiện 3** chứ
> không đổi thành 1. Đó là một lỗ hổng KHÁC mà tôi phát hiện khi soạn checklist này —
> `CartController` tính tồn **không truyền kho khách đã chọn**, nên nó cũng đang lấy "max qua
> các kho". Đã ghi thành **bopcamping-3o0x** (xem cuối file). Bản vá lần này **chỉ sửa trang danh
> sách**, chưa sửa trang giỏ.

Vậy khách chỉ biết mình không đặt được **ở bước cuối cùng**, sau khi đã chọn đồ và điền hết
thông tin. Đó là thất bại muộn mà bản vá muốn giảm.

---

## 3. SAU khi vá — so trên staging

Trên `https://staging.bopcamping.cloud`, làm **đúng các bước ở mục 2**:

| Kiểm | TRƯỚC (production) | SAU (staging) |
|---|---|---|
| [SP-LỆCH], cơ sở "Tất cả" | `Còn 3 bộ` | **`Còn 3 bộ tại Vinh`** |
| [SP-ĐỀU], cơ sở "Tất cả" | `Còn 2 bộ` | `Còn 2 bộ` *(không đổi — đúng)* |
| Món sắp hết mà lệch kho | `Sắp hết · 1 bộ` | `Sắp hết · 1 bộ tại <kho>` |
| Món hết hàng | `Hết hàng` | `Hết hàng` *(không đổi)* |

**Điểm mấu chốt:** chỉ [SP-LỆCH] đổi. [SP-ĐỀU] **phải giữ nguyên** — nếu nó cũng mọc thêm
tên kho thì bản vá đang làm rối chỗ không cần, báo tôi.

---

## 4. Chọn cơ sở cụ thể thì KHÔNG được hiện tên kho

Vẫn trên staging, với [SP-LỆCH]:

| Bấm cơ sở | Badge phải là |
|---|---|
| **Vinh** | `Còn 3 bộ` — không kèm tên, vì con số đã là của Vinh |
| **Hà Nội** | `Sắp hết · 1 bộ` — không kèm tên, và **là số 1** chứ không phải 3 |
| **Tất cả** | `Còn 3 bộ tại Vinh` |

Ô "Hà Nội" cho **số 1** là phần quan trọng: nó chứng minh khách chọn cơ sở thì thấy đúng
con số của cơ sở đó.

---

## 5. Bỏ lọc ngày thì không hiện tên kho

Vẫn trên staging:

1. Bấm **"Bỏ lọc ngày"**.
2. Badge quay về tổng tồn tĩnh, ví dụ `Còn 4 bộ`.

**Phải KHÔNG có chữ "tại ..."** — vì lúc này con số là tổng cả kho, ghép tên kho vào là tạo
ra một con số sai kiểu mới.

---

## 6. Trên điện thoại — kiểm chữ không tràn khỏi thẻ

Đây là chỗ tôi tìm ra một lỗi lúc đo: `"Còn 12 bộ tại Hà Nội"` rộng **145px** trong thẻ
**157px** — vừa sát mép, không còn pixel dư. Đã chặn bằng cắt chữ, nhưng bạn nên xem mắt.

1. Mở staging bằng **điện thoại** (hoặc thu hẹp cửa sổ còn ~375px).
2. Vào `/thiet-bi`, chọn ngày, để cơ sở **"Tất cả"**.
3. Nhìn badge trên thẻ [SP-LỆCH].

| Kiểm | Kết quả đúng |
|---|---|
| Badge | Nằm gọn trong thẻ, **không** đè ra ngoài |
| Chữ dài quá | Bị cắt bớt kèm `…`, **không** xuống dòng, **không** tràn |
| Trang | **Không** cuộn ngang được |

---

## 7. Trả dữ liệu về như cũ ⚠️ ĐỪNG QUÊN

Trên **staging**, mở lại [SP-LỆCH] và [SP-ĐỀU] trong admin, **đặt lại đúng hai số bạn đã ghi
ở mục 1**.

Nếu ở mục 2 bạn có đổi tồn kho trên **production** thì **phải trả về đúng số thật của shop** —
đây là dữ liệu thật, sai số tồn là nhận đơn quá tay.

> Khuyến nghị: chỉ đổi số trên **staging**, còn production thì **chỉ xem**, đừng sửa.

---

## 8. Kết luận

- [ ] Mục 3: [SP-LỆCH] hiện tên kho, [SP-ĐỀU] **không** đổi
- [ ] Mục 4: chọn cơ sở cụ thể → không kèm tên kho, và Hà Nội cho số của Hà Nội
- [ ] Mục 5: bỏ lọc ngày → không kèm tên kho
- [ ] Mục 6: điện thoại không tràn thẻ, không cuộn ngang
- [ ] Mục 7: **đã trả dữ liệu về như cũ**

---

## Điều bản vá này KHÔNG giải quyết

Nó làm con số **hết nói dối**, nhưng **không** làm khách đặt được nhiều hơn. Nếu Hà Nội chỉ
có 1 cái thì khách Hà Nội vẫn chỉ thuê được 1 — chỉ khác là họ biết **sớm**, ngay ở trang
danh sách, thay vì biết lúc bấm đặt.

Muốn khách gom hàng từ nhiều kho trong một đơn thì đó là việc khác hẳn: phải bỏ ràng buộc
"một đơn = một kho" của `StoreResolver`, kéo theo chuyện ai giao, giao mấy lượt, phí thế
nào. Chưa có bead cho việc đó, và tôi cũng không khuyên làm bây giờ.

**Và quan trọng hơn — trang GIỎ vẫn còn đúng lỗi này.** `CartController` (dòng 163, 177) tính
tồn mà **không truyền kho khách đã chọn**, nên nó vẫn lấy "max qua các kho". Khách chọn Hà
Nội trong giỏ mà con số vẫn là của Vinh. Bản vá lần này chỉ sửa **trang danh sách**.
Đã ghi thành **bopcamping-3o0x** để làm tiếp — không gộp vào đây vì nó đụng vào luồng giỏ và
cần bộ test riêng.

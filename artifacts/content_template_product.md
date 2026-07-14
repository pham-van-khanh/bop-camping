# Template bố cục "Nội dung chi tiết" sản phẩm

Mẫu nội dung dùng cho ô **Nội dung chi tiết** (admin → Sản phẩm → Sửa nội dung).
Đã áp dụng sẵn cho **Lều Naturehike Cloud Up 2** để xem trực tiếp trên trang
`/thiet-bi/leu-naturehike-cloud-up-2` (khối "Chi tiết sản phẩm").

## Cách bố cục tự sắp (MagazineContent)

Admin **chỉ cần soạn tuần tự** (tiêu đề, đoạn văn, ảnh, danh sách…). Component
`resources/js/Components/site/MagazineContent.tsx` tự dựng bố cục magazine:

| Bạn soạn | Kết quả hiển thị |
|----------|------------------|
| 1 đoạn text + 1 ảnh liền nhau | Hàng 2 cột: ảnh ↔ text (tự **đảo bên** luân phiên) |
| 2+ ảnh liền nhau (không có chữ xen giữa) | Dải ảnh ngang (strip) |
| 1 ảnh đứng một mình | Ảnh căn giữa |
| chỉ text | Đoạn full-width |

**Ảnh KHÔNG bị cắt**: hiển thị đúng tỉ lệ gốc, căn giữa, giới hạn chiều cao cho
gọn (pair ≤460px, strip ≤260px, ảnh đơn ≤520px). Ảnh nhỏ giữ nguyên kích thước,
ảnh lớn co lại vừa khung — không crop.

## Cấu trúc mẫu (thứ tự soạn)

1. `H2` **Giới thiệu chung** + 1–2 đoạn văn → **1 ảnh** (thành hàng ảnh|text)
2. `H3` **Điểm nổi bật** + danh sách gạch đầu dòng → **1 ảnh** (đảo bên)
3. `H3` **Hướng dẫn dựng/dùng** + danh sách đánh số (các bước)
4. **2 ảnh liền nhau** (dải ảnh minh hoạ)
5. `H3` **Mẹo bảo quản** + danh sách → **1 ảnh**
6. `blockquote` **Lưu ý khi thuê**

> Mẹo: xen kẽ "1 đoạn text ↔ 1 ảnh" để có bố cục 2 cột đẹp; muốn dải ảnh thì đặt
> 2–3 ảnh liền nhau; tiêu đề dùng H2 cho mục lớn, H3 cho mục con.

## HTML mẫu (đã lưu trong DB cho lều Cloud Up 2)

```html
<h2>Giới thiệu chung</h2>
<p>Lều <strong>Naturehike Cloud Up 2</strong> là mẫu lều 2 lớp siêu nhẹ…</p>
<img src="/images/album/forest-camp-aerial.jpg" alt="Lều giữa rừng">
<h3>Điểm nổi bật</h3>
<ul><li><strong>Siêu nhẹ:</strong> …</li>…</ul>
<img src="/images/album/tent-interior-night.jpg" alt="Bên trong lều">
<h3>Hướng dẫn dựng lều (5 bước)</h3>
<ol><li>Trải tấm lót nền…</li>…</ol>
<img src="/images/album/beach-night-tent.jpg" alt="Lều bờ biển">
<img src="/images/album/cloud-sea-sunrise.jpg" alt="Bình minh biển mây">
<h3>Mẹo bảo quản</h3>
<ul><li>Phơi khô trước khi gấp…</li>…</ul>
<img src="/images/album/cliff-turquoise.jpg" alt="Vách đá biển xanh">
<blockquote>Lưu ý khi thuê: kiểm tra đủ cọc, dây néo…</blockquote>
```

Tag được phép (HTMLPurifier profile `editor`): `h2 h3 h4 p br strong/b em/i u s ul ol li blockquote hr a img`. Ảnh có thể để `src` tương đối (vd `/images/album/…`) hoặc ảnh upload qua editor.

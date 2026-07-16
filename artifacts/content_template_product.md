# Template bố cục "Nội dung chi tiết" sản phẩm

Mẫu nội dung dùng cho ô **Nội dung chi tiết** (admin → Sản phẩm → Sửa nội dung).
Đã áp dụng sẵn cho **Lều Naturehike Cloud Up 2** để xem trực tiếp trên trang
`/thiet-bi/leu-naturehike-cloud-up-2` (khối "Chi tiết sản phẩm").

## Cách bố cục tự sắp (MagazineContent) — lưới 2 ảnh / hàng

Admin **chỉ cần soạn tuần tự** (tiêu đề, đoạn văn, ảnh…). Component
`resources/js/Components/site/MagazineContent.tsx` tự dựng bố cục:

| Bạn soạn | Kết quả hiển thị |
|----------|------------------|
| Nhiều ảnh **liền nhau** (không chữ xen giữa) | Lưới **2 ảnh / hàng**, có khoảng cách giữa 2 ảnh |
| chữ (tiêu đề / đoạn) | **Căn trái**, xen giữa các nhóm ảnh |

**Ảnh hiện ĐẦY ĐỦ**: mỗi ảnh chiếm nửa bề ngang, giữ tỉ lệ gốc (`w-full h-auto`)
→ không cắt; **không bo góc, không viền**.

> Muốn nhiều ảnh vào lưới → đặt chúng **liền nhau, KHÔNG chèn chữ** ở giữa.
> Muốn tách nhóm → chèn 1 dòng chữ / tiêu đề giữa các nhóm.
> Số ảnh lẻ (vd 3) → hàng cuối 1 ảnh chiếm nửa bề ngang bên trái.

## Cấu trúc mẫu (ảnh là chính, chữ tối giản — thứ tự soạn)

1. `H2` tên sản phẩm + 1 câu giới thiệu ngắn
2. **Nhóm 3–4 ảnh liền nhau** (thành 1 hàng)
3. `H3` một dòng ngắn
4. **Nhóm 3 ảnh liền nhau** (hàng tiếp)
5. `blockquote` **Lưu ý khi thuê**

> Nguyên tắc: gom ảnh theo chủ đề thành từng nhóm, mỗi nhóm 1 tiêu đề ngắn là đủ.

## HTML mẫu (đã lưu trong DB cho lều Cloud Up 2)

```html
<h2>Lều Naturehike Cloud Up 2</h2>
<p>Lều 2 lớp siêu nhẹ cho 2 người — gọn nhẹ, dựng nhanh, chống mưa gió tốt.</p>
<img src="/images/album/forest-camp-aerial.jpg" alt="Cắm trại giữa rừng">
<img src="/images/album/tent-interior-night.jpg" alt="Bên trong lều về đêm">
<img src="/images/album/beach-night-tent.jpg" alt="Lều bên bờ biển đêm">
<img src="/images/album/cliff-turquoise.jpg" alt="Cắm trại bên vách đá">
<h3>Đồng hành mọi địa hình</h3>
<img src="/images/album/bay-overview.jpg" alt="Toàn cảnh vịnh">
<img src="/images/album/beach-sunset-relax.jpg" alt="Hoàng hôn bên lều">
<img src="/images/album/cloud-sea-sunrise.jpg" alt="Bình minh biển mây">
<blockquote>Lưu ý khi thuê: kiểm tra đủ cọc, dây néo và túi đựng trước khi trả.</blockquote>
```

Trong mẫu này: **4 ảnh liền nhau** (rừng → lều → biển đêm → vách đá) thành 1 hàng
cùng chiều cao; sau tiêu đề là **3 ảnh liền nhau** thành hàng tiếp — tất cả cạnh
nhau, gọn đều, không cắt.

Tag được phép (HTMLPurifier profile `editor`): `h2 h3 h4 p br strong/b em/i u s ul ol li blockquote hr a img`. Ảnh có thể để `src` tương đối (vd `/images/album/…`) hoặc ảnh upload qua editor.

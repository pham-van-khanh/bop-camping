# Template bố cục "Nội dung chi tiết" sản phẩm

Mẫu nội dung dùng cho ô **Nội dung chi tiết** (admin → Sản phẩm → Sửa nội dung).
Đã áp dụng sẵn cho **Lều Naturehike Cloud Up 2** để xem trực tiếp trên trang
`/thiet-bi/leu-naturehike-cloud-up-2` (khối "Chi tiết sản phẩm").

## Cách bố cục tự sắp (MagazineContent) — ưu tiên ẢNH

Admin **chỉ cần soạn tuần tự** (tiêu đề, đoạn văn, ảnh, danh sách…). Component
`resources/js/Components/site/MagazineContent.tsx` tự dựng bố cục **ưu tiên ảnh**:

| Bạn soạn | Kết quả hiển thị |
|----------|------------------|
| 1 ảnh đứng một mình | **Ảnh full-width, TO, hiện trọn ảnh** |
| 2+ ảnh liền nhau (không chữ xen giữa) | Dải 2 cột (desktop), xếp dọc trên mobile |
| chữ (tiêu đề / đoạn / danh sách) | Full-width, xen giữa các ảnh |

**Ảnh KHÔNG bị cắt** và **hiển thị đầy đủ**: full bề ngang khối nội dung, giữ đúng
tỉ lệ gốc (`w-full h-auto`) — không crop, không ép chiều cao.

> Muốn ảnh nào đứng riêng TO thì đặt **chữ (dù chỉ 1 dòng) xen giữa** hai ảnh.
> Đặt 2 ảnh SÁT nhau (không chữ) để tạo dải 2 cột.

## Cấu trúc mẫu (nhiều ảnh, chữ ngắn — thứ tự soạn)

1. `H2` tên sản phẩm + 1 câu giới thiệu ngắn
2. **Ảnh** (full-width)
3. `H3` một dòng ngắn → **Ảnh**
4. 1 dòng **Điểm nổi bật** (gộp 1 dòng) → **Ảnh**
5. `H3` một dòng → **2 ảnh sát nhau** (dải)
6. 1 dòng ngắn → **Ảnh** → `H3` → **Ảnh**
7. `blockquote` **Lưu ý khi thuê**

> Nguyên tắc: mỗi ảnh 1 câu/1 tiêu đề ngắn là đủ — để ẢNH kể chuyện, chữ chỉ dẫn dắt.

## HTML mẫu (đã lưu trong DB cho lều Cloud Up 2)

```html
<h2>Lều Naturehike Cloud Up 2</h2>
<p>Lều 2 lớp siêu nhẹ cho 2 người — gọn như một chai nước, dựng nhanh…</p>
<img src="/images/album/forest-camp-aerial.jpg" alt="Lều giữa rừng">
<h3>Không gian riêng giữa thiên nhiên</h3>
<img src="/images/album/tent-interior-night.jpg" alt="Bên trong lều">
<p><strong>Điểm nổi bật:</strong> khung nhôm 7001 · chống nước 4.000mm · ~1,5kg.</p>
<img src="/images/album/beach-night-tent.jpg" alt="Lều bờ biển đêm">
<h3>Đồng hành mọi địa hình</h3>
<img src="/images/album/cliff-turquoise.jpg" alt="Vách đá biển xanh">
<img src="/images/album/bay-overview.jpg" alt="Toàn cảnh vịnh">
<p>Từ rừng thông, bãi biển đến vách núi — một chiếc lều cho mọi chuyến đi.</p>
<img src="/images/album/beach-sunset-relax.jpg" alt="Hoàng hôn bên lều">
<h3>Sẵn sàng cho mọi bình minh</h3>
<img src="/images/album/cloud-sea-sunrise.jpg" alt="Bình minh biển mây">
<blockquote>Lưu ý khi thuê: kiểm tra đủ cọc, dây néo và túi đựng trước khi trả.</blockquote>
```

Trong mẫu này: **2 ảnh sát nhau** (vách đá + vịnh) tạo dải 2 cột; các ảnh còn lại
đều có chữ xen giữa nên **đứng riêng full-width, hiện trọn**.

Tag được phép (HTMLPurifier profile `editor`): `h2 h3 h4 p br strong/b em/i u s ul ol li blockquote hr a img`. Ảnh có thể để `src` tương đối (vd `/images/album/…`) hoặc ảnh upload qua editor.

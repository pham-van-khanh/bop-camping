# ADR: Cho phép Admin upload video cho sản phẩm

- **Trạng thái:** Accepted
- **Ngày:** 2026-07-01
- **Liên quan:** `bopcamping-qwg`, [tech-strategy.md](../.claude/rules/tech-strategy.md), `database/migrations/2026_06_25_000001_add_type_to_review_images_table.php`, `database/migrations/2026_06_26_000003_create_camping_spot_media_table.php`

## Bối cảnh

Admin hiện chỉ upload được **ảnh** cho sản phẩm (bảng `product_images`: `path` +
`sort_order`, không có cột phân loại). Yêu cầu: cho phép admin đính kèm thêm **video**
(demo dựng lều, hướng dẫn sử dụng...) vào cùng gallery sản phẩm.

Codebase đã có **2 tiền lệ y hệt bài toán này**, cả hai đã qua review/audit trước đó:

1. **`review_images`** — ban đầu chỉ ảnh, sau đó **mở rộng tại chỗ** bằng 1 migration
   thêm cột `type enum('image','video') default 'image'`. Không đổi tên bảng/model/route.
2. **`camping_spot_media`** — thiết kế mới hoàn toàn từ đầu (camping spot vốn chưa có
   bảng ảnh riêng) với cùng cấu trúc `type` + `path` + `sort_order`.

Cả hai dùng chung 1 hằng `MEDIA_MIMES = 'mimetypes:image/jpeg,image/png,image/webp,
video/mp4,video/webm,video/quicktime'`, phân loại bằng `str_starts_with($file->
getMimeType(), 'video/')`, và cùng 1 endpoint upload cho cả ảnh + video (không tách 2
input riêng).

## Quyết định

### 1. Mở rộng `product_images` tại chỗ (theo tiền lệ review_images), KHÔNG tạo bảng mới

| Phương án | Ưu điểm | Nhược điểm |
|---|---|---|
| **(A) Thêm cột `type` vào `product_images`** ✅ | Không đổi tên bảng/model/route/quan hệ `Product::images()`; 1 nguồn dữ liệu gallery duy nhất; tái dùng tối đa code hiện có | Tên bảng "images" giờ chứa cả video (đã có tiền lệ `review_images` chấp nhận điều này) |
| (B) Tạo bảng `product_media` riêng (như camping_spot_media) | Tên bảng mô tả đúng nội dung hơn | Sản phẩm **đã có** 1 gallery ảnh dùng khắp FE (Admin/Products, ProductDetail, ProductCard) → tạo bảng riêng buộc phải hợp nhất 2 nguồn dữ liệu ở FE, vi phạm DRY, rủi ro cao hơn không cần thiết |

**Chọn (A)** vì sản phẩm chỉ có một khái niệm "gallery" (ảnh+video xen kẽ theo
`sort_order`), giống bản chất `review_images` (media của 1 review) hơn là
`camping_spot_media` (trường hợp tạo mới hoàn toàn, chưa từng có bảng ảnh).

### 2. Giữ nguyên `thumbnail` — chỉ nhận ảnh, không đổi

`Product.thumbnail` là ảnh đại diện tĩnh dùng làm cover cho `ProductCard` (danh sách/lưới
sản phẩm). **Không mở rộng cho video** — giữ nguyên validate
`mimes:jpg,jpeg,png,webp|max:4096`. Xác nhận `ProductCard.tsx` dùng riêng `p.thumbnail`
(không đụng `images[0]`) nên không có rủi ro video lọt vào lưới danh sách.

### 3. Giới hạn upload: theo cấu hình `camping_spot_media` (admin-only), không theo `review_images` (khách hàng)

| | review_images (khách) | camping_spot_media (admin) | **product_images (admin) — chọn** |
|---|---|---|---|
| Max size/file | 30MB | 50MB | **50MB** |
| Max file/lần | 4 | 12 | **12** |
| Rate limit | — | `throttle:60,1` | **`throttle:60,1`** |

Lý do: admin là actor tin cậy, nội dung mục đích tương tự camping-spot (giới thiệu/demo),
nên dùng đúng cấu hình đó để nhất quán và có rate-limit chống lạm dụng upload lặp lại.

### 4. Validate MIME theo nội dung thực (không chỉ theo đuôi file)

Dùng `mimetypes:` (Symfony finfo, đọc magic bytes) — **không** dùng `mimes:` (chỉ so đuôi
file, dễ giả mạo bằng cách đổi tên `.php.mp4`). Đây là rule đã áp dụng ở 2 tiền lệ, giữ
nguyên.

## Phạm vi thay đổi

**Backend**

1. Migration `add_type_to_product_images_table`: thêm `type enum('image','video')
   default 'image' after product_id` — clone cấu trúc
   `add_type_to_review_images_table`.
2. `ProductImage`: thêm `type` vào `$fillable`.
3. `Admin\ProductController`:
   - Thêm `const MEDIA_MIMES` (như `CampingSpotController`).
   - `storeImage()`: đổi validate `images.*` từ `mimes:jpg,jpeg,png,webp|max:4096` →
     `['file', self::MEDIA_MIMES, 'max:51200']`; gán `type` theo mimetype khi tạo record.
   - `index()`: bổ sung `type` vào map `images` trả cho Inertia.
   - `destroyImage()`: giữ nguyên (đã có chặn IDOR `abort_unless($image->product_id ===
     $product->id, 404)`).
4. Route `admin.products.images.store`: thêm middleware `throttle:60,1` (khớp
   `camping-spot.media.store`).
5. `Shop\ProductController` (trang public): bổ sung `type` vào map `images` nếu có,
   để FE render đúng.

**Frontend**

6. `Admin/Products.tsx`: input file `accept="image/*"` → `"image/*,video/*"`; type
   `ProductImage` thêm `type: 'image' | 'video'`; thumbnail trong lưới ảnh phụ render
   `<video muted>` + badge ▶ khi `type === 'video'` (theo mẫu `CampingSpots.tsx`); đổi
   label nút "Upload ảnh" → "Upload ảnh/video".
7. `Pages/ProductDetail.tsx`: gallery type mở rộng thêm nhánh video (
   `{ type: 'img' | 'video'; src }`); slide chính render `<video controls>` khi video
   (theo mẫu `ProductReviews.tsx`); thumbnail dải dưới dùng `<video muted>` + badge ▶
   (theo mẫu `CampingGuideModal.tsx`).
8. `ProductCard.tsx`: không cần đổi — đã dùng riêng `thumbnail`, không đụng `images`.

## Non-goals (ngoài phạm vi)

- Không giới hạn tổng dung lượng đĩa cho toàn bộ video của 1 sản phẩm (mỗi lần upload
  giới hạn 50MB × 12 file, nhưng có thể upload nhiều lần) — quyết định có chủ đích, chấp
  nhận rủi ro dung lượng đĩa tăng theo thời gian; theo dõi thủ công, chưa cần tự động hoá.
- Không transcode/nén/tạo poster-frame tự động cho video — video gốc phát trực tiếp qua
  `<video>` tag từ `storage/app/public`.
- Không đổi hạ tầng lưu trữ (vẫn Laravel Storage local disk theo tech-strategy — không
  thêm S3/CDN).

## Rủi ro đã ghi nhận

| Rủi ro | Mức | Ghi chú |
|---|---|---|
| Dung lượng đĩa server tăng dần do video tích lũy | Thấp (theo dõi) | Ngoài phạm vi ADR này; nếu cần sẽ có ADR riêng khi gần hết dung lượng VPS |
| Giả mạo MIME type qua đổi tên file | Đã xử lý | Dùng `mimetypes:` (đọc magic bytes), không dùng `mimes:` (theo đuôi) |
| IDOR khi xoá media (sửa `product_id` trên URL) | Đã xử lý | `destroyImage()` đã có `abort_unless`, giữ nguyên |
| Spam upload nhiều lần liên tiếp | Đã xử lý | `throttle:60,1` trên route store |

## Hệ quả

- `product_images` chính thức trở thành "product media" về bản chất dữ liệu (giữ tên
  bảng/model để tránh churn), giống `review_images` — cần lưu ý khi đọc code sau này.
- Không cần migration dữ liệu cũ: mọi bản ghi hiện có mặc định `type = 'image'`.

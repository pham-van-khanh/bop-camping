# Design Spec — Admin: sắp xếp + tái sử dụng ảnh (product & combo)

- **Beads:** bopcamping-byeb
- **Ngày:** 2026-07-14
- **Phạm vi:** ảnh phụ / gallery của **product** và **combo** trong admin.
- **Ngoài phạm vi:** thumbnail chính (giữ upload như cũ); nâng giới hạn PHP cho video (`upload_max_filesize`/`post_max_size`) — **hoãn**.

## 1. Mục tiêu (user request)
1. Kéo-thả sắp xếp lại thứ tự ảnh trong gallery.
2. Chọn ảnh **đã upload** để tái sử dụng thay vì upload lại file mới; liệt kê nhóm theo product/combo, chọn chéo product ↔ combo.
3. (Kèm) cho phép **GIF** upload — thêm `image/gif` vào mimetype cho phép.

## 2. Mô hình dữ liệu — chia sẻ file (refcount), KHÔNG đổi schema
- Ảnh dùng chung = nhiều row `product_images`/`combo_images` cùng một `path` (không copy file).
- Bảng đã có `path`, `sort_order`, `type` — không thêm cột.
- **Xoá 1 ảnh:** chỉ `Storage::disk('media')->delete($path)` khi **không còn** row nào ở CẢ HAI bảng tham chiếu `path` đó; còn thì chỉ xoá row (gỡ liên kết).
- **Xoá cả product/combo:** áp dụng cùng quy tắc refcount cho từng ảnh (sửa `destroy` hiện đang xoá thẳng file).

## 3. Backend (Product + Combo song song)
Route mới (nhóm admin, đã có auth middleware):
- `POST products/{product}/images/reorder`, `POST combos/{combo}/images/reorder`
  - Nhận `image_ids: number[]` theo thứ tự mới. Validate mọi id thuộc đúng item (chống IDOR — CWE-639). Cập nhật `sort_order` theo index.
- `POST products/{product}/images/attach`, `POST combos/{combo}/images/attach`
  - Nhận `source_image_ids: number[]` (id ảnh có sẵn bất kỳ, cả product_images lẫn combo_images).
  - Tạo row mới trỏ cùng `path`/`type`, `sort_order` nối tiếp `max+1`.
  - **Bỏ qua** ảnh mà gallery hiện tại đã có cùng `path` (tránh trùng).
- **Kho ảnh cho picker:** nạp lazy bằng Inertia optional prop (`Inertia::optional(...)`) + partial reload (`router.reload({ only: [...] })`) khi mở picker → danh sách nhóm `{ type: 'product'|'combo', id, name, images: [{id, path, type}] }`, chỉ item có ảnh.

Helper refcount dùng chung để tránh lặp: 1 hàm kiểm tra "path còn được tham chiếu ở product_images/combo_images khác không". Đặt ở nơi dùng chung (vd trait hoặc `App\Support\MediaRef`).

`MediaType::MIMES_RULE` thêm `image/gif`.

## 4. Frontend — tách component chung (DRY)
Hiện `Pages/Admin/Products.tsx` và `Pages/Admin/Combos.tsx` lặp gần y hệt phần gallery. Rút ra:
- `Components/admin/MediaGallery.tsx`: grid ảnh **kéo-thả** (framer-motion `Reorder.Group`/`Reorder.Item`), lưu thứ tự khi thả (POST reorder). Nút **Upload**, nút **Chọn ảnh có sẵn**, nút xoá từng ảnh. Props: `items`, `images`, endpoints/ids, callbacks.
- `Components/admin/MediaPickerModal.tsx`: modal (dùng pattern modal có sẵn / `@headlessui/react`) liệt kê kho ảnh nhóm theo product/combo, multi-select, nút "Thêm N ảnh" → POST attach.
- Kéo-thả dùng `framer-motion` (đã có sẵn — KHÔNG thêm dependency). Modal dùng `@headlessui/react` (đã có sẵn).

## 5. Quality gates
- `php artisan test` (thêm test: reorder cập nhật đúng sort_order + chống IDOR; attach tạo row cùng path + dedupe; destroyImage refcount giữ file khi còn nơi khác dùng; xoá product không phá ảnh combo đang dùng chung).
- `./vendor/bin/pint --test`
- `npm run build` (tsc + vite), `npm run lint`.
- Verify trên preview (kéo-thả, picker, gif).

## 6. Checklist prod (ghi lại, KHÔNG tự đụng server) — cho phần video hoãn
- Nginx: `client_max_body_size` ≥ kích thước video tối đa.
- PHP-FPM: `upload_max_filesize`, `post_max_size` tương ứng.

# Security Audit — Upload video cho sản phẩm

- **Ngày:** 2026-07-01
- **Phạm vi:** `feature/product-video-upload` (bopcamping-qwg) — so với `feat/scaffold-laravel`
- **Phương pháp:** STRIDE + trace data flow qua `Admin\ProductController::storeImage/destroyImage`, `Shop\ProductController`, route, model

## Tóm tắt

Không có finding Critical/High. 2 finding Medium/Low liên quan tới giới hạn tài
nguyên (không phải lỗ hổng cho actor bên ngoài — cả 2 chỉ khai thác được bởi
admin đã đăng nhập, hoặc do lệch cấu hình hạ tầng). Phần còn lại (auth, IDOR,
MIME/type spoofing, path traversal filename) đều đã xử lý đúng, kế thừa đúng
pattern đã audit từ `review_images`/`camping_spot_media`.

## STRIDE

| Threat | Đánh giá |
|---|---|
| **Spoofing** | Route trong nhóm middleware admin (`EnsureAdmin`) + CSRF (nhóm `web`, xác nhận qua `route:list`). Không có gap. |
| **Tampering** | MIME validate bằng `mimetypes:` (đọc magic bytes thật qua finfo), không dùng `mimes:` (theo đuôi file) → chống giả mạo đuôi. `type` (image/video) do **server** tự tính từ `$file->getMimeType()` thật, client không thể set trực tiếp. Filename lưu qua `$file->store()` → Laravel sinh tên ngẫu nhiên + extension suy từ MIME thật, KHÔNG dùng tên file client gửi → không có path traversal. Không có gap. |
| **Repudiation** | Không audit log ai upload/xoá (chỉ timestamps). Đây là pattern có sẵn từ `review_images`/`camping_spot_media`, không phải regression riêng của nhánh này — chỉ ghi nhận, không phải finding mới. |
| **Information Disclosure** | Media public ngay khi admin upload (không qua duyệt như review khách hàng) — là quyết định CÓ CHỦ ĐÍCH trong ADR (admin là actor tin cậy), khớp đúng cách `camping_spot_media` đã làm. Không phải gap. |
| **Denial of Service** | **2 finding thật — xem chi tiết dưới.** |
| **Elevation of Privilege** | Test `non_admin_cannot_upload_product_media` xác nhận khách thường bị redirect `admin.login`. Không có gap. |

## Findings

### [Medium] Thiếu giới hạn số file/lần upload — lệch ADR + lệch precedent

- **File:** `app/Http/Controllers/Admin/ProductController.php:182-184` (`storeImage()`)
- **CWE:** CWE-770 (Allocation of Resources Without Limits)
- **Hiện trạng:**
  ```php
  $request->validate([
      'images' => 'required|array',                          // KHÔNG có max:12
      'images.*' => ['file', self::MEDIA_MIMES, 'max:51200'],
  ]);
  ```
  So sánh với `CampingSpotController::storeMedia()` (chính pattern mà tính năng
  này được thiết kế để mirror — xem `artifacts/adr_product_video_upload.md`
  mục 3, đã quyết định "Max file/lần: 12"):
  ```php
  $request->validate([
      'media' => ['required', 'array', 'max:12'],             // CÓ max:12
      ...
  ```
- **Tác động:** 1 request có thể gửi số lượng file KHÔNG GIỚI HẠN, mỗi file
  tối đa 50MB → PHP buffer toàn bộ file tạm cùng lúc, Laravel tạo N record
  trong 1 transaction ngầm (foreach không transaction, nhưng vẫn N query +
  N lần `$file->store()`). Chỉ admin gọi được (đã auth), nên đây là rủi ro
  tự-DoS (chọn nhầm hàng trăm file) hơn là lỗ hổng cho người ngoài — nhưng
  vẫn là lệch spec rõ ràng, dễ khai thác nếu session admin bị chiếm qua vector
  khác (khuếch đại tác động).
- **Khuyến nghị:** thêm `'max:12'` vào rule `images`, khớp đúng ADR + precedent:
  ```php
  'images' => ['required', 'array', 'max:12'],
  ```

### [Low-Medium] Trần "50MB/file" không khớp hạ tầng thực tế — video lớn sẽ bị chặn âm thầm

- **File:** `app/Http/Controllers/Admin/ProductController.php:185` (comment `≤50MB`),
  `artifacts/adr_product_video_upload.md` mục 3, `artifacts/deploy_runbook.md:206`
- **Hiện trạng đo trên máy dev:**
  ```
  upload_max_filesize = 2M
  post_max_size = 8M
  ```
  `artifacts/deploy_runbook.md:206` đặt Nginx `client_max_body_size 20M` cho
  production — cũng thấp hơn 50MB. Không nơi nào trong repo cấu hình
  `upload_max_filesize`/`post_max_size` cho PHP-FPM.
- **Tác động:** Video (thường >8-20MB với chất lượng dùng được) sẽ bị PHP hoặc
  Nginx CHẶN TRƯỚC KHI Laravel kịp validate — admin nhận lỗi mơ hồ
  (`PostTooLargeException`, chưa có handler riêng → thông báo lỗi chung, không
  rõ nguyên nhân là do file quá lớn). Tính năng "cho phép video tới 50MB" trên
  thực tế **không hoạt động đúng như quảng cáo** ngoài dev với file rất nhỏ
  (đúng là lý do test E2E ban đầu ở Step 3 "qua" — file test chỉ 48 byte).
  **Lưu ý:** gap hạ tầng này đã tồn tại từ tính năng `camping_spot_media`
  trước đó (không phải do nhánh này tạo ra), nhưng nhánh này KẾ THỪA + lặp lại
  cùng lời hứa 50MB mà không sửa hạ tầng, nên vẫn đáng flag vì trực tiếp phá
  vỡ giá trị cốt lõi của tính năng.
- **Khuyến nghị:**
  1. Cập nhật `artifacts/deploy_runbook.md`: `client_max_body_size` ≥ 55M
     (chừa margin cho multipart overhead).
  2. Thêm vào runbook bước set `upload_max_filesize = 55M`, `post_max_size = 55M`
     trong `php.ini` (hoặc pool `.conf` của PHP-FPM) — hiện chưa có bước này.
  3. Cân nhắc thêm handler cho `PostTooLargeException` (Laravel exception
     handler) để admin thấy thông báo rõ "File quá lớn" thay vì lỗi chung.
  4. Việc này ảnh hưởng CHUNG cho cả `camping_spot_media` — nên xử lý 1 lần ở
     tầng hạ tầng (Nginx + PHP-FPM), không phải sửa riêng từng feature.

## Việc đã kiểm tra, KHÔNG có finding

- IDOR khi xoá media (`destroyImage()`giữ nguyên `abort_unless`) — đã có test
  `cannot_delete_image_through_wrong_product`.
- Path traversal qua tên file — Laravel tự sinh tên, không dùng tên client gửi.
- Giả mạo mimetype qua đổi đuôi file — dùng `mimetypes:` (magic bytes), không
  dùng `mimes:` (đuôi file).
- CSRF — route trong nhóm `web` middleware (mặc định có CSRF).
- Authorization — route trong nhóm `EnsureAdmin`; test `non_admin_cannot_upload_product_media`
  xác nhận khách thường bị chặn.
- Rate limiting request — `throttle:60,1` đã có trên route store (không giải
  quyết finding #1 vì đó là giới hạn per-request, không phải per-request-count).

## Theo dõi

- `bopcamping-7ty` — [Medium] thêm `max:12` cho validate `images` trong `storeImage()`.
- `bopcamping-t9j` — [Low-Medium] đồng bộ trần upload (Nginx + PHP-FPM) với
  50MB đã quảng cáo — ảnh hưởng chung `product_images` + `camping_spot_media`.

# Codebase Health Report — feature/product-video-upload

- **Ngày:** 2026-07-01
- **Phạm vi:** diff `feat/scaffold-laravel...feature/product-video-upload` (14 file, ~460 dòng)

## Executive Summary

**Health Score:** B+ (1 vấn đề DRY thật, đã vá; còn lại sạch — do bám sát
precedent có sẵn thay vì phát sinh pattern mới)
**Critical Issues:** 0
**Total Issues:** 1 (đã fix)

## DRY Violations

| Type | Files | Pattern | Remediation |
|---|---|---|---|
| **Knowledge duplication** | `app/Http/Controllers/Shop/ReviewController.php:14`, `app/Http/Controllers/Admin/CampingSpotController.php:18`, `app/Http/Controllers/Admin/ProductController.php:20` | Hằng `MEDIA_MIMES = 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime'` **giống byte-for-byte** lặp lại 3 lần; logic phân loại `str_starts_with($file->getMimeType(), 'video/') ? 'video' : 'image'` cũng lặp lại 3 lần y hệt. `ProductController` (nhánh này) là lần lặp THỨ BA — vượt ngưỡng "rule of three". Rủi ro thực tế: nếu sau này cần thêm mimetype (vd `video/x-matroska`), rất dễ chỉ sửa 1-2/3 nơi rồi quên nơi còn lại → hành vi lệch nhau giữa review/camping-spot/product. | Trích xuất thành 1 nguồn dùng chung. **Đã thực hiện** (xem dưới) — tạo `App\Support\MediaType` với `MIMES_RULE` (const) + `detect(UploadedFile $file): string`, cả 3 controller dùng lại. |

## SOLID Violations

Không có finding. Mỗi controller vẫn giữ đúng 1 trách nhiệm (CRUD của resource
tương ứng); việc trích xuất `MediaType` là tách concern "phân loại media" ra
khỏi controller, đúng hướng SRP/DIP — không phải sửa lỗi SOLID có sẵn mà là
cải thiện thêm.

## Code Smells

Không có finding mới. `storeImage()`/`storeMedia()` đều ngắn (~15 dòng), không
có method dài, không có class phình to. `ProductImage`/`ProductController`
không tăng cyclomatic complexity đáng kể (thêm 1 ternary).

## Consistency Issues

| Area | Finding | Recommendation |
|---|---|---|
| Naming | `product_images`/`ProductImage` giờ chứa cả video (tên không mô tả đúng 100% nội dung) | Đã ghi nhận CÓ CHỦ ĐÍCH trong ADR (mục "Hệ quả") — nhất quán với `review_images` đã làm y vậy trước đó. Không phải finding mới, chỉ nhắc lại để người đọc sau không bất ngờ. |
| Error messages | Message validate (`'Chỉ nhận ảnh (jpg, png, webp) hoặc video (mp4, webm, mov).'`, `'Mỗi tệp tối đa 50MB.'`) copy nguyên văn từ `CampingSpotController` | Đúng chủ đích (đồng nhất UX thông báo lỗi giữa các màn upload media) — không phải vấn đề. |

## Complexity Hotspots

Không có hotspot mới. File lớn nhất trong diff (`ProductDetail.tsx`) chỉ thêm
2 nhánh JSX điều kiện (`type === 'video'`), không tăng độ phức tạp đáng kể.

## Dead Code

Không có. Không có export/hàm nào bị bỏ không dùng trong diff.

## Đã thực hiện (refactor an toàn — Two Hats: chỉ đổi cấu trúc, không đổi hành vi)

Tạo `app/Support/MediaType.php`:
```php
final class MediaType
{
    public const MIMES_RULE = 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime';

    public static function detect(UploadedFile $file): string
    {
        return str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image';
    }
}
```
Cập nhật `ReviewController`, `CampingSpotController`, `ProductController` dùng
`MediaType::MIMES_RULE` + `MediaType::detect($file)` thay cho hằng/ternary cục
bộ. Đã chạy lại toàn bộ test liên quan (`ReviewSubmitTest`, `AdminCampingSpotTest`,
`AdminProductTest`) — hành vi giữ nguyên 100%, không có test nào cần sửa.

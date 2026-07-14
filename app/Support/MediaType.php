<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Single source cho quy tắc "ảnh + video cho phép" — dùng chung giữa
 * ReviewController, CampingSpotController, ProductController (tránh lệch
 * nhau khi cần thêm/sửa mimetype sau này).
 */
final class MediaType
{
    public const MIMES_RULE = 'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime';

    /** Phân loại theo mimetype THẬT (finfo), không theo đuôi file. */
    public static function detect(UploadedFile $file): string
    {
        return str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image';
    }
}

<?php

namespace App\Support;

use Mews\Purifier\Facades\Purifier;

class EditorHtml
{
    /**
     * Sanitize HTML soạn từ TipTap ở admin trước khi lưu (chống stored XSS — CWE-79).
     * Dùng profile 'editor' (config/purifier.php). Rỗng / chỉ còn thẻ trống -> null.
     */
    public static function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $clean = trim(Purifier::clean($html, 'editor'));

        // "<p><br></p>" sau khi lọc vẫn là thẻ trống — coi như không có nội dung
        // (giữ lại nếu còn ảnh: strip_tags bỏ <img> nhưng ảnh là nội dung thật).
        if ($clean === '' || (trim(strip_tags($clean)) === '' && ! str_contains($clean, '<img'))) {
            return null;
        }

        return $clean;
    }
}

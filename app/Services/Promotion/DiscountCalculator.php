<?php

namespace App\Services\Promotion;

/**
 * Công thức tiền giảm giá — NGUỒN DUY NHẤT (DRY) cho mọi ưu đãi kiểu
 * "percent | fixed": referral (đơn đầu), email-bonus, voucher.
 *
 *   raw      = type==='percent' ? floor(base * value / 100) : round(value)
 *   rawDiscount = clamp raw về [0, base]           (không giảm quá giá trị áp lên)
 *   compute     = min(rawDiscount, floor(base * capPercent / 100))  (thêm trần % đơn)
 *
 * Trước đây công thức này bị copy y hệt ở ReferralService/EmailBonusService và một
 * phần ở VoucherService → dễ lệch khi đổi cách làm tròn/trần. Giờ gom về một chỗ.
 */
class DiscountCalculator
{
    /** Giảm thô từ type/value, đã kẹp về [0, $base] (chưa áp trần % toàn đơn). */
    public static function rawDiscount(int $base, string $type, float $value): int
    {
        $raw = $type === 'percent'
            ? (int) floor($base * $value / 100)
            : (int) round($value);

        return max(0, min($raw, $base));
    }

    /** Giảm cuối cùng: rawDiscount thêm trần floor($base * $capPercent / 100). */
    public static function compute(int $base, string $type, float $value, float $capPercent): int
    {
        $cap = (int) floor($base * $capPercent / 100);

        return min(self::rawDiscount($base, $type, $value), $cap);
    }
}

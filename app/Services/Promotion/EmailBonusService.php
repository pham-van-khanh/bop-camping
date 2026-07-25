<?php

namespace App\Services\Promotion;

use App\Models\Order;
use App\Models\PromotionSetting;

/**
 * Ưu đãi khuyến khích thêm email (email không bắt buộc khi đăng ký/SĐT — chỉ số điện thoại
 * là khoá định danh). Giảm % cho đơn ĐẦU TIÊN của khách đã có email thật + đã xác thực OTP.
 * Cùng cơ chế "đơn đầu" như ReferralService::applyRefereeFirstOrderDiscount — single source
 * cho định nghĩa "đơn đầu" là "chưa có order nào khác gắn user_id này".
 */
class EmailBonusService
{
    /**
     * @return array{discount:int, reason:string}
     */
    public function applyFirstOrderDiscount(Order $order, ?PromotionSetting $settings = null): array
    {
        $settings ??= PromotionSetting::current();
        $fail = fn (string $reason) => ['discount' => 0, 'reason' => $reason];

        if (! $settings->email_bonus_enabled) {
            return $fail('disabled');
        }

        $user = $order->user;
        if (! $user) {
            return $fail('guest');
        }

        if (! $user->email_verified_at || $user->hasPlaceholderEmail()) {
            return $fail('no_verified_email');
        }

        // Chỉ đếm đơn TOP-LEVEL — đơn CON thuộc cụm hiện tại không phải "đơn trước đó" (bopcamping-wtuv T8).
        $hasPriorOrder = Order::where('user_id', $user->id)
            ->whereNull('parent_id')
            ->where('id', '!=', $order->id)
            ->exists();
        if ($hasPriorOrder) {
            return $fail('not_first_order');
        }

        $base = (int) $order->total_price;
        $raw = $settings->email_bonus_discount_type === 'percent'
            ? (int) floor($base * (float) $settings->email_bonus_discount_value / 100)
            : (int) round((float) $settings->email_bonus_discount_value);
        $cap = (int) floor($base * (float) $settings->max_discount_percent_per_order / 100);
        $discount = max(0, min($raw, $cap, $base));

        if ($discount > 0) {
            $order->applyDiscountLines([[
                'source' => 'email_bonus',
                'amount' => $discount,
                // Cờ để đổi lịch scale đúng theo ngày (bopcamping-lmk6).
                'percent' => $settings->email_bonus_discount_type === 'percent',
            ]]);
        }

        return ['discount' => $discount, 'reason' => 'ok'];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cấu hình chương trình khuyến mãi (singleton — 1 dòng duy nhất).
 * Admin chỉnh ở trang Settings. Mọi % / số tiền lấy từ đây, KHÔNG hardcode.
 */
class PromotionSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'referral_enabled' => 'boolean',
        'referee_discount_value' => 'decimal:2',
        'referrer_reward_value' => 'decimal:2',
        'referrer_reward_per_referral' => 'boolean',
        'max_discount_percent_per_order' => 'decimal:2',
        'max_vouchers_stack_per_order' => 'integer',
        'voucher_validity_days' => 'integer',
        'min_order_amount' => 'decimal:2',
        'max_referrals_per_user_per_month' => 'integer',
        'reward_clawback_enabled' => 'boolean',
        'email_bonus_enabled' => 'boolean',
        'email_bonus_discount_value' => 'decimal:2',
    ];

    /** Lấy bản ghi cấu hình duy nhất (tạo mặc định nếu chưa có). */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }
}

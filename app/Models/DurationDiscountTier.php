<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Bậc giảm giá thuê dài ngày (bopcamping-e36e). Admin cấu hình mốc ngày + %.
 * Chọn bậc: trong các bậc active, min_days lớn nhất mà days >= min_days.
 * Xem artifacts/adr_duration_discount.md.
 */
class DurationDiscountTier extends Model
{
    protected $guarded = [];

    protected $casts = [
        'min_days' => 'integer',
        'discount_percent' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /** Các bậc đang bật, sắp xếp min_days giảm dần (để chọn bậc cao nhất trước). */
    public static function activeDescending(): Collection
    {
        return static::query()->where('is_active', true)->orderByDesc('min_days')->get();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * OTP đăng nhập gửi qua email (KE_HOACH 8.1).
 * Lưu mã ĐÃ HASH; OTP 6 số, hết hạn 10', dùng 1 lần.
 * Logic gửi/xác thực nằm ở luồng đăng nhập (issue s2q) — model chỉ giữ trạng thái.
 */
class EmailOtp extends Model
{
    protected $guarded = [];

    protected $casts = [
        'attempts' => 'integer',
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    /** Còn dùng được: chưa hết hạn và chưa bị tiêu thụ. */
    public function isUsable(): bool
    {
        return ! $this->isExpired() && ! $this->isConsumed();
    }

    /** OTP còn hiệu lực cho một email (chưa hết hạn, chưa dùng). */
    public function scopeUsableFor(Builder $query, string $email): Builder
    {
        return $query->where('email', $email)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', Carbon::now());
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Voucher extends Model
{
    protected $fillable = [
        'user_id',
        'code',
        'amount',
        'source',
        'referred_user_id',
        'trigger_order_id',
        'order_id',
        'redeemed_at',
        'expires_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'redeemed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    /** Còn dùng được: chưa redeem và chưa hết hạn. */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('redeemed_at')
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function isUsable(): bool
    {
        return $this->redeemed_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}

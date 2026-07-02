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
        'type',
        'value',
        'source',
        'referral_id',
        'status',
        'min_order_amount',
        'applies_to',
        'applicable_to_combos',
        'max_uses',
        'used_count',
        'expires_at',
        'used_at',
        'order_id',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'applicable_to_combos' => 'boolean',
        'max_uses' => 'integer',
        'used_count' => 'integer',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Còn dùng được: active, chưa hết lượt, chưa hết hạn. */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereColumn('used_count', '<', 'max_uses')
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function isUsable(): bool
    {
        return $this->status === 'active'
            && $this->used_count < $this->max_uses
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}

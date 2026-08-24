<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Đánh giá (KE_HOACH 8.2). category: 'product' (màn chi tiết SP) | 'combo' (chi tiết
 * combo) | 'system' (trang chủ). status: pending → approved/rejected — chỉ approved
 * hiển thị công khai.
 */
class Review extends Model
{
    /** Các giá trị `category` hợp lệ — nguồn duy nhất cho validate + lọc ở admin. */
    public const CATEGORIES = ['system', 'product', 'combo'];

    protected $fillable = [
        'order_item_id', 'product_id', 'combo_id', 'user_id', 'reviewer_name', 'rating',
        'content', 'category', 'status', 'admin_note', 'review_token', 'review_token_used_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'review_token_used_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ReviewImage::class)->orderBy('sort_order');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }
}

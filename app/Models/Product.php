<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'name_normalized',
        'slug',
        'description',
        'price_per_day',
        'quantity',
        'deposit',
        'thumbnail',
        'status',
    ];

    protected $casts = [
        'price_per_day' => 'integer',
        'quantity' => 'integer',
        'deposit' => 'integer',
    ];

    /** Tự cập nhật name_normalized (bỏ dấu) mỗi khi name thay đổi. */
    protected static function booted(): void
    {
        static::saving(function (self $product) {
            if ($product->isDirty('name')) {
                $product->name_normalized = static::normalizeText($product->name);
            }
        });
    }

    /** Bỏ dấu tiếng Việt + thường hoá để tìm kiếm không dấu (tái dùng Str::slug). */
    public static function normalizeText(?string $text): string
    {
        return str_replace('-', ' ', Str::slug((string) $text));
    }

    /** Tìm theo tên (có/không dấu) + mô tả. */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $needle = static::normalizeText($term);

        return $query->where(function (Builder $sq) use ($term, $needle) {
            $sq->where('name', 'like', "%{$term}%")
                ->orWhere('name_normalized', 'like', "%{$needle}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}

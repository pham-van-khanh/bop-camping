<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** Vị trí phục vụ (Vinh, Hà Nội...) mà sản phẩm có cho thuê. */
    public function serviceLocations(): BelongsToMany
    {
        return $this->belongsToMany(ServiceLocation::class, 'product_service_location');
    }

    /** Case 2 — "thường thuê cùng": phụ kiện admin gán tay, theo sort_order (US-08). */
    public function accessories(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_accessories', 'product_id', 'related_product_id')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('product_accessories.sort_order');
    }

    /** Lọc sản phẩm cho thuê tại vị trí có slug tương ứng (vd 'vinh', 'ha-noi'). */
    public function scopeServedAt(Builder $query, int $serviceLocationId): Builder
    {
        return $query->whereHas('serviceLocations', fn (Builder $q) => $q->where('service_locations.id', $serviceLocationId));
    }

    /** Đánh giá đã duyệt (mới nhất trước) kèm ảnh. */
    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('status', 'approved')->with('images')->latest();
    }

    /** Điểm trung bình của đánh giá đã duyệt (1 chữ số thập phân), 0 nếu chưa có. */
    public function averageRating(): float
    {
        return round((float) $this->reviews()->where('status', 'approved')->avg('rating'), 1);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}

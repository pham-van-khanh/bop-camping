<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'name_normalized',
        'slug',
        'description',
        'specs',
        'setup_content',
        'price_per_day',
        'quantity',
        'deposit',
        'early_return_discount_pct',
        'pickup_hour',
        'return_hour',
        'thumbnail',
        'status',
    ];

    protected $casts = [
        'specs' => 'array',
        'price_per_day' => 'integer',
        'quantity' => 'integer',
        'deposit' => 'integer',
        'early_return_discount_pct' => 'integer',
        'pickup_hour' => 'integer',
        'return_hour' => 'integer',
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

    /** Vị trí phục vụ (Vinh, Hà Nội...) mà sản phẩm có cho thuê, kèm tồn kho + đệm quay vòng tại nơi đó. */
    public function serviceLocations(): BelongsToMany
    {
        return $this->belongsToMany(ServiceLocation::class, 'product_service_location')
            ->withPivot('quantity', 'buffer_days');
    }

    /** Tồn kho tại 1 cửa hàng (per-store stock). 0 nếu không phục vụ ở đó. */
    public function stockAt(int $serviceLocationId): int
    {
        $this->loadMissing('serviceLocations');
        $loc = $this->serviceLocations->firstWhere('id', $serviceLocationId);

        return $loc ? (int) $loc->pivot->quantity : 0;
    }

    /**
     * Đệm quay vòng (giặt/phơi) THEO KHO — số ngày sau ngày trả mà món chưa sẵn sàng
     * cho thuê lại (adr_turnaround_buffer). 0 nếu không phục vụ ở đó.
     */
    public function bufferAt(int $serviceLocationId): int
    {
        $this->loadMissing('serviceLocations');
        $loc = $this->serviceLocations->firstWhere('id', $serviceLocationId);

        return $loc ? (int) $loc->pivot->buffer_days : 0;
    }

    /**
     * Đệm lớn nhất trong các kho — dùng cho nhánh tính tồn TOÀN CỤC cũ ($location = null,
     * dữ liệu chưa gắn store) để không cho thuê lại khi món ở kho nào đó còn đang phơi.
     */
    public function maxBufferAcrossLocations(): int
    {
        $this->loadMissing('serviceLocations');

        return (int) ($this->serviceLocations->max('pivot.buffer_days') ?? 0);
    }

    /** Case 2 — "thường thuê cùng": phụ kiện admin gán tay, theo sort_order (US-08). */
    public function accessories(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_accessories', 'product_id', 'related_product_id')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('product_accessories.sort_order');
    }

    /** "You may also like" (Epic 1, 1.6): sản phẩm gợi ý admin tự chọn, theo sort_order. */
    public function related(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_related', 'product_id', 'related_product_id')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('product_related.sort_order');
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

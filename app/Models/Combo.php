<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

class Combo extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'combo_price',
        'deposit',
        'suitable_for',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'combo_price' => 'integer',
        'deposit' => 'integer',
        'suitable_for' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ComboItem::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ComboImage::class)->orderBy('sort_order');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Tổng giá thuê lẻ/ngày của các món trong combo — tính runtime, không lưu,
     * để không lệch khi giá lẻ thay đổi (PRD 5.2).
     */
    public function sumIndividualPrice(): int
    {
        $this->loadMissing('items.product');

        return (int) $this->items->sum(
            fn (ComboItem $item) => (int) ($item->product?->price_per_day ?? 0) * $item->quantity
        );
    }

    /** Tiết kiệm so với thuê lẻ (₫/ngày), không âm. */
    public function savingsAmount(): int
    {
        return max(0, $this->sumIndividualPrice() - $this->combo_price);
    }

    /** % tiết kiệm (làm tròn số nguyên), 0 nếu chưa có món nào. */
    public function savingsPercent(): int
    {
        $sum = $this->sumIndividualPrice();

        return $sum > 0 ? (int) round($this->savingsAmount() * 100 / $sum) : 0;
    }

    /**
     * Vị trí phục vụ của combo = GIAO các vị trí đang mở của mọi món con
     * (giỏ chỉ 1 vị trí — combo tham gia ràng buộc vị trí như sản phẩm lẻ).
     *
     * @return array<int, array{slug: string, name: string}>
     */
    public function commonOpenLocations(): array
    {
        $this->loadMissing('items.product.serviceLocations');

        $sets = $this->items
            ->map(fn (ComboItem $item) => $item->product?->serviceLocations
                ?->where('status', 'open')->keyBy('slug') ?? collect())
            ->filter(fn ($set) => $set->isNotEmpty());

        if ($sets->isEmpty()) {
            return [];
        }

        $common = $sets->shift();
        foreach ($sets as $set) {
            $common = $common->intersectByKeys($set);
        }

        return $common->map(fn (ServiceLocation $l) => ['slug' => $l->slug, 'name' => $l->name])
            ->values()
            ->all();
    }

    /**
     * US-07 — ẩn mọi combo active chứa sản phẩm (gọi khi admin ẩn/xoá product).
     * Single source of truth cho rule "không bán combo thiếu món".
     *
     * @return int số combo vừa bị ẩn
     */
    public static function hideForProduct(Product $product, string $reason): int
    {
        $comboIds = static::query()
            ->where('is_active', true)
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->pluck('id');

        if ($comboIds->isEmpty()) {
            return 0;
        }

        $hidden = static::whereIn('id', $comboIds)->update(['is_active' => false]);

        Log::info('Tự ẩn combo vì sản phẩm con không còn bán', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'combo_ids' => $comboIds->all(),
            'reason' => $reason,
        ]);

        return $hidden;
    }
}

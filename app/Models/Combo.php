<?php

namespace App\Models;

use Database\Factories\ComboFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class Combo extends Model
{
    /** @use HasFactory<ComboFactory> */
    use HasFactory;

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

    /** Kho ĐƯỢC GÁN cho combo (bopcamping-e5pi) — admin chọn tường minh, không suy ra từ món con. */
    public function serviceLocations(): BelongsToMany
    {
        return $this->belongsToMany(ServiceLocation::class, 'combo_service_location');
    }

    /**
     * Vị trí phục vụ của combo = kho ĐƯỢC GÁN và đang mở.
     * Giỏ chỉ 1 vị trí — combo tham gia ràng buộc vị trí như sản phẩm lẻ.
     *
     * Thay cho commonOpenLocations() cũ (GIAO kho của mọi món con): trước đây combo không có
     * kho riêng nên phải suy ra; nay admin gán tường minh. Giữ nguyên dạng trả về [{slug, name}]
     * để 4 chỗ gọi và FE không phải đổi type.
     *
     * @return array<int, array{slug: string, name: string}>
     */
    public function openLocations(): array
    {
        return $this->openServiceLocations()
            ->map(fn (ServiceLocation $l) => ['slug' => $l->slug, 'name' => $l->name])
            ->values()
            ->all();
    }

    /**
     * Id các kho được gán và đang mở — cho chỗ cần id thay vì slug/name (tính khả dụng,
     * validate checkout). Một chỗ duy nhất giữ luật "kho bán được của combo" để 3 nơi
     * dùng không lệch nhau.
     *
     * @return array<int, int>
     */
    public function openLocationIds(): array
    {
        return $this->openServiceLocations()->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    /** @return Collection<int, ServiceLocation> */
    private function openServiceLocations()
    {
        $this->loadMissing('serviceLocations');

        return $this->serviceLocations->where('status', 'open');
    }

    /**
     * Kho mà combo ĐƯỢC PHÉP gán: đang mở và MỌI món con đều phục vụ ở đó.
     *
     * ⚠️ Cơ sở là TƯ CÁCH THÀNH VIÊN pivot, KHÔNG phải tồn > 0 (quyết định #2, PRD mục 6 R2).
     * Chặn theo tồn sẽ khoá sạch: trên prod chỉ 3/11 sản phẩm còn tồn, có combo mọi món tồn 0
     * — admin sẽ không gán nổi kho nào. Tồn 0 là trạng thái vận hành bình thường của shop.
     *
     * @return array<int, int> id các kho
     */
    public function assignableLocationIds(): array
    {
        $this->loadMissing('items.product.serviceLocations');

        $sets = $this->items
            ->map(fn (ComboItem $item) => $item->product?->serviceLocations
                ?->where('status', 'open')->pluck('id') ?? collect())
            ->values();

        // Combo rỗng món / món đã bị xoá → không có kho nào chắc chắn phục vụ được.
        if ($sets->isEmpty() || $sets->contains(fn ($set) => $set->isEmpty())) {
            return [];
        }

        $common = $sets->shift();
        foreach ($sets as $set) {
            $common = $common->intersect($set);
        }

        return $common->map(fn ($id) => (int) $id)->values()->all();
    }

    /**
     * Tồn CẤU HÌNH (pivot quantity) của từng món con tại một kho — cho bảng "Món tại kho này"
     * ở admin. Chỉ là THÔNG TIN, không dùng để chặn gán kho (xem assignableLocationIds()).
     * Món không phục vụ ở kho đó → 0.
     *
     * @return array<int, int> [product_id => quantity]
     */
    public function stockAtLocation(int $serviceLocationId): array
    {
        $this->loadMissing('items.product.serviceLocations');

        $out = [];
        foreach ($this->items as $item) {
            if (! $item->product) {
                continue;
            }
            $out[$item->product->id] = $item->product->stockAt($serviceLocationId);
        }

        return $out;
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

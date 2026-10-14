<?php

namespace App\Models;

use App\Models\Concerns\HasAutoSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceLocation extends Model
{
    /*
     * `slug` là cột thật (bopcamping-y05c), dùng cho CẢ HAI: khoá lọc ?vi-tri= và URL trang
     * khu vực /thue-do-cam-trai/{slug}. Trước đây là accessor Str::slug(name); migration đã
     * backfill đúng công thức đó nên link ?vi-tri= cũ vẫn chạy.
     */
    use HasAutoSlug;

    protected $fillable = ['name', 'slug', 'area', 'address', 'map_url', 'intro', 'status', 'sort_order'];

    /** Điểm cắm trại gần vị trí phục vụ này. */
    public function campingSpots(): HasMany
    {
        return $this->hasMany(CampingSpot::class, 'nearest_service_location_id');
    }

    /** Sản phẩm cho thuê tại vị trí này. */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_service_location');
    }

    /** Sắp theo thứ tự hiển thị. */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** Chỉ vị trí đang mở. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }
}

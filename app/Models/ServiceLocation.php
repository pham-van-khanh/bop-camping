<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ServiceLocation extends Model
{
    protected $fillable = ['name', 'area', 'status', 'sort_order'];

    /** Slug không dấu từ tên (vd 'Hà Nội' -> 'ha-noi') để dùng cho filter URL. */
    public function getSlugAttribute(): string
    {
        return Str::slug($this->name);
    }

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

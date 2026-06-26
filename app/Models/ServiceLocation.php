<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceLocation extends Model
{
    protected $fillable = ['name', 'area', 'status', 'sort_order'];

    /** Điểm cắm trại gần vị trí phục vụ này. */
    public function campingSpots(): HasMany
    {
        return $this->hasMany(CampingSpot::class, 'nearest_service_location_id');
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampingSpot extends Model
{
    protected $fillable = [
        'name', 'province', 'district', 'terrain_tag', 'description', 'map_url',
        'best_season_from', 'best_season_to', 'nearest_service_location_id',
        'is_suggested', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'best_season_from' => 'integer',
            'best_season_to' => 'integer',
            'is_suggested' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** Ảnh + video của điểm (đã sắp thứ tự). */
    public function media(): HasMany
    {
        return $this->hasMany(CampingSpotMedia::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Vị trí phục vụ gần nhất (cho danh sách gợi ý ở hero). */
    public function nearestServiceLocation(): BelongsTo
    {
        return $this->belongsTo(ServiceLocation::class, 'nearest_service_location_id');
    }

    /** Điểm hiện ở panel hero "ĐIỂM CẮM TRẠI GỢI Ý". */
    public function scopeSuggested(Builder $query): Builder
    {
        return $query->where('is_suggested', true);
    }

    /** Sắp theo thứ tự hiển thị. */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** Danh sách tỉnh/thành đã có (cho gợi ý nhập + gom nhóm). */
    public static function provinces(): array
    {
        return static::query()->select('province')->distinct()->orderBy('province')->pluck('province')->all();
    }

    /** Nhãn mùa đẹp: "T10 - T4", hoặc "Cả năm" khi không đặt tháng. */
    public function seasonLabel(): string
    {
        if (! $this->best_season_from || ! $this->best_season_to) {
            return 'Cả năm';
        }

        return "T{$this->best_season_from} - T{$this->best_season_to}";
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampingSpot extends Model
{
    /** Nhãn hiển thị cho từng miền (single source of truth). */
    public const REGIONS = [
        'mien_bac' => 'Miền Bắc',
        'mien_trung' => 'Miền Trung',
        'mien_nam_tay_nguyen' => 'Miền Nam & Tây Nguyên',
    ];

    protected $fillable = [
        'name', 'region', 'province', 'district', 'terrain_tag', 'description',
        'best_season_from', 'best_season_to', 'nearest_service_location_id',
        'travel_time', 'is_suggested', 'sort_order',
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

    /** Vị trí phục vụ gần nhất (cho danh sách gợi ý + thời gian di chuyển). */
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

    /** Nhãn miền để hiển thị. */
    public function regionLabel(): string
    {
        return self::REGIONS[$this->region] ?? $this->region;
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Banner extends Model
{
    /** Vị trí hiển thị (single source of truth). */
    public const PLACEMENTS = [
        'hero' => 'Slideshow đầu trang',
        'promo' => 'Dải khuyến mãi',
    ];

    protected $fillable = [
        'placement', 'image', 'title', 'subtitle', 'link_url', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** Chỉ banner đang bật. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Lọc theo vị trí. */
    public function scopePlacement(Builder $query, string $placement): Builder
    {
        return $query->where('placement', $placement);
    }

    /** Sắp theo thứ tự hiển thị. */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** URL ảnh: ảnh upload (storage) hoặc đường dẫn public (/images/...) / link ngoài. */
    public function imageUrl(): string
    {
        return Str::startsWith($this->image, ['/', 'http']) ? $this->image : Storage::disk('media')->url($this->image);
    }
}

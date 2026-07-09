<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Câu hỏi thường gặp — hiển thị ở trang chủ (section accordion), admin CRUD.
 */
class Faq extends Model
{
    protected $fillable = ['question', 'answer', 'sort_order', 'is_active'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /** Chỉ FAQ đang hiển thị. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Sắp theo thứ tự hiển thị (nhỏ = lên trước). */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}

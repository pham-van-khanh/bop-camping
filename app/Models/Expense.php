<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Chi phí phát sinh của shop (bopcamping-h1s) — admin nhập tay để dựng bảng thu-chi.
 */
class Expense extends Model
{
    protected $fillable = ['spent_on', 'amount', 'category', 'note'];

    protected $casts = [
        'spent_on' => 'date',
        'amount' => 'integer',
    ];

    /** Loại chi phí hợp lệ + nhãn hiển thị (single source cho validate + FE). */
    public const CATEGORIES = ['equipment', 'repair', 'shipping', 'marketing', 'other'];

    public const CATEGORY_LABELS = [
        'equipment' => 'Mua thiết bị',
        'repair' => 'Sửa chữa',
        'shipping' => 'Vận chuyển',
        'marketing' => 'Marketing',
        'other' => 'Khác',
    ];
}

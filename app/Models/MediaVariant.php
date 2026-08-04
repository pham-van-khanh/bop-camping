<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Một biến thể đã resize của file ảnh `source_path` (bopcamping-ix4n).
 * Sinh/xoá qua App\Services\MediaVariantService — đừng tạo tay.
 */
class MediaVariant extends Model
{
    protected $fillable = ['source_path', 'width', 'height', 'path'];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
    ];
}

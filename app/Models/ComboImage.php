<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComboImage extends Model
{
    protected $fillable = [
        'combo_id',
        'path',
        'sort_order',
        'type',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Event log gợi ý combo trong giỏ (US-09): shown / converted, kèm loại gợi ý.
 */
class ComboEvent extends Model
{
    public const SHOWN = 'shown';

    public const CONVERTED = 'converted';

    protected $fillable = ['combo_id', 'event', 'suggestion_type', 'user_id'];

    public function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class);
    }
}

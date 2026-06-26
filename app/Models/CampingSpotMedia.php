<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampingSpotMedia extends Model
{
    protected $table = 'camping_spot_media';

    protected $fillable = ['camping_spot_id', 'type', 'path', 'sort_order'];

    public function campingSpot(): BelongsTo
    {
        return $this->belongsTo(CampingSpot::class);
    }
}

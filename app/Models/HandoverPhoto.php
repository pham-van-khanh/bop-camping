<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ảnh chụp lúc giao / lúc thu đồ — bằng chứng thực hiện hợp đồng. */
class HandoverPhoto extends Model
{
    public const KINDS = ['pickup', 'return'];

    protected $fillable = ['contract_id', 'contract_item_id', 'kind', 'path', 'uploaded_by'];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}

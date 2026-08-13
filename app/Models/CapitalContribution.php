<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một lần góp vốn của thành viên quản trị (bopcamping-n4qy).
 *
 * Tổng vốn của một người = SUM các dòng của người đó — đừng cache thành cột riêng,
 * sớm muộn cũng lệch với sổ.
 */
class CapitalContribution extends Model
{
    protected $fillable = ['user_id', 'amount', 'contributed_on', 'note'];

    protected $casts = [
        'contributed_on' => 'date',
        'amount' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

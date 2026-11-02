<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một sự kiện truy cập của khách (chỉ ghi, không sửa). Tự dọn sau RETENTION_DAYS ngày. */
class VisitEvent extends Model
{
    use Prunable;

    public const TYPE_PAGE = 'page_view';

    public const TYPE_PRODUCT = 'product_view';

    public const TYPE_CONTACT = 'contact_click';

    public const METHODS = ['zalo', 'phone', 'messenger'];

    private const RETENTION_DAYS = 180;

    public $timestamps = false;

    protected $fillable = [
        'visitor_id', 'type', 'path', 'product_id', 'method', 'placement', 'source', 'user_id', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }
}

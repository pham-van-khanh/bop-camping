<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'combo_id',
        'combo_group_uuid',
        'quantity',
        'price_per_day',
        'days',
        'subtotal',
        'duration_discount_percent',
        'allocated_price',
        'allocated_deposit',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price_per_day' => 'integer',
        'days' => 'integer',
        'subtotal' => 'integer',
        'duration_discount_percent' => 'decimal:2',
        'allocated_price' => 'integer',
        'allocated_deposit' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Combo mà item này được bung ra từ đó — null nếu thuê lẻ. */
    public function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class);
    }
}

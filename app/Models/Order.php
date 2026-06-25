<?php

namespace App\Models;

use App\Observers\OrderObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[ObservedBy([OrderObserver::class])]
class Order extends Model
{
    protected $fillable = [
        'user_id',
        'code',
        'customer_name',
        'customer_phone',
        'customer_email',
        'customer_address',
        'review_token',
        'review_invited_at',
        'review_submitted_at',
        'start_date',
        'end_date',
        'total_price',
        'deposit_total',
        'discount_total',
        'status',
        'payment_method',
        'note',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_price' => 'integer',
        'deposit_total' => 'integer',
        'discount_total' => 'integer',
        'review_invited_at' => 'datetime',
        'review_submitted_at' => 'datetime',
    ];

    /** Tự sinh mã đơn khi tạo */
    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->code)) {
                $order->code = 'BOP-'.strtoupper(substr(uniqid(), -6));
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Voucher đã áp cho đơn này (đã dùng). */
    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    /** Lượt giới thiệu mà đơn này là đơn đầu (referee) — nếu có. */
    public function referralUse(): HasOne
    {
        return $this->hasOne(Referral::class, 'first_order_id');
    }

    /** Số ngày thuê */
    public function getDaysAttribute(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    /** Số tiền phải trả khi nhận (thuê + cọc − giảm giá). */
    public function getAmountDueAttribute(): int
    {
        return (int) $this->total_price + (int) $this->deposit_total - (int) $this->discount_total;
    }

    /** Email gửi thông báo được (bỏ email tạm <phone>@bopcamping.local). Null nếu không gửi được. */
    public function notifiableEmail(): ?string
    {
        $email = $this->customer_email;

        if (! $email || str_ends_with($email, '@bopcamping.local')) {
            return null;
        }

        return $email;
    }

    /** Các trạng thái hợp lệ để tính tồn kho (đơn chưa huỷ) */
    public static function activeStatuses(): array
    {
        return ['pending', 'confirmed', 'renting'];
    }
}

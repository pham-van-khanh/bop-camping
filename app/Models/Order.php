<?php

namespace App\Models;

use App\Observers\OrderObserver;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

#[ObservedBy([OrderObserver::class])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'parent_id',
        'is_parent',
        'service_location_id',
        'location_auto_assigned',
        'code',
        'customer_name',
        'customer_phone',
        'customer_email',
        'customer_address',
        'review_token',
        'review_invited_at',
        'review_submitted_at',
        'pickup_reminder_sent_at',
        'start_date',
        'end_date',
        'is_half_day',
        'session',
        'requested_pickup_time',
        'requested_return_time',
        'total_price',
        'deposit_total',
        'extra_fee',
        'extra_fee_note',
        'discount_total',
        'discount_breakdown',
        'status',
        'payment_method',
        'payment_status',
        'deposit_refund_status',
        'deposit_refund_note',
        'note',
    ];

    /** Tình trạng chuyển tiền (marker admin — bopcamping-7be). */
    public const PAYMENT_STATUSES = ['unpaid', 'deposit', 'full'];

    /** Tình trạng hoàn cọc khi đơn đã trả (bopcamping-7be). */
    public const REFUND_STATUSES = ['pending', 'refunded'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_half_day' => 'boolean',
        'total_price' => 'integer',
        'deposit_total' => 'integer',
        'extra_fee' => 'integer',
        'discount_total' => 'integer',
        'discount_breakdown' => 'array',
        'is_parent' => 'boolean',
        'location_auto_assigned' => 'boolean',
        'review_invited_at' => 'datetime',
        'review_submitted_at' => 'datetime',
        'pickup_reminder_sent_at' => 'datetime',
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

    /** Cửa hàng thuê (per-store stock) — trừ kho đúng store này. */
    public function serviceLocation(): BelongsTo
    {
        return $this->belongsTo(ServiceLocation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Đơn cha (bopcamping-wtuv) — null nếu là đơn thường hoặc chính là cha. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'parent_id');
    }

    /** Các đơn con (mỗi con = 1 khoảng ngày) — rỗng với đơn thường. */
    public function children(): HasMany
    {
        return $this->hasMany(Order::class, 'parent_id')->orderBy('start_date');
    }

    /**
     * Đơn "cấp cao" cho danh sách/đếm: đơn thường + đơn cha, ẨN đơn con
     * (con chỉ hiện trong cha). Tránh nhân đôi khi liệt kê/đếm.
     */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Trạng thái hiển thị của đơn CHA — suy từ các con (cha không có status thao tác riêng):
     * còn con chờ xác nhận → pending; có con đang thuê → renting; mọi con đã trả → returned;
     * mọi con huỷ → cancelled; còn lại → confirmed.
     */
    public function aggregateStatus(): string
    {
        $statuses = $this->children->pluck('status');
        if ($statuses->isEmpty()) {
            return $this->status;
        }
        if ($statuses->every(fn ($s) => $s === 'cancelled')) {
            return 'cancelled';
        }
        $active = $statuses->reject(fn ($s) => $s === 'cancelled');
        if ($active->contains('pending')) {
            return 'pending';
        }
        if ($active->contains('renting')) {
            return 'renting';
        }
        if ($active->every(fn ($s) => $s === 'returned')) {
            return 'returned';
        }

        return 'confirmed';
    }

    /**
     * Phân bổ discount của đơn CHA xuống các con ∝ tiền thuê con (bopcamping-wtuv) — NGUỒN
     * CHÂN LÝ chung cho checkout lẫn recompute (huỷ/đổi lịch con). Dồn phần dư vào con cuối
     * để Σ discount con === $this->discount_total. Con nhận = $children (thường là con active).
     *
     * @param  Collection<int, Order>  $children
     */
    public function allocateDiscountToChildren(Collection $children): void
    {
        $discount = (int) $this->discount_total;
        $totalRental = (int) $children->sum('total_price');
        $allocated = 0;
        $last = $children->count() - 1;

        foreach ($children->values() as $i => $child) {
            $share = ($discount <= 0 || $totalRental <= 0)
                ? 0
                : ($i === $last ? $discount - $allocated : (int) floor($discount * (int) $child->total_price / $totalRental));
            $allocated += $share;
            $child->update([
                'discount_total' => $share,
                'discount_breakdown' => $share > 0 ? [['source' => 'parent_alloc', 'amount' => $share, 'percent' => true]] : null,
            ]);
        }
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

    /** Số tiền phải trả khi nhận (thuê + cọc + phụ phí ngoài khung giờ − giảm giá). */
    public function getAmountDueAttribute(): int
    {
        return (int) $this->total_price + (int) $this->deposit_total + (int) $this->extra_fee - (int) $this->discount_total;
    }

    /**
     * Sinh review_token nếu chưa có, trả về token (bopcamping-bhr).
     * OrderObserver chỉ sinh token khi đơn trả CÓ email; đơn vãng lai (chỉ SĐT)
     * không có token → tạo on-demand để khách đánh giá từ trang tài khoản.
     */
    public function ensureReviewToken(): string
    {
        if (! $this->review_token) {
            $this->forceFill(['review_token' => Str::random(40)])->saveQuietly();
        }

        return $this->review_token;
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

    /**
     * Cộng giảm giá KÈM lưu vết nguồn (bopcamping-3ag) — dòng amount 0 bị loại.
     * Bất biến: sum(discount_breakdown.amount) === discount_total.
     *
     * @param  array<int, array{source: string, amount: int, code?: string, percent?: bool}>  $lines
     */
    public function applyDiscountLines(array $lines): void
    {
        $lines = array_values(array_filter($lines, fn (array $l) => (int) $l['amount'] !== 0));
        if ($lines === []) {
            return;
        }

        $this->update([
            'discount_total' => (int) $this->discount_total + (int) array_sum(array_column($lines, 'amount')),
            'discount_breakdown' => array_merge($this->discount_breakdown ?? [], $lines),
        ]);
    }
}

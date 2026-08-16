<?php

namespace App\Models;

use App\Observers\OrderObserver;
use App\Services\DeliveryScheduleService;
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
        'delivery_method',
        'location_auto_assigned',
        'code',
        'customer_name',
        'customer_phone',
        'customer_email',
        'customer_address',
        // Mã địa chỉ sau sát nhập (bopcamping-9299) — chỉ để thống kê; customer_address
        // vẫn là nguồn chân lý cho giao nhận.
        'province_code',
        'ward_code',
        'street',
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
        // Giờ giao/thu shop ĐÃ CHỐT + ghi chú nội bộ cho shipper (bopcamping-641t).
        'confirmed_pickup_time',
        'confirmed_return_time',
        'schedule_note',
        'schedule_confirmed_at',
        // Gán shipper cho từng lượt giao/thu (bopcamping-xdvx).
        'pickup_shipper_id',
        'return_shipper_id',
        // Thu tiền theo 2 khoản độc lập (bopcamping-q7i0) — payment_status là giá trị SUY RA.
        'rental_paid_at',
        'rental_paid_by',
        'rental_paid_amount',
        'deposit_paid_at',
        'deposit_paid_by',
        'deposit_paid_amount',
        'fee_paid_at',
        'fee_paid_by',
        'fee_paid_amount',
        // Dấu ai đã làm gì: hoàn cọc, bấm đã giao, bấm đã thu đồ (bopcamping-3wfk).
        'deposit_refunded_at',
        'deposit_refunded_by',
        'delivered_at',
        'delivered_by',
        'collected_at',
        'collected_by',
        'total_price',
        'deposit_total',
        'extra_fee',
        'extra_fee_note',
        'extra_fees',
        'discount_total',
        'discount_breakdown',
        'status',
        'payment_method',
        'payment_status',
        'deposit_refund_status',
        'deposit_refund_note',
        'deposit_refund_amount',
        'note',
    ];

    /** Tình trạng hoàn cọc khi đơn đã trả (bopcamping-7be). */
    public const REFUND_STATUSES = ['pending', 'refunded'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_half_day' => 'boolean',
        'total_price' => 'integer',
        'deposit_total' => 'integer',
        'extra_fee' => 'integer',
        'extra_fees' => 'array',
        'discount_total' => 'integer',
        'discount_breakdown' => 'array',
        'is_parent' => 'boolean',
        'location_auto_assigned' => 'boolean',
        'review_invited_at' => 'datetime',
        'review_submitted_at' => 'datetime',
        'pickup_reminder_sent_at' => 'datetime',
        'schedule_confirmed_at' => 'datetime',
        'rental_paid_at' => 'datetime',
        'rental_paid_amount' => 'integer',
        'deposit_paid_at' => 'datetime',
        'deposit_paid_amount' => 'integer',
        'fee_paid_at' => 'datetime',
        'fee_paid_amount' => 'integer',
        'deposit_refund_amount' => 'integer',
        'deposit_refunded_at' => 'datetime',
        'delivered_at' => 'datetime',
        'collected_at' => 'datetime',
    ];

    /**
     * 5 mốc có ghi dấu "ai làm, lúc nào" (bopcamping-3wfk) → tiền tố cột `<key>_at|_by`.
     * Thứ tự = thứ tự việc diễn ra trên thực tế, dùng luôn cho UI.
     */
    public const TRACKED_ACTIONS = [
        'rental_paid' => 'Đã nhận tiền thuê',
        'fee_paid' => 'Đã nhận phụ phí',
        'deposit_paid' => 'Đã nhận tiền cọc',
        'delivered' => 'Đã giao đồ',
        'collected' => 'Đã thu đồ',
        'deposit_refunded' => 'Đã hoàn cọc',
    ];

    /**
     * Các khoản tiền thu riêng của 1 đơn (bopcamping-q7i0; thêm 'fee' ở bopcamping-urqo).
     *
     * Phụ phí tách khỏi tiền thuê để chủ shop biết khoản nào đã thu — trước đây gộp chung
     * nên "tiền thuê còn thiếu 50k" không nói được thiếu ở đâu.
     */
    public const PAYMENT_KINDS = ['rental', 'fee', 'deposit'];

    /**
     * Hình thức GIAO khách chọn ở checkout (bopcamping-z3ug).
     *
     * Chỉ hỏi lượt GIAO. Lượt TRẢ thoả thuận khi shop nhắn tin với khách rồi ghi vào
     * `schedule_note`; phí ship ghi vào `extra_fee` — checkout KHÔNG tự tính tiền.
     */
    public const DELIVERY_METHODS = ['self_pickup', 'ship'];

    public const DELIVERY_METHOD_LABELS = [
        'self_pickup' => 'Tự đến xem đồ',
        'ship' => 'Nhận tại địa điểm',
    ];

    /** Mô tả ngắn đi kèm nhãn — nói thẳng chuyện phí để khách không bất ngờ lúc gọi xác nhận. */
    public const DELIVERY_METHOD_HINTS = [
        'self_pickup' => 'Bạn tới kho xem và kiểm đồ tại chỗ. Không mất phí.',
        'ship' => 'Bốp giao tới nơi bạn hẹn. Phí giao tụi mình báo khi gọi xác nhận đơn.',
    ];

    /** @return array<int, array{value: string, label: string, hint: string}> */
    public static function deliveryMethodOptions(): array
    {
        return array_map(fn (string $v) => [
            'value' => $v,
            'label' => self::DELIVERY_METHOD_LABELS[$v],
            'hint' => self::DELIVERY_METHOD_HINTS[$v],
        ], self::DELIVERY_METHODS);
    }

    public function deliveryMethodLabel(): string
    {
        return self::DELIVERY_METHOD_LABELS[$this->delivery_method] ?? $this->delivery_method;
    }

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

    /** Shipper đi GIAO đơn này (null = chưa gán) — bopcamping-xdvx. */
    public function pickupShipper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pickup_shipper_id');
    }

    /** Shipper đi THU đơn này (null = chưa gán). */
    public function returnShipper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'return_shipper_id');
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
        return $this->rental_due + (int) $this->deposit_total;
    }

    /**
     * Số tiền THẬT SỰ đã thu của từng khoản (bopcamping-r3fy).
     *
     * Đơn cũ có mốc thu nhưng chưa có cột số tiền (trước migration) thì coi như đã thu đủ
     * phần đó — đúng bằng nghĩa cũ của cái cờ, để không bỗng dưng biến chúng thành còn nợ.
     */
    public function rentalPaidAmount(): int
    {
        if (! $this->rentalPaid()) {
            return 0;
        }

        // KHÔNG kẹp về tiền thuê gốc. Kẹp thì admin rút ngắn lịch (giá tụt 500k→300k) là
        // số "Shop đã nhận" hiện cho khách tụt theo — hệ thống tự khai nhận ít hơn số khách
        // đã trả. Phần dôi ra do legacyFeeCredit() lo riêng, và outstanding_due đã max(0)
        // từng khoản nên không đếm đôi.
        return max(0, (int) ($this->rental_paid_amount ?? $this->rental_due));
    }

    public function depositPaidAmount(): int
    {
        return $this->depositPaid() ? (int) ($this->deposit_paid_amount ?? $this->deposit_total) : 0;
    }

    /**
     * Phụ phí đã thu chưa (bopcamping-urqo).
     *
     * ĐƠN CŨ KHÔNG BỊ GHI ĐÈ MỘT DÒNG NÀO. Trước tính năng này, "đã thu tiền thuê" mang
     * nghĩa thu cả phụ phí, và số ghi lại là `rental_due` (đã gồm phụ phí). Nhận ra bằng
     * chính con số đó thay vì hardcode một mốc ngày — mốc ngày luôn mục nát sau vài lần
     * deploy, còn luật này tự đúng cho cả hai chiều:
     *
     *   đơn cũ  thu 550k, rental_due 550k → 550 ≥ 550 → phụ phí đã thu
     *   đơn mới thu 500k gốc, sau thêm ship 50k → 500 < 550 → phụ phí còn nợ
     */
    public function feePaid(): bool
    {
        return $this->fee_due > 0 && $this->feePaidAmount() >= $this->fee_due;
    }

    /**
     * Phần phụ phí mà đơn CŨ đã thu kèm trong tiền thuê — suy ra SỐ TIỀN, không phải cờ.
     *
     * Nghĩa cũ: "đã thu tiền thuê" = thu cả rental_due (gồm phụ phí). Phần vượt quá tiền
     * thuê gốc chính là phụ phí đã thu.
     *
     * PHẢI là số tiền chứ không phải đúng/sai. Bản đầu dùng cờ
     * (rental_paid_amount >= rental_due) và hỏng ở hai chỗ đã đo được:
     *   - admin NÂNG phụ phí 50k→80k trên đơn cũ: cờ lật về false, hệ thống đòi lại cả
     *     80k thay vì 30k chênh — khách bị trừ cọc thừa đúng 50k.
     *   - không bỏ đánh dấu được: markPaid('fee', false) xong cờ vẫn true.
     * Suy ra số tiền thì phần đã thu đứng yên, giá đổi bao nhiêu cũng chỉ đòi phần chênh.
     */
    private function legacyFeeCredit(): int
    {
        if (! $this->rentalPaid()) {
            return 0;
        }

        // Kẹp trần bằng chính phụ phí: phần dôi ra vì lý do khác (vd admin giảm giá sau
        // khi thu) không phải là phụ phí đã trả.
        return min($this->fee_due, max(0, (int) ($this->rental_paid_amount ?? $this->rental_due) - $this->base_rental_due));
    }

    /**
     * fee_paid_amount = null nghĩa là CHƯA TỪNG ghi nhận (đơn cũ) → suy ra từ tiền thuê.
     * Ghi 0 là ghi nhận rõ ràng "chưa thu đồng nào" — nhờ vậy bỏ đánh dấu mới đè được
     * lên phần suy ra của đơn cũ.
     */
    public function feePaidAmount(): int
    {
        return $this->fee_paid_amount !== null
            ? (int) $this->fee_paid_amount
            : $this->legacyFeeCredit();
    }

    /** Phụ phí còn thiếu — dùng để trừ vào cọc lúc hoàn. */
    public function feeOutstanding(): int
    {
        return max(0, $this->fee_due - $this->feePaidAmount());
    }

    /**
     * Số tiền CÒN phải thu (bopcamping-pew1, sửa gốc ở bopcamping-r3fy).
     *
     * Trừ theo SỐ TIỀN đã thu chứ không theo cờ đã-thu-hay-chưa: giá đơn còn đổi được sau
     * lúc thu (admin nhập phụ phí, đổi lịch), nên lấy cờ là mọi khoản chênh sau đó biến mất
     * không ai đòi. Chỗ nào đòi tiền khách (vd QR chuyển khoản) phải dùng con số này.
     *
     * Kẹp max(0) TỪNG KHOẢN, không kẹp ở tổng: giảm giá vượt tiền thuê làm rental_due âm,
     * kẹp ở tổng thì phần âm đó ăn lẹm vào tiền cọc và QR đòi thiếu đúng bằng nó.
     */
    public function getOutstandingDueAttribute(): int
    {
        return max(0, $this->base_rental_due - $this->rentalPaidAmount())
            + $this->feeOutstanding()
            + max(0, (int) $this->deposit_total - $this->depositPaidAmount());
    }

    /**
     * Số tiền QR đòi CHUYỂN KHOẢN — khác `outstanding_due` ở đúng một chỗ: phụ phí
     * (bopcamping-urqo).
     *
     * Khách đã chuyển tiền thuê rồi thì không bắt chuyển thêm lần nữa cho khoản lẻ vài
     * chục nghìn; phụ phí đó TRỪ VÀO CỌC lúc hoàn (xem refund_due). Nhờ vậy khách không
     * phải cầm thêm tiền mặt, nên chỗ nào hiện "còn phải trả" cho khách cũng dùng con số
     * này — dùng outstanding_due sẽ doạ khách một khoản họ không cần chuẩn bị.
     */
    public function getTransferDueAttribute(): int
    {
        return max(0, $this->base_rental_due - $this->rentalPaidAmount())
            + ($this->rentalPaid() ? 0 : $this->feeOutstanding())
            + max(0, (int) $this->deposit_total - $this->depositPaidAmount());
    }

    /** Tiền thuê GỐC, chưa gồm phụ phí (bopcamping-urqo). */
    public function getBaseRentalDueAttribute(): int
    {
        return (int) $this->total_price - (int) $this->discount_total;
    }

    /** Phụ phí — gộp mọi dòng, là một khoản thu độc lập. */
    public function getFeeDueAttribute(): int
    {
        return (int) $this->extra_fee;
    }

    /**
     * Số cọc thực trả lại khách = cọc đã thu − phụ phí còn thiếu (bopcamping-urqo).
     *
     * Kẹp ≥ 0: phụ phí lớn hơn cọc thì trừ không đủ, phần thiếu để refundShortfall() báo
     * admin thu tay chứ không đẻ ra số âm.
     */
    public function getRefundDueAttribute(): int
    {
        return max(0, $this->depositPaidAmount() - $this->feeOutstanding());
    }

    /**
     * Số phụ phí THỰC SỰ giữ lại từ cọc — cọc ít hơn phụ phí thì chỉ giữ được bấy nhiêu.
     *
     * Đã hoàn rồi thì đọc từ số đã chốt (cọc đã thu − số trả lại khách), vì feeOutstanding()
     * lúc đó đã trừ đi phần vừa giữ nên tính lại sẽ ra thiếu.
     */
    public function refundWithheld(): int
    {
        if ($this->deposit_refund_status === 'refunded') {
            return max(0, $this->depositPaidAmount() - (int) $this->deposit_refund_amount);
        }

        return min($this->feeOutstanding(), $this->depositPaidAmount());
    }

    /**
     * Phần phụ phí KHÔNG trừ hết được vào cọc — admin phải thu tay.
     *
     * Đã hoàn rồi thì cọc không còn gì để trừ nữa, nên phần phụ phí còn thiếu CHÍNH LÀ
     * phần phải thu tay. Trừ tiếp depositPaidAmount() ở đây là con số về 0 ngay sau khi
     * hoàn — cảnh báo biến mất khỏi màn admin đúng lúc cần nó nhất (bopcamping-urqo).
     */
    public function refundShortfall(): int
    {
        if ($this->deposit_refund_status === 'refunded') {
            return $this->feeOutstanding();
        }

        return max(0, $this->feeOutstanding() - $this->depositPaidAmount());
    }

    /** Riêng phần TIỀN THUÊ phải thu (đã gồm phụ phí, đã trừ giảm giá) — không gồm cọc. */
    public function getRentalDueAttribute(): int
    {
        return (int) $this->total_price + (int) $this->extra_fee - (int) $this->discount_total;
    }

    /**
     * Danh sách phụ phí đã chuẩn hoá để hiển thị (bopcamping-f1yj).
     *
     * Đọc `extra_fees`; đơn CŨ chưa có JSON thì dựng lại một khoản từ cặp
     * (extra_fee, extra_fee_note) — nhờ vậy mail và admin không phải if/else theo đời
     * dữ liệu. Bỏ khoản value <= 0 và khoản không tên để không in dòng rỗng.
     *
     * @return list<array{name: string, value: int}>
     */
    public function extraFeeLines(): array
    {
        $rows = is_array($this->extra_fees) && $this->extra_fees !== []
            ? $this->extra_fees
            : ((int) $this->extra_fee > 0
                ? [['name' => $this->extra_fee_note ?: 'Phụ phí', 'value' => (int) $this->extra_fee]]
                : []);

        return collect($rows)
            ->map(fn ($r) => [
                'name' => trim((string) ($r['name'] ?? '')) ?: 'Phụ phí',
                'value' => (int) ($r['value'] ?? 0),
            ])
            ->filter(fn (array $r) => $r['value'] > 0)
            ->values()
            ->all();
    }

    /**
     * Tổng phụ phí suy TỪ DANH SÁCH — nguồn duy nhất để ghi vào cột `extra_fee`.
     *
     * Cột đó là bản tổng đã lưu sẵn (rental_due đọc nó), nên nếu tính tổng ở nhiều nơi
     * thì sớm muộn cũng lệch với danh sách. Mọi chỗ ghi phải đi qua đây.
     *
     * @param  array<int, array{name?: string, value?: mixed}>  $lines
     */
    public static function sumExtraFees(array $lines): int
    {
        return collect($lines)->sum(fn ($r) => max(0, (int) ($r['value'] ?? 0)));
    }

    /**
     * Đơn đã được admin xác nhận với khách (đang chờ giao hoặc đang thuê). Mốc để: áp giờ
     * mặc định toàn shop, và cho phép thu tiền — đơn còn 'pending' thì giá/lịch chưa chắc.
     */
    public function isConfirmed(): bool
    {
        return in_array($this->status, DeliveryScheduleService::CONFIRMED_STATUSES, true);
    }

    public function rentalPaid(): bool
    {
        return $this->rental_paid_at !== null;
    }

    public function depositPaid(): bool
    {
        return $this->deposit_paid_at !== null;
    }

    /**
     * Đánh dấu (bỏ đánh dấu) đã thu 1 khoản — LỐI VÀO DUY NHẤT để đổi tình trạng tiền
     * (bopcamping-q7i0). Ghi luôn ai thu để đối soát, rồi đồng bộ payment_status suy ra.
     *
     * @param  'rental'|'deposit'  $kind
     */
    public function markPaid(string $kind, bool $paid, ?int $byUserId = null): void
    {
        // Chụp lại SỐ TIỀN tại đúng thời điểm bấm (bopcamping-r3fy). Giá đơn còn đổi được
        // sau đó, nên chỉ ghi cờ là mất dấu khoản chênh — xem chú thích ở outstanding_due.
        //
        // 'rental' ghi tiền thuê GỐC, không gồm phụ phí (bopcamping-urqo) — nhờ vậy đơn mới
        // luôn rơi vào vế "<" của luật đơn cũ ở feePaid() khi phụ phí phát sinh sau.
        $amount = match ($kind) {
            'rental' => $this->base_rental_due,
            'fee' => $this->fee_due,
            default => (int) $this->deposit_total,
        };

        $this->forceFill([
            "{$kind}_paid_at" => $paid ? now() : null,
            "{$kind}_paid_by" => $paid ? $byUserId : null,
            // Bỏ đánh dấu ghi 0 chứ KHÔNG ghi null: null mang nghĩa "chưa từng ghi nhận"
            // và với khoản phụ phí thì nó rơi về phần suy ra của đơn cũ (legacyFeeCredit),
            // tức bấm bỏ đánh dấu xong vẫn hiện đã thu — đã đo được đúng lỗi này.
            "{$kind}_paid_amount" => $paid ? max(0, $amount) : 0,
        ]);
        $this->syncPaymentStatus();
        $this->save();
    }

    /**
     * payment_status là GIÁ TRỊ SUY RA từ 2 khoản (nghĩa cũ chỉ có 3 mức nên "mới thu 1
     * trong 2 khoản" đều gom vào 'deposit' = đã thu một phần). Không ghi payment_status
     * ở bất kỳ chỗ nào khác — mọi thay đổi phải đi qua markPaid().
     */
    public function syncPaymentStatus(): void
    {
        // Suy từ SỐ TIỀN còn thiếu, không đếm cờ (bopcamping-urqo): từ khi có khoản phụ
        // phí, đếm cờ sẽ báo 'full' cho đơn mới thu 2/3 khoản.
        $collected = $this->rentalPaidAmount() + $this->feePaidAmount() + $this->depositPaidAmount();

        $this->payment_status = match (true) {
            $this->outstanding_due === 0 => 'full',
            $collected > 0 => 'deposit',   // nghĩa cũ: đã thu MỘT PHẦN (3 mức, giữ nguyên tên)
            default => 'unpaid',
        };
    }

    /** Người đánh dấu đã thu tiền thuê (admin hoặc shipper) — cho đối soát. */
    public function rentalPaidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rental_paid_by');
    }

    /** Người đánh dấu đã thu cọc. */
    public function depositPaidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deposit_paid_by');
    }

    /** Người hoàn cọc lại cho khách. */
    public function depositRefundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deposit_refunded_by');
    }

    /** Người bấm "đã giao đồ". */
    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    /** Người bấm "đã thu đồ". */
    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    /**
     * Ghi dấu 1 mốc nếu CHƯA có dấu — giữ người làm ĐẦU TIÊN, không cho lần bấm sau ghi đè
     * (vd admin đổi trạng thái qua lại thì vẫn còn dấu shipper đã giao thật).
     *
     * @param  key-of<self::TRACKED_ACTIONS>  $action
     */
    public function stampAction(string $action, ?int $byUserId): void
    {
        if ($this->{"{$action}_at"} !== null) {
            return;
        }

        $this->forceFill(["{$action}_at" => now(), "{$action}_by" => $byUserId])->save();
    }

    /**
     * Hoàn cọc cho khách — LỐI VÀO DUY NHẤT (admin và shipper dùng chung) để luôn có dấu
     * ai hoàn, lúc nào. Đặt lại 'pending' thì xoá dấu (coi như chưa hoàn).
     */
    public function markRefunded(bool $refunded, ?int $byUserId, ?string $note = null): void
    {
        $was = $this->deposit_refund_status === 'refunded';

        if ($refunded && ! $was) {
            // CHUYỂN pending → refunded. Phụ phí chưa thu được GIỮ LẠI từ cọc, nên đánh dấu
            // khoản đó đã thu luôn: tiền đã về tay shop qua đường giữ lại.
            //
            // Chỉ làm ở đúng lần chuyển này. Bản đầu làm mỗi lần gọi, nên admin chỉ cần
            // bổ sung ghi chú sau khi đã hoàn là feeOutstanding() đã về 0 → số hoàn ghi
            // lại nhảy lên NGUYÊN cọc. Admin đọc con số đó rồi đưa khách dư đúng phần
            // phụ phí vừa giữ (đã đo được: 150.000 → 200.000).
            $deducted = min($this->feeOutstanding(), $this->depositPaidAmount());

            // Chốt số hoàn TRƯỚC khi đánh dấu phụ phí — đánh dấu xong feeOutstanding() về 0.
            $refundAmount = $this->refund_due;

            if ($deducted > 0) {
                $this->forceFill([
                    'fee_paid_at' => now(),
                    'fee_paid_by' => $byUserId,
                    'fee_paid_amount' => $this->feePaidAmount() + $deducted,
                ]);
            }

            $this->forceFill(['deposit_refund_amount' => $refundAmount]);
        } elseif (! $refunded && $was) {
            // CHUYỂN refunded → pending: trả lại hiện trạng, không để lại phần đã giữ.
            $withheld = max(0, $this->depositPaidAmount() - (int) $this->deposit_refund_amount);

            if ($withheld > 0) {
                $left = max(0, $this->feePaidAmount() - $withheld);
                $this->forceFill([
                    'fee_paid_amount' => $left,
                    'fee_paid_at' => $left > 0 ? $this->fee_paid_at : null,
                    'fee_paid_by' => $left > 0 ? $this->fee_paid_by : null,
                ]);
            }

            $this->forceFill(['deposit_refund_amount' => null]);
        }
        // Không đổi trạng thái (vd admin chỉ sửa ghi chú): giữ nguyên mọi con số tiền.

        $this->forceFill([
            'deposit_refund_status' => $refunded ? 'refunded' : 'pending',
            'deposit_refund_note' => $note,
            'deposit_refunded_at' => $refunded ? ($this->deposit_refunded_at ?? now()) : null,
            'deposit_refunded_by' => $refunded ? ($this->deposit_refunded_by ?? $byUserId) : null,
        ]);

        $this->syncPaymentStatus();
        $this->save();
    }

    /** Người đánh dấu đã thu phụ phí — cho đối soát. */
    public function feePaidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fee_paid_by');
    }

    /**
     * "Ai đã làm gì" của đơn — dùng chung cho admin và app shipper (bopcamping-3wfk).
     * Mốc đã xảy ra nhưng không có dấu (đơn cũ trước khi có tính năng) → by = null,
     * UI hiển thị "không rõ ai" thay vì đoán bừa.
     *
     * @return list<array{key:string,label:string,done:bool,at:?string,by:?string}>
     */
    public function actionLog(): array
    {
        // Mốc suy từ trạng thái: đơn cũ đã giao/đã trả/đã hoàn cọc mà chưa có cột dấu.
        $impliedDone = [
            'rental_paid' => $this->rentalPaid(),
            'fee_paid' => $this->fee_due > 0 && $this->feePaid(),
            'deposit_paid' => $this->depositPaid(),
            'delivered' => in_array($this->status, ['renting', 'returned'], true),
            'collected' => $this->status === 'returned',
            'deposit_refunded' => $this->deposit_refund_status === 'refunded',
        ];

        $relation = [
            'rental_paid' => 'rentalPaidBy',
            'fee_paid' => 'feePaidBy',
            'deposit_paid' => 'depositPaidBy',
            'delivered' => 'deliveredBy',
            'collected' => 'collectedBy',
            'deposit_refunded' => 'depositRefundedBy',
        ];

        $log = [];
        foreach (self::TRACKED_ACTIONS as $key => $label) {
            // Đơn không có phụ phí thì không có việc gì để làm — treo mốc "chưa làm"
            // vĩnh viễn chỉ làm nhiễu bảng việc (bopcamping-urqo).
            if ($key === 'fee_paid' && $this->fee_due <= 0) {
                continue;
            }

            /** @var User|null $actor */
            $actor = $this->{$relation[$key]};

            $log[] = [
                'key' => $key,
                'label' => $label,
                'done' => (bool) $impliedDone[$key],
                'at' => $this->{"{$key}_at"}?->format('d/m H:i'),
                // Chỉ TÊN người làm — chủ shop 31/07: không cần ghi rõ vai.
                'by' => $actor?->name,
            ];
        }

        return $log;
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

    /**
     * Các trạng thái KHOÁ tồn kho. Chỉ khi admin ĐÃ XÁC NHẬN (confirmed) trở đi mới chiếm
     * kho + chừa ngày phơi (feedback 2026-07-27). Đơn 'pending' (chưa xác nhận) KHÔNG khoá
     * — tránh giữ chỗ cho đơn bỏ dở; đổi lại 2 khách có thể cùng đặt 1 món khi chưa xác nhận,
     * admin tự xử khi xác nhận.
     */
    public static function activeStatuses(): array
    {
        return ['confirmed', 'renting'];
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

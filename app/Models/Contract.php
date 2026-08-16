<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Hợp đồng thuê điện tử của một đơn (bopcamping-4jao) — số hoá hợp đồng giấy 1408/HĐTTB.
 *
 * Một đơn = một hợp đồng = một token = một link. Ba giai đoạn ký đều dùng CHUNG cái link
 * đó; trang tự hiện đúng giai đoạn đang cần ký. Không tồn tại hai phiên bản hợp đồng cho
 * một đơn — đó là bất biến quan trọng nhất của tính năng này.
 */
class Contract extends Model
{
    /**
     * Ba giai đoạn ký, ĐÚNG THỨ TỰ. Không đổi thứ tự — nextStage() duyệt theo mảng này để
     * quyết định lượt ký kế tiếp.
     */
    public const STAGES = ['main', 'handover', 'return'];

    public const STAGE_LABELS = [
        'main' => 'Hợp đồng thuê thiết bị',
        'handover' => 'Phụ lục A — Biên bản bàn giao',
        'return' => 'Phụ lục B — Biên bản nhận lại thiết bị',
    ];

    protected $fillable = [
        'order_id', 'code', 'token',
        'signer_id_number', 'signer_id_issued_on', 'signer_id_issued_place',
        'id_front_path', 'id_back_path',
        'pdf_path', 'first_viewed_at',
    ];

    protected $casts = [
        // Mã hoá ở tầng ứng dụng: lộ DB cũng không đọc được số CCCD.
        'signer_id_number' => 'encrypted',
        'signer_id_issued_on' => 'date',
        'first_viewed_at' => 'datetime',
    ];

    /**
     * Lớp chặn CUỐI cho dữ liệu định danh: kể cả ai đó lỡ trả nguyên model ra prop Inertia
     * thì số CCCD, đường dẫn ảnh và token vẫn không lọt ra. Controller vẫn phải chọn field
     * tường minh — đây chỉ là lưới an toàn, không phải giấy phép cẩu thả.
     */
    protected $hidden = ['signer_id_number', 'id_front_path', 'id_back_path', 'token'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(ContractSignature::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ContractItem::class)->orderBy('sort_order');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(HandoverPhoto::class);
    }

    public function signatureFor(string $stage): ?ContractSignature
    {
        return $this->signatures->firstWhere('stage', $stage);
    }

    public function isSigned(string $stage): bool
    {
        return $this->signatureFor($stage) !== null;
    }

    /** Giai đoạn kế tiếp cần ký — null khi đã ký đủ cả ba. */
    public function nextStage(): ?string
    {
        foreach (self::STAGES as $stage) {
            if (! $this->isSigned($stage)) {
                return $stage;
            }
        }

        return null;
    }

    /**
     * 4 số cuối SĐT — cửa mở link (xem ContractController).
     *
     * Bỏ hết ký tự không phải số trước khi cắt: khách hay lưu SĐT dạng "091 234 5678" hoặc
     * "+84...", cắt thẳng chuỗi thô sẽ ra 4 ký tự sai và khoá luôn khách thật ra ngoài.
     */
    public function phoneLast4(): string
    {
        return substr(preg_replace('/\D/', '', (string) $this->order->customer_phone), -4);
    }
}

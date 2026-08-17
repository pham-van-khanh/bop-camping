<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Món đồ trong hợp đồng — đóng băng tên/phụ kiện/giá đền bù tại thời điểm lập hợp đồng.
 *
 * Hai bộ hằng tình trạng lấy đúng từ checkbox của Phụ lục A và B trên hợp đồng giấy. Đừng
 * gộp làm một: lúc GIAO hỏi "đồ mới hay có vết cũ", lúc TRẢ hỏi "có giống lúc giao không" —
 * hai câu hỏi khác nhau, gộp lại là mất nghĩa của biên bản.
 */
class ContractItem extends Model
{
    public const HANDOVER_CONDITIONS = ['new', 'good', 'used_marks'];

    public const RETURN_CONDITIONS = ['same', 'wear', 'damaged'];

    public const HANDOVER_LABELS = [
        'new' => 'Mới',
        'good' => 'Tốt',
        'used_marks' => 'Có vết cũ',
    ];

    public const RETURN_LABELS = [
        'same' => 'Như lúc giao',
        'wear' => 'Hao mòn thường',
        'damaged' => 'Hư hỏng',
    ];

    protected $fillable = [
        'contract_id', 'product_id', 'combo_name', 'name', 'parts_list', 'quantity',
        'replacement_value', 'handover_condition', 'handover_note',
        'return_condition', 'return_note', 'sort_order',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'replacement_value' => 'integer',
        'sort_order' => 'integer',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}

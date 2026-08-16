<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một lần ký của một giai đoạn — kèm nội dung đã đóng băng và dấu vết (IP, thiết bị, giờ).
 *
 * content_html ở đây là BẤT BIẾN: admin sửa mẫu hợp đồng về sau không đụng được vào bản đã
 * ký. Đó là điều làm cho hash có ý nghĩa.
 */
class ContractSignature extends Model
{
    protected $fillable = [
        'contract_id', 'stage', 'content_html', 'content_hash',
        'signature_path', 'signed_at', 'signed_ip', 'signed_user_agent',
    ];

    protected $casts = ['signed_at' => 'datetime'];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}

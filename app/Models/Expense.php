<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Chi phí phát sinh của shop (bopcamping-h1s) — admin nhập tay để dựng bảng thu-chi.
 */
class Expense extends Model
{
    protected $fillable = ['spent_on', 'amount', 'category', 'note'];

    protected $casts = [
        'spent_on' => 'date',
        'amount' => 'integer',
    ];

    /**
     * Loại chi phí hợp lệ + nhãn hiển thị (single source cho validate + FE).
     *
     * Thêm loại mới thì nối vào CUỐI và khai nhãn tương ứng — bản ghi cũ lưu chuỗi
     * category nên đổi tên khoá là mất dữ liệu đã nhập.
     */
    public const CATEGORIES = ['equipment', 'repair', 'shipping', 'marketing', 'operation', 'contingency', 'profit_share', 'other'];

    public const CATEGORY_LABELS = [
        'equipment' => 'Mua thiết bị',
        'repair' => 'Sửa chữa',
        'shipping' => 'Vận chuyển',
        'marketing' => 'Marketing',
        'operation' => 'Vận hành',
        // "CHI dự phòng" chứ không phải "Dự phòng": màn Tài chính còn có QUỸ dự phòng
        // (55% lợi nhuận giữ lại). Một bên là tiền đã tiêu, một bên là tiền giữ lại —
        // để trùng tên trên cùng màn là mời người đọc hiểu ngược.
        'contingency' => 'Chi dự phòng',
        // Tiền thực trả cho thành viên góp vốn sau mỗi lần chia (bopcamping-qipx).
        // Ghi thành khoản chi nên nó trừ vào lãi của quý ghi nhận, như mọi khoản chi khác.
        'profit_share' => 'Chia lợi nhuận',
        'other' => 'Khác',
    ];

    /** Màu của từng loại — dùng chung cho chart phân bổ và nhãn trong bảng. */
    public const CATEGORY_COLORS = [
        'equipment' => '#557A2B',
        'repair' => '#B4762A',
        'shipping' => '#4A7C9B',
        'marketing' => '#9B5A8C',
        'operation' => '#6B8E5A',
        'contingency' => '#C9A227',
        'profit_share' => '#7A5FA3',
        'other' => '#8A8A7B',
    ];

    /**
     * Danh sách loại chi đã kèm nhãn + màu, để controller đẩy thẳng sang FE.
     *
     * @return list<array{value: string, label: string, color: string}>
     */
    public static function categoryOptions(): array
    {
        return collect(self::CATEGORIES)->map(fn (string $c) => [
            'value' => $c,
            'label' => self::CATEGORY_LABELS[$c],
            'color' => self::CATEGORY_COLORS[$c] ?? '#8A8A7B',
        ])->values()->all();
    }
}

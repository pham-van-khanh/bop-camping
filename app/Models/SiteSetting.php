<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Thông tin liên hệ/mạng xã hội của shop (singleton — 1 dòng duy nhất).
 * Admin chỉnh ở trang "Cài đặt shop". Footer + dải Zalo đọc qua shared prop 'site'.
 * Địa chỉ KHÔNG lưu ở đây — lấy từ ServiceLocation::open() (single source).
 */
class SiteSetting extends Model
{
    protected $guarded = [];

    /** Giờ giao/trả mặc định (adr_turnaround_buffer) — đảm bảo bản ghi mới có sẵn 8/20. */
    protected $attributes = [
        'pickup_hour' => 8,
        'return_hour' => 20,
        'morning_end_hour' => 12,
        'afternoon_start_hour' => 13,
    ];

    protected $casts = [
        'pickup_hour' => 'integer',
        'return_hour' => 'integer',
        'morning_end_hour' => 'integer',
        'afternoon_start_hour' => 'integer',
    ];

    /** Lấy bản ghi cấu hình duy nhất (tạo mặc định nếu chưa có). */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    /**
     * Zalo OA chính thức của shop (bopcamping-yki5) — SINGLE SOURCE, đừng chép link
     * này ra chỗ khác. Shop đã chốt dùng một OA cố định thay cho Zalo cá nhân.
     */
    public const ZALO_OA_URL = 'https://zalo.me/791036380751013489';

    /**
     * URL Zalo cho tài khoản thứ $n (1|2).
     *
     * Thứ tự: url override của admin → OA chính thức → null.
     *
     * SĐT KHÔNG còn được ghép thành zalo.me/<sđt> nữa (bopcamping-yki5): mọi nút
     * "nhắn Zalo" đều phải về cùng một OA, không rẽ vào trang cá nhân theo số. SĐT
     * vẫn giữ nguyên trong cài đặt và vẫn hiện ở footer/tooltip để khách GỌI —
     * nó chỉ thôi đóng vai trò nguồn suy ra đường dẫn.
     *
     * Vẫn cần có url hoặc sđt thì mới coi là "đã cấu hình": tài khoản #2 bỏ trống
     * phải trả null, nếu không nút Zalo nổi sẽ tưởng có 2 tài khoản và bung panel
     * cho khách chọn giữa hai mục trỏ cùng một chỗ.
     */
    public function zaloUrl(int $n): ?string
    {
        $url = $this->{"zalo{$n}_url"};
        if ($url) {
            return $url;
        }

        return $this->{"zalo{$n}_phone"} ? self::ZALO_OA_URL : null;
    }
}

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
     * URL Zalo THEO SỐ cho tài khoản thứ $n (1|2): url override → zalo.me/<sđt> → null.
     *
     * Cố ý KHÁC với OA ở trên (bopcamping-h0hh). Hai đường liên hệ phục vụ hai nhu
     * cầu khác nhau, đừng gộp lại:
     *   - OA (nút nổi): kênh chính thức, tiện, hiện to.
     *   - Theo số (footer): khách chưa tin OA thì vẫn nhắn thẳng được số nhân viên.
     * Từng gộp hết về OA ở bopcamping-yki5 và hậu quả là mất hẳn đường thứ hai.
     */
    public function zaloUrl(int $n): ?string
    {
        $url = $this->{"zalo{$n}_url"};
        if ($url) {
            return $url;
        }

        $phone = $this->{"zalo{$n}_phone"};

        return $phone ? 'https://zalo.me/'.$phone : null;
    }
}

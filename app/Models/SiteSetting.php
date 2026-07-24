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
    ];

    protected $casts = [
        'pickup_hour' => 'integer',
        'return_hour' => 'integer',
    ];

    /** Lấy bản ghi cấu hình duy nhất (tạo mặc định nếu chưa có). */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    /**
     * URL Zalo cho tài khoản thứ $n (1|2): ưu tiên url override, nếu trống mà có
     * số điện thoại thì fallback zalo.me/<sđt> (mở đúng trang cá nhân của số đã
     * đăng ký Zalo). Null nếu không có cả hai.
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

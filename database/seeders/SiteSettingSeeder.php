<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

/**
 * Giá trị liên hệ mặc định (ADR home_faq_contact B4): 2 hotline, 2 tài khoản Zalo
 * (url trống → tự dùng zalo.me/<sđt>), FB/TikTok để trống chờ chủ shop điền.
 * Idempotent: cập nhật dòng singleton hiện có.
 */
class SiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        SiteSetting::current()->update([
            'hotline_primary' => '0976544370',
            'hotline_secondary' => '0373655008',
            'zalo1_label' => 'Tư vấn & đặt đồ',
            'zalo1_phone' => '0976544370',
            'zalo1_url' => null,
            'zalo2_label' => 'Hỗ trợ thêm',
            'zalo2_phone' => '0373655008',
            'zalo2_url' => null,
            'facebook_url' => null,
            'tiktok_url' => null,
            'working_hours' => '8:00 – 21:00 hằng ngày',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceLocation;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cài đặt shop — thông tin liên hệ/mạng xã hội (singleton, update-only,
 * mirror PromotionController). ADR home_faq_contact B5.
 */
class SiteSettingController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Admin/SiteSettings', [
            'settings' => SiteSetting::current(),
            // Địa chỉ hiển thị ở footer lấy từ Điểm cắm trại — cho admin xem, không sửa ở đây
            'locations' => ServiceLocation::open()->ordered()->get(['name', 'area']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'hotline_primary' => 'nullable|string|max:20',
            'hotline_secondary' => 'nullable|string|max:20',
            'zalo1_label' => 'nullable|string|max:60',
            'zalo1_phone' => 'nullable|string|max:20',
            'zalo1_url' => 'nullable|url|max:255',
            'zalo2_label' => 'nullable|string|max:60',
            'zalo2_phone' => 'nullable|string|max:20',
            'zalo2_url' => 'nullable|url|max:255',
            'facebook_url' => 'nullable|url|max:255',
            'tiktok_url' => 'nullable|url|max:255',
            'working_hours' => 'nullable|string|max:100',
            // Khung giờ giao/trả mặc định (adr_turnaround_buffer) — chỉ hiển thị kỳ vọng.
            // 'sometimes' để form cũ/không gửi vẫn giữ giá trị mặc định (tương thích ngược).
            'pickup_hour' => 'sometimes|integer|min:0|max:23',
            'return_hour' => 'sometimes|integer|min:0|max:23',
            // SEO: GA4 dạng "G-XXXXXXXX"; mã xác minh Search Console là chuỗi token.
            'ga_measurement_id' => 'nullable|string|max:40|regex:/^G-[A-Z0-9]+$/i',
            'google_site_verification' => 'nullable|string|max:120',
        ], [
            'zalo1_url.url' => 'Link Zalo phải là URL hợp lệ.',
            'zalo2_url.url' => 'Link Zalo phải là URL hợp lệ.',
            'facebook_url.url' => 'Link Facebook phải là URL hợp lệ.',
            'tiktok_url.url' => 'Link TikTok phải là URL hợp lệ.',
            'ga_measurement_id.regex' => 'Mã GA4 có dạng G-XXXXXXXX.',
        ]);

        SiteSetting::current()->update($data);

        return back()->with('success', 'Đã lưu thông tin liên hệ.');
    }
}

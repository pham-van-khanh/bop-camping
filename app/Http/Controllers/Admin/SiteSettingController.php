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
        ], [
            'zalo1_url.url' => 'Link Zalo phải là URL hợp lệ.',
            'zalo2_url.url' => 'Link Zalo phải là URL hợp lệ.',
            'facebook_url.url' => 'Link Facebook phải là URL hợp lệ.',
            'tiktok_url.url' => 'Link TikTok phải là URL hợp lệ.',
        ]);

        SiteSetting::current()->update($data);

        return back()->with('success', 'Đã lưu thông tin liên hệ.');
    }
}

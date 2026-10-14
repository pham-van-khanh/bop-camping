<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Banner đồng ý cookie (bopcamping-vqwr) — chế độ CHẶN HẲN.
 *
 * Trước đây blade chèn thẳng GA4/GTM/Meta Pixel/TikTok Pixel nên cookie được đặt ngay khi
 * trang mở, trước khi khách kịp đồng ý. Giờ server KHÔNG bao giờ in script đo lường ra
 * HTML; nó chỉ chia sẻ mã qua prop `analytics`, và front-end (lib/consent.ts) chỉ nạp
 * script sau khi khách bấm "Đồng ý".
 */
class CookieConsentTest extends TestCase
{
    use RefreshDatabase;

    private function enableEverything(): void
    {
        SiteSetting::current()->update([
            'ga_measurement_id' => 'G-ABC12345',
            'meta_pixel_id' => self::META,
            'tiktok_pixel_id' => self::TIKTOK,
        ]);
        config(['services.gtm.id' => 'GTM-TEST123']);
    }

    private const META = '123456789012345';

    private const TIKTOK = 'C4ABC123DEF456GHI789';

    /** @test */
    public function tracking_scripts_are_never_rendered_server_side(): void
    {
        $this->enableEverything();

        $html = $this->get('/')->getContent();

        $this->assertStringNotContainsString('googletagmanager.com/gtag/js', $html);
        $this->assertStringNotContainsString('googletagmanager.com/gtm.js', $html);
        $this->assertStringNotContainsString("gtag('config'", $html);
        $this->assertStringNotContainsString('fbevents.js', $html);
        $this->assertStringNotContainsString('analytics.tiktok.com', $html);
        // <noscript> (iframe GTM, ảnh Meta) đặt cookie mà không có cách nào xin phép → bỏ hẳn.
        $this->assertStringNotContainsString('googletagmanager.com/ns.html', $html);
        $this->assertStringNotContainsString('facebook.com/tr?id=', $html);
    }

    /** @test */
    public function all_tracking_ids_are_shared_for_the_consent_banner(): void
    {
        $this->enableEverything();

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('analytics.ga_id', 'G-ABC12345')
            ->where('analytics.gtm_id', 'GTM-TEST123')
            ->where('analytics.meta_pixel_id', self::META)
            ->where('analytics.tiktok_pixel_id', self::TIKTOK)
        );
    }

    /** Trang nội bộ không bao giờ nạp đo lường (bopcamping-wi6h) — prop null ở đó. @test */
    public function internal_pages_get_no_tracking_ids(): void
    {
        $this->enableEverything();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('analytics', null));
        $this->get(route('shipper.login'))
            ->assertInertia(fn (Assert $page) => $page->where('analytics', null));
    }

    /** Mã ghi thẳng DB (bỏ qua validate) sai định dạng → không đưa xuống trình duyệt. @test */
    public function malformed_ids_are_dropped_before_reaching_the_browser(): void
    {
        SiteSetting::current()->forceFill([
            'ga_measurement_id' => "G-X');alert(1)//",
            'meta_pixel_id' => "1');alert(1)//",
            'tiktok_pixel_id' => "C4ABC123\n",
        ])->save();
        config(['services.gtm.id' => null]);

        $res = $this->get('/');

        $this->assertStringNotContainsString('alert(1)', $res->getContent());
        $res->assertInertia(fn (Assert $page) => $page->where('analytics', null));
    }

    /** @test */
    public function only_the_configured_tool_is_shared(): void
    {
        SiteSetting::current()->update(['ga_measurement_id' => 'G-ABC12345']);
        config(['services.gtm.id' => null]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('analytics.ga_id', 'G-ABC12345')
            ->where('analytics.gtm_id', null)
            ->where('analytics.meta_pixel_id', null)
            ->where('analytics.tiktok_pixel_id', null)
        );
    }

    /** Không cấu hình gì = không có cookie nào cần hỏi → không hiện banner. @test */
    public function analytics_is_null_when_nothing_is_configured(): void
    {
        config(['services.gtm.id' => null]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('analytics', null)
        );
    }
}

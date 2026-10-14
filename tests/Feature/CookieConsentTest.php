<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Banner đồng ý cookie (bopcamping-vqwr) — chế độ CHẶN HẲN.
 *
 * Trước đây blade chèn thẳng thẻ gtag/gtm nên cookie _ga được đặt ngay khi trang mở,
 * trước khi khách kịp đồng ý. Giờ server KHÔNG bao giờ in script phân tích ra HTML;
 * nó chỉ chia sẻ mã GA4/GTM qua prop `analytics`, và front-end (lib/consent.ts) chỉ
 * nạp script sau khi khách bấm "Đồng ý".
 */
class CookieConsentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function analytics_scripts_are_never_rendered_server_side(): void
    {
        SiteSetting::current()->update(['ga_measurement_id' => 'G-ABC12345']);
        config(['services.gtm.id' => 'GTM-TEST123']);

        $html = $this->get('/')->getContent();

        $this->assertStringNotContainsString('googletagmanager.com/gtag/js', $html);
        $this->assertStringNotContainsString('googletagmanager.com/gtm.js', $html);
        // <noscript> iframe của GTM đặt cookie mà không có cách nào xin phép → bỏ hẳn.
        $this->assertStringNotContainsString('googletagmanager.com/ns.html', $html);
        $this->assertStringNotContainsString("gtag('config'", $html);
    }

    /** @test */
    public function analytics_ids_are_shared_for_the_consent_banner(): void
    {
        SiteSetting::current()->update(['ga_measurement_id' => 'G-ABC12345']);
        config(['services.gtm.id' => 'GTM-TEST123']);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('analytics.ga_id', 'G-ABC12345')
            ->where('analytics.gtm_id', 'GTM-TEST123')
        );
    }

    /** @test */
    public function only_the_configured_tool_is_shared(): void
    {
        SiteSetting::current()->update(['ga_measurement_id' => 'G-ABC12345']);
        config(['services.gtm.id' => null]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('analytics.ga_id', 'G-ABC12345')
            ->where('analytics.gtm_id', null)
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

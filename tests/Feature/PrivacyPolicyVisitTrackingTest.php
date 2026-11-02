<?php

namespace Tests\Feature;

use App\Models\StaticPage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Migration thêm gạch "cookie bop_vid" vào mục 5 Chính sách bảo mật. */
class PrivacyPolicyVisitTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_02_000002_add_visit_tracking_to_privacy_policy.php');
    }

    private function page(string $content): StaticPage
    {
        return StaticPage::create(['slug' => 'chinh-sach-bao-mat', 'title' => 'CS', 'content' => $content]);
    }

    /** @test */
    public function default_content_mentions_bop_vid_and_telegram(): void
    {
        $content = StaticPage::policyDefaults('chinh-sach-bao-mat')['content'];

        $this->assertStringContainsString('bop_vid', $content);
        $this->assertStringContainsString('Telegram', $content);
    }

    /** @test */
    public function up_inserts_once_after_the_attribution_item_and_down_removes_it(): void
    {
        $default = StaticPage::policyDefaults('chinh-sach-bao-mat')['content'];
        $without = (string) preg_replace('#<li><strong>Thống kê truy cập của shop.*?</li>#u', '', $default);
        $page = $this->page($without);

        $this->migration()->up();
        $this->migration()->up();
        $this->assertSame(1, substr_count((string) $page->refresh()->content, 'bop_vid'));

        $this->migration()->down();
        $this->assertSame($without, (string) $page->refresh()->content);
    }

    /** @test */
    public function up_skips_page_without_cookie_section(): void
    {
        $page = $this->page('<p>Chủ shop tự viết.</p>');

        $this->migration()->up();

        $this->assertSame('<p>Chủ shop tự viết.</p>', (string) $page->refresh()->content);
    }
}

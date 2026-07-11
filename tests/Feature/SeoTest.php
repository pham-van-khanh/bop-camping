<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Models\SiteSetting;
use App\Models\StaticPage;
use App\Models\User;
use App\Services\SeoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Epic 5: SEO — GA4/verification từ admin, meta động, JSON-LD breadcrumb/localbusiness. */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(string $name, string $slug): Product
    {
        $category = Category::firstOrCreate(['slug' => 'leu'], ['name' => 'Lều']);

        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'price_per_day' => 50000,
            'quantity' => 5,
            'status' => 'active',
        ]);
    }

    /* ---- T1: GA4 + Google verification từ admin ---- */

    /** @test */
    public function ga4_and_verification_render_only_when_admin_sets_them(): void
    {
        // Chưa cấu hình → không có script/meta
        $html = $this->get('/')->getContent();
        $this->assertStringNotContainsString('gtag/js?id=', $html);
        $this->assertStringNotContainsString('google-site-verification', $html);

        SiteSetting::current()->update([
            'ga_measurement_id' => 'G-ABC12345',
            'google_site_verification' => 'verify-token-xyz',
        ]);

        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('https://www.googletagmanager.com/gtag/js?id=G-ABC12345', $html);
        $this->assertStringContainsString("gtag('config','G-ABC12345')", $html);
        $this->assertStringContainsString('<meta name="google-site-verification" content="verify-token-xyz">', $html);
    }

    /** @test */
    public function admin_can_save_ga_and_verification(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'ga_measurement_id' => 'G-XYZ99999',
                'google_site_verification' => 'tok123',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('G-XYZ99999', SiteSetting::current()->ga_measurement_id);
    }

    /** @test */
    public function invalid_ga_id_is_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), ['ga_measurement_id' => 'UA-123-456'])
            ->assertSessionHasErrors('ga_measurement_id');
    }

    /** LocalBusiness JSON-LD chỉ render khi có hotline. @test */
    public function local_business_jsonld_renders_when_hotline_set(): void
    {
        $this->assertStringNotContainsString('"LocalBusiness"', $this->get('/')->getContent());

        SiteSetting::current()->update(['hotline_primary' => '0976544370', 'working_hours' => '8:00 – 21:00']);

        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('"@type":"LocalBusiness"', $html);
        $this->assertStringContainsString('0976544370', $html);
    }

    /* ---- T2: meta động + breadcrumb ---- */

    /** @test */
    public function product_listing_sets_title_and_breadcrumb(): void
    {
        $this->makeProduct('Lều 2 người', 'leu-2-nguoi');

        $this->get('/thiet-bi?cat=leu')
            ->assertInertia(fn ($page) => $page
                ->where('seo.title', 'Thuê Lều tại BỐP CAMPING')
                ->where('seo.jsonld.@type', 'BreadcrumbList')
            );
    }

    /** @test */
    public function product_page_has_product_and_breadcrumb_jsonld(): void
    {
        $this->makeProduct('Lều 2 người', 'leu-2-nguoi');

        $this->get('/thiet-bi/leu-2-nguoi')
            ->assertInertia(fn ($page) => $page
                ->where('seo.jsonld.0.@type', 'Product')
                ->where('seo.jsonld.1.@type', 'BreadcrumbList')
            );
    }

    /** @test */
    public function about_page_sets_seo_title_from_static_page(): void
    {
        StaticPage::about();

        $this->get('/gioi-thieu')
            ->assertInertia(fn ($page) => $page
                ->has('seo.title')
                ->where('seo.jsonld.@type', 'BreadcrumbList')
            );
    }

    /* ---- SeoService unit ---- */

    /** @test */
    public function seo_service_limits_description_and_absolutizes_image(): void
    {
        $seo = app(SeoService::class)->page(
            'Tiêu đề',
            '<p>'.str_repeat('rất dài ', 60).'</p>',
            '/images/x.jpg',
        );

        // Str::limit(160) cắt tại 160 rồi nối '...' → tối đa ~163 ký tự.
        $this->assertLessThanOrEqual(163, mb_strlen($seo['description']));
        $this->assertStringNotContainsString('<p>', $seo['description']);
        $this->assertStringStartsWith('http', $seo['image']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        // areaServed của LocalBusiness đọc ServiceLocation — tạo 1 vị trí mở cho ổn định.
        ServiceLocation::firstOrCreate(['name' => 'Vinh'], ['area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1]);
    }
}

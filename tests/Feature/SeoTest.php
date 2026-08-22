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

    /**
     * Chưa cơ sở nào điền địa chỉ -> vẫn 1 khối LocalBusiness chung (hành vi cũ), không
     * khai `address` bịa ra.
     *
     * @test
     */
    public function local_business_stays_generic_without_any_address_filled(): void
    {
        SiteSetting::current()->update(['hotline_primary' => '0976544370']);

        $html = $this->get('/')->getContent();

        $this->assertSame(1, substr_count($html, '"@type":"LocalBusiness"'));
        $this->assertStringContainsString('"name":"BỐP CAMPING"', $html);
        $this->assertStringNotContainsString('"address"', $html);
    }

    /**
     * Cơ sở nào ĐÃ điền địa chỉ (Cài đặt > Điểm cắm trại) -> LocalBusiness riêng cho cơ sở
     * đó với PostalAddress cụ thể — cần cho rich result "gần bạn"/Google Maps. Cơ sở còn
     * lại (Hà Nội, tạo sẵn ở setUp) chưa có địa chỉ nên KHÔNG được khai bịa.
     *
     * @test
     */
    public function local_business_becomes_per_branch_once_address_is_filled(): void
    {
        SiteSetting::current()->update(['hotline_primary' => '0976544370']);
        ServiceLocation::where('name', 'Vinh')->update(['address' => '12 Lê Duẩn']);
        ServiceLocation::firstOrCreate(['name' => 'Hà Nội'], ['area' => 'Nội thành', 'status' => 'open', 'sort_order' => 2]);

        $html = $this->get('/')->getContent();

        $this->assertSame(1, substr_count($html, '"@type":"LocalBusiness"'));
        $this->assertStringContainsString('"name":"BỐP CAMPING - Vinh"', $html);
        $this->assertStringContainsString('"streetAddress":"12 Lê Duẩn"', $html);
        $this->assertStringContainsString('"addressRegion":"Nghệ An"', $html);
        $this->assertStringContainsString('"addressCountry":"VN"', $html);
        // Hà Nội chưa có địa chỉ -> không được lên thành LocalBusiness riêng.
        $this->assertStringNotContainsString('"name":"BỐP CAMPING - Hà Nội"', $html);
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

    /**
     * Brand chỉ được xuất hiện MỘT lần trong title (bopcamping-12n9).
     *
     * Tiêu đề trang tĩnh do admin nhập và người nhập hay tự gõ luôn brand; nối thêm
     * lần nữa là thành "… — BỐP CAMPING | BỐP CAMPING", ăn chỗ trong ~60 ký tự SERP.
     *
     * @test
     */
    public function with_brand_does_not_repeat_the_brand_already_in_the_title(): void
    {
        $seo = app(SeoService::class);

        // Chưa có brand -> nối vào.
        $this->assertSame('Chính sách bảo mật | BỐP CAMPING', $seo->withBrand('Chính sách bảo mật'));

        // Đã có brand (đúng dạng) -> giữ nguyên.
        $this->assertSame('Chính sách bảo mật — BỐP CAMPING', $seo->withBrand('Chính sách bảo mật — BỐP CAMPING'));

        // Khác hoa/thường và dấu vẫn tính là đã có.
        $this->assertSame('Về Bốp Camping', $seo->withBrand('Về Bốp Camping'));

        // Rỗng -> chỉ còn brand, không ra ' | BỐP CAMPING' cụt đầu.
        $this->assertSame('BỐP CAMPING', $seo->withBrand(''));
        $this->assertSame('BỐP CAMPING', $seo->withBrand(null));
    }
}

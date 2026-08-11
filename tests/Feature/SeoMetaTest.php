<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Faq;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-bg1 — thẻ SEO ở <head> (canonical, favicon, OG/Twitter, JSON-LD).
 */
class SeoMetaTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function home_has_core_seo_tags(): void
    {
        $res = $this->get('/');

        $res->assertSee('rel="canonical"', false);
        $res->assertSee('/favicon-32.png', false);
        $res->assertSee('property="og:site_name"', false);
        $res->assertSee('name="twitter:card"', false);
        $res->assertSee('application/ld+json', false);
        $res->assertSee('name="description"', false);
        $res->assertSee('og:image', false);
        $res->assertSee('name="thumbnail"', false);
    }

    /** @test */
    public function home_has_website_structured_data(): void
    {
        $res = $this->get('/');

        // WebSite + ô tìm kiếm
        $res->assertSee('"WebSite"', false);
        $res->assertSee('SearchAction', false);
        $res->assertSee('search_term_string', false);
    }

    /**
     * FAQPage phải sinh từ ĐÚNG câu đang hiện trên trang (bopcamping-s5ct).
     *
     * Trước đây khối này hardcode 4 câu trong app.blade.php còn trang chủ render 8 câu
     * lấy từ bảng `faqs` — khớp 0/4. Khoá lại bằng cách đổi dữ liệu DB rồi soi markup:
     * hardcode thì kiểu gì cũng trượt.
     *
     * @test
     */
    public function faqpage_is_built_from_the_faqs_actually_shown_on_the_page(): void
    {
        Faq::create([
            'question' => 'Câu hỏi CHỈ CÓ trong DB?',
            'answer' => 'Trả lời chỉ có trong DB.',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        // Câu đang tắt thì không được lọt vào markup — nó không hiện trên trang.
        Faq::create([
            'question' => 'Câu hỏi ĐANG TẮT?',
            'answer' => 'Không được khai.',
            'sort_order' => 2,
            'is_active' => false,
        ]);

        $res = $this->get('/');

        $res->assertSee('"FAQPage"', false);
        $res->assertSee('Câu hỏi CHỈ CÓ trong DB?', false);
        $res->assertDontSee('Câu hỏi ĐANG TẮT?', false);
    }

    /**
     * Không có FAQ thì đừng khai FAQPage rỗng.
     *
     * @test
     */
    public function faqpage_is_omitted_when_there_is_no_faq(): void
    {
        $this->get('/')->assertDontSee('"FAQPage"', false);
    }

    /**
     * FAQPage KHÔNG được xuất ở trang không có FAQ (bopcamping-s5ct).
     *
     * Đây là nửa còn lại của lỗi: khối cũ nằm ở layout chung nên trang sản phẩm và
     * trang chính sách cũng khai FAQ dù không hiện câu nào. Chỉ kiểm chiều "có ở trang
     * chủ" sẽ không bắt được.
     *
     * @test
     */
    public function faqpage_does_not_leak_onto_pages_without_faq(): void
    {
        Faq::create([
            'question' => 'Có cần đặt cọc không?',
            'answer' => 'Có.',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $cat = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $cat->id, 'status' => 'active']);

        $this->get('/')->assertSee('"FAQPage"', false);
        $this->get('/thiet-bi/'.$product->slug)->assertDontSee('"FAQPage"', false);
        $this->get('/thiet-bi')->assertDontSee('"FAQPage"', false);
    }

    /**
     * /combos phải có SEO RIÊNG (bopcamping-u3u3).
     *
     * Trước đây trang này không khai `seo` nên rơi vào mặc định site-wide — title và
     * description trùng hệt trang chủ, với Google là hai trang nội dung giống nhau.
     *
     * @test
     */
    public function combo_listing_has_its_own_seo_not_the_site_wide_default(): void
    {
        $res = $this->get('/combos');

        $res->assertInertia(fn ($p) => $p
            ->where('seo.title', 'Combo thuê đồ cắm trại trọn bộ — tiết kiệm hơn thuê lẻ')
            ->has('seo.description'));
        // Không được mang title mặc định của trang chủ.
        $res->assertDontSee('<title inertia>BỐP CAMPING — Cho thuê thiết bị cắm trại</title>', false);
        $res->assertSee('"BreadcrumbList"', false);
    }

    /**
     * Chi tiết combo phải có Product + Breadcrumb như chi tiết sản phẩm (bopcamping-u3u3).
     *
     * @test
     */
    public function combo_detail_has_product_and_breadcrumb_jsonld(): void
    {
        $cat = Category::create(['name' => 'Lều', 'slug' => 'leu-combo-seo']);
        $product = Product::create([
            'category_id' => $cat->id, 'name' => 'Lều đôi', 'slug' => 'leu-doi-combo-seo',
            'price_per_day' => 100000, 'quantity' => 5, 'status' => 'active',
        ]);
        $combo = Combo::create([
            'name' => 'Combo Cắm Trại 2 Người', 'slug' => 'combo-2-nguoi-seo',
            'combo_price' => 250000, 'deposit' => 500000, 'is_active' => true,
        ]);
        $combo->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        $res = $this->get('/combos/'.$combo->slug);

        $res->assertSee('"Product"', false);
        $res->assertSee('"price":250000', false);
        $res->assertSee('"BreadcrumbList"', false);
        $res->assertSee('Combo Cắm Trại 2 Người', false);
    }

    /** @test */
    public function gtm_not_rendered_without_id(): void
    {
        config(['services.gtm.id' => null]);
        $this->get('/')->assertDontSee('googletagmanager.com/gtm.js', false);
    }

    /** @test */
    public function product_page_has_product_specific_og(): void
    {
        $cat = Category::create(['name' => 'Lều', 'slug' => 'leu']);
        $product = Product::create([
            'category_id' => $cat->id, 'name' => 'Lều Naturehike Cloud-Up 2', 'slug' => 'leu-cloud-up-2',
            'description' => 'Lều 2 người siêu nhẹ, chống nước tốt.',
            'price_per_day' => 120000, 'quantity' => 3, 'status' => 'active',
        ]);

        $res = $this->get(route('products.show', $product));

        // og:title chứa tên sản phẩm; mô tả lấy từ description sản phẩm.
        $res->assertSee('Lều Naturehike Cloud-Up 2', false);
        $res->assertSee('Lều 2 người siêu nhẹ, chống nước tốt.', false);
        $res->assertSee('property="og:title"', false);

        // Product schema (giá + tồn kho) cho Google rich result.
        $res->assertSee('"Product"', false);
        $res->assertSee('"price":120000', false);
        $res->assertSee('schema.org/InStock', false);
    }
}

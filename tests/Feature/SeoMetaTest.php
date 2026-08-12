<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Faq;
use App\Models\Product;
use App\Models\ServiceLocation;
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

    /**
     * Combo SEO "mạnh" (bopcamping-gyg8): /combos có ItemList; chi tiết combo dùng
     * hasPart cho món bên trong và KHÔNG bịa aggregateRating.
     *
     * @test
     */
    public function combo_pages_carry_rich_structured_data(): void
    {
        $cat = Category::create(['name' => 'Lều', 'slug' => 'leu-rich-seo']);
        $product = Product::create([
            'category_id' => $cat->id, 'name' => 'Lều đôi Rich', 'slug' => 'leu-doi-rich-seo',
            'price_per_day' => 100000, 'quantity' => 5, 'status' => 'active',
        ]);
        $combo = Combo::create([
            'name' => 'Combo Rich SEO', 'slug' => 'combo-rich-seo',
            'combo_price' => 250000, 'deposit' => 500000,
            'suitable_for' => 2, 'is_active' => true,
        ]);
        $combo->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        // Trang danh sách: ItemList nêu tên + giá combo.
        $list = $this->get('/combos');
        $list->assertSee('"ItemList"', false);
        $list->assertSee('Combo Rich SEO', false);

        $detail = $this->get('/combos/'.$combo->slug);
        // hasPart = combo GỒM món này. isSimilarTo là sai nghĩa ("sản phẩm tương tự"),
        // khoá lại để không ai đổi ngược.
        $detail->assertSee('"hasPart"', false);
        $detail->assertDontSee('"isSimilarTo"', false);
        $detail->assertSee('Lều đôi Rich', false);
        $detail->assertSee('"sku"', false);
        $detail->assertSee('PeopleAudience', false);
        // Review chỉ gắn vào product — combo không có, đừng bịa điểm.
        $detail->assertDontSee('aggregateRating', false);
    }

    /**
     * Description lấy danh mục từ DB, KHÔNG gõ tay và KHÔNG nhắc địa điểm (bopcamping-gyg8).
     *
     * Trước đây câu này ghi cứng "lều, bếp, túi ngủ, đèn trại... tại Vinh & Hà Nội".
     * Đã đo được lỗi: mở thêm cơ sở Đà Nẵng thì DB có 3 khu vực nhưng description vẫn
     * ghi "Vinh & Hà Nội". Địa điểm là dữ liệu admin quản lý nên bỏ hẳn khỏi câu.
     *
     * @test
     */
    public function listing_descriptions_come_from_real_categories_and_never_name_locations(): void
    {
        Category::create(['name' => 'Xuồng hơi', 'slug' => 'xuong-hoi-seo']);
        ServiceLocation::create([
            'name' => 'Đà Nẵng', 'area' => 'Hải Châu', 'status' => 'open', 'sort_order' => 9,
        ]);

        foreach (['/', '/thiet-bi', '/combos'] as $path) {
            $res = $this->get($path);

            // Danh mục thật phải có mặt.
            $res->assertSee('xuồng hơi', false);
            // Không được nhắc tên cơ sở — đó là dữ liệu thay đổi được.
            $res->assertDontSee('Vinh &amp; Hà Nội', false);
            $res->assertDontSee('Hải Châu', false);
        }
    }

    /**
     * categoryPhrase phải đủ ngắn để đuôi description không bị Str::limit cắt mất.
     *
     * @test
     */
    public function listing_description_is_not_truncated_mid_sentence(): void
    {
        foreach (['Lều A', 'Bếp B', 'Túi C', 'Đèn D', 'Bàn E', 'Ghế F', 'Ba lô G'] as $i => $n) {
            Category::create(['name' => $n, 'slug' => 'cat-trunc-'.$i]);
        }

        foreach (['/thiet-bi', '/combos'] as $path) {
            $res = $this->get($path);
            // Đuôi mới là thứ đáng đọc — còn nguyên thì tức là chưa bị cắt.
            $res->assertSee('trả tiền khi nhận (COD).', false);
            // Nối "..." với "." thành "...." là lỗi trình bày.
            $res->assertDontSee('....', false);
        }
    }

    /**
     * MỌI khối JSON-LD phải parse được và có @context hợp lệ (bopcamping-gyg8).
     *
     * Lỗi thật đã xảy ra trên production: Laravel 11+ có directive `@context`, nên mảng
     * viết thẳng trong .blade.php bị compiler biến '@context' thành mã PHP — key JSON-LD
     * ra thành "<?php $__contextArgs = []; ...". Organization, WebSite và FAQPage đều
     * hỏng suốt một thời gian dài mà không ai biết, vì audit chỉ soi @type.
     *
     * Test này quét TẤT CẢ khối trên nhiều loại trang nên khối mới thêm cũng được bảo vệ.
     *
     * @test
     */
    public function every_json_ld_block_parses_and_declares_a_valid_context(): void
    {
        $cat = Category::create(['name' => 'Lều', 'slug' => 'leu-ctx']);
        $product = Product::create([
            'category_id' => $cat->id, 'name' => 'Lều ctx', 'slug' => 'leu-ctx-sp',
            'price_per_day' => 100000, 'quantity' => 2, 'status' => 'active',
        ]);
        Faq::create(['question' => 'Hỏi?', 'answer' => 'Đáp.', 'sort_order' => 1, 'is_active' => true]);

        foreach (['/', '/thiet-bi', '/combos', '/thiet-bi/'.$product->slug, '/chinh-sach-bao-mat'] as $path) {
            $html = $this->get($path)->getContent();

            preg_match_all(
                '#<script type="application/ld\+json">(.*?)</script>#s',
                $html,
                $matches
            );
            $this->assertNotEmpty($matches[1], "$path không có khối JSON-LD nào");

            foreach ($matches[1] as $raw) {
                // Bắt đúng triệu chứng của lỗi: mã PHP rò vào markup.
                $this->assertStringNotContainsString('<?php', $raw, "$path: JSON-LD lẫn mã PHP");

                $decoded = json_decode($raw, true);
                $this->assertNotNull($decoded, "$path: JSON-LD không parse được");

                foreach (is_array($decoded) && array_is_list($decoded) ? $decoded : [$decoded] as $block) {
                    $this->assertSame(
                        'https://schema.org',
                        $block['@context'] ?? null,
                        "$path: khối ".($block['@type'] ?? '?').' thiếu @context hợp lệ'
                    );
                }
            }
        }
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

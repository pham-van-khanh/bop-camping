<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Product;
use App\Models\StaticPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Epic 3: sitemap.xml động từ DB. */
class SitemapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush(); // sitemap cache 1h — flush để mỗi test thấy dữ liệu của chính nó
    }

    private function makeProduct(string $name, string $slug, string $status = 'active'): Product
    {
        $category = Category::firstOrCreate(['slug' => 'leu'], ['name' => 'Lều']);

        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'price_per_day' => 50000,
            'quantity' => 5,
            'status' => $status,
        ]);
    }

    /** @test */
    public function sitemap_returns_xml_with_active_content_only(): void
    {
        $this->makeProduct('Lều 2 người', 'leu-2-nguoi');
        $this->makeProduct('Bàn ẩn', 'ban-an', 'hidden');
        Combo::create(['name' => 'Combo đôi', 'slug' => 'combo-doi', 'combo_price' => 200000, 'is_active' => true]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $this->assertStringContainsString('application/xml', $response->headers->get('Content-Type'));

        $xml = $response->getContent();
        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString(url('/thiet-bi/leu-2-nguoi'), $xml);
        $this->assertStringContainsString(url('/gioi-thieu'), $xml);
        $this->assertStringContainsString(url('/combos/combo-doi'), $xml);
        $this->assertStringContainsString('cat=leu', $xml);
        $this->assertStringNotContainsString('ban-an', $xml);

        // XML hợp lệ — parse được
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    /** @test */
    public function sitemap_is_cached(): void
    {
        $this->get('/sitemap.xml')->assertOk();
        $this->makeProduct('Lều mới', 'leu-moi-sau-cache');

        // Trong TTL cache: sản phẩm mới chưa xuất hiện
        $this->assertStringNotContainsString('leu-moi-sau-cache', $this->get('/sitemap.xml')->getContent());
    }

    /**
     * Trang chính sách phải có trong sitemap (bopcamping-12n9).
     *
     * Chúng trả 200 và có canonical nên rõ ràng là muốn index, nhưng trước đây
     * SitemapController liệt kê thủ công và bỏ sót cả 5 trang.
     *
     * @test
     */
    public function sitemap_includes_every_policy_page(): void
    {
        $xml = $this->get('/sitemap.xml')->getContent();

        foreach (array_keys(StaticPage::POLICIES) as $slug) {
            $this->assertStringContainsString(url('/'.$slug), $xml, "sitemap thiếu /$slug");
        }
    }

    /**
     * MỌI url phải có lastmod (bopcamping-10x2). Google chỉ dùng trường này khi nó nhất
     * quán với nội dung thật; trước đây 16/35 URL bỏ trống nên Google không có mốc nào
     * để quyết định crawl lại các trang danh sách và trang chính sách.
     *
     * @test
     */
    public function every_sitemap_url_carries_a_lastmod(): void
    {
        $this->makeProduct('Lều 2 người', 'leu-2-nguoi');
        Combo::create(['name' => 'Combo đôi', 'slug' => 'combo-doi', 'combo_price' => 200000, 'is_active' => true]);
        StaticPage::provisionAll();

        $xml = simplexml_load_string($this->get('/sitemap.xml')->getContent());

        $missing = [];
        foreach ($xml->url as $u) {
            if (! isset($u->lastmod) || trim((string) $u->lastmod) === '') {
                $missing[] = (string) $u->loc;
            }
        }

        $this->assertSame([], $missing, 'URL thiếu <lastmod>: '.implode(', ', $missing));
        $this->assertGreaterThan(5, $xml->url->count());
    }

    /**
     * lastmod phải LẤY TỪ DỮ LIỆU, không phải now(). Đặt thời điểm hiện tại cho mọi URL
     * là cách nhanh nhất để Google kết luận sitemap không đáng tin và bỏ qua cả trường.
     *
     * @test
     */
    public function lastmod_reflects_real_content_dates_not_the_current_time(): void
    {
        // Chốt mốc MỘT lần: gọi now() hai lần (lúc ghi và lúc assert) thì lệch nhau đúng
        // một giây là test đỏ oan — flaky do chính test, không phải do sitemap.
        $stamp = now()->subDays(30)->startOfSecond();
        $product = $this->makeProduct('Lều 2 người', 'leu-2-nguoi');
        $product->forceFill(['updated_at' => $stamp])->saveQuietly();

        $xml = simplexml_load_string($this->get('/sitemap.xml')->getContent());

        $byLoc = [];
        foreach ($xml->url as $u) {
            $byLoc[(string) $u->loc] = (string) $u->lastmod;
        }

        // Trang chi tiết, trang danh sách và trang danh mục đều nhìn vào cùng một sản phẩm
        // duy nhất, nên cả ba phải mang mốc 30 ngày trước chứ không phải hôm nay.
        foreach ([url('/thiet-bi/leu-2-nguoi'), url('/thiet-bi'), url('/thiet-bi').'?cat=leu'] as $loc) {
            $this->assertArrayHasKey($loc, $byLoc);
            $this->assertSame(
                $stamp->toAtomString(),
                $byLoc[$loc],
                "$loc lấy lastmod sai — phải là mốc updated_at của sản phẩm"
            );
        }
    }

    /**
     * Danh mục không còn sản phẩm đang bán thì KHÔNG khai (bopcamping-10x2): trang chỉ
     * hiện "không tìm thấy sản phẩm" là thin content, mời Google vào đọc chỉ hạ chất
     * lượng chung. Có hàng trở lại thì tự vào lại khi hết cache.
     *
     * @test
     */
    public function empty_category_is_left_out_of_the_sitemap(): void
    {
        Category::create(['name' => 'Bếp', 'slug' => 'bep']); // 0 sản phẩm
        $this->makeProduct('Lều 2 người', 'leu-2-nguoi');     // tạo kèm category 'leu'

        $xml = $this->get('/sitemap.xml')->getContent();

        $this->assertStringContainsString('cat=leu', $xml);
        $this->assertStringNotContainsString('cat=bep', $xml);
    }

    /**
     * Danh mục chỉ còn hàng ẩn cũng là danh mục rỗng — bản trước lọc theo "có sản phẩm
     * nào không" thì vẫn lọt, vì Product::active() mới là điều kiện đúng.
     *
     * @test
     */
    public function category_holding_only_hidden_products_is_left_out(): void
    {
        $this->makeProduct('Lều 2 người', 'leu-2-nguoi');
        $bep = Category::create(['name' => 'Bếp', 'slug' => 'bep']);
        Product::create([
            'category_id' => $bep->id,
            'name' => 'Bếp ẩn',
            'slug' => 'bep-an',
            'price_per_day' => 50000,
            'quantity' => 5,
            'status' => 'hidden',
        ]);

        $this->assertStringNotContainsString('cat=bep', $this->get('/sitemap.xml')->getContent());
    }
}

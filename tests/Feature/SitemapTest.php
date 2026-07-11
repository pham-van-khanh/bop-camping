<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Product;
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
}

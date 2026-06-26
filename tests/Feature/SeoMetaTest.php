<?php

namespace Tests\Feature;

use App\Models\Category;
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
        $res->assertSee('/favicon.svg', false);
        $res->assertSee('property="og:site_name"', false);
        $res->assertSee('name="twitter:card"', false);
        $res->assertSee('application/ld+json', false);
        $res->assertSee('name="description"', false);
        $res->assertSee('og:image', false);
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
    }
}

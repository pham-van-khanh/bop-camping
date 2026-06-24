<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * bopcamping-wyg — trang chủ + danh sách + chi tiết sản phẩm (Shop frontend).
 */
class ProductPageTest extends TestCase
{
    use RefreshDatabase;

    // ─── helpers ────────────────────────────────────────────────────────────

    private function category(string $name = 'Lều cắm trại', string $slug = 'leu-cam-trai'): Category
    {
        return Category::create(['name' => $name, 'slug' => $slug]);
    }

    private function product(Category $cat, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'category_id' => $cat->id,
            'name' => 'Lều Test',
            'slug' => 'leu-test-'.uniqid(),
            'description' => 'Mô tả lều',
            'price_per_day' => 120000,
            'quantity' => 3,
            'deposit' => 300000,
            'status' => 'active',
        ], $overrides));
    }

    // ─── Home ───────────────────────────────────────────────────────────────

    /** @test */
    public function home_page_renders_with_featured_products(): void
    {
        $cat = $this->category();
        $this->product($cat, ['name' => 'Lều Nổi Bật']);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Welcome')
            ->has('featured', 1)
            ->where('featured.0.name', 'Lều Nổi Bật')
        );
    }

    /** @test */
    public function home_page_returns_at_most_4_featured_products(): void
    {
        $cat = $this->category();
        for ($i = 1; $i <= 6; $i++) {
            $this->product($cat, ['name' => "Lều $i"]);
        }

        $response = $this->get('/');

        $response->assertInertia(fn ($page) => $page->has('featured', 4)
        );
    }

    /** @test */
    public function home_page_excludes_hidden_products(): void
    {
        $cat = $this->category();
        $this->product($cat, ['name' => 'Lều ẩn', 'status' => 'hidden']);
        $this->product($cat, ['name' => 'Lều hiện', 'status' => 'active']);

        $response = $this->get('/');

        $response->assertInertia(fn ($page) => $page->has('featured', 1)
            ->where('featured.0.name', 'Lều hiện')
        );
    }

    // ─── Products list ───────────────────────────────────────────────────────

    /** @test */
    public function products_page_renders_all_active_products_and_categories(): void
    {
        $cat = $this->category();
        $this->product($cat, ['name' => 'Lều A']);
        $this->product($cat, ['name' => 'Lều B']);
        $this->product($cat, ['status' => 'hidden']);

        $response = $this->get('/thiet-bi');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Products')
            ->has('products', 2)
            ->has('categories', 1)
            ->has('filters')
        );
    }

    /** @test */
    public function products_page_filters_by_category_slug(): void
    {
        $leu = $this->category('Lều cắm trại', 'leu-cam-trai');
        $bep = $this->category('Bếp & Nấu ăn', 'bep-nau-an');
        $this->product($leu, ['name' => 'Lều A']);
        $this->product($bep, ['name' => 'Bếp A']);

        $response = $this->get('/thiet-bi?cat=leu-cam-trai');

        $response->assertInertia(fn ($page) => $page->has('products', 1)
            ->where('products.0.name', 'Lều A')
            ->where('filters.cat', 'leu-cam-trai')
        );
    }

    /** @test */
    public function products_page_filters_by_search_query(): void
    {
        $cat = $this->category();
        $this->product($cat, ['name' => 'Lều siêu nhẹ']);
        $this->product($cat, ['name' => 'Túi ngủ lông vũ']);

        $response = $this->get('/thiet-bi?q=leu');

        $response->assertInertia(fn ($page) => $page->has('products', 1)
            ->where('products.0.name', 'Lều siêu nhẹ')
        );
    }

    /** @test */
    public function products_page_sorts_by_price_low_to_high(): void
    {
        $cat = $this->category();
        $this->product($cat, ['name' => 'Đắt', 'price_per_day' => 200000]);
        $this->product($cat, ['name' => 'Rẻ',  'price_per_day' => 80000]);

        $response = $this->get('/thiet-bi?sort=low');

        $response->assertInertia(fn ($page) => $page->where('products.0.name', 'Rẻ')
            ->where('products.1.name', 'Đắt')
            ->where('filters.sort', 'low')
        );
    }

    /** @test */
    public function products_page_sorts_by_price_high_to_low(): void
    {
        $cat = $this->category();
        $this->product($cat, ['name' => 'Đắt', 'price_per_day' => 200000]);
        $this->product($cat, ['name' => 'Rẻ',  'price_per_day' => 80000]);

        $response = $this->get('/thiet-bi?sort=high');

        $response->assertInertia(fn ($page) => $page->where('products.0.name', 'Đắt')
            ->where('products.1.name', 'Rẻ')
        );
    }

    // ─── Product detail ──────────────────────────────────────────────────────

    /** @test */
    public function product_detail_page_renders_with_correct_props(): void
    {
        $cat = $this->category();
        $p = $this->product($cat, ['name' => 'Lều Chi Tiết', 'quantity' => 2]);

        $response = $this->get("/thiet-bi/{$p->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('ProductDetail')
            ->where('product.id', $p->id)
            ->where('product.name', 'Lều Chi Tiết')
            ->where('product.quantity', 2)
            ->has('unavailable_dates')
        );
    }

    /** @test */
    public function product_detail_returns_404_for_hidden_product(): void
    {
        $cat = $this->category();
        $p = $this->product($cat, ['status' => 'hidden']);

        $response = $this->get("/thiet-bi/{$p->id}");

        $response->assertStatus(404);
    }

    /** @test */
    public function product_detail_returns_404_for_nonexistent_product(): void
    {
        $response = $this->get('/thiet-bi/99999');

        $response->assertStatus(404);
    }

    /** @test */
    public function product_detail_unavailable_dates_reflect_fully_booked_days(): void
    {
        $cat = $this->category();
        $p = $this->product($cat, ['quantity' => 1]);

        // Đặt hết 1 bộ duy nhất — ngày phải nằm trong cửa sổ 90 ngày mà show() tính.
        $start = Carbon::today()->addDays(10);
        $end = Carbon::today()->addDays(12);
        $order = Order::create([
            'code' => 'BOP-TEST01',
            'customer_name' => 'Khách Test',
            'customer_phone' => '0900000001',
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'status' => 'confirmed',
            'total_price' => 360000,
            'deposit_total' => 300000,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $p->id,
            'quantity' => 1,
            'price_per_day' => 120000,
            'days' => 3,
            'subtotal' => 360000,
        ]);

        $response = $this->get("/thiet-bi/{$p->id}");

        $response->assertInertia(fn ($page) => $page->where('unavailable_dates', fn ($dates) => collect($dates)->contains($start->toDateString())
                && collect($dates)->contains($end->toDateString())
        )
        );
    }

    /** @test */
    public function product_detail_includes_category_and_images_in_props(): void
    {
        $cat = $this->category('Túi ngủ', 'tui-ngu');
        $p = $this->product($cat);

        $response = $this->get("/thiet-bi/{$p->id}");

        $response->assertInertia(fn ($page) => $page->where('product.category.slug', 'tui-ngu')
            ->has('product.images')
        );
    }
}

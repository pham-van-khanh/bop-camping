<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ServiceLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-7sj — làm tươi giỏ: trả giá/vị trí mới nhất, bỏ sản phẩm ẩn/xoá.
 */
class CartRefreshTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $name, string $slug, int $price, string $status = 'active'): Product
    {
        $cat = Category::firstOrCreate(['slug' => 'leu'], ['name' => 'Lều']);

        return Product::create([
            'category_id' => $cat->id,
            'name' => $name,
            'slug' => $slug,
            'price_per_day' => $price,
            'deposit' => 100000,
            'quantity' => 5,
            'status' => $status,
        ]);
    }

    /** @test */
    public function returns_fresh_price_and_locations(): void
    {
        $vinh = ServiceLocation::create(['name' => 'Vinh', 'status' => 'open', 'sort_order' => 1]);
        ServiceLocation::create(['name' => 'Hà Nội', 'status' => 'open', 'sort_order' => 2]);

        $p = $this->product('Lều', 'leu-a', 90000);
        $p->serviceLocations()->sync([$vinh->id]); // chỉ Vinh

        $this->getJson(route('cart.refresh', ['ids' => [$p->id]]))
            ->assertOk()
            ->assertJsonPath("products.{$p->id}.price_per_day", 90000)
            ->assertJsonPath("products.{$p->id}.deposit", 100000)
            ->assertJsonPath("products.{$p->id}.all_locations", false)
            ->assertJsonPath("products.{$p->id}.locations.0.slug", 'vinh');
    }

    /** @test */
    public function all_locations_true_when_serving_every_open_location(): void
    {
        $vinh = ServiceLocation::create(['name' => 'Vinh', 'status' => 'open', 'sort_order' => 1]);
        $hanoi = ServiceLocation::create(['name' => 'Hà Nội', 'status' => 'open', 'sort_order' => 2]);

        $p = $this->product('Lều', 'leu-b', 90000);
        $p->serviceLocations()->sync([$vinh->id, $hanoi->id]);

        $this->getJson(route('cart.refresh', ['ids' => [$p->id]]))
            ->assertOk()
            ->assertJsonPath("products.{$p->id}.all_locations", true);
    }

    /** @test */
    public function hidden_or_missing_products_are_omitted(): void
    {
        $active = $this->product('Còn bán', 'con-ban', 50000);
        $hidden = $this->product('Đã ẩn', 'da-an', 50000, status: 'hidden');

        $res = $this->getJson(route('cart.refresh', ['ids' => [$active->id, $hidden->id, 99999]]))
            ->assertOk();

        $res->assertJsonPath("products.{$active->id}.name", 'Còn bán');
        $this->assertArrayNotHasKey((string) $hidden->id, $res->json('products'));
        $this->assertArrayNotHasKey('99999', $res->json('products'));
    }

    /** @test */
    public function empty_ids_returns_empty(): void
    {
        $this->getJson(route('cart.refresh'))
            ->assertOk()
            ->assertExactJson(['products' => []]);
    }
}

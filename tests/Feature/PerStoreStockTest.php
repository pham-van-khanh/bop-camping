<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ServiceLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Per-store stock — T1: pivot lưu tồn theo cửa hàng. */
class PerStoreStockTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function pivot_stores_quantity_per_location(): void
    {
        $cat = Category::create(['name' => 'Lều', 'slug' => 'leu']);
        $vinh = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1]);
        $hanoi = ServiceLocation::create(['name' => 'Hà Nội', 'area' => 'Nội thành', 'status' => 'open', 'sort_order' => 2]);

        $product = Product::create([
            'category_id' => $cat->id, 'name' => 'Lều 2 người', 'slug' => 'leu-2-nguoi',
            'price_per_day' => 50000, 'quantity' => 8,
        ]);
        $product->serviceLocations()->sync([
            $vinh->id => ['quantity' => 5],
            $hanoi->id => ['quantity' => 3],
        ]);

        $fresh = Product::with('serviceLocations')->find($product->id);
        $this->assertSame(5, $fresh->stockAt($vinh->id));
        $this->assertSame(3, $fresh->stockAt($hanoi->id));
        // Store không phục vụ → 0
        $this->assertSame(0, $fresh->stockAt(999));
    }
}

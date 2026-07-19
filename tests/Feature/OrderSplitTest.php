<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-wtuv (T2) — checkout tách đơn theo khoảng ngày:
 * 1 khoảng → đơn thường; ≥2 khoảng → cha + con.
 */
class OrderSplitTest extends TestCase
{
    use RefreshDatabase;

    private Product $a;

    private Product $b;

    protected function setUp(): void
    {
        parent::setUp();
        $cat = Category::create(['name' => 'Lều', 'slug' => 'leu']);
        $this->a = Product::create(['category_id' => $cat->id, 'name' => 'Món A', 'slug' => 'mon-a', 'price_per_day' => 100000, 'quantity' => 3, 'deposit' => 200000]);
        $this->b = Product::create(['category_id' => $cat->id, 'name' => 'Món B', 'slug' => 'mon-b', 'price_per_day' => 50000, 'quantity' => 3, 'deposit' => 100000]);
    }

    /** @test */
    public function single_range_cart_creates_one_normal_order(): void
    {
        $this->post(route('order.store'), [
            'name' => 'Khách', 'phone' => '0912345678',
            'items' => [
                ['product_id' => $this->a->id, 'quantity' => 1, 'start' => '2030-09-01', 'end' => '2030-09-02'],
                ['product_id' => $this->b->id, 'quantity' => 1, 'start' => '2030-09-01', 'end' => '2030-09-02'],
            ],
        ])->assertSessionHas('order_code');

        $this->assertSame(1, Order::count());
        $order = Order::first();
        $this->assertFalse($order->is_parent);
        $this->assertNull($order->parent_id);
        $this->assertSame(2, $order->items()->count());
    }

    /** @test */
    public function multi_range_cart_creates_parent_and_children(): void
    {
        // A 01→02 (2 ngày, 100k×2=200k), B 05→07 (3 ngày, 50k×3=150k).
        $this->post(route('order.store'), [
            'name' => 'Khách', 'phone' => '0912345678',
            'items' => [
                ['product_id' => $this->a->id, 'quantity' => 1, 'start' => '2030-09-01', 'end' => '2030-09-02'],
                ['product_id' => $this->b->id, 'quantity' => 1, 'start' => '2030-09-05', 'end' => '2030-09-07'],
            ],
        ])->assertSessionHas('order_code');

        $parent = Order::where('is_parent', true)->firstOrFail();
        $children = $parent->children()->get();

        // Cha: envelope, không món, tổng = Σ con.
        $this->assertSame('2030-09-01', $parent->start_date->format('Y-m-d'));
        $this->assertSame('2030-09-07', $parent->end_date->format('Y-m-d'));
        $this->assertSame(0, $parent->items()->count());
        $this->assertSame(2, $children->count());

        // Con 1: A 01→02 = 200k thuê + 200k cọc; Con 2: B 05→07 = 150k thuê + 100k cọc.
        // children() đã orderBy start_date → [0]=01/09, [1]=05/09.
        $c1 = $children[0];
        $c2 = $children[1];
        $this->assertSame(200000, (int) $c1->total_price);
        $this->assertSame(200000, (int) $c1->deposit_total);
        $this->assertSame('2030-09-02', $c1->end_date->format('Y-m-d'));
        $this->assertSame(150000, (int) $c2->total_price);
        $this->assertSame(100000, (int) $c2->deposit_total);

        // Cha gom tổng.
        $this->assertSame(350000, (int) $parent->total_price);
        $this->assertSame(300000, (int) $parent->deposit_total);

        // Mã con = mã cha + hậu tố.
        $this->assertSame($parent->code.'-1', $c1->code);
        $this->assertSame($parent->code.'-2', $c2->code);

        // Danh sách top-level chỉ có cha (ẩn con).
        $this->assertSame(1, Order::topLevel()->count());
    }
}

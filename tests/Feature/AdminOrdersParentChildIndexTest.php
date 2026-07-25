<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-wtuv (T6) — admin list: gom cha (ẩn con khỏi top-level, con nằm trong
 * children của cha), stats không đếm trùng con, search theo mã (cả mã con)/tên/SĐT.
 */
class AdminOrdersParentChildIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $p;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true]);
        $cat = Category::create(['name' => 'Lều', 'slug' => 'leu']);
        $this->p = Product::create(['category_id' => $cat->id, 'name' => 'A', 'slug' => 'a', 'price_per_day' => 100000, 'quantity' => 5, 'deposit' => 0]);
    }

    /** Cha + 2 con qua checkout thật (2 khoảng ngày) + 1 đơn thường. */
    private function seedOrders(): Order
    {
        $this->post(route('order.store'), [
            'name' => 'Khách Gộp', 'phone' => '0911888001',
            'items' => [
                ['product_id' => $this->p->id, 'quantity' => 1, 'start' => '2030-09-01', 'end' => '2030-09-02'],
                ['product_id' => $this->p->id, 'quantity' => 1, 'start' => '2030-09-05', 'end' => '2030-09-06'],
            ],
        ])->assertSessionHas('order_code');

        $this->post(route('order.store'), [
            'name' => 'Khách Thường', 'phone' => '0911888002',
            'items' => [['product_id' => $this->p->id, 'quantity' => 1, 'start' => '2030-09-10', 'end' => '2030-09-11']],
        ])->assertSessionHas('order_code');

        return Order::where('is_parent', true)->firstOrFail();
    }

    /** @test */
    public function index_lists_top_level_only_with_children_nested_and_stats_not_double_counting(): void
    {
        $this->seedOrders();

        $this->actingAs($this->admin)->get(route('admin.orders'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Orders')
                ->has('orders', 2) // cha + đơn thường, KHÔNG gồm 2 con rời
                ->where('stats.total', 2)
                ->where('stats.pending', 2));

        $props = $this->actingAs($this->admin)->get(route('admin.orders'))->inertiaProps();
        $parent = collect($props['orders'])->firstWhere('is_parent', true);
        $this->assertNotNull($parent);
        $this->assertCount(2, $parent['children']);
        $this->assertSame($parent['code'].'-1', $parent['children'][0]['code']);
    }

    /** @test */
    public function search_finds_parent_by_child_code_and_by_phone(): void
    {
        $parent = $this->seedOrders();

        // Tìm bằng MÃ CON → ra cha (chứa con đó).
        $this->actingAs($this->admin)->get(route('admin.orders', ['q' => $parent->code.'-2']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('orders', 1)
                ->where('orders.0.code', $parent->code));

        // Tìm bằng SĐT đơn thường.
        $this->actingAs($this->admin)->get(route('admin.orders', ['q' => '0911888002']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('orders', 1)
                ->where('orders.0.customer_name', 'Khách Thường'));

        // Không khớp → rỗng.
        $this->actingAs($this->admin)->get(route('admin.orders', ['q' => 'KHONG-CO']))
            ->assertInertia(fn (Assert $page) => $page->has('orders', 0));
    }
}

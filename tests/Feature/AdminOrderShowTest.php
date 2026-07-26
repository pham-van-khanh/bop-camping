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
 * spec 2026-07-26 (Phần B) — màn hình riêng cho 1 đơn (admin.orders.show):
 * render Inertia 'Admin/Orders/Show' với đủ thông tin; đơn cha kèm children,
 * đơn con kèm link cha; guest bị chặn. Route action (status…) không đổi.
 */
class AdminOrderShowTest extends TestCase
{
    use RefreshDatabase;

    private Product $chair;

    protected function setUp(): void
    {
        parent::setUp();
        $cat = Category::create(['name' => 'Ghế', 'slug' => 'ghe']);
        $this->chair = Product::create([
            'category_id' => $cat->id, 'name' => 'Ghế Gấp', 'slug' => 'ghe-gap',
            'price_per_day' => 100000, 'quantity' => 5, 'deposit' => 50000,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function order(array $attrs = []): Order
    {
        $order = Order::create(array_merge([
            'code' => 'BOP-'.strtoupper(uniqid()), 'customer_name' => 'Nguyễn Test', 'customer_phone' => '0900000000',
            'start_date' => '2030-07-01', 'end_date' => '2030-07-01', 'status' => 'pending', 'payment_method' => 'cod',
            'total_price' => 100000, 'deposit_total' => 50000, 'session' => 'morning',
            'requested_pickup_time' => '08:00', 'requested_return_time' => '14:00', 'is_half_day' => true,
        ], $attrs));
        $order->items()->create([
            'product_id' => $this->chair->id, 'quantity' => 1, 'price_per_day' => 100000, 'days' => 1,
            'start_date' => '2030-07-01', 'end_date' => '2030-07-01', 'subtotal' => 100000, 'duration_discount_percent' => 0,
        ]);

        return $order;
    }

    /** @test */
    public function admin_sees_order_detail_page(): void
    {
        $order = $this->order();
        $this->actingAs($this->admin())->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Orders/Show')
                ->where('order.code', $order->code)
                ->where('order.session', 'morning')
                ->where('order.items.0.name', 'Ghế Gấp'));
    }

    /** @test */
    public function guest_is_redirected_to_admin_login(): void
    {
        $order = $this->order();
        $this->get(route('admin.orders.show', $order))->assertRedirect(route('admin.login'));
    }

    /** @test */
    public function parent_order_includes_children(): void
    {
        $parent = $this->order(['is_parent' => true, 'session' => null, 'start_date' => '2030-07-01', 'end_date' => '2030-07-05']);
        $child = $this->order(['parent_id' => $parent->id, 'code' => $parent->code.'-1']);

        $this->actingAs($this->admin())->get(route('admin.orders.show', $parent))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('order.is_parent', true)
                ->has('order.children', 1)
                ->where('order.children.0.code', $child->code));
    }

    /** @test */
    public function child_order_links_back_to_parent(): void
    {
        $parent = $this->order(['is_parent' => true, 'session' => null]);
        $child = $this->order(['parent_id' => $parent->id, 'code' => $parent->code.'-1']);

        $this->actingAs($this->admin())->get(route('admin.orders.show', $child))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('order.parent.id', $parent->id)
                ->where('order.parent.code', $parent->code));
    }
}

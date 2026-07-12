<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Per-store stock — T6: checkout gắn cửa hàng + kiểm tồn theo store. */
class OrderPerStoreTest extends TestCase
{
    use RefreshDatabase;

    private ServiceLocation $vinh;

    private ServiceLocation $hanoi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vinh = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1]);
        $this->hanoi = ServiceLocation::create(['name' => 'Hà Nội', 'area' => 'Nội thành', 'status' => 'open', 'sort_order' => 2]);
    }

    private function product(array $stockByLoc): Product
    {
        $cat = Category::firstOrCreate(['slug' => 'leu'], ['name' => 'Lều']);
        $p = Product::create([
            'category_id' => $cat->id, 'name' => 'Lều', 'slug' => 'leu-'.uniqid(),
            'price_per_day' => 50000, 'quantity' => array_sum($stockByLoc), 'deposit' => 100000,
        ]);
        $p->serviceLocations()->sync(collect($stockByLoc)->mapWithKeys(fn ($q, $id) => [$id => ['quantity' => $q]])->all());

        return $p;
    }

    private function order(Product $p, ?int $locationId, int $qty = 1): TestResponse
    {
        return $this->post(route('order.store'), [
            'name' => 'Khách', 'phone' => '0912345678',
            'items' => [['product_id' => $p->id, 'quantity' => $qty, 'start' => '2030-07-01', 'end' => '2030-07-03', 'location_id' => $locationId]],
        ]);
    }

    /** @test */
    public function chosen_store_is_saved_and_only_that_store_is_deducted(): void
    {
        $p = $this->product([$this->vinh->id => 5, $this->hanoi->id => 4]);

        $this->order($p, $this->vinh->id, 2)->assertRedirect()->assertSessionHas('order_code');

        $order = Order::latest('id')->first();
        $this->assertSame($this->vinh->id, $order->service_location_id);
        $this->assertFalse($order->location_auto_assigned);
    }

    /** @test */
    public function no_choice_auto_assigns_a_store_that_can_fill(): void
    {
        // Vinh hết, Hà Nội còn → tự gán Hà Nội, cờ auto = true
        $p = $this->product([$this->vinh->id => 0, $this->hanoi->id => 4]);

        $this->order($p, null, 2)->assertRedirect()->assertSessionHas('order_code');

        $order = Order::latest('id')->first();
        $this->assertSame($this->hanoi->id, $order->service_location_id);
        $this->assertTrue($order->location_auto_assigned);
    }

    /** @test */
    public function rejects_when_no_single_store_can_fill_cart(): void
    {
        $p = $this->product([$this->vinh->id => 1, $this->hanoi->id => 1]);

        $this->order($p, null, 2)->assertSessionHasErrors('items');
        $this->assertSame(0, Order::count());
    }

    /** @test */
    public function admin_can_change_order_store_when_target_has_stock(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $p = $this->product([$this->vinh->id => 5, $this->hanoi->id => 5]);
        $this->order($p, $this->vinh->id, 2)->assertRedirect();
        $order = Order::latest('id')->first();

        $this->actingAs($admin)
            ->patch(route('admin.orders.location', $order), ['service_location_id' => $this->hanoi->id])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame($this->hanoi->id, $order->refresh()->service_location_id);
        $this->assertFalse($order->location_auto_assigned);
    }

    /** @test */
    public function admin_cannot_change_to_store_without_enough_stock(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $p = $this->product([$this->vinh->id => 5, $this->hanoi->id => 1]);
        $this->order($p, $this->vinh->id, 3)->assertRedirect();
        $order = Order::latest('id')->first();

        $this->actingAs($admin)
            ->patch(route('admin.orders.location', $order), ['service_location_id' => $this->hanoi->id])
            ->assertSessionHasErrors('location');

        $this->assertSame($this->vinh->id, $order->refresh()->service_location_id);
    }

    /** @test */
    public function booking_at_one_store_does_not_block_the_other(): void
    {
        $p = $this->product([$this->vinh->id => 1, $this->hanoi->id => 1]);

        // Đặt hết Vinh
        $this->order($p, $this->vinh->id, 1)->assertRedirect();
        // Vẫn đặt được ở Hà Nội (không bị Vinh chiếm)
        $this->order($p, $this->hanoi->id, 1)->assertRedirect();

        $this->assertSame(2, Order::count());
        $this->assertEqualsCanonicalizing(
            [$this->vinh->id, $this->hanoi->id],
            Order::pluck('service_location_id')->all(),
        );
    }
}

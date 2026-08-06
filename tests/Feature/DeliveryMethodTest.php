<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-z3ug — hình thức GIAO khách chọn ở checkout.
 *
 * Chủ shop chốt 06/08/2026: chỉ hỏi lượt GIAO. Lượt TRẢ và phí ship thoả thuận khi
 * nhắn tin rồi note vào schedule_note / extra_fee, KHÔNG hỏi khách ở checkout.
 */
class DeliveryMethodTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $cat = Category::firstOrCreate(['slug' => 'leu'], ['name' => 'Lều']);

        return Product::create([
            'category_id' => $cat->id,
            'name' => 'Lều',
            'slug' => 'leu-'.uniqid(),
            'price_per_day' => 100000,
            'quantity' => 5,
            'deposit' => 200000,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(Product $p, array $extra = []): array
    {
        return array_merge([
            'name' => 'Khách A',
            'phone' => '0912345678',
            'items' => [['product_id' => $p->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-02']],
        ], $extra);
    }

    /** @test */
    public function customer_can_choose_self_pickup(): void
    {
        $p = $this->product();

        $this->post(route('order.store'), $this->payload($p, ['delivery_method' => 'self_pickup']))
            ->assertSessionHas('order_code');

        $this->assertSame('self_pickup', Order::firstOrFail()->delivery_method);
    }

    /** @test */
    public function customer_can_choose_shipping(): void
    {
        $p = $this->product();

        $this->post(route('order.store'), $this->payload($p, ['delivery_method' => 'ship']))
            ->assertSessionHas('order_code');

        $this->assertSame('ship', Order::firstOrFail()->delivery_method);
    }

    /**
     * @test
     *
     * Mặc định là tự đến lấy — phương án RẺ NHẤT. Quan trọng với Nghệ An vì ở đó phải
     * thuê xe ngoài; không được để đơn im lặng rơi vào 'ship'.
     */
    public function defaults_to_self_pickup_when_client_sends_nothing(): void
    {
        $p = $this->product();

        $this->post(route('order.store'), $this->payload($p))->assertSessionHas('order_code');

        $this->assertSame('self_pickup', Order::firstOrFail()->delivery_method);
    }

    /** @test */
    public function rejects_unknown_delivery_method(): void
    {
        $p = $this->product();

        $this->post(route('order.store'), $this->payload($p, ['delivery_method' => 'drone']))
            ->assertSessionHasErrors('delivery_method');

        $this->assertSame(0, Order::count());
    }

    /**
     * @test
     *
     * Hình thức giao KHÔNG được đụng vào tiền — phí ship do admin nhập sau qua extra_fee.
     */
    public function delivery_method_does_not_change_order_totals(): void
    {
        $p = $this->product();

        $this->post(route('order.store'), $this->payload($p, ['delivery_method' => 'self_pickup']))
            ->assertSessionHas('order_code');
        $selfPickup = Order::latest('id')->firstOrFail();

        $p2 = $this->product();
        $this->post(route('order.store'), $this->payload($p2, ['delivery_method' => 'ship']))
            ->assertSessionHas('order_code');
        $ship = Order::latest('id')->firstOrFail();

        $this->assertSame($selfPickup->total_price, $ship->total_price);
        $this->assertSame($selfPickup->deposit_total, $ship->deposit_total);
        $this->assertSame(0, (int) $ship->extra_fee, 'checkout không được tự cộng phí ship');
    }

    /**
     * @test
     *
     * Đơn gộp nhiều đợt giao: mọi đơn con thừa hưởng hình thức giao của lần đặt.
     */
    public function split_order_propagates_delivery_method_to_children(): void
    {
        $p = $this->product();

        $this->post(route('order.store'), [
            'name' => 'Khách A',
            'phone' => '0912345678',
            'delivery_method' => 'ship',
            'items' => [
                ['product_id' => $p->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-02'],
                ['product_id' => $p->id, 'quantity' => 1, 'start' => '2030-08-01', 'end' => '2030-08-02'],
            ],
        ])->assertSessionHas('order_code');

        $parent = Order::where('is_parent', true)->firstOrFail();
        $this->assertSame('ship', $parent->delivery_method);
        foreach ($parent->children as $child) {
            $this->assertSame('ship', $child->delivery_method);
        }
    }

    /** @test */
    public function checkout_page_receives_delivery_options(): void
    {
        $this->get(route('cart'))->assertInertia(fn (Assert $p) => $p
            ->has('delivery_methods', 2)
            ->where('delivery_methods.0.value', 'self_pickup')
            ->where('delivery_methods.1.value', 'ship'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromotionSetting;
use App\Models\ServiceLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-jrh8 (Part B) — checkout nửa ngày (adr_pricing_models): đơn CÙNG NGÀY,
 * khách chọn "trả sớm trong ngày" → is_half_day=true + áp early_return_discount_pct
 * của SẢN PHẨM (server tính, không tin client). Đơn nhiều ngày bỏ qua.
 */
class HalfDayCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Product $chair; // 100k/ngày, ưu đãi trả sớm 10%

    protected function setUp(): void
    {
        parent::setUp();
        PromotionSetting::current()->update(['email_bonus_enabled' => false, 'max_discount_percent_per_order' => 50]);

        $cat = Category::create(['name' => 'Ghế', 'slug' => 'ghe']);
        $this->chair = Product::create([
            'category_id' => $cat->id, 'name' => 'Ghế Test', 'slug' => 'ghe-test',
            'price_per_day' => 100000, 'quantity' => 5, 'early_return_discount_pct' => 10,
        ]);
        $loc = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1]);
        $this->chair->serviceLocations()->attach($loc->id, ['quantity' => 5]);
    }

    private function checkout(User $user, string $start, string $end, bool $halfDay)
    {
        return $this->actingAs($user)->post(route('order.store'), [
            'name' => $user->name,
            'phone' => $user->phone,
            'items' => [[
                'product_id' => $this->chair->id, 'quantity' => 1,
                'start' => $start, 'end' => $end, 'half_day' => $halfDay,
            ]],
        ]);
    }

    /** @test */
    public function same_day_half_day_applies_early_return_discount(): void
    {
        $user = User::factory()->create(['phone' => '0911000101']);
        $this->checkout($user, '2030-07-01', '2030-07-01', halfDay: true)->assertSessionHas('order_code');

        $order = Order::latest('id')->with('items')->first();
        $this->assertTrue($order->is_half_day);
        $this->assertSame(90000, $order->total_price);                 // 100k − 10%
        $this->assertSame(90000, (int) $order->items->first()->subtotal);
        $this->assertSame('10.00', (string) $order->items->first()->duration_discount_percent);
    }

    /** @test */
    public function same_day_without_half_day_has_no_discount(): void
    {
        $user = User::factory()->create(['phone' => '0911000102']);
        $this->checkout($user, '2030-07-01', '2030-07-01', halfDay: false)->assertSessionHas('order_code');

        $order = Order::latest('id')->first();
        $this->assertFalse($order->is_half_day);
        $this->assertSame(100000, $order->total_price);
    }

    /** @test */
    public function multi_day_ignores_half_day_flag(): void
    {
        $user = User::factory()->create(['phone' => '0911000103']);
        // 3 ngày, dù client gửi half_day=true → không áp (chỉ đơn cùng ngày).
        $this->checkout($user, '2030-07-01', '2030-07-03', halfDay: true)->assertSessionHas('order_code');

        $order = Order::latest('id')->first();
        $this->assertFalse($order->is_half_day);
        $this->assertSame(300000, $order->total_price);
    }

    /** @test */
    public function discount_percent_comes_from_product_not_client(): void
    {
        // Sản phẩm 0% ưu đãi → dù khách chọn nửa ngày cũng không giảm (server không tin client).
        $this->chair->update(['early_return_discount_pct' => 0]);
        $user = User::factory()->create(['phone' => '0911000104']);
        $this->checkout($user, '2030-07-01', '2030-07-01', halfDay: true)->assertSessionHas('order_code');

        $order = Order::latest('id')->first();
        $this->assertTrue($order->is_half_day);          // cờ vẫn bật (đơn cùng ngày, khách chọn)
        $this->assertSame(100000, $order->total_price);  // nhưng không giảm vì sp = 0%
    }
}

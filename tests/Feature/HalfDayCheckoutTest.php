<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromotionSetting;
use App\Models\ServiceLocation;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-jrh8 + spec 2026-07-26 — checkout nửa ngày qua BUỔI: đơn CÙNG NGÀY,
 * khách chọn buổi sáng/chiều → is_half_day=true + áp early_return_discount_pct của SẢN PHẨM
 * (server tính giờ + %, không tin client). Cả ngày/nhiều ngày → không giảm.
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

    private function checkout(User $user, string $start, string $end, ?string $session)
    {
        return $this->actingAs($user)->post(route('order.store'), [
            'name' => $user->name,
            'phone' => $user->phone,
            'items' => [[
                'product_id' => $this->chair->id, 'quantity' => 1,
                'start' => $start, 'end' => $end, 'session' => $session,
            ]],
        ]);
    }

    /** @test */
    public function morning_session_applies_early_return_discount(): void
    {
        $user = User::factory()->create(['phone' => '0911000101']);
        $this->checkout($user, '2030-07-01', '2030-07-01', session: 'morning')->assertSessionHas('order_code');

        $order = Order::latest('id')->with('items')->first();
        $this->assertTrue($order->is_half_day);
        $this->assertSame('morning', $order->session);
        $this->assertSame('08:00', $order->requested_pickup_time);      // server suy từ setting 8/12/13/20
        $this->assertSame('12:00', $order->requested_return_time);      // cuối buổi sáng
        $this->assertSame(90000, $order->total_price);                 // 100k − 10%
        $this->assertSame(90000, (int) $order->items->first()->subtotal);
        $this->assertSame('10.00', (string) $order->items->first()->duration_discount_percent);
    }

    /** @test */
    public function afternoon_session_uses_split_to_return_window(): void
    {
        $user = User::factory()->create(['phone' => '0911000105']);
        $this->checkout($user, '2030-07-01', '2030-07-01', session: 'afternoon')->assertSessionHas('order_code');

        $order = Order::latest('id')->first();
        $this->assertTrue($order->is_half_day);
        $this->assertSame('afternoon', $order->session);
        $this->assertSame('13:00', $order->requested_pickup_time);      // đầu buổi chiều
        $this->assertSame('20:00', $order->requested_return_time);
        $this->assertSame(90000, $order->total_price);
    }

    /** @test */
    public function full_day_session_has_no_discount(): void
    {
        $user = User::factory()->create(['phone' => '0911000102']);
        $this->checkout($user, '2030-07-01', '2030-07-01', session: 'full')->assertSessionHas('order_code');

        $order = Order::latest('id')->first();
        $this->assertFalse($order->is_half_day);
        $this->assertSame('full', $order->session);
        $this->assertSame(100000, $order->total_price);
    }

    /** @test */
    public function multi_day_ignores_session(): void
    {
        $user = User::factory()->create(['phone' => '0911000103']);
        // 3 ngày, dù client gửi session=morning → server ép null (chỉ đơn cùng ngày mới có buổi).
        $this->checkout($user, '2030-07-01', '2030-07-03', session: 'morning')->assertSessionHas('order_code');

        $order = Order::latest('id')->first();
        $this->assertFalse($order->is_half_day);
        $this->assertNull($order->session);
        $this->assertNull($order->requested_pickup_time);
        $this->assertSame(300000, $order->total_price);
    }

    /** @test */
    public function derived_times_follow_session_window_settings(): void
    {
        // 2 cửa sổ có khoảng nghỉ (feedback 2026-07-27): sáng 8→11, chiều 13→20.
        SiteSetting::current()->update(['morning_end_hour' => 11, 'afternoon_start_hour' => 13]);
        $user = User::factory()->create(['phone' => '0911000106']);

        $this->checkout($user, '2030-07-01', '2030-07-01', session: 'morning')->assertSessionHas('order_code');
        $morning = Order::latest('id')->first();
        $this->assertSame('08:00', $morning->requested_pickup_time);
        $this->assertSame('11:00', $morning->requested_return_time); // theo morning_end=11

        $this->checkout($user, '2030-07-02', '2030-07-02', session: 'afternoon')->assertSessionHas('order_code');
        $afternoon = Order::latest('id')->first();
        $this->assertSame('13:00', $afternoon->requested_pickup_time); // theo afternoon_start=13
        $this->assertSame('20:00', $afternoon->requested_return_time);
    }

    /** @test */
    public function discount_percent_comes_from_product_not_client(): void
    {
        // Sản phẩm 0% ưu đãi → dù chọn buổi sáng cũng không giảm (server không tin client).
        $this->chair->update(['early_return_discount_pct' => 0]);
        $user = User::factory()->create(['phone' => '0911000104']);
        $this->checkout($user, '2030-07-01', '2030-07-01', session: 'morning')->assertSessionHas('order_code');

        $order = Order::latest('id')->first();
        $this->assertTrue($order->is_half_day);          // buổi sáng = nửa ngày (đơn cùng ngày)
        $this->assertSame(100000, $order->total_price);  // nhưng không giảm vì sp = 0%
    }
}

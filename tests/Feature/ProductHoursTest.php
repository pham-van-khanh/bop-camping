<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromotionSetting;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-n6mr — khung giờ nhận/trả:
 *   - 8/20 là MẶC ĐỊNH TOÀN HỆ THỐNG (site_settings), admin sửa được.
 *   - Ở trang sản phẩm, khách thuê ĐÚNG 1 NGÀY thì tự chọn giờ → lưu vào ĐƠN.
 *     Thuê nhiều ngày: không có giờ (null).
 */
class ProductHoursTest extends TestCase
{
    use RefreshDatabase;

    private Product $chair;

    protected function setUp(): void
    {
        parent::setUp();
        PromotionSetting::current()->update(['email_bonus_enabled' => false, 'max_discount_percent_per_order' => 50]);
        $cat = Category::create(['name' => 'Ghế', 'slug' => 'ghe']);
        $this->chair = Product::create([
            'category_id' => $cat->id, 'name' => 'Ghế', 'slug' => 'ghe-test',
            'price_per_day' => 100000, 'quantity' => 5, 'deposit' => 20000,
        ]);
    }

    /** @test */
    public function shop_default_hours_are_8_and_20(): void
    {
        $this->get('/')->assertInertia(fn (Assert $p) => $p
            ->where('site.pickup_hour', 8)
            ->where('site.return_hour', 20));
    }

    /** @test */
    public function admin_updates_shop_default_hours(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'pickup_hour' => 6, 'return_hour' => 22,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $s = SiteSetting::current();
        $this->assertSame(6, (int) $s->pickup_hour);
        $this->assertSame(22, (int) $s->return_hour);
    }

    /** @test */
    public function same_day_checkout_stores_customer_chosen_times(): void
    {
        $user = User::factory()->create(['phone' => '0911333001']);
        $this->actingAs($user)->post(route('order.store'), [
            'name' => $user->name, 'phone' => $user->phone,
            'items' => [[
                'product_id' => $this->chair->id, 'quantity' => 1,
                'start' => '2030-07-01', 'end' => '2030-07-01',
                'requested_pickup_time' => '09:30', 'requested_return_time' => '17:00',
            ]],
        ])->assertSessionHas('order_code');

        $order = Order::latest('id')->first();
        $this->assertSame('09:30', $order->requested_pickup_time);
        $this->assertSame('17:00', $order->requested_return_time);
    }

    /** @test */
    public function multi_day_checkout_ignores_requested_times(): void
    {
        $user = User::factory()->create(['phone' => '0911333002']);
        // 3 ngày — dù client gửi giờ, đơn KHÔNG lưu (chỉ áp thuê 1 ngày).
        $this->actingAs($user)->post(route('order.store'), [
            'name' => $user->name, 'phone' => $user->phone,
            'items' => [[
                'product_id' => $this->chair->id, 'quantity' => 1,
                'start' => '2030-07-01', 'end' => '2030-07-03',
                'requested_pickup_time' => '06:00', 'requested_return_time' => '22:00',
            ]],
        ])->assertSessionHas('order_code');

        $order = Order::latest('id')->first();
        $this->assertNull($order->requested_pickup_time);
        $this->assertNull($order->requested_return_time);
    }

    /** @test */
    public function checkout_rejects_invalid_time_format(): void
    {
        $user = User::factory()->create(['phone' => '0911333003']);
        $this->actingAs($user)->post(route('order.store'), [
            'name' => $user->name, 'phone' => $user->phone,
            'items' => [[
                'product_id' => $this->chair->id, 'quantity' => 1,
                'start' => '2030-07-01', 'end' => '2030-07-01',
                'requested_pickup_time' => '25:99',
            ]],
        ])->assertSessionHasErrors('items.0.requested_pickup_time');
    }
}

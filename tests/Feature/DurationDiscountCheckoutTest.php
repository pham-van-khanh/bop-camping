<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DurationDiscountTier;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromotionSetting;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-e36e — giảm giá thuê dài ngày áp vào GIÁ THUÊ lúc checkout (net = subtotal),
 * snapshot % vào order_item; voucher áp THÊM trên net (ngoài trần dài ngày).
 */
class DurationDiscountCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Product $tent; // 100k/ngày

    protected function setUp(): void
    {
        parent::setUp();
        PromotionSetting::current()->update(['email_bonus_enabled' => false, 'max_discount_percent_per_order' => 50]);
        DurationDiscountTier::create(['min_days' => 5, 'discount_percent' => 20, 'is_active' => true]);
        DurationDiscountTier::create(['min_days' => 10, 'discount_percent' => 30, 'is_active' => true]);

        $cat = Category::create(['name' => 'Lều', 'slug' => 'leu']);
        $this->tent = Product::create([
            'category_id' => $cat->id, 'name' => 'Lều Test', 'slug' => 'leu-test',
            'price_per_day' => 100000, 'quantity' => 5,
        ]);
    }

    private function checkout(User $user, string $start, string $end, array $extra = [])
    {
        return $this->actingAs($user)->post(route('order.store'), array_merge([
            'name' => $user->name,
            'phone' => $user->phone,
            'items' => [['product_id' => $this->tent->id, 'quantity' => 1, 'start' => $start, 'end' => $end]],
        ], $extra));
    }

    /** @test */
    public function short_rental_below_tier_has_no_duration_discount(): void
    {
        $user = User::factory()->create(['phone' => '0911000001']);
        $this->checkout($user, '2030-07-01', '2030-07-03')->assertSessionHas('order_code'); // 3 ngày

        $order = Order::latest('id')->with('items')->first();
        $this->assertSame(300000, $order->total_price);
        $this->assertSame(300000, (int) $order->items->first()->subtotal);
        $this->assertSame('0.00', (string) $order->items->first()->duration_discount_percent);
    }

    /** @test */
    public function long_rental_applies_tier_to_rental_price(): void
    {
        $user = User::factory()->create(['phone' => '0911000002']);
        $this->checkout($user, '2030-07-01', '2030-07-10')->assertSessionHas('order_code'); // 10 ngày → −30%

        $order = Order::latest('id')->with('items')->first();
        // gross 1.000.000 × (1−0.30) = 700.000
        $this->assertSame(700000, $order->total_price);
        $this->assertSame(700000, (int) $order->items->first()->subtotal);
        $this->assertSame('30.00', (string) $order->items->first()->duration_discount_percent);
    }

    /** @test */
    public function voucher_applies_on_top_of_duration_discounted_price(): void
    {
        $user = User::factory()->create(['phone' => '0911000003']);
        Voucher::create([
            'user_id' => $user->id, 'code' => 'GIAM10', 'type' => 'percent', 'value' => 10,
            'status' => 'active', 'max_uses' => 1, 'used_count' => 0,
        ]);

        $this->checkout($user, '2030-07-01', '2030-07-10', ['voucher_codes' => ['GIAM10']])->assertSessionHas('order_code');

        $order = Order::latest('id')->first();
        // net thuê 700k; voucher 10% × 700k = 70k (dưới trần 50%)
        $this->assertSame(700000, $order->total_price);
        $this->assertSame(70000, (int) $order->discount_total);
    }
}

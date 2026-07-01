<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromotionSetting;
use App\Models\User;
use App\Services\Promotion\EmailBonusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * bopcamping-3xr — email không bắt buộc khi đăng ký; giảm % đơn đầu khi có email đã xác thực.
 */
class EmailBonusTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function first_order_with_verified_email_gets_percent_discount(): void
    {
        $user = $this->verifiedUser();
        $order = $this->order($user, 200000);

        $result = app(EmailBonusService::class)->applyFirstOrderDiscount($order);

        $this->assertSame('ok', $result['reason']);
        $this->assertSame(10000, $result['discount']); // 5% mặc định của 200k
        $this->assertSame(10000, (int) $order->fresh()->discount_total);
    }

    /** @test */
    public function user_without_verified_email_gets_no_discount(): void
    {
        $user = User::create(['name' => 'Chỉ SĐT', 'phone' => '0900000401']); // email tạm, chưa verify
        $order = $this->order($user, 200000);

        $result = app(EmailBonusService::class)->applyFirstOrderDiscount($order);

        $this->assertSame('no_verified_email', $result['reason']);
        $this->assertSame(0, $result['discount']);
    }

    /** @test */
    public function second_order_does_not_get_discount(): void
    {
        $user = $this->verifiedUser();
        $this->order($user, 150000); // đơn cũ
        $order = $this->order($user, 200000);

        $result = app(EmailBonusService::class)->applyFirstOrderDiscount($order);

        $this->assertSame('not_first_order', $result['reason']);
        $this->assertSame(0, $result['discount']);
    }

    /** @test */
    public function disabled_setting_skips_discount(): void
    {
        $settings = PromotionSetting::current();
        $settings->update(['email_bonus_enabled' => false]);
        $user = $this->verifiedUser();
        $order = $this->order($user, 200000);

        $result = app(EmailBonusService::class)->applyFirstOrderDiscount($order, $settings->fresh());

        $this->assertSame('disabled', $result['reason']);
    }

    /** @test HTTP: checkout tự áp giảm cho khách đã verify email ở đơn đầu. */
    public function checkout_applies_email_bonus_for_first_order(): void
    {
        $user = $this->verifiedUser();
        $product = $this->product(); // 100k/ngày
        $day = Carbon::today()->addDays(3)->toDateString();

        $this->actingAs($user)->post(route('order.store'), [
            'name' => 'Khách có email',
            'phone' => $user->phone,
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'start' => $day, 'end' => $day]],
        ])->assertSessionHas('order_discount', 5000); // 5% của 100k
    }

    // ---- Helpers -------------------------------------------------------------

    private function verifiedUser(): User
    {
        return User::factory()->create([
            'phone' => '0900000400', 'email' => 'that@example.com', 'email_verified_at' => now(),
        ]);
    }

    private function order(User $user, int $total): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'customer_name' => $user->name,
            'customer_phone' => $user->phone,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-02',
            'total_price' => $total,
            'status' => 'pending',
            'payment_method' => 'cod',
        ]);
    }

    private function product(): Product
    {
        $category = Category::create(['name' => 'Test', 'slug' => 'test-'.uniqid()]);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Lều Test',
            'slug' => 'leu-'.uniqid(),
            'price_per_day' => 100000,
            'quantity' => 10,
        ]);
    }
}

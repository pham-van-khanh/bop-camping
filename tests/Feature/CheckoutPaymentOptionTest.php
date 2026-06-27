<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-452 — thanh toán 2 tầng: option full / deposit + admin đánh dấu đã nhận tiền.
 */
class CheckoutPaymentOptionTest extends TestCase
{
    use RefreshDatabase;

    private function product(int $price = 100000, int $deposit = 200000): Product
    {
        $cat = Category::firstOrCreate(['slug' => 'leu'], ['name' => 'Lều']);

        return Product::create([
            'category_id' => $cat->id,
            'name' => 'Lều',
            'slug' => 'leu-'.uniqid(),
            'price_per_day' => $price,
            'quantity' => 5,
            'deposit' => $deposit,
        ]);
    }

    private function admin(): User
    {
        return User::firstOrCreate(['phone' => '0976544370'], ['name' => 'Admin', 'email' => 'a@bop.test', 'is_admin' => true]);
    }

    private function placeOrder(string $option): Order
    {
        $p = $this->product();
        $this->post(route('order.store'), [
            'name' => 'Khách',
            'phone' => '0912345678',
            'payment_option' => $option,
            'items' => [['product_id' => $p->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-02']],
        ])->assertSessionHas('order_code');

        return Order::latest('id')->first();
    }

    /** @test */
    public function full_option_prepays_everything_no_cod(): void
    {
        $order = $this->placeOrder('full');

        // 1 bộ × 2 ngày × 100000 = 200000 thuê; cọc 200000.
        $this->assertSame('full', $order->payment_option);
        $this->assertSame(400000, $order->prepayAmount()); // thuê + cọc
        $this->assertSame(0, $order->codAmount());
    }

    /** @test */
    public function deposit_option_prepays_deposit_rental_is_cod(): void
    {
        $order = $this->placeOrder('deposit');

        $this->assertSame('deposit', $order->payment_option);
        $this->assertSame(200000, $order->prepayAmount()); // chỉ cọc
        $this->assertSame(200000, $order->codAmount());    // phí thuê COD
    }

    /** @test */
    public function defaults_to_deposit_when_option_missing(): void
    {
        $p = $this->product();
        $this->post(route('order.store'), [
            'name' => 'Khách',
            'phone' => '0912345678',
            'items' => [['product_id' => $p->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-02']],
        ])->assertSessionHas('order_code');

        $this->assertSame('deposit', Order::latest('id')->first()->payment_option);
    }

    /** @test */
    public function rejects_invalid_option(): void
    {
        $p = $this->product();
        $this->post(route('order.store'), [
            'name' => 'Khách',
            'phone' => '0912345678',
            'payment_option' => 'bank-robbery',
            'items' => [['product_id' => $p->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-02']],
        ])->assertSessionHasErrors('payment_option');

        $this->assertSame(0, Order::count());
    }

    /** @test */
    public function payment_label_reflects_state(): void
    {
        $order = $this->placeOrder('deposit');
        $this->assertSame('Chờ chuyển khoản cọc', $order->paymentLabel());

        $order->update(['deposit_paid_at' => now()]);
        $this->assertSame('Đã nhận cọc · chờ COD phí thuê', $order->fresh()->paymentLabel());

        $order->update(['rental_paid_at' => now()]);
        $this->assertSame('Đã thanh toán đủ', $order->fresh()->paymentLabel());
    }

    /** @test */
    public function admin_can_mark_deposit_and_rental_paid(): void
    {
        $order = $this->placeOrder('deposit');

        $this->actingAs($this->admin())
            ->patch(route('admin.orders.payment', $order), ['field' => 'deposit', 'paid' => true])
            ->assertRedirect();
        $this->assertTrue($order->fresh()->depositPaid());

        $this->actingAs($this->admin())
            ->patch(route('admin.orders.payment', $order), ['field' => 'rental', 'paid' => true])
            ->assertRedirect();
        $this->assertTrue($order->fresh()->rentalPaid());
        $this->assertTrue($order->fresh()->isFullyPaid());

        // Bỏ đánh dấu cọc.
        $this->actingAs($this->admin())
            ->patch(route('admin.orders.payment', $order), ['field' => 'deposit', 'paid' => false])
            ->assertRedirect();
        $this->assertFalse($order->fresh()->depositPaid());
    }

    /** @test */
    public function guest_cannot_mark_payment(): void
    {
        $order = $this->placeOrder('deposit');

        $this->patch(route('admin.orders.payment', $order), ['field' => 'deposit', 'paid' => true])
            ->assertRedirect(route('admin.login'));
        $this->assertFalse($order->fresh()->depositPaid());
    }
}

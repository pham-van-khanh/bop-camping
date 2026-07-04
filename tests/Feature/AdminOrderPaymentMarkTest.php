<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-7be — admin đánh dấu tình trạng chuyển tiền của đơn:
 * unpaid (chưa chuyển) · deposit (đã chuyển cọc) · full (chuyển hết).
 */
class AdminOrderPaymentMarkTest extends TestCase
{
    use RefreshDatabase;

    private function order(): Order
    {
        return Order::create([
            'customer_name' => 'Khách', 'customer_phone' => '0900000001',
            'start_date' => '2030-07-10', 'end_date' => '2030-07-12',
            'total_price' => 300000, 'deposit_total' => 200000,
            'status' => 'confirmed', 'payment_method' => 'cod',
        ]);
    }

    /** @test */
    public function new_order_defaults_to_unpaid(): void
    {
        // Default 'unpaid' áp ở tầng DB → đọc lại từ DB (fresh) để thấy.
        $this->assertSame('unpaid', $this->order()->fresh()->payment_status);
    }

    /** @test */
    public function admin_orders_payload_includes_payment_status(): void
    {
        $this->order();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.orders'))->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders')
            ->where('orders.0.payment_status', 'unpaid'));
    }

    /** @test */
    public function admin_can_mark_deposit_and_full(): void
    {
        $order = $this->order();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->patch(route('admin.orders.payment', $order), ['payment_status' => 'deposit'])
            ->assertSessionHasNoErrors();
        $this->assertSame('deposit', $order->fresh()->payment_status);

        $this->actingAs($admin)->patch(route('admin.orders.payment', $order), ['payment_status' => 'full']);
        $this->assertSame('full', $order->fresh()->payment_status);

        // Đánh dấu ngược lại "chưa chuyển" vẫn được (admin sửa nhầm).
        $this->actingAs($admin)->patch(route('admin.orders.payment', $order), ['payment_status' => 'unpaid']);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    /** @test */
    public function invalid_payment_status_is_rejected(): void
    {
        $order = $this->order();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->patch(route('admin.orders.payment', $order), ['payment_status' => 'bogus'])
            ->assertSessionHasErrors('payment_status');
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    /** @test */
    public function non_admin_cannot_mark_payment(): void
    {
        $order = $this->order();
        $user = User::factory()->create(['is_admin' => false]);

        // EnsureAdmin chuyển hướng non-admin về trang đăng nhập admin (302), không phải 403.
        $this->actingAs($user)->patch(route('admin.orders.payment', $order), ['payment_status' => 'full'])
            ->assertRedirect(route('admin.login'));
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }
}

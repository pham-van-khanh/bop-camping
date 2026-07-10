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

    private function order(string $status = 'confirmed'): Order
    {
        return Order::create([
            'customer_name' => 'Khách', 'customer_phone' => '0900000001',
            'start_date' => '2030-07-10', 'end_date' => '2030-07-12',
            'total_price' => 300000, 'deposit_total' => 200000,
            'status' => $status, 'payment_method' => 'cod',
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
            ->where('orders.data.0.payment_status', 'unpaid'));
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

    /** bopcamping-7be — đơn đã trả: khoá chuyển tiền, mở hoàn cọc + lý do. */

    /** @test */
    public function returned_order_blocks_payment_status_change(): void
    {
        $order = $this->order('returned');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->patch(route('admin.orders.payment', $order), ['payment_status' => 'full'])
            ->assertSessionHasErrors('payment_status');
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    /** @test */
    public function new_order_defaults_to_refund_pending(): void
    {
        $this->assertSame('pending', $this->order()->fresh()->deposit_refund_status);
    }

    /** @test */
    public function admin_can_mark_refund_with_reason_note_on_returned_order(): void
    {
        $order = $this->order('returned');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->patch(route('admin.orders.refund', $order), [
            'deposit_refund_status' => 'refunded',
            'deposit_refund_note' => 'Trừ 50k do rách lều',
        ])->assertSessionHasNoErrors();

        $fresh = $order->fresh();
        $this->assertSame('refunded', $fresh->deposit_refund_status);
        $this->assertSame('Trừ 50k do rách lều', $fresh->deposit_refund_note);
    }

    /** @test */
    public function refund_rejected_when_order_not_returned(): void
    {
        $order = $this->order('confirmed');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->patch(route('admin.orders.refund', $order), ['deposit_refund_status' => 'refunded'])
            ->assertSessionHasErrors('deposit_refund_status');
        $this->assertSame('pending', $order->fresh()->deposit_refund_status);
    }

    /** @test */
    public function invalid_refund_status_is_rejected(): void
    {
        $order = $this->order('returned');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->patch(route('admin.orders.refund', $order), ['deposit_refund_status' => 'bogus'])
            ->assertSessionHasErrors('deposit_refund_status');
    }

    /** @test */
    public function admin_orders_payload_includes_refund_fields(): void
    {
        $order = $this->order('returned');
        $order->update(['deposit_refund_status' => 'refunded', 'deposit_refund_note' => 'Hỏng bếp']);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.orders'))->assertInertia(fn (Assert $page) => $page
            ->where('orders.data.0.deposit_refund_status', 'refunded')
            ->where('orders.data.0.deposit_refund_note', 'Hỏng bếp'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-7be + q7i0 — admin đánh dấu thu tiền. Từ 30/07/2026 tách thành 2 KHOẢN
 * ĐỘC LẬP (tiền thuê / cọc); `payment_status` chỉ còn là tóm tắt SUY RA: chưa thu gì =
 * unpaid · thu 1 trong 2 = deposit · thu cả hai = full.
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
            ->where('orders.0.payment_status', 'unpaid'));
    }

    /** @test */
    public function admin_marks_each_amount_independently(): void
    {
        $order = $this->order();
        $admin = User::factory()->create(['is_admin' => true]);

        // Thu TIỀN THUÊ trước, cọc vẫn chưa thu — tổ hợp mà mô hình cũ không biểu diễn được.
        $this->actingAs($admin)->patch(route('admin.orders.payment', $order), ['kind' => 'rental', 'paid' => true])
            ->assertSessionHasNoErrors();
        $fresh = $order->fresh();
        $this->assertTrue($fresh->rentalPaid());
        $this->assertFalse($fresh->depositPaid());
        $this->assertSame('deposit', $fresh->payment_status, 'thu 1 trong 2 khoản → tóm tắt "một phần"');
        $this->assertSame($admin->id, $fresh->rental_paid_by, 'phải ghi ai thu để đối soát');
        $this->assertNotNull($fresh->rental_paid_at);

        // Thu thêm cọc → đủ cả hai.
        $this->actingAs($admin)->patch(route('admin.orders.payment', $order), ['kind' => 'deposit', 'paid' => true]);
        $this->assertSame('full', $order->fresh()->payment_status);

        // Bỏ đánh dấu (admin bấm nhầm) — xoá luôn dấu ai thu.
        $this->actingAs($admin)->patch(route('admin.orders.payment', $order), ['kind' => 'rental', 'paid' => false]);
        $fresh = $order->fresh();
        $this->assertFalse($fresh->rentalPaid());
        $this->assertNull($fresh->rental_paid_by);
        $this->assertSame('deposit', $fresh->payment_status);

        $this->actingAs($admin)->patch(route('admin.orders.payment', $order), ['kind' => 'deposit', 'paid' => false]);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    /** @test */
    public function rental_due_excludes_deposit_and_includes_extra_fee(): void
    {
        $order = $this->order();
        $order->update(['extra_fee' => 20000, 'discount_total' => 50000]);

        // thuê 300k + phụ phí 20k − giảm 50k = 270k; cọc 200k tính riêng.
        $this->assertSame(270000, $order->fresh()->rental_due);
        $this->assertSame(470000, $order->fresh()->amount_due);
    }

    /** @test */
    public function invalid_payment_kind_is_rejected(): void
    {
        $order = $this->order();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->patch(route('admin.orders.payment', $order), ['kind' => 'bogus', 'paid' => true])
            ->assertSessionHasErrors('kind');
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    /** @test */
    public function non_admin_cannot_mark_payment(): void
    {
        $order = $this->order();
        $user = User::factory()->create(['is_admin' => false]);

        // EnsureAdmin chuyển hướng non-admin về trang đăng nhập admin (302), không phải 403.
        $this->actingAs($user)->patch(route('admin.orders.payment', $order), ['kind' => 'rental', 'paid' => true])
            ->assertRedirect(route('admin.login'));
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    /** @test */
    public function returned_order_still_allows_marking_money_collected(): void
    {
        // ĐỔI so với bản cũ (bopcamping-q7i0): tiền thuê có thể mới thu đúng lúc thu đồ,
        // nên đơn đã trả KHÔNG được khoá việc đánh dấu thu tiền nữa.
        $order = $this->order('returned');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->patch(route('admin.orders.payment', $order), ['kind' => 'rental', 'paid' => true])
            ->assertSessionHasNoErrors();
        $this->assertTrue($order->fresh()->rentalPaid());
    }

    /** @test */
    public function cancelled_order_blocks_marking_money(): void
    {
        $order = $this->order('cancelled');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->patch(route('admin.orders.payment', $order), ['kind' => 'rental', 'paid' => true])
            ->assertSessionHasErrors('payment');
        $this->assertFalse($order->fresh()->rentalPaid());
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
            ->where('orders.0.deposit_refund_status', 'refunded')
            ->where('orders.0.deposit_refund_note', 'Hỏng bếp'));
    }
}

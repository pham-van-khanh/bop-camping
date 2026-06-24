<?php

namespace Tests\Feature;

use App\Mail\OrderStatusMail;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * bopcamping-b7q — mail thông báo khi đơn đổi trạng thái.
 * Đổi trạng thái qua endpoint admin (HTTP) để afterResponse gửi mail thật.
 */
class OrderStatusMailTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function confirmed_returned_cancelled_each_send_mail(): void
    {
        $admin = $this->admin();

        foreach (['confirmed', 'returned', 'cancelled'] as $status) {
            Mail::fake();
            $order = $this->order('khach@example.com');

            $this->actingAs($admin)
                ->patch(route('admin.orders.update', $order), ['status' => $status])
                ->assertRedirect();

            Mail::assertQueued(OrderStatusMail::class, fn (OrderStatusMail $m) => $m->status === $status
                && $m->order->is($order)
                && $m->hasTo('khach@example.com'));
        }
    }

    /** @test */
    public function non_notifying_status_sends_nothing(): void
    {
        Mail::fake();
        $order = $this->order('khach@example.com');

        $this->actingAs($this->admin())
            ->patch(route('admin.orders.update', $order), ['status' => 'renting'])
            ->assertRedirect();

        Mail::assertNothingOutgoing();
    }

    /** @test */
    public function order_without_real_email_sends_nothing(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $order = $this->order(null);
        $this->actingAs($admin)->patch(route('admin.orders.update', $order), ['status' => 'confirmed']);
        Mail::assertNothingOutgoing();

        $local = $this->order('0900000001@bopcamping.local');
        $this->actingAs($admin)->patch(route('admin.orders.update', $local), ['status' => 'confirmed']);
        Mail::assertNothingOutgoing();
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'phone' => '0900000999']);
    }

    private function order(?string $email): Order
    {
        return Order::create([
            'customer_name' => 'Khách Test',
            'customer_phone' => '0900000001',
            'customer_email' => $email,
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-12',
            'total_price' => 360000,
            'deposit_total' => 300000,
            'status' => 'pending',
        ]);
    }
}

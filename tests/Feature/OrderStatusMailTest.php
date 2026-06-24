<?php

namespace Tests\Feature;

use App\Mail\OrderStatusMail;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * bopcamping-b7q — mail thông báo khi đơn đổi trạng thái.
 */
class OrderStatusMailTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function confirmed_returned_cancelled_each_send_mail(): void
    {
        foreach (['confirmed', 'returned', 'cancelled'] as $status) {
            Mail::fake();
            $order = $this->order('khach@example.com');

            $order->update(['status' => $status]);

            Mail::assertSent(OrderStatusMail::class, fn (OrderStatusMail $m) => $m->status === $status
                && $m->order->is($order)
                && $m->hasTo('khach@example.com'));
        }
    }

    /** @test */
    public function non_notifying_statuses_send_nothing(): void
    {
        Mail::fake();
        $order = $this->order('khach@example.com');

        $order->update(['status' => 'renting']); // không nằm trong danh sách gửi mail

        Mail::assertNothingSent();
    }

    /** @test */
    public function order_without_real_email_sends_nothing(): void
    {
        Mail::fake();
        $order = $this->order(null);                 // không có email
        $order->update(['status' => 'confirmed']);
        Mail::assertNothingSent();

        Mail::fake();
        $local = $this->order('0900000001@bopcamping.local'); // email tạm
        $local->update(['status' => 'confirmed']);
        Mail::assertNothingSent();
    }

    /** @test */
    public function status_unchanged_update_sends_nothing(): void
    {
        Mail::fake();
        $order = $this->order('khach@example.com');

        $order->update(['note' => 'Đổi ghi chú, không đổi trạng thái']);

        Mail::assertNothingSent();
    }

    private function order(?string $email): Order
    {
        // Observer chỉ chạy ở 'updated' nên tạo bình thường (code tự sinh) là an toàn.
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

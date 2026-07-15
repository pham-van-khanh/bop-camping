<?php

namespace Tests\Feature;

use App\Mail\OrderPickupReminderMail;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * bopcamping-sdy8 — email nhắc nhận đồ (trước 1 ngày). Command chỉ gửi cho đơn
 * confirmed, start_date = ngày mai, có email, chưa gửi; idempotent.
 */
class SendPickupRemindersTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(array $attrs = []): Order
    {
        return Order::factory()->create(array_merge([
            'status' => 'confirmed',
            'customer_email' => 'khach@example.com',
            'start_date' => '2026-07-11',
            'end_date' => '2026-07-12',
        ], $attrs));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-07-10 08:00:00'); // ngày mai = 2026-07-11
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @test */
    public function sends_reminder_for_eligible_confirmed_order_and_stamps_it(): void
    {
        Mail::fake();
        $order = $this->makeOrder();

        $this->artisan('orders:send-pickup-reminders')->assertSuccessful();

        Mail::assertQueued(OrderPickupReminderMail::class, 1);
        Mail::assertQueued(OrderPickupReminderMail::class, fn ($m) => $m->order->is($order));
        $this->assertNotNull($order->fresh()->pickup_reminder_sent_at);
    }

    /** @test */
    public function skips_orders_that_are_not_eligible(): void
    {
        Mail::fake();

        $this->makeOrder(['status' => 'pending']);                     // chưa xác nhận
        $this->makeOrder(['start_date' => '2026-07-10', 'end_date' => '2026-07-12']); // nhận hôm nay
        $this->makeOrder(['start_date' => '2026-07-12', 'end_date' => '2026-07-13']); // còn 2 ngày
        $this->makeOrder(['customer_email' => null]);                  // không email
        $this->makeOrder(['pickup_reminder_sent_at' => now()]);        // đã gửi

        $this->artisan('orders:send-pickup-reminders')->assertSuccessful();

        Mail::assertNothingQueued();
    }

    /** @test */
    public function does_not_stamp_order_without_email(): void
    {
        Mail::fake();
        $order = $this->makeOrder(['customer_email' => null]);

        $this->artisan('orders:send-pickup-reminders');

        $this->assertNull($order->fresh()->pickup_reminder_sent_at);
    }

    /** @test */
    public function is_idempotent_when_run_twice(): void
    {
        Mail::fake();
        $this->makeOrder();

        $this->artisan('orders:send-pickup-reminders');
        $this->artisan('orders:send-pickup-reminders');

        Mail::assertQueued(OrderPickupReminderMail::class, 1);
    }
}

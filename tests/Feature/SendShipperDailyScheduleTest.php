<?php

namespace Tests\Feature;

use App\Mail\ShipperScheduleMail;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * bopcamping-5r5m — command `shipper:send-daily-schedule` chạy 06:00 hằng ngày.
 * Idempotent: chạy lại trong cùng ngày KHÔNG gửi trùng (cache key theo ngày).
 * Cần cron trên server (bopcamping-ybsm) + queue worker vì mail là ShouldQueue.
 */
class SendShipperDailyScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function shipper(string $name, ?string $email): User
    {
        return User::factory()->create(['name' => $name, 'email' => $email, 'is_shipper' => true]);
    }

    /** Đơn cần giao HÔM NAY gán cho $shipper. */
    private function todayOrder(User $shipper): Order
    {
        return Order::create([
            'code' => 'BOP-'.strtoupper(uniqid()),
            'customer_name' => 'Khách', 'customer_phone' => '0900000000',
            'start_date' => now()->toDateString(), 'end_date' => now()->addDays(2)->toDateString(),
            'status' => 'confirmed', 'payment_method' => 'cod',
            'total_price' => 100000, 'deposit_total' => 50000,
            'pickup_shipper_id' => $shipper->id,
        ]);
    }

    /** @test */
    public function sends_today_schedule_to_each_shipper_with_legs(): void
    {
        Mail::fake();
        $busy = $this->shipper('Có việc', 'busy@example.com');
        $this->shipper('Rảnh', 'idle@example.com');
        $this->todayOrder($busy);

        $this->artisan('shipper:send-daily-schedule')
            ->expectsOutputToContain('Đã gửi lịch cho 1 shipper.')
            ->assertSuccessful();

        Mail::assertQueued(ShipperScheduleMail::class, fn ($m) => $m->hasTo('busy@example.com'));
        Mail::assertQueuedCount(1);
    }

    /** @test */
    public function running_twice_in_the_same_day_does_not_send_again(): void
    {
        Mail::fake();
        $shipper = $this->shipper('Có việc', 'busy@example.com');
        $this->todayOrder($shipper);

        $this->artisan('shipper:send-daily-schedule')->assertSuccessful();
        $this->artisan('shipper:send-daily-schedule')
            ->expectsOutputToContain('đã gửi lịch cho shipper')
            ->assertSuccessful();

        Mail::assertQueuedCount(1);
    }

    /** @test */
    public function force_sends_again(): void
    {
        Mail::fake();
        $shipper = $this->shipper('Có việc', 'busy@example.com');
        $this->todayOrder($shipper);

        $this->artisan('shipper:send-daily-schedule')->assertSuccessful();
        $this->artisan('shipper:send-daily-schedule', ['--force' => true])->assertSuccessful();

        Mail::assertQueuedCount(2);
    }

    /** @test */
    public function shipper_without_a_real_email_is_warned_not_silently_skipped(): void
    {
        Mail::fake();
        $noEmail = $this->shipper('Không Email', null);   // email tạm @bopcamping.local
        $this->todayOrder($noEmail);

        $this->artisan('shipper:send-daily-schedule')
            ->expectsOutputToContain('Không Email')
            ->assertSuccessful();

        Mail::assertNothingQueued();
    }

    /** @test */
    public function no_legs_today_sends_nothing_and_still_succeeds(): void
    {
        Mail::fake();
        $this->shipper('Rảnh', 'idle@example.com');

        $this->artisan('shipper:send-daily-schedule')
            ->expectsOutputToContain('Đã gửi lịch cho 0 shipper.')
            ->assertSuccessful();

        Mail::assertNothingQueued();
    }
}

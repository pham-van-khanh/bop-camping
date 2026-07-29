<?php

namespace Tests\Feature;

use App\Mail\ShipperScheduleMail;
use App\Models\Order;
use App\Models\User;
use App\Services\DeliveryScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * bopcamping-5r5m — nút "Gửi lịch qua email" của admin. Mail này là NỘI BỘ cho shipper:
 * có SĐT/địa chỉ khách, tiền cần thu và ghi chú shipper. Xem prd_shipper_delivery_ops FR-4.
 */
class ShipperScheduleMailTest extends TestCase
{
    use RefreshDatabase;

    private const DATE = '2030-10-05';

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function shipper(string $name = 'Shipper A', ?string $email = 'shipper@example.com'): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,   // null → model tự điền email tạm @bopcamping.local
            'is_shipper' => true,
        ]);
    }

    private function order(array $attrs = []): Order
    {
        return Order::create(array_merge([
            'code' => 'BOP-'.strtoupper(uniqid()),
            'customer_name' => 'Nguyễn Khách', 'customer_phone' => '0977000111',
            'customer_address' => '12 Ngõ 5, Hà Nội',
            'start_date' => self::DATE, 'end_date' => '2030-10-07',
            'status' => 'confirmed', 'payment_method' => 'cod',
            'total_price' => 100000, 'deposit_total' => 50000,
        ], $attrs));
    }

    /** @test */
    public function admin_sends_the_day_schedule_to_one_shipper(): void
    {
        Mail::fake();
        $shipper = $this->shipper();
        $this->order(['pickup_shipper_id' => $shipper->id, 'confirmed_pickup_time' => '09:00']);

        $this->actingAs($this->admin())
            ->post(route('admin.schedule.email'), ['date' => self::DATE, 'shipper_id' => $shipper->id])
            ->assertSessionHasNoErrors();

        Mail::assertQueued(ShipperScheduleMail::class, fn (ShipperScheduleMail $mail) => $mail->hasTo('shipper@example.com')
            && $mail->shipper->id === $shipper->id
            && count($mail->pickups) === 1);
    }

    /** @test */
    public function mail_contains_what_the_shipper_needs_on_the_road(): void
    {
        $shipper = $this->shipper();
        $this->order([
            'pickup_shipper_id' => $shipper->id,
            'confirmed_pickup_time' => '14:30',
            'schedule_note' => 'Gọi trước 15 phút',
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.schedule.email'), ['date' => self::DATE, 'shipper_id' => $shipper->id]);

        // Render trực tiếp để kiểm nội dung (mail là ShouldQueue nên không gửi ngay).
        $mail = new ShipperScheduleMail(
            $shipper,
            Carbon::parse(self::DATE),
            app(DeliveryScheduleService::class)
                ->legOrders('pickup', Carbon::parse(self::DATE), $shipper->id)
                ->map(fn (Order $o) => app(DeliveryScheduleService::class)->row($o, 'pickup'))
                ->values()->all(),
            [],
        );
        $html = $mail->render();

        $this->assertStringContainsString('14:30', $html);
        $this->assertStringContainsString('Nguyễn Khách', $html);
        $this->assertStringContainsString('0977000111', $html);
        $this->assertStringContainsString('12 Ngõ 5, Hà Nội', $html);
        $this->assertStringContainsString('Gọi trước 15 phút', $html);   // ghi chú nội bộ CÓ trong mail shipper
        $this->assertStringContainsString('Thu khi giao', $html);
    }

    /** @test */
    public function shipper_without_legs_that_day_gets_no_mail(): void
    {
        Mail::fake();
        $shipper = $this->shipper();
        // Đơn của ngày khác.
        $this->order(['pickup_shipper_id' => $shipper->id, 'start_date' => '2030-10-09', 'end_date' => '2030-10-10']);

        $this->actingAs($this->admin())
            ->post(route('admin.schedule.email'), ['date' => self::DATE, 'shipper_id' => $shipper->id])
            ->assertSessionHasErrors('message');

        Mail::assertNothingQueued();
    }

    /** @test */
    public function shipper_with_placeholder_email_is_reported_not_mailed(): void
    {
        Mail::fake();
        $shipper = $this->shipper('Không Email', null);   // email tạm @bopcamping.local
        $this->order(['pickup_shipper_id' => $shipper->id]);

        $this->actingAs($this->admin())
            ->post(route('admin.schedule.email'), ['date' => self::DATE, 'shipper_id' => $shipper->id])
            ->assertSessionHasErrors('message');

        Mail::assertNothingQueued();
    }

    /** @test */
    public function sending_to_all_covers_only_shippers_with_legs(): void
    {
        Mail::fake();
        $busy = $this->shipper('Có việc', 'busy@example.com');
        $idle = $this->shipper('Rảnh', 'idle@example.com');
        $this->order(['pickup_shipper_id' => $busy->id]);

        $this->actingAs($this->admin())
            ->post(route('admin.schedule.email'), ['date' => self::DATE])
            ->assertSessionHasNoErrors();

        Mail::assertQueued(ShipperScheduleMail::class, fn ($m) => $m->hasTo('busy@example.com'));
        Mail::assertNotQueued(ShipperScheduleMail::class, fn ($m) => $m->hasTo('idle@example.com'));
        Mail::assertQueuedCount(1);
        unset($idle);
    }

    /** @test */
    public function sending_to_all_when_nobody_has_legs_reports_back(): void
    {
        Mail::fake();
        $this->shipper();

        $this->actingAs($this->admin())
            ->post(route('admin.schedule.email'), ['date' => self::DATE])
            ->assertSessionHasErrors('message');

        Mail::assertNothingQueued();
    }

    /** @test */
    public function collect_leg_is_included_for_the_assigned_shipper(): void
    {
        Mail::fake();
        $shipper = $this->shipper();
        $this->order([
            'start_date' => '2030-10-01', 'end_date' => self::DATE,
            'status' => 'renting', 'return_shipper_id' => $shipper->id,
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.schedule.email'), ['date' => self::DATE, 'shipper_id' => $shipper->id])
            ->assertSessionHasNoErrors();

        Mail::assertQueued(ShipperScheduleMail::class, fn (ShipperScheduleMail $m) => count($m->returns) === 1 && $m->pickups === []);
    }

    /** @test */
    public function non_admin_cannot_send_schedule_emails(): void
    {
        Mail::fake();
        $shipper = $this->shipper();
        $this->order(['pickup_shipper_id' => $shipper->id]);

        $this->actingAs($shipper)
            ->post(route('admin.schedule.email'), ['date' => self::DATE, 'shipper_id' => $shipper->id])
            ->assertRedirect(route('admin.login'));

        Mail::assertNothingQueued();
    }
}

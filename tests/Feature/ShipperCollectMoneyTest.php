<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-lvw3 — shipper thu hộ tiền thuê / cọc và trả cọc lại cho khách.
 * Không cần admin uỷ quyền riêng (chốt 30/07): khoản nào chưa thu thì shipper thu được,
 * nhưng CHỈ trên đơn được gán cho mình (OWASP A01 / CWE-639).
 */
class ShipperCollectMoneyTest extends TestCase
{
    use RefreshDatabase;

    private function shipper(): User
    {
        return User::factory()->create(['is_shipper' => true]);
    }

    private function order(array $attrs = []): Order
    {
        return Order::create(array_merge([
            'code' => 'BOP-'.strtoupper(uniqid()),
            'customer_name' => 'Khách', 'customer_phone' => '0900000000',
            'start_date' => now()->toDateString(), 'end_date' => now()->addDays(2)->toDateString(),
            'status' => 'confirmed', 'payment_method' => 'cod',
            'total_price' => 300000, 'deposit_total' => 200000,
        ], $attrs));
    }

    /** @test */
    public function shipper_collects_each_amount_independently_and_is_recorded_as_collector(): void
    {
        $me = $this->shipper();
        $order = $this->order(['pickup_shipper_id' => $me->id]);

        $this->actingAs($me)->patch(route('shipper.orders.collect', ['order' => $order->id, 'kind' => 'rental']))
            ->assertSessionHasNoErrors();

        $fresh = $order->fresh();
        $this->assertTrue($fresh->rentalPaid());
        $this->assertFalse($fresh->depositPaid());
        $this->assertSame($me->id, $fresh->rental_paid_by, 'phải ghi shipper nào thu để đối soát');

        $this->actingAs($me)->patch(route('shipper.orders.collect', ['order' => $order->id, 'kind' => 'deposit']))
            ->assertSessionHasNoErrors();

        $fresh = $order->fresh();
        $this->assertTrue($fresh->depositPaid());
        $this->assertSame($me->id, $fresh->deposit_paid_by);
        $this->assertSame('full', $fresh->payment_status);
    }

    /** @test */
    public function collecting_an_already_paid_amount_is_rejected(): void
    {
        $me = $this->shipper();
        $admin = User::factory()->create(['is_admin' => true]);
        $order = $this->order(['pickup_shipper_id' => $me->id]);
        $order->markPaid('rental', true, $admin->id);   // admin đã thu trước

        $this->actingAs($me)->patch(route('shipper.orders.collect', ['order' => $order->id, 'kind' => 'rental']))
            ->assertSessionHasErrors('payment');

        // Không được ghi đè người thu ban đầu.
        $this->assertSame($admin->id, $order->fresh()->rental_paid_by);
    }

    /** @test */
    public function the_return_leg_shipper_can_also_collect_money(): void
    {
        // Tiền thuê có thể mới thu đúng lúc đi thu đồ → được gán 1 trong 2 lượt là đủ.
        $me = $this->shipper();
        $order = $this->order(['status' => 'renting', 'return_shipper_id' => $me->id]);

        $this->actingAs($me)->patch(route('shipper.orders.collect', ['order' => $order->id, 'kind' => 'rental']))
            ->assertSessionHasNoErrors();

        $this->assertTrue($order->fresh()->rentalPaid());
    }

    /** @test */
    public function shipper_cannot_collect_on_someone_elses_order(): void
    {
        $me = $this->shipper();
        $other = $this->shipper();
        $order = $this->order(['pickup_shipper_id' => $other->id]);

        $this->actingAs($me)->patch(route('shipper.orders.collect', ['order' => $order->id, 'kind' => 'rental']))
            ->assertNotFound();

        $this->assertFalse($order->fresh()->rentalPaid());
    }

    /** @test */
    public function unknown_payment_kind_is_rejected(): void
    {
        $me = $this->shipper();
        $order = $this->order(['pickup_shipper_id' => $me->id]);

        $this->actingAs($me)->patch(route('shipper.orders.collect', ['order' => $order->id, 'kind' => 'bogus']))
            ->assertNotFound();

        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    /** @test */
    public function cancelled_order_cannot_be_collected(): void
    {
        $me = $this->shipper();
        $order = $this->order(['status' => 'cancelled', 'pickup_shipper_id' => $me->id]);

        $this->actingAs($me)->patch(route('shipper.orders.collect', ['order' => $order->id, 'kind' => 'rental']))
            ->assertSessionHasErrors('payment');

        $this->assertFalse($order->fresh()->rentalPaid());
    }

    /** @test */
    public function pending_order_cannot_be_collected_by_shipper_but_admin_can_still_record(): void
    {
        // Đơn chưa xác nhận: giá/lịch còn có thể đổi, thậm chí huỷ → shipper KHÔNG thu hộ.
        $me = $this->shipper();
        $admin = User::factory()->create(['is_admin' => true]);
        $order = $this->order(['status' => 'pending', 'pickup_shipper_id' => $me->id]);

        $this->actingAs($me)->patch(route('shipper.orders.collect', ['order' => $order->id, 'kind' => 'rental']))
            ->assertSessionHasErrors('payment');
        $this->assertFalse($order->fresh()->rentalPaid());

        // Nhưng admin vẫn ghi nhận được khi khách chuyển khoản trước — việc của admin.
        $this->actingAs($admin)->patch(route('admin.orders.payment', $order), ['kind' => 'rental', 'paid' => true])
            ->assertSessionHasNoErrors();
        $this->assertTrue($order->fresh()->rentalPaid());
    }

    /** @test */
    public function schedule_payload_no_longer_exposes_the_misleading_payment_status(): void
    {
        // payment_status 3 mức không phân biệt được đã thu tiền thuê hay đã thu cọc → bỏ khỏi
        // payload để không ai dựng nhãn sai từ nó nữa (review 31/07).
        $me = $this->shipper();
        $order = $this->order(['pickup_shipper_id' => $me->id]);
        $order->markPaid('rental', true, $me->id);

        $this->actingAs($me)->get(route('shipper.schedule'))
            ->assertInertia(fn ($page) => $page
                ->missing('pickups.0.payment_status')
                ->where('pickups.0.rental_paid', true)
                ->where('pickups.0.deposit_paid', false));
    }

    /** @test */
    public function return_shipper_refunds_the_deposit_with_a_deduction_note(): void
    {
        $me = $this->shipper();
        $order = $this->order(['status' => 'renting', 'return_shipper_id' => $me->id]);

        $this->actingAs($me)->patch(route('shipper.orders.refund', $order), [
            'deposit_refund_note' => 'Trừ 50k do rách lều',
        ])->assertSessionHasNoErrors();

        $fresh = $order->fresh();
        $this->assertSame('refunded', $fresh->deposit_refund_status);
        $this->assertSame('Trừ 50k do rách lều', $fresh->deposit_refund_note);
    }

    /** @test */
    public function refund_is_rejected_twice_and_for_the_wrong_leg_or_status(): void
    {
        $me = $this->shipper();
        $other = $this->shipper();

        // Lần 2 → chặn.
        $order = $this->order(['status' => 'renting', 'return_shipper_id' => $me->id]);
        $this->actingAs($me)->patch(route('shipper.orders.refund', $order))->assertSessionHasNoErrors();
        $this->actingAs($me)->patch(route('shipper.orders.refund', $order))->assertSessionHasErrors('refund');

        // Chỉ được gán lượt GIAO → không hoàn cọc được.
        $pickupOnly = $this->order(['status' => 'renting', 'pickup_shipper_id' => $me->id]);
        $this->actingAs($me)->patch(route('shipper.orders.refund', $pickupOnly))->assertNotFound();

        // Đơn của người khác → 404.
        $notMine = $this->order(['status' => 'renting', 'return_shipper_id' => $other->id]);
        $this->actingAs($me)->patch(route('shipper.orders.refund', $notMine))->assertNotFound();

        // Đơn chưa giao (confirmed) → chặn theo trạng thái.
        $notYet = $this->order(['status' => 'confirmed', 'return_shipper_id' => $me->id]);
        $this->actingAs($me)->patch(route('shipper.orders.refund', $notYet))->assertSessionHasErrors('refund');
        $this->assertSame('pending', $notYet->fresh()->deposit_refund_status);
    }

    /** @test */
    public function schedule_uses_default_shop_hours_when_time_is_not_confirmed(): void
    {
        // Thuê trong ngày buổi chiều: mặc định 13:00–20:00 (requested_* suy lúc checkout).
        $me = $this->shipper();
        $order = $this->order([
            'end_date' => now()->toDateString(),
            'session' => 'afternoon',
            'is_half_day' => true,
            'requested_pickup_time' => '13:00',
            'requested_return_time' => '20:00',
            'pickup_shipper_id' => $me->id,
            'return_shipper_id' => $me->id,
        ]);

        $this->actingAs($me)->get(route('shipper.schedule'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('pickups.0.time', '13:00')
                ->where('pickups.0.time_is_default', true)
                // Cả hai mốc luôn có mặt để shipper biết giao/thu lúc nào.
                ->where('pickups.0.pickup_time', '13:00')
                ->where('pickups.0.return_time', '20:00')
                ->where('returns.0.time', '20:00'));

        // Chốt giờ khác → giờ đã chốt thắng và không còn bị coi là mặc định.
        $order->update(['confirmed_pickup_time' => '12:30']);
        $this->actingAs($me)->get(route('shipper.schedule'))
            ->assertInertia(fn ($page) => $page
                ->where('pickups.0.time', '12:30')
                ->where('pickups.0.time_is_default', false));
    }

    /** @test */
    public function orders_with_default_hours_sort_by_that_hour_not_last(): void
    {
        $me = $this->shipper();
        $today = now()->toDateString();

        // 08:00 là giờ MẶC ĐỊNH (chưa chốt) — phải xếp trước đơn đã chốt 10:00.
        $this->order([
            'code' => 'BOP-DEFAULT8', 'end_date' => $today, 'session' => 'morning',
            'requested_pickup_time' => '08:00', 'pickup_shipper_id' => $me->id,
        ]);
        $this->order([
            'code' => 'BOP-FIXED10', 'confirmed_pickup_time' => '10:00', 'pickup_shipper_id' => $me->id,
        ]);
        // Chưa xác nhận → không có giờ mặc định nào → xuống cuối.
        $this->order(['code' => 'BOP-NOTIME', 'status' => 'pending', 'pickup_shipper_id' => $me->id]);

        $this->actingAs($me)->get(route('shipper.schedule'))
            ->assertInertia(fn ($page) => $page
                ->where('pickups.0.code', 'BOP-DEFAULT8')
                ->where('pickups.1.code', 'BOP-FIXED10')
                ->where('pickups.2.code', 'BOP-NOTIME'));
    }

    /** @test */
    public function confirmed_multi_day_order_falls_back_to_shop_wide_hours(): void
    {
        // Đơn nhiều ngày không suy được giờ từ buổi → đã xác nhận thì giao 08:00 / thu 21:00
        // (chủ shop chốt 30/07). Đơn còn pending KHÔNG được áp mặc định này.
        $me = $this->shipper();
        $today = now()->toDateString();

        $this->order([
            'code' => 'BOP-MULTI', 'start_date' => $today, 'end_date' => $today,
            'status' => 'confirmed', 'pickup_shipper_id' => $me->id, 'return_shipper_id' => $me->id,
        ]);

        $this->actingAs($me)->get(route('shipper.schedule'))
            ->assertInertia(fn ($page) => $page
                ->where('pickups.0.time', '08:00')
                ->where('pickups.0.time_is_default', true)
                ->where('returns.0.time', '21:00')
                ->where('returns.0.time_is_default', true));
    }

    /** @test */
    public function pending_order_has_no_default_time_at_all(): void
    {
        $me = $this->shipper();

        $this->order(['status' => 'pending', 'pickup_shipper_id' => $me->id]);

        $this->actingAs($me)->get(route('shipper.schedule'))
            ->assertInertia(fn ($page) => $page
                ->where('pickups.0.time', null)
                ->where('pickups.0.time_is_default', false));
    }

    /** @test */
    public function guests_and_non_shippers_cannot_collect(): void
    {
        $shipper = $this->shipper();
        $order = $this->order(['pickup_shipper_id' => $shipper->id]);

        $this->patch(route('shipper.orders.collect', ['order' => $order->id, 'kind' => 'rental']))
            ->assertRedirect(route('shipper.login'));
        $this->actingAs(User::factory()->create())
            ->patch(route('shipper.orders.collect', ['order' => $order->id, 'kind' => 'rental']))
            ->assertRedirect(route('shipper.login'));

        $this->assertFalse($order->fresh()->rentalPaid());
    }

    /** @test */
    public function schedule_page_shows_month_grid_and_money_state_for_own_orders(): void
    {
        $me = $this->shipper();
        $order = $this->order(['pickup_shipper_id' => $me->id, 'confirmed_pickup_time' => '09:00']);
        $order->markPaid('deposit', true, $me->id);

        $this->actingAs($me)->get(route('shipper.schedule'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Shipper/Schedule')
                ->where('month', now()->format('Y-m'))
                ->has('days', 1)
                ->where('days.0.pickups', 1)
                ->where('pickups.0.rental_due', 300000)
                ->where('pickups.0.rental_paid', false)
                ->where('pickups.0.deposit_paid', true));
    }
}

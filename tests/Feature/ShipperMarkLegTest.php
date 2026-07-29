<?php

namespace Tests\Feature;

use App\Mail\OrderStatusMail;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * bopcamping-w2yl — shipper tự đánh dấu ĐÃ GIAO / ĐÃ THU trên đơn ĐƯỢC GÁN CHO MÌNH.
 * Trọng tâm: uỷ quyền theo bản ghi (chống IDOR — OWASP A01 / CWE-639) và đi đúng luồng
 * trạng thái sẵn có để khách vẫn nhận mail. Xem prd_shipper_delivery_ops FR-3.
 */
class ShipperMarkLegTest extends TestCase
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
            'customer_email' => 'khach@example.com',
            'start_date' => now()->toDateString(), 'end_date' => now()->addDays(2)->toDateString(),
            'status' => 'confirmed', 'payment_method' => 'cod',
            'total_price' => 100000, 'deposit_total' => 50000,
        ], $attrs));
    }

    /** @test */
    public function assigned_shipper_marks_delivered_and_customer_is_notified(): void
    {
        Mail::fake();
        $me = $this->shipper();
        $order = $this->order(['pickup_shipper_id' => $me->id]);

        $this->actingAs($me)->patch(route('shipper.orders.delivered', $order))
            ->assertSessionHasNoErrors();

        $this->assertSame('renting', $order->fresh()->status);
    }

    /** @test */
    public function assigned_shipper_marks_collected_and_customer_is_notified(): void
    {
        Mail::fake();
        $me = $this->shipper();
        $order = $this->order(['status' => 'renting', 'return_shipper_id' => $me->id]);

        $this->actingAs($me)->patch(route('shipper.orders.collected', $order))
            ->assertSessionHasNoErrors();

        $this->assertSame('returned', $order->fresh()->status);
        // Đi qua luồng status sẵn có ⇒ OrderObserver vẫn gửi mail "đã hoàn tất" cho khách.
        Mail::assertQueued(OrderStatusMail::class);
    }

    /** @test */
    public function shipper_cannot_touch_an_order_assigned_to_someone_else(): void
    {
        $me = $this->shipper();
        $other = $this->shipper();
        $notMine = $this->order(['pickup_shipper_id' => $other->id]);

        // 404 (không phải 403) để không tiết lộ đơn đó có tồn tại.
        $this->actingAs($me)->patch(route('shipper.orders.delivered', $notMine))->assertNotFound();

        $this->assertSame('confirmed', $notMine->fresh()->status);
    }

    /** @test */
    public function shipper_cannot_touch_an_unassigned_order(): void
    {
        $me = $this->shipper();
        $free = $this->order();

        $this->actingAs($me)->patch(route('shipper.orders.delivered', $free))->assertNotFound();

        $this->assertSame('confirmed', $free->fresh()->status);
    }

    /** @test */
    public function the_other_leg_does_not_grant_permission(): void
    {
        $me = $this->shipper();
        // Tôi chỉ được gán lượt THU, không được đánh dấu đã GIAO.
        $order = $this->order(['return_shipper_id' => $me->id]);

        $this->actingAs($me)->patch(route('shipper.orders.delivered', $order))->assertNotFound();

        $this->assertSame('confirmed', $order->fresh()->status);
    }

    /** @test */
    public function pending_order_cannot_be_marked_delivered_by_shipper(): void
    {
        $me = $this->shipper();
        // Đơn chưa được shop xác nhận — shipper không xác nhận thay admin.
        $order = $this->order(['status' => 'pending', 'pickup_shipper_id' => $me->id]);

        $this->actingAs($me)->patch(route('shipper.orders.delivered', $order))
            ->assertSessionHasErrors('status');

        $this->assertSame('pending', $order->fresh()->status);
    }

    /** @test */
    public function marking_delivered_twice_is_rejected(): void
    {
        $me = $this->shipper();
        $order = $this->order(['pickup_shipper_id' => $me->id]);

        $this->actingAs($me)->patch(route('shipper.orders.delivered', $order))->assertSessionHasNoErrors();
        $this->actingAs($me)->patch(route('shipper.orders.delivered', $order))->assertSessionHasErrors('status');

        $this->assertSame('renting', $order->fresh()->status);
    }

    /** @test */
    public function collected_requires_the_order_to_be_renting(): void
    {
        $me = $this->shipper();
        $order = $this->order(['status' => 'confirmed', 'return_shipper_id' => $me->id]);

        $this->actingAs($me)->patch(route('shipper.orders.collected', $order))
            ->assertSessionHasErrors('status');

        $this->assertSame('confirmed', $order->fresh()->status);
    }

    /** @test */
    public function guests_and_non_shippers_cannot_mark_anything(): void
    {
        $shipper = $this->shipper();
        $order = $this->order(['pickup_shipper_id' => $shipper->id]);

        $this->patch(route('shipper.orders.delivered', $order))->assertRedirect(route('shipper.login'));
        $this->actingAs(User::factory()->create())->patch(route('shipper.orders.delivered', $order))
            ->assertRedirect(route('shipper.login'));
        // Admin cũng không lọt vào khu vực shipper nếu không có cờ shipper.
        $this->actingAs(User::factory()->create(['is_admin' => true]))->patch(route('shipper.orders.delivered', $order))
            ->assertRedirect(route('shipper.login'));

        $this->assertSame('confirmed', $order->fresh()->status);
    }
}

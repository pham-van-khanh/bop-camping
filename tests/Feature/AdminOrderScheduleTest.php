<?php

namespace Tests\Feature;

use App\Mail\OrderScheduleConfirmedMail;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * bopcamping-5xir — admin chốt/sửa giờ giao + giờ thu của đơn (không phải giờ khách xin),
 * kèm ghi chú nội bộ cho shipper. Xem prd_delivery_schedule.md FR-1..FR-3.
 */
class AdminOrderScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $attrs = []): Order
    {
        return Order::create(array_merge([
            'customer_name' => 'Khách', 'customer_phone' => '0900000001',
            'customer_email' => 'khach@example.com',
            'start_date' => '2030-07-10', 'end_date' => '2030-07-12',
            'total_price' => 300000, 'deposit_total' => 200000,
            'status' => 'confirmed', 'payment_method' => 'cod',
        ], $attrs));
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /** @test */
    public function admin_chot_gio_giao_va_thu(): void
    {
        Mail::fake();
        $order = $this->order();
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.orders.schedule', $order), [
            'confirmed_pickup_time' => '14:30',
            'confirmed_return_time' => '09:00',
        ])->assertSessionHasNoErrors();

        $fresh = $order->fresh();
        $this->assertSame('14:30', $fresh->confirmed_pickup_time);
        $this->assertSame('09:00', $fresh->confirmed_return_time);
        $this->assertNotNull($fresh->schedule_confirmed_at);
        Mail::assertQueued(OrderScheduleConfirmedMail::class);
    }

    /** @test */
    public function gio_khach_xin_khong_bi_ghi_de(): void
    {
        Mail::fake();
        $order = $this->order([
            'requested_pickup_time' => '08:00',
            'requested_return_time' => '18:00',
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.orders.schedule', $order), [
            'confirmed_pickup_time' => '14:30',
            'confirmed_return_time' => '09:00',
        ])->assertSessionHasNoErrors();

        $fresh = $order->fresh();
        $this->assertSame('08:00', $fresh->requested_pickup_time);
        $this->assertSame('18:00', $fresh->requested_return_time);
        $this->assertSame('14:30', $fresh->confirmed_pickup_time);
    }

    /** @test */
    public function gio_sai_dinh_dang_bi_chan(): void
    {
        Mail::fake();
        $order = $this->order();
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.orders.schedule', $order), [
            'confirmed_pickup_time' => '25:00',
        ])->assertSessionHasErrors('confirmed_pickup_time');

        $this->assertNull($order->fresh()->confirmed_pickup_time);
        Mail::assertNothingQueued();
    }

    /** @test */
    public function don_cung_ngay_gio_thu_phai_sau_gio_giao(): void
    {
        Mail::fake();
        $order = $this->order(['start_date' => '2030-07-10', 'end_date' => '2030-07-10']);
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.orders.schedule', $order), [
            'confirmed_pickup_time' => '14:30',
            'confirmed_return_time' => '09:00',
        ])->assertSessionHasErrors('confirmed_return_time');

        $this->assertNull($order->fresh()->confirmed_pickup_time);
        Mail::assertNothingQueued();
    }

    /** @test */
    public function don_cha_bi_chan(): void
    {
        Mail::fake();
        $order = $this->order(['is_parent' => true]);
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.orders.schedule', $order), [
            'confirmed_pickup_time' => '14:30',
        ])->assertSessionHasErrors('confirmed_pickup_time');

        $this->assertNull($order->fresh()->confirmed_pickup_time);
        Mail::assertNothingQueued();
    }

    /** @test */
    public function don_da_tra_bi_chan(): void
    {
        Mail::fake();
        $order = $this->order(['status' => 'returned']);
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.orders.schedule', $order), [
            'confirmed_pickup_time' => '14:30',
        ])->assertSessionHasErrors('confirmed_pickup_time');

        $this->assertNull($order->fresh()->confirmed_pickup_time);
        Mail::assertNothingQueued();
    }

    /** @test */
    public function don_da_huy_bi_chan(): void
    {
        Mail::fake();
        $order = $this->order(['status' => 'cancelled']);
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.orders.schedule', $order), [
            'confirmed_pickup_time' => '14:30',
        ])->assertSessionHasErrors('confirmed_pickup_time');

        $this->assertNull($order->fresh()->confirmed_pickup_time);
        Mail::assertNothingQueued();
    }

    /** @test */
    public function xoa_gio_ve_null(): void
    {
        Mail::fake();
        $order = $this->order([
            'confirmed_pickup_time' => '14:30',
            'confirmed_return_time' => '09:00',
            'schedule_confirmed_at' => now(),
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.orders.schedule', $order), [
            'confirmed_pickup_time' => null,
            'confirmed_return_time' => null,
        ])->assertSessionHasNoErrors();

        $fresh = $order->fresh();
        $this->assertNull($fresh->confirmed_pickup_time);
        $this->assertNull($fresh->confirmed_return_time);
        $this->assertNull($fresh->schedule_confirmed_at);
        // Huỷ chốt giờ → KHÔNG gửi mail "đã chốt giờ" rỗng nghĩa (admin gọi khách như cũ).
        Mail::assertNothingQueued();
    }

    /** @test */
    public function sua_moi_ghi_chu_khong_gui_mail(): void
    {
        Mail::fake();
        $order = $this->order([
            'confirmed_pickup_time' => '14:30',
            'confirmed_return_time' => '09:00',
            'schedule_confirmed_at' => now(),
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.orders.schedule', $order), [
            'confirmed_pickup_time' => '14:30',
            'confirmed_return_time' => '09:00',
            'schedule_note' => 'Gọi trước 15 phút, nhà cuối hẻm',
        ])->assertSessionHasNoErrors();

        $fresh = $order->fresh();
        $this->assertSame('Gọi trước 15 phút, nhà cuối hẻm', $fresh->schedule_note);
        $this->assertSame('14:30', $fresh->confirmed_pickup_time);
        Mail::assertNothingQueued();
    }

    /** @test */
    public function don_khong_co_email_hop_le_khong_gui_mail(): void
    {
        Mail::fake();
        $order = $this->order(['customer_email' => '0900000001@bopcamping.local']);
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.orders.schedule', $order), [
            'confirmed_pickup_time' => '14:30',
            'confirmed_return_time' => '09:00',
        ])->assertSessionHasNoErrors();

        $fresh = $order->fresh();
        $this->assertSame('14:30', $fresh->confirmed_pickup_time);
        Mail::assertNothingQueued();
    }

    /** @test */
    public function non_admin_cannot_update_schedule(): void
    {
        $order = $this->order();
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->patch(route('admin.orders.schedule', $order), [
            'confirmed_pickup_time' => '14:30',
        ])->assertRedirect(route('admin.login'));

        $this->assertNull($order->fresh()->confirmed_pickup_time);
    }
}

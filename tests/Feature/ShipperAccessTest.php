<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-lsch — ranh giới khu vực /shipper/* và /admin/*, và giới hạn dữ liệu:
 * shipper CHỈ thấy đơn được gán cho chính mình (adr_shipper_role_and_access mục 3,
 * OWASP A01 Broken Access Control).
 */
class ShipperAccessTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $attrs = []): Order
    {
        return Order::create(array_merge([
            'code' => 'BOP-'.strtoupper(uniqid()),
            'customer_name' => 'Khách', 'customer_phone' => '0900000000', 'customer_address' => '12 Đường ABC',
            'start_date' => now()->toDateString(), 'end_date' => now()->addDays(2)->toDateString(),
            'status' => 'confirmed', 'payment_method' => 'cod',
            'total_price' => 100000, 'deposit_total' => 50000,
        ], $attrs));
    }

    /** @test */
    public function guest_is_redirected_to_shipper_login(): void
    {
        $this->get(route('shipper.schedule'))->assertRedirect(route('shipper.login'));
    }

    /** @test */
    public function plain_user_and_admin_without_shipper_flag_are_blocked(): void
    {
        $this->actingAs(User::factory()->create())->get(route('shipper.schedule'))
            ->assertRedirect(route('shipper.login'));

        // Admin KHÔNG tự động là shipper (quyết định trong ADR mục 3).
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get(route('shipper.schedule'))
            ->assertRedirect(route('shipper.login'));
    }

    /** @test */
    public function shipper_cannot_reach_admin_area(): void
    {
        $shipper = User::factory()->create(['is_shipper' => true]);

        $this->actingAs($shipper)->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->actingAs($shipper)->get(route('admin.schedule'))->assertRedirect(route('admin.login'));
        $this->actingAs($shipper)->get(route('admin.orders'))->assertRedirect(route('admin.login'));
    }

    /** @test */
    public function shipper_sees_only_orders_assigned_to_self(): void
    {
        $me = User::factory()->create(['is_shipper' => true]);
        $other = User::factory()->create(['is_shipper' => true]);

        $mine = $this->order(['code' => 'BOP-MINE', 'pickup_shipper_id' => $me->id, 'confirmed_pickup_time' => '09:00']);
        $this->order(['code' => 'BOP-OTHER', 'pickup_shipper_id' => $other->id]);
        $this->order(['code' => 'BOP-NOONE']);   // chưa gán ai

        $this->actingAs($me)->get(route('shipper.schedule'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Shipper/Schedule')
                ->has('pickups', 1)
                ->where('pickups.0.code', $mine->code)
                ->where('pickups.0.time', '09:00'));
    }

    /** @test */
    public function pickup_and_return_legs_are_kept_separate(): void
    {
        $me = User::factory()->create(['is_shipper' => true]);
        $other = User::factory()->create(['is_shipper' => true]);
        $today = now()->toDateString();

        // Tôi đi THU đơn này hôm nay; người khác đi GIAO nó (ngày khác).
        $this->order([
            'code' => 'BOP-COLLECT',
            'start_date' => now()->subDays(2)->toDateString(), 'end_date' => $today,
            'status' => 'renting',
            'pickup_shipper_id' => $other->id, 'return_shipper_id' => $me->id,
        ]);

        $this->actingAs($me)->get(route('shipper.schedule', ['date' => $today]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('pickups', 0)
                ->has('returns', 1)
                ->where('returns.0.code', 'BOP-COLLECT'));
    }

    /** @test */
    public function date_is_clamped_to_allowed_window_and_invalid_input_falls_back(): void
    {
        $me = User::factory()->create(['is_shipper' => true]);

        // Quá xa trong tương lai → kẹp về +14 ngày; quá khứ xa → kẹp về −2 ngày.
        $this->actingAs($me)->get(route('shipper.schedule', ['date' => now()->addYear()->toDateString()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('date', now()->addDays(14)->toDateString()));

        $this->actingAs($me)->get(route('shipper.schedule', ['date' => now()->subYear()->toDateString()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('date', now()->subDays(2)->toDateString()));

        $this->actingAs($me)->get(route('shipper.schedule', ['date' => 'khong-phai-ngay']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('date', now()->toDateString()));
    }

    /** @test */
    public function shared_props_expose_shipper_flag(): void
    {
        $this->actingAs(User::factory()->create(['is_shipper' => true]))->get(route('shipper.schedule'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.is_shipper', true));
    }
}

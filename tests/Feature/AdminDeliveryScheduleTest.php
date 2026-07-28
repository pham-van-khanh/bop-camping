<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-rtkh — trang "Lịch giao" (/admin/lich-giao) cho shipper: lịch THÁNG (ngày có
 * đơn bôi đỏ, ngày đã qua khoá) + bấm 1 ngày ra danh sách cần giao/thu, sắp theo giờ chốt.
 * Xem plan_delivery_schedule.md mục 2.6 + 4, prd_delivery_schedule.md FR-5.
 */
class AdminDeliveryScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function order(array $attrs = []): Order
    {
        return Order::create(array_merge([
            'code' => 'BOP-'.strtoupper(uniqid()),
            'customer_name' => 'Khách', 'customer_phone' => '0900000000', 'customer_address' => '12 Đường ABC',
            'start_date' => '2030-08-01', 'end_date' => '2030-08-01',
            'status' => 'confirmed', 'payment_method' => 'cod',
            'total_price' => 100000, 'deposit_total' => 50000,
        ], $attrs));
    }

    /** @test */
    public function default_date_is_today_and_renders_page(): void
    {
        $this->travelTo(Carbon::parse('2030-08-01'));
        $this->order(['start_date' => '2030-08-01', 'end_date' => '2030-08-03', 'status' => 'confirmed', 'confirmed_pickup_time' => '09:00']);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.schedule'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/DeliverySchedule')
                ->where('date', '2030-08-01')
                ->where('today', '2030-08-01')
                ->has('pickups', 1)
                ->where('pickups.0.time', '09:00'));
    }

    /** @test */
    public function orders_are_grouped_correctly_and_parent_cancelled_returned_are_excluded(): void
    {
        $date = '2030-08-05';

        // Cần giao hôm đó — status pending, hợp lệ.
        $pickup = $this->order(['code' => 'BOP-PICKUP', 'start_date' => $date, 'end_date' => '2030-08-07', 'status' => 'pending']);
        // Cần thu hôm đó — status renting, hợp lệ.
        $return = $this->order(['code' => 'BOP-RETURN', 'start_date' => '2030-08-02', 'end_date' => $date, 'status' => 'renting']);
        // Đơn cha: KHÔNG lên lịch (chốt giờ/giao thu ở đơn con).
        $this->order(['code' => 'BOP-PARENT', 'is_parent' => true, 'start_date' => $date, 'end_date' => $date, 'status' => 'confirmed']);
        // Đơn huỷ khớp ngày giao — bị loại.
        $this->order(['code' => 'BOP-CANCEL', 'start_date' => $date, 'end_date' => $date, 'status' => 'cancelled']);
        // Đơn đã trả khớp ngày thu — bị loại.
        $this->order(['code' => 'BOP-RETURNED', 'start_date' => '2030-08-02', 'end_date' => $date, 'status' => 'returned']);

        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.schedule', ['date' => $date]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('pickups', 1)
                ->where('pickups.0.code', $pickup->code)
                ->has('returns', 1)
                ->where('returns.0.code', $return->code));
    }

    /** @test */
    public function unscheduled_orders_sort_last_after_confirmed_time_ascending(): void
    {
        $date = '2030-08-10';

        $this->order(['code' => 'BOP-A1', 'start_date' => $date, 'end_date' => $date, 'status' => 'confirmed', 'confirmed_pickup_time' => null]);
        $this->order(['code' => 'BOP-B1', 'start_date' => $date, 'end_date' => $date, 'status' => 'confirmed', 'confirmed_pickup_time' => '10:00']);
        $this->order(['code' => 'BOP-C1', 'start_date' => $date, 'end_date' => $date, 'status' => 'confirmed', 'confirmed_pickup_time' => '08:00']);

        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.schedule', ['date' => $date]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('pickups', 3)
                ->where('pickups.0.code', 'BOP-C1')
                ->where('pickups.1.code', 'BOP-B1')
                ->where('pickups.2.code', 'BOP-A1'));
    }

    /** @test */
    public function date_query_param_changes_list_and_invalid_date_falls_back_to_today(): void
    {
        $this->travelTo(Carbon::parse('2030-08-01'));
        $other = $this->order(['start_date' => '2030-08-20', 'end_date' => '2030-08-22', 'status' => 'pending']);
        $admin = $this->admin();

        // Ngày khác → danh sách đổi, chứa đơn của ngày đó.
        $this->actingAs($admin)->get(route('admin.schedule', ['date' => '2030-08-20']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('date', '2030-08-20')
                ->has('pickups', 1)
                ->where('pickups.0.code', $other->code));

        // Ngày không hợp lệ → fallback hôm nay, KHÔNG 500.
        $this->actingAs($admin)->get(route('admin.schedule', ['date' => 'abc']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('date', '2030-08-01')
                ->where('today', '2030-08-01'));
    }

    /** @test */
    public function stats_unscheduled_counts_pickups_and_returns_without_confirmed_time(): void
    {
        $date = '2030-08-15';

        // Giao: 1 đã chốt giờ, 1 chưa. end_date SAU ngày lịch để không lẫn vào nhóm "cần thu".
        $this->order(['code' => 'BOP-P1', 'start_date' => $date, 'end_date' => '2030-08-17', 'status' => 'confirmed', 'confirmed_pickup_time' => '09:00']);
        $this->order(['code' => 'BOP-P2', 'start_date' => $date, 'end_date' => '2030-08-17', 'status' => 'pending', 'confirmed_pickup_time' => null]);
        // Thu: 1 chưa chốt giờ.
        $this->order(['code' => 'BOP-R1', 'start_date' => '2030-08-13', 'end_date' => $date, 'status' => 'renting', 'confirmed_return_time' => null]);

        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.schedule', ['date' => $date]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.pickups', 2)
                ->where('stats.returns', 1)
                ->where('stats.unscheduled', 2));
    }

    /** @test */
    public function month_grid_marks_days_that_have_orders(): void
    {
        $this->travelTo(Carbon::parse('2030-08-01'));
        // 03/08: 2 đơn cần giao · 06/08: 1 đơn cần thu (đơn thuê 03→06).
        $this->order(['code' => 'BOP-M1', 'start_date' => '2030-08-03', 'end_date' => '2030-08-06', 'status' => 'confirmed']);
        $this->order(['code' => 'BOP-M2', 'start_date' => '2030-08-03', 'end_date' => '2030-08-09', 'status' => 'pending']);
        // Đơn cha + đơn huỷ không được tính vào lịch.
        $this->order(['code' => 'BOP-M3', 'is_parent' => true, 'start_date' => '2030-08-04', 'end_date' => '2030-08-04', 'status' => 'confirmed']);
        $this->order(['code' => 'BOP-M4', 'start_date' => '2030-08-05', 'end_date' => '2030-08-05', 'status' => 'cancelled']);
        // Tháng khác — không lọt vào lưới tháng 8.
        $this->order(['code' => 'BOP-M5', 'start_date' => '2030-09-02', 'end_date' => '2030-09-03', 'status' => 'confirmed']);

        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.schedule'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('month', '2030-08')
                ->where('month_label', 'Tháng 8 · 2030')
                ->where('prev_month', '2030-07')
                ->where('next_month', '2030-09')
                // 03 (2 giao) · 06 (1 thu). Loại: 04 (đơn cha), 05 (đơn huỷ), 02/09 (khác tháng)
                // và 09/08 — ngày thu của BOP-M2 chưa tính vì đơn còn pending (chưa xác nhận).
                ->has('days', 2)
                ->where('days.0', ['date' => '2030-08-03', 'pickups' => 2, 'returns' => 0])
                ->where('days.1', ['date' => '2030-08-06', 'pickups' => 0, 'returns' => 1]));
    }

    /** @test */
    public function month_param_browses_other_month_and_invalid_month_falls_back(): void
    {
        $this->travelTo(Carbon::parse('2030-08-01'));
        $this->order(['code' => 'BOP-SEP', 'start_date' => '2030-09-10', 'end_date' => '2030-09-12', 'status' => 'confirmed']);
        $admin = $this->admin();

        // Xem tháng 9 nhưng ngày đang chọn vẫn là ngày cũ (danh sách dưới không đổi).
        $this->actingAs($admin)->get(route('admin.schedule', ['month' => '2030-09', 'date' => '2030-08-01']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('month', '2030-09')
                ->where('date', '2030-08-01')
                ->has('days', 2)
                ->where('days.0.date', '2030-09-10'));

        // Tháng không hợp lệ → tháng của ngày đang chọn, KHÔNG 500.
        $this->actingAs($admin)->get(route('admin.schedule', ['month' => 'xyz']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('month', '2030-08'));
    }

    /** @test */
    public function non_admin_is_redirected_to_admin_login(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get(route('admin.schedule'))
            ->assertRedirect(route('admin.login'));
    }
}

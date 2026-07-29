<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-yc7d — admin gán shipper cho từng LƯỢT (giao/thu), gán cả ngày cho đơn chưa
 * có người, và lọc lịch theo shipper. Xem prd_shipper_delivery_ops FR-2.
 */
class AdminShipperAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private const DATE = '2030-09-10';

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function shipper(string $name = 'Shipper A'): User
    {
        return User::factory()->create(['name' => $name, 'is_shipper' => true]);
    }

    private function order(array $attrs = []): Order
    {
        return Order::create(array_merge([
            'code' => 'BOP-'.strtoupper(uniqid()),
            'customer_name' => 'Khách', 'customer_phone' => '0900000000',
            'start_date' => self::DATE, 'end_date' => '2030-09-12',
            'status' => 'confirmed', 'payment_method' => 'cod',
            'total_price' => 100000, 'deposit_total' => 50000,
        ], $attrs));
    }

    /** @test */
    public function assigning_writes_only_the_column_of_that_leg(): void
    {
        $order = $this->order();
        $giao = $this->shipper('Giao');

        $this->actingAs($this->admin())
            ->patch(route('admin.schedule.assign', $order), ['leg' => 'pickup', 'shipper_id' => $giao->id])
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame($giao->id, $order->pickup_shipper_id);
        $this->assertNull($order->return_shipper_id, 'Gán lượt giao KHÔNG được chạm lượt thu');

        $thu = $this->shipper('Thu');
        $this->actingAs($this->admin())
            ->patch(route('admin.schedule.assign', $order), ['leg' => 'return', 'shipper_id' => $thu->id])
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame($giao->id, $order->pickup_shipper_id);
        $this->assertSame($thu->id, $order->return_shipper_id);
    }

    /** @test */
    public function assigning_null_clears_the_shipper(): void
    {
        $order = $this->order(['pickup_shipper_id' => $this->shipper()->id]);

        $this->actingAs($this->admin())
            ->patch(route('admin.schedule.assign', $order), ['leg' => 'pickup', 'shipper_id' => null])
            ->assertSessionHasNoErrors();

        $this->assertNull($order->fresh()->pickup_shipper_id);
    }

    /** @test */
    public function a_user_who_is_not_a_shipper_cannot_be_assigned(): void
    {
        $order = $this->order();
        $khach = User::factory()->create();                        // khách thường
        $adminOnly = User::factory()->create(['is_admin' => true]); // admin nhưng không có cờ shipper

        foreach ([$khach, $adminOnly] as $user) {
            $this->actingAs($this->admin())
                ->patch(route('admin.schedule.assign', $order), ['leg' => 'pickup', 'shipper_id' => $user->id])
                ->assertSessionHasErrors('shipper_id');
        }

        $this->assertNull($order->fresh()->pickup_shipper_id);
    }

    /** @test */
    public function parent_and_finished_orders_cannot_be_assigned(): void
    {
        $shipper = $this->shipper();
        $parent = $this->order(['is_parent' => true]);
        $returned = $this->order(['status' => 'returned']);
        $cancelled = $this->order(['status' => 'cancelled']);

        foreach ([$parent, $returned, $cancelled] as $order) {
            $this->actingAs($this->admin())
                ->patch(route('admin.schedule.assign', $order), ['leg' => 'pickup', 'shipper_id' => $shipper->id])
                ->assertSessionHasErrors('shipper_id');
            $this->assertNull($order->fresh()->pickup_shipper_id);
        }
    }

    /** @test */
    public function assign_all_only_touches_orders_without_a_shipper(): void
    {
        $someone = $this->shipper('Đã có người');
        $target = $this->shipper('Người mới');

        $free1 = $this->order(['code' => 'BOP-FREE1']);
        $free2 = $this->order(['code' => 'BOP-FREE2']);
        $taken = $this->order(['code' => 'BOP-TAKEN', 'pickup_shipper_id' => $someone->id]);
        // Ngày khác → ngoài phạm vi, không được chạm.
        $otherDay = $this->order(['code' => 'BOP-OTHERDAY', 'start_date' => '2030-09-11', 'end_date' => '2030-09-13']);

        $this->actingAs($this->admin())
            ->post(route('admin.schedule.assignAll'), ['leg' => 'pickup', 'date' => self::DATE, 'shipper_id' => $target->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($target->id, $free1->fresh()->pickup_shipper_id);
        $this->assertSame($target->id, $free2->fresh()->pickup_shipper_id);
        $this->assertSame($someone->id, $taken->fresh()->pickup_shipper_id, 'Không được ghi đè đơn đã gán');
        $this->assertNull($otherDay->fresh()->pickup_shipper_id, 'Không được chạm đơn ngày khác');
    }

    /** @test */
    public function list_is_ordered_by_confirmed_time_with_unscheduled_last(): void
    {
        // Không có sắp thứ tự thủ công (bỏ kéo-thả 29/07) — giờ đã chốt quyết định thứ tự.
        $this->order(['code' => 'BOP-TIME20', 'confirmed_pickup_time' => '20:00']);
        $this->order(['code' => 'BOP-NOTIME']);
        $this->order(['code' => 'BOP-TIME8', 'confirmed_pickup_time' => '08:00']);

        $this->actingAs($this->admin())->get(route('admin.schedule', ['date' => self::DATE]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('pickups.0.code', 'BOP-TIME8')
                ->where('pickups.1.code', 'BOP-TIME20')
                ->where('pickups.2.code', 'BOP-NOTIME'));
    }

    /** @test */
    public function filter_by_shipper_and_by_unassigned(): void
    {
        $me = $this->shipper('Của tôi');
        $mine = $this->order(['code' => 'BOP-MINE', 'pickup_shipper_id' => $me->id]);
        $free = $this->order(['code' => 'BOP-FREE']);

        $admin = $this->admin();

        // Lọc theo 1 shipper
        $this->actingAs($admin)->get(route('admin.schedule', ['date' => self::DATE, 'shipper' => $me->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('pickups', 1)
                ->where('pickups.0.code', $mine->code)
                ->where('pickups.0.shipper_name', 'Của tôi')
                ->where('filters.shipper', (string) $me->id)
                // lịch tháng cũng phải theo bộ lọc
                ->where('days.0.pickups', 1));

        // Lọc "chưa gán"
        $this->actingAs($admin)->get(route('admin.schedule', ['date' => self::DATE, 'shipper' => 'none']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('pickups', 1)
                ->where('pickups.0.code', $free->code)
                ->where('filters.shipper', 'none'));

        // Không lọc → thấy cả hai
        $this->actingAs($admin)->get(route('admin.schedule', ['date' => self::DATE]))
            ->assertInertia(fn (Assert $page) => $page->has('pickups', 2)->where('filters.shipper', ''));
    }

    /** @test */
    public function shippers_list_and_unassigned_count_are_exposed(): void
    {
        $this->shipper('An');
        $this->shipper('Bình');
        User::factory()->create(['name' => 'Khách thường']);
        $this->order();

        $this->actingAs($this->admin())->get(route('admin.schedule', ['date' => self::DATE]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('shippers', 2)
                ->where('shippers.0.name', 'An')
                ->where('stats.unassigned', 1));
    }

    /** @test */
    public function non_admin_cannot_assign(): void
    {
        $order = $this->order();
        $shipper = $this->shipper();

        // Ngay cả shipper cũng không được tự gán đơn cho mình.
        $this->actingAs($shipper)
            ->patch(route('admin.schedule.assign', $order), ['leg' => 'pickup', 'shipper_id' => $shipper->id])
            ->assertRedirect(route('admin.login'));
        $this->actingAs($shipper)
            ->post(route('admin.schedule.assignAll'), ['leg' => 'pickup', 'date' => self::DATE, 'shipper_id' => $shipper->id])
            ->assertRedirect(route('admin.login'));

        $this->assertNull($order->fresh()->pickup_shipper_id);
    }
}

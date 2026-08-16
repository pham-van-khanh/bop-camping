<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-3wfk — ghi dấu AI ĐÃ LÀM GÌ trên đơn: nhận tiền thuê, nhận cọc, giao đồ,
 * thu đồ, hoàn cọc. Chủ shop 31/07: "action trong admin khó để biết ai đã nhận cọc/tiền
 * thuê/hoàn cọc". Trọng tâm: dấu đúng người + đúng vai, và KHÔNG bịa khi không biết.
 */
class OrderActionAttributionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $name = 'Chủ shop'): User
    {
        return User::factory()->create(['name' => $name, 'is_admin' => true]);
    }

    private function shipper(string $name = 'Shipper An'): User
    {
        return User::factory()->create(['name' => $name, 'is_shipper' => true]);
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

    /** Lấy 1 mốc trong actionLog theo key. */
    private function entry(Order $order, string $key): array
    {
        $log = collect($order->fresh()->actionLog())->keyBy('key');

        return $log[$key];
    }

    /** @test */
    public function admin_collecting_money_records_the_admin_name(): void
    {
        $admin = $this->admin('Chủ shop');
        $order = $this->order();

        $this->actingAs($admin)->patch(route('admin.orders.payment', $order), ['kind' => 'rental', 'paid' => true]);

        $entry = $this->entry($order, 'rental_paid');
        $this->assertTrue($entry['done']);
        $this->assertSame('Chủ shop', $entry['by']);
        $this->assertArrayNotHasKey('role', $entry, 'chủ shop 31/07: chỉ cần TÊN, không ghi vai');
        $this->assertNotNull($entry['at']);
    }

    /** @test */
    public function shipper_collecting_money_records_the_shipper_name(): void
    {
        $shipper = $this->shipper('Shipper An');
        $order = $this->order(['pickup_shipper_id' => $shipper->id]);

        $this->actingAs($shipper)->patch(route('shipper.orders.collect', ['order' => $order->id, 'kind' => 'deposit']));

        $this->assertSame('Shipper An', $this->entry($order, 'deposit_paid')['by']);
    }

    /** @test */
    public function who_marked_delivered_is_recorded_for_both_admin_and_shipper(): void
    {
        // Shipper bấm đã giao.
        $shipper = $this->shipper();
        $byShipper = $this->order(['pickup_shipper_id' => $shipper->id]);
        $this->actingAs($shipper)->patch(route('shipper.orders.delivered', $byShipper));
        $this->assertSame($shipper->name, $this->entry($byShipper, 'delivered')['by']);

        // Admin bấm đã giao (đổi trạng thái trong panel).
        $admin = $this->admin();
        $byAdmin = $this->order();
        $this->actingAs($admin)->patch(route('admin.orders.update', $byAdmin), ['status' => 'renting']);
        $this->assertSame($admin->name, $this->entry($byAdmin, 'delivered')['by']);
    }

    /** @test */
    public function who_marked_collected_is_recorded(): void
    {
        $shipper = $this->shipper();
        $order = $this->order(['status' => 'renting', 'return_shipper_id' => $shipper->id]);

        $this->actingAs($shipper)->patch(route('shipper.orders.collected', $order));

        $entry = $this->entry($order, 'collected');
        $this->assertTrue($entry['done']);
        $this->assertSame($shipper->name, $entry['by']);
    }

    /** @test */
    public function who_refunded_the_deposit_is_recorded_for_both_sides(): void
    {
        // Shipper hoàn cọc tại chỗ.
        $shipper = $this->shipper('Shipper An');
        $byShipper = $this->order(['status' => 'renting', 'return_shipper_id' => $shipper->id]);
        $this->actingAs($shipper)->patch(route('shipper.orders.refund', $byShipper), ['deposit_refund_note' => 'Đủ đồ']);

        $entry = $this->entry($byShipper, 'deposit_refunded');
        $this->assertTrue($entry['done']);
        $this->assertSame('Shipper An', $entry['by']);

        // Admin hoàn cọc trong panel.
        $admin = $this->admin('Chủ shop');
        $byAdmin = $this->order(['status' => 'returned']);
        $this->actingAs($admin)->patch(route('admin.orders.refund', $byAdmin), ['deposit_refund_status' => 'refunded']);

        $this->assertSame('Chủ shop', $this->entry($byAdmin, 'deposit_refunded')['by']);
    }

    /** @test */
    public function setting_refund_back_to_pending_clears_the_stamp(): void
    {
        $admin = $this->admin();
        $order = $this->order(['status' => 'returned']);

        $this->actingAs($admin)->patch(route('admin.orders.refund', $order), ['deposit_refund_status' => 'refunded']);
        $this->actingAs($admin)->patch(route('admin.orders.refund', $order), ['deposit_refund_status' => 'pending']);

        $fresh = $order->fresh();
        $this->assertNull($fresh->deposit_refunded_at);
        $this->assertNull($fresh->deposit_refunded_by);
        $this->assertFalse($this->entry($order, 'deposit_refunded')['done']);
    }

    /** @test */
    public function the_first_actor_is_kept_when_status_is_toggled_again(): void
    {
        // Shipper giao thật; sau đó admin sửa trạng thái qua lại → vẫn còn dấu của shipper.
        $shipper = $this->shipper('Shipper An');
        $admin = $this->admin();
        $order = $this->order(['pickup_shipper_id' => $shipper->id]);

        $this->actingAs($shipper)->patch(route('shipper.orders.delivered', $order));
        $stampedAt = $order->fresh()->delivered_at;

        $this->actingAs($admin)->patch(route('admin.orders.update', $order), ['status' => 'confirmed']);
        $this->actingAs($admin)->patch(route('admin.orders.update', $order), ['status' => 'renting']);

        $fresh = $order->fresh();
        $this->assertSame($shipper->id, $fresh->delivered_by, 'không được ghi đè người giao thật');
        $this->assertEquals($stampedAt, $fresh->delivered_at);
    }

    /** @test */
    public function legacy_orders_without_a_stamp_report_unknown_instead_of_guessing(): void
    {
        // Đơn cũ (trước khi có tính năng): trạng thái nói đã giao/đã trả nhưng không có dấu.
        $order = $this->order(['status' => 'returned', 'deposit_refund_status' => 'refunded']);

        foreach (['delivered', 'collected', 'deposit_refunded'] as $key) {
            $entry = $this->entry($order, $key);
            $this->assertTrue($entry['done'], "$key phải coi là ĐÃ làm (suy từ trạng thái)");
            $this->assertNull($entry['by'], "$key không được bịa người làm");
            $this->assertNull($entry['at']);
        }
    }

    /** @test */
    public function admin_order_payload_carries_the_action_log(): void
    {
        $admin = $this->admin('Chủ shop');
        $order = $this->order();
        $this->actingAs($admin)->patch(route('admin.orders.payment', $order), ['kind' => 'deposit', 'paid' => true]);

        // Tìm mốc theo KEY chứ không theo vị trí: thêm một mốc vào Order::TRACKED_ACTIONS là
        // mọi chỉ số phía sau dịch hết (đã dính đúng vậy khi thêm 'fee_paid' ở bopcamping-urqo).
        $this->actingAs($admin)->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertInertia(function ($page) {
                $actions = collect($page->toArray()['props']['order']['actions']);

                // Đơn không có phụ phí thì mốc 'fee_paid' bị bỏ hẳn, không treo "chưa
                // làm" vĩnh viễn (bopcamping-urqo) — nên ít hơn hằng đúng 1.
                $this->assertSame(count(Order::TRACKED_ACTIONS) - 1, $actions->count());
                $this->assertNull($actions->firstWhere('key', 'fee_paid'));

                $deposit = $actions->firstWhere('key', 'deposit_paid');
                $this->assertNotNull($deposit, 'thiếu mốc deposit_paid');
                $this->assertTrue($deposit['done']);
                $this->assertSame('Chủ shop', $deposit['by']);
            });
    }

    /** @test */
    public function schedule_row_lists_what_is_left_to_do_for_the_leg(): void
    {
        $shipper = $this->shipper();
        $admin = $this->admin();
        $order = $this->order(['pickup_shipper_id' => $shipper->id]);

        // Chưa làm gì: giao đồ + thu cả 2 khoản.
        $this->actingAs($admin)->get(route('admin.schedule'))
            ->assertInertia(fn ($page) => $page
                ->where('pickups.0.todo', ['Giao đồ', 'Thu tiền thuê 300.000đ', 'Thu cọc 200.000đ']));

        // Thu tiền thuê rồi → không nhắc lại khoản đó.
        $order->markPaid('rental', true, $admin->id);
        $this->actingAs($admin)->get(route('admin.schedule'))
            ->assertInertia(fn ($page) => $page
                ->where('pickups.0.todo', ['Giao đồ', 'Thu cọc 200.000đ']));
    }

    /** @test */
    public function return_leg_todo_includes_refunding_the_deposit_only_after_it_was_collected(): void
    {
        $admin = $this->admin();
        $shipper = $this->shipper();
        $today = now()->toDateString();

        $order = $this->order([
            'start_date' => now()->subDays(2)->toDateString(), 'end_date' => $today,
            'status' => 'renting', 'return_shipper_id' => $shipper->id,
        ]);

        // Chưa thu cọc của khách → chưa có việc hoàn cọc.
        $this->actingAs($admin)->get(route('admin.schedule', ['date' => $today]))
            ->assertInertia(fn ($page) => $page
                ->where('returns.0.todo', ['Thu đồ', 'Thu tiền thuê 300.000đ', 'Thu cọc 200.000đ']));

        // Đã thu cọc → mới phải hoàn lại cho khách.
        $order->markPaid('deposit', true, $shipper->id);
        $this->actingAs($admin)->get(route('admin.schedule', ['date' => $today]))
            ->assertInertia(fn ($page) => $page
                ->where('returns.0.todo', ['Thu đồ', 'Thu tiền thuê 300.000đ', 'Hoàn cọc 200.000đ']));
    }
}

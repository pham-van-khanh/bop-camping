<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-xdvx — schema vai shipper + gán shipper theo từng lượt.
 * Xem adr_shipper_role_and_access mục 3, prd_shipper_delivery_ops mục 3.
 */
class ShipperAssignmentSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $attrs = []): Order
    {
        return Order::create(array_merge([
            'code' => 'BOP-'.strtoupper(uniqid()),
            'customer_name' => 'Khách', 'customer_phone' => '0900000000',
            'start_date' => '2030-08-01', 'end_date' => '2030-08-03',
            'status' => 'confirmed', 'payment_method' => 'cod',
            'total_price' => 100000, 'deposit_total' => 50000,
        ], $attrs));
    }

    /** @test */
    public function shipper_flag_defaults_to_false_and_is_independent_from_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $shipper = User::factory()->create(['is_shipper' => true]);

        $this->assertFalse($admin->fresh()->isShipper());   // admin KHÔNG tự động là shipper
        $this->assertTrue($shipper->isShipper());
        $this->assertFalse((bool) $shipper->is_admin);
        $this->assertFalse(User::factory()->create()->isShipper());
    }

    /** @test */
    public function shippers_scope_lists_only_shippers_by_name(): void
    {
        User::factory()->create(['name' => 'Zũng', 'is_shipper' => true]);
        User::factory()->create(['name' => 'An', 'is_shipper' => true]);
        User::factory()->create(['name' => 'Khách thường']);

        $this->assertSame(['An', 'Zũng'], User::shippers()->pluck('name')->all());
    }

    /** @test */
    public function each_leg_can_be_assigned_to_a_different_shipper(): void
    {
        $giao = User::factory()->create(['is_shipper' => true]);
        $thu = User::factory()->create(['is_shipper' => true]);

        $order = $this->order([
            'pickup_shipper_id' => $giao->id, 'pickup_sort' => 1,
            'return_shipper_id' => $thu->id, 'return_sort' => 2,
        ]);

        $order->refresh()->load(['pickupShipper', 'returnShipper']);
        $this->assertSame($giao->id, $order->pickupShipper->id);
        $this->assertSame($thu->id, $order->returnShipper->id);
        $this->assertSame(1, (int) $order->pickup_sort);
        $this->assertSame(2, (int) $order->return_sort);

        // Quan hệ ngược: shipper thấy được lượt của mình.
        $this->assertTrue($giao->pickupOrders()->whereKey($order->id)->exists());
        $this->assertTrue($thu->returnOrders()->whereKey($order->id)->exists());
        $this->assertFalse($giao->returnOrders()->whereKey($order->id)->exists());
    }

    /** @test */
    public function deleting_a_shipper_clears_assignment_but_keeps_the_order(): void
    {
        $shipper = User::factory()->create(['is_shipper' => true]);
        $order = $this->order(['pickup_shipper_id' => $shipper->id, 'return_shipper_id' => $shipper->id]);

        $shipper->delete();

        $fresh = $order->fresh();
        $this->assertNotNull($fresh, 'Xoá shipper KHÔNG được xoá đơn');
        $this->assertNull($fresh->pickup_shipper_id);
        $this->assertNull($fresh->return_shipper_id);
    }

    /** @test */
    public function assignment_columns_default_to_null_for_existing_orders(): void
    {
        $order = $this->order();

        $this->assertNull($order->pickup_shipper_id);
        $this->assertNull($order->return_shipper_id);
        $this->assertNull($order->pickup_sort);
        $this->assertNull($order->return_sort);
    }
}

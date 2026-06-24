<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Referral;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-8zy — chi tiết đơn trong admin: email/địa chỉ + voucher đã dùng + giới thiệu.
 */
class AdminOrderDetailTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_orders_include_full_detail_voucher_and_referral(): void
    {
        $customer = User::factory()->create(['name' => 'Khách', 'phone' => '0900000009', 'email' => 'kh@example.com']);
        $referrer = User::factory()->create(['name' => 'Người Mời', 'phone' => '0900000010']);

        $order = Order::create([
            'user_id' => $customer->id,
            'customer_name' => 'Khách', 'customer_phone' => '0900000009',
            'customer_email' => 'kh@example.com', 'customer_address' => '12 ABC',
            'start_date' => '2026-07-10', 'end_date' => '2026-07-12',
            'total_price' => 300000, 'deposit_total' => 200000, 'discount_total' => 30000,
            'status' => 'pending',
        ]);

        Voucher::create([
            'user_id' => $customer->id, 'code' => 'VC-USED', 'type' => 'fixed', 'value' => 30000,
            'source' => 'manual_admin', 'status' => 'used', 'order_id' => $order->id,
        ]);
        Referral::create([
            'referrer_id' => $referrer->id, 'referee_id' => $customer->id,
            'code_used' => 'ABC123', 'status' => 'pending', 'first_order_id' => $order->id,
        ]);

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.orders'))->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders')
            ->where('orders.0.customer_email', 'kh@example.com')
            ->where('orders.0.customer_address', '12 ABC')
            ->where('orders.0.amount_due', 470000) // 300k + 200k − 30k
            ->where('orders.0.vouchers.0.code', 'VC-USED')
            ->where('orders.0.vouchers.0.value', 30000)
            ->where('orders.0.referral.referrer_name', 'Người Mời')
        );
    }
}

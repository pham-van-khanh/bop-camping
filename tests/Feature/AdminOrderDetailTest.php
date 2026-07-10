<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Order;
use App\Models\Product;
use App\Models\Referral;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-8zy — chi tiết đơn trong admin: email/địa chỉ + voucher đã dùng + giới thiệu.
 * bopcamping-d7l — items thuộc combo mang combo_group_uuid/combo_name/allocated_price
 * để FE nhóm thành khối combo (AC-3, phát hiện ở phiên test tổng hợp).
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
            ->where('orders.data.0.customer_email', 'kh@example.com')
            ->where('orders.data.0.customer_address', '12 ABC')
            ->where('orders.data.0.amount_due', 470000) // 300k + 200k − 30k
            ->where('orders.data.0.vouchers.0.code', 'VC-USED')
            ->where('orders.data.0.vouchers.0.value', 30000)
            ->where('orders.data.0.referral.referrer_name', 'Người Mời')
        );
    }

    /**
     * bopcamping-d7l (regression): đơn có combo → items payload mang metadata
     * nhóm combo; dòng lẻ trong cùng đơn có metadata null.
     *
     * @test
     */
    public function order_items_carry_combo_grouping_metadata(): void
    {
        $cat = Category::create(['name' => 'Lều', 'slug' => 'leu']);
        $tent = Product::create(['category_id' => $cat->id, 'name' => 'Lều Test', 'slug' => 'leu-test', 'price_per_day' => 100000, 'quantity' => 3]);
        $bag = Product::create(['category_id' => $cat->id, 'name' => 'Túi Test', 'slug' => 'tui-test', 'price_per_day' => 50000, 'quantity' => 5]);

        $combo = Combo::create(['name' => 'Combo Test', 'slug' => 'combo-test', 'combo_price' => 120000]);
        $combo->items()->create(['product_id' => $tent->id, 'quantity' => 1]);
        $combo->items()->create(['product_id' => $bag->id, 'quantity' => 1]);

        $order = Order::create([
            'customer_name' => 'X', 'customer_phone' => '0900000000',
            'start_date' => '2030-07-10', 'end_date' => '2030-07-12',
            'total_price' => 460000, 'status' => 'pending',
        ]);
        $uuid = (string) Str::uuid();
        // 2 dòng combo + 1 dòng lẻ
        $order->items()->create(['product_id' => $tent->id, 'combo_id' => $combo->id, 'combo_group_uuid' => $uuid, 'quantity' => 1, 'price_per_day' => 100000, 'days' => 3, 'subtotal' => 240000, 'allocated_price' => 80000]);
        $order->items()->create(['product_id' => $bag->id, 'combo_id' => $combo->id, 'combo_group_uuid' => $uuid, 'quantity' => 1, 'price_per_day' => 50000, 'days' => 3, 'subtotal' => 120000, 'allocated_price' => 40000]);
        $order->items()->create(['product_id' => $bag->id, 'quantity' => 1, 'price_per_day' => 50000, 'days' => 3, 'subtotal' => 150000]);

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.orders'))->assertInertia(fn (Assert $page) => $page
            ->where('orders.data.0.items.0.combo_group_uuid', $uuid)
            ->where('orders.data.0.items.0.combo_name', 'Combo Test')
            ->where('orders.data.0.items.0.allocated_price', 80000)
            ->where('orders.data.0.items.1.combo_group_uuid', $uuid)
            ->where('orders.data.0.items.2.combo_group_uuid', null)
            ->where('orders.data.0.items.2.combo_name', null)
            ->where('orders.data.0.items.2.allocated_price', null)
        );
    }
}

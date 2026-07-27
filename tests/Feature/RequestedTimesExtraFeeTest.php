<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromotionSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-h4to (Phase 2 turnaround) — giao/trả ngoài khung giờ: khách ghi giờ nhận/trả
 * mong muốn ở checkout; admin nhập PHỤ PHÍ tay (cộng vào amount_due). KHÔNG đụng tồn kho.
 */
class RequestedTimesExtraFeeTest extends TestCase
{
    use RefreshDatabase;

    private Product $chair;

    protected function setUp(): void
    {
        parent::setUp();
        PromotionSetting::current()->update(['email_bonus_enabled' => false, 'max_discount_percent_per_order' => 50]);
        $cat = Category::create(['name' => 'Ghế', 'slug' => 'ghe']);
        $this->chair = Product::create([
            'category_id' => $cat->id, 'name' => 'Ghế', 'slug' => 'ghe-test',
            'price_per_day' => 100000, 'quantity' => 5, 'deposit' => 50000,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function order(int $extraFee = 0, bool $parent = false): Order
    {
        return Order::create([
            'code' => 'BOP-'.strtoupper(uniqid()), 'customer_name' => 'X', 'customer_phone' => '0900000000',
            'start_date' => '2030-07-01', 'end_date' => '2030-07-02', 'status' => 'pending', 'payment_method' => 'cod',
            'total_price' => 200000, 'deposit_total' => 50000, 'extra_fee' => $extraFee, 'is_parent' => $parent,
        ]);
    }

    /** @test */
    public function checkout_stores_session_and_derived_times(): void
    {
        $user = User::factory()->create(['phone' => '0911222001']);
        $this->actingAs($user)->post(route('order.store'), [
            'name' => $user->name, 'phone' => $user->phone,
            // Buổi khách chọn khi thuê 1 ngày — gửi ở cấp DÒNG; server suy giờ (spec 2026-07-26).
            'items' => [['product_id' => $this->chair->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-01', 'session' => 'afternoon']],
        ])->assertSessionHas('order_code');

        $order = Order::latest('id')->first();
        $this->assertSame('afternoon', $order->session);
        $this->assertSame('13:00', $order->requested_pickup_time); // đầu buổi chiều (setting 8/12/13/20)
        $this->assertSame('20:00', $order->requested_return_time);
        $this->assertSame(0, (int) $order->extra_fee); // checkout không đặt phụ phí
    }

    /** @test */
    public function checkout_rejects_invalid_session(): void
    {
        $user = User::factory()->create(['phone' => '0911222002']);
        $this->actingAs($user)->post(route('order.store'), [
            'name' => $user->name, 'phone' => $user->phone,
            'items' => [['product_id' => $this->chair->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-01', 'session' => 'evening']],
        ])->assertSessionHasErrors('items.0.session');
    }

    /** @test */
    public function amount_due_includes_extra_fee(): void
    {
        $order = $this->order(extraFee: 30000);
        // 200k thuê + 50k cọc + 30k phụ phí − 0 giảm = 280k
        $this->assertSame(280000, $order->amount_due);
    }

    /** @test */
    public function admin_sets_extra_fee_and_note(): void
    {
        $order = $this->order();
        $this->actingAs($this->admin())->patch(route('admin.orders.fee', $order), [
            'extra_fee' => 40000, 'extra_fee_note' => 'Giao sớm 6h',
        ])->assertRedirect()->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(40000, (int) $order->extra_fee);
        $this->assertSame('Giao sớm 6h', $order->extra_fee_note);
        $this->assertSame(290000, $order->amount_due); // 200k + 50k + 40k
    }

    /** @test */
    public function admin_extra_fee_rejects_negative(): void
    {
        $order = $this->order();
        $this->actingAs($this->admin())->patch(route('admin.orders.fee', $order), ['extra_fee' => -5])
            ->assertSessionHasErrors('extra_fee');
    }

    /** @test */
    public function extra_fee_blocked_on_parent_order(): void
    {
        $parent = $this->order(parent: true);
        $this->actingAs($this->admin())->patch(route('admin.orders.fee', $parent), ['extra_fee' => 10000])
            ->assertSessionHasErrors('extra_fee');
        $this->assertSame(0, (int) $parent->fresh()->extra_fee);
    }

    /** @test */
    public function non_admin_cannot_set_extra_fee(): void
    {
        $order = $this->order();
        $guest = User::factory()->create(['is_admin' => false]);
        $this->actingAs($guest)->patch(route('admin.orders.fee', $order), ['extra_fee' => 10000])
            ->assertRedirect(route('admin.login'));
        $this->assertSame(0, (int) $order->fresh()->extra_fee);
    }
}

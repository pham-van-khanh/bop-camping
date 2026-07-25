<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromotionSetting;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-wtuv (T4) — huỷ 1 đơn con → tính lại voucher + phân bổ trên con còn active.
 */
class ParentChildCancelTest extends TestCase
{
    use RefreshDatabase;

    private Product $a; // 100k/ngày

    private Product $b; // 50k/ngày

    protected function setUp(): void
    {
        parent::setUp();
        PromotionSetting::current()->update(['email_bonus_enabled' => false, 'max_discount_percent_per_order' => 25]);
        $cat = Category::create(['name' => 'Lều', 'slug' => 'leu']);
        $this->a = Product::create(['category_id' => $cat->id, 'name' => 'A', 'slug' => 'a', 'price_per_day' => 100000, 'quantity' => 3, 'deposit' => 0]);
        $this->b = Product::create(['category_id' => $cat->id, 'name' => 'B', 'slug' => 'b', 'price_per_day' => 50000, 'quantity' => 3, 'deposit' => 0]);
    }

    /** Tạo đơn cha 350k (A 200k 01-02 + B 150k 05-07) với voucher 10% (35k) đã phân bổ. */
    private function makeParentWithVoucher(): Order
    {
        $user = User::factory()->create(['phone' => '0911777001']);
        Voucher::create(['user_id' => $user->id, 'code' => 'V10', 'type' => 'percent', 'value' => 10, 'status' => 'active', 'max_uses' => 1, 'used_count' => 0]);
        $this->actingAs($user)->post(route('order.store'), [
            'name' => $user->name, 'phone' => $user->phone, 'voucher_codes' => ['V10'],
            'items' => [
                ['product_id' => $this->a->id, 'quantity' => 1, 'start' => '2030-09-01', 'end' => '2030-09-02'],
                ['product_id' => $this->b->id, 'quantity' => 1, 'start' => '2030-09-05', 'end' => '2030-09-07'],
            ],
        ])->assertSessionHas('order_code');

        return Order::where('is_parent', true)->firstOrFail();
    }

    /** @test */
    public function cancelling_a_child_recomputes_voucher_on_remaining_children(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $parent = $this->makeParentWithVoucher();
        $children = $parent->children()->get();
        $cA = $children[0]; // A 200k
        $cB = $children[1]; // B 150k
        $this->assertSame(35000, (int) $parent->discount_total);

        // Huỷ con B.
        $this->actingAs($admin)->patch(route('admin.orders.update', $cB), ['status' => 'cancelled'])->assertSessionHas('success');

        $parent->refresh();
        // Còn A 200k: voucher 10% scale 35k×200/350 = 20000; tổng cha = 200k.
        $this->assertSame(200000, (int) $parent->total_price);
        $this->assertSame(20000, (int) $parent->discount_total);
        $this->assertSame(20000, (int) $cA->fresh()->discount_total);
        $this->assertSame(0, (int) $cB->fresh()->discount_total);
        $this->assertSame('cancelled', $cB->fresh()->status);
    }

    /** @test */
    public function cancelling_parent_cancels_all_children(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $parent = $this->makeParentWithVoucher();

        $this->actingAs($admin)->patch(route('admin.orders.update', $parent), ['status' => 'cancelled'])->assertSessionHas('success');

        $this->assertSame('cancelled', $parent->fresh()->status);
        foreach ($parent->children()->get() as $child) {
            $this->assertSame('cancelled', $child->status);
        }
    }
}

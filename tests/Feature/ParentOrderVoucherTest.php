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
 * bopcamping-wtuv (T3) — voucher tính trên TỔNG đơn cha rồi phân bổ ∝ tiền thuê xuống con.
 * Bất biến: Σ discount con === discount cha. COD thu theo từng con.
 */
class ParentOrderVoucherTest extends TestCase
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

    /** @test */
    public function voucher_on_parent_total_is_allocated_to_children_by_rental_share(): void
    {
        $user = User::factory()->create(['phone' => '0911000009']);
        Voucher::create(['user_id' => $user->id, 'code' => 'GIAM10', 'type' => 'percent', 'value' => 10, 'status' => 'active', 'max_uses' => 1, 'used_count' => 0]);

        // A 01→02 = 200k ; B 05→07 = 150k ; tổng cha 350k. Voucher 10% = 35k (< trần 87.5k).
        $this->actingAs($user)->post(route('order.store'), [
            'name' => $user->name, 'phone' => $user->phone,
            'voucher_codes' => ['GIAM10'],
            'items' => [
                ['product_id' => $this->a->id, 'quantity' => 1, 'start' => '2030-09-01', 'end' => '2030-09-02'],
                ['product_id' => $this->b->id, 'quantity' => 1, 'start' => '2030-09-05', 'end' => '2030-09-07'],
            ],
        ])->assertSessionHas('order_code');

        $parent = Order::where('is_parent', true)->firstOrFail();
        $children = $parent->children()->get();

        $this->assertSame(350000, (int) $parent->total_price);
        $this->assertSame(35000, (int) $parent->discount_total);

        // Phân bổ ∝ tiền thuê: c1 (200k) = floor(35k×200/350)=20000; c2 (150k) = phần dư 15000.
        $c1 = $children[0];
        $c2 = $children[1];
        $this->assertSame(20000, (int) $c1->discount_total);
        $this->assertSame(15000, (int) $c2->discount_total);

        // Bất biến: Σ con === cha.
        $this->assertSame((int) $parent->discount_total, (int) $children->sum('discount_total'));

        // COD từng con phản ánh giảm phân bổ (amount_due = thuê − giảm + cọc).
        $this->assertSame(200000 - 20000, (int) $c1->amount_due);
        $this->assertSame(150000 - 15000, (int) $c2->amount_due);
    }

    /** @test */
    public function voucher_respects_cap_on_parent_total(): void
    {
        $user = User::factory()->create(['phone' => '0911000010']);
        // Voucher 90% — vượt trần 25% của tổng cha.
        Voucher::create(['user_id' => $user->id, 'code' => 'BIG90', 'type' => 'percent', 'value' => 90, 'status' => 'active', 'max_uses' => 1, 'used_count' => 0]);

        $this->actingAs($user)->post(route('order.store'), [
            'name' => $user->name, 'phone' => $user->phone,
            'voucher_codes' => ['BIG90'],
            'items' => [
                ['product_id' => $this->a->id, 'quantity' => 1, 'start' => '2030-10-01', 'end' => '2030-10-02'], // 200k
                ['product_id' => $this->b->id, 'quantity' => 1, 'start' => '2030-10-05', 'end' => '2030-10-07'], // 150k
            ],
        ])->assertSessionHas('order_code');

        $parent = Order::where('is_parent', true)->firstOrFail();
        // Trần 25% × 350k = 87500 (không phải 90% = 315k).
        $this->assertSame(87500, (int) $parent->discount_total);
        $this->assertSame(87500, (int) $parent->children()->sum('discount_total'));
    }
}

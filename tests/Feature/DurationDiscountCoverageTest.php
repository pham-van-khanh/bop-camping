<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\DurationDiscountTier;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromotionSetting;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-e36e — QA coverage cho giảm giá thuê dài ngày, bù các khoảng còn thiếu:
 * combo áp bậc (penny-exact + snapshot), per-dòng khác số ngày, cọc không bị giảm,
 * và tương tác với trần voucher 25% (cap tính trên NET).
 */
class DurationDiscountCoverageTest extends TestCase
{
    use RefreshDatabase;

    private Product $tent; // 100k/ngày, cọc 300k

    private Product $bag;  // 30k/ngày, cọc 100k

    private Combo $combo;  // 1 lều + 3 túi, giá 150k/ngày, cọc 400k

    protected function setUp(): void
    {
        parent::setUp();
        PromotionSetting::current()->update(['email_bonus_enabled' => false, 'max_discount_percent_per_order' => 25]);
        DurationDiscountTier::create(['min_days' => 5, 'discount_percent' => 20, 'is_active' => true]);
        DurationDiscountTier::create(['min_days' => 10, 'discount_percent' => 30, 'is_active' => true]);

        $cat = Category::create(['name' => 'Lều', 'slug' => 'leu']);
        $this->tent = Product::create(['category_id' => $cat->id, 'name' => 'Lều Test', 'slug' => 'leu-test', 'price_per_day' => 100000, 'quantity' => 5, 'deposit' => 300000]);
        $this->bag = Product::create(['category_id' => $cat->id, 'name' => 'Túi Test', 'slug' => 'tui-test', 'price_per_day' => 30000, 'quantity' => 9, 'deposit' => 100000]);

        $this->combo = Combo::create(['name' => 'Combo Test', 'slug' => 'combo-test', 'combo_price' => 150000, 'deposit' => 400000]);
        $this->combo->items()->create(['product_id' => $this->tent->id, 'quantity' => 1]);
        $this->combo->items()->create(['product_id' => $this->bag->id, 'quantity' => 3]);
    }

    /** @test */
    public function combo_checkout_applies_tier_penny_exact_and_snapshots_percent(): void
    {
        $this->post(route('order.store'), [
            'name' => 'Khách', 'phone' => '0912345678',
            'combos' => [['combo_id' => $this->combo->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-10']], // 10 ngày → −30%
        ])->assertSessionHas('order_code');

        $order = Order::latest('id')->with('items')->first();
        // Mỗi dòng combo: net = round(allocated_price × 10 × 0.7); snapshot 30%.
        foreach ($order->items as $item) {
            $this->assertSame((int) round($item->allocated_price * 10 * 0.7), (int) $item->subtotal);
            $this->assertSame('30.00', (string) $item->duration_discount_percent);
        }
        // Bất biến: total_price = Σ subtotal dòng (penny-exact), ~ combo_price×10×0.7 = 1.050.000.
        $this->assertSame((int) $order->items->sum('subtotal'), (int) $order->total_price);
        $this->assertEqualsWithDelta(1050000, $order->total_price, 2);
        // Cọc KHÔNG bị giảm dài ngày.
        $this->assertSame(400000, (int) $order->deposit_total);
    }

    /** @test */
    public function each_line_gets_tier_by_its_own_days(): void
    {
        $this->actingAs(User::factory()->create(['phone' => '0911222333']))->post(route('order.store'), [
            'name' => 'Khách', 'phone' => '0911222333',
            'items' => [
                ['product_id' => $this->tent->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-10'], // 10 ngày −30%
                ['product_id' => $this->bag->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-03'],  // 3 ngày, không bậc
            ],
        ])->assertSessionHas('order_code');

        // 2 khoảng ngày khác nhau → tách đơn cha + 2 con (bopcamping-wtuv). Mỗi con ăn bậc
        // theo số ngày RIÊNG của khoảng đó.
        $parent = Order::whereNull('parent_id')->where('is_parent', true)->latest('id')->with('children.items')->first();
        $this->assertNotNull($parent);
        $this->assertSame(2, $parent->children->count());
        $this->assertSame(790000, (int) $parent->total_price);

        $tentChild = $parent->children->first(fn ($c) => $c->items->contains('product_id', $this->tent->id));
        $bagChild = $parent->children->first(fn ($c) => $c->items->contains('product_id', $this->bag->id));
        $this->assertSame(700000, (int) $tentChild->items->firstWhere('product_id', $this->tent->id)->subtotal);   // 100k×10 −30%
        $this->assertSame('30.00', (string) $tentChild->items->firstWhere('product_id', $this->tent->id)->duration_discount_percent);
        $this->assertSame(90000, (int) $bagChild->items->firstWhere('product_id', $this->bag->id)->subtotal);      // 30k×3, không giảm
        $this->assertSame('0.00', (string) $bagChild->items->firstWhere('product_id', $this->bag->id)->duration_discount_percent);
    }

    /** @test */
    public function order_wide_cap_is_applied_on_the_discounted_net(): void
    {
        $user = User::factory()->create(['phone' => '0911444555']);
        // Voucher 40% — vượt trần 25%, phải bị kẹp theo NET.
        Voucher::create(['user_id' => $user->id, 'code' => 'BIG40', 'type' => 'percent', 'value' => 40, 'status' => 'active', 'max_uses' => 1, 'used_count' => 0]);

        $this->actingAs($user)->post(route('order.store'), [
            'name' => 'Khách', 'phone' => '0911444555',
            'items' => [['product_id' => $this->tent->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-10']], // 10 ngày → net 700k
            'voucher_codes' => ['BIG40'],
        ])->assertSessionHas('order_code');

        $order = Order::latest('id')->first();
        $this->assertSame(700000, (int) $order->total_price);
        // Trần 25% của NET 700k = 175k (không phải 40% = 280k, cũng không tính trên gross 1tr).
        $this->assertSame(175000, (int) $order->discount_total);
    }
}

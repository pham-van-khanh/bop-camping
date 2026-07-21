<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromotionSetting;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-wtuv — QA bù khoảng trống: khôi phục con đã huỷ (recompute ngược),
 * phân bổ voucher cho 3 con (bất biến làm tròn), combo tách thành đơn con riêng.
 */
class ParentChildQaCoverageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $tent; // 100k/ngày

    private Product $bag;  // 30k/ngày

    private Combo $combo;  // 1 lều + 3 túi, 150k/ngày, cọc 400k

    protected function setUp(): void
    {
        parent::setUp();
        PromotionSetting::current()->update(['email_bonus_enabled' => false, 'max_discount_percent_per_order' => 50]);
        $this->admin = User::factory()->create(['is_admin' => true]);
        $cat = Category::create(['name' => 'Lều', 'slug' => 'leu']);
        $this->tent = Product::create(['category_id' => $cat->id, 'name' => 'Lều', 'slug' => 'leu', 'price_per_day' => 100000, 'quantity' => 9, 'deposit' => 100000]);
        $this->bag = Product::create(['category_id' => $cat->id, 'name' => 'Túi', 'slug' => 'tui', 'price_per_day' => 30000, 'quantity' => 9, 'deposit' => 50000]);
        $this->combo = Combo::create(['name' => 'Combo', 'slug' => 'combo', 'combo_price' => 150000, 'deposit' => 400000]);
        $this->combo->items()->create(['product_id' => $this->tent->id, 'quantity' => 1]);
        $this->combo->items()->create(['product_id' => $this->bag->id, 'quantity' => 3]);
    }

    private function voucher(User $u, string $code, int $pct): void
    {
        Voucher::create(['user_id' => $u->id, 'code' => $code, 'type' => 'percent', 'value' => $pct, 'status' => 'active', 'max_uses' => 1, 'used_count' => 0]);
    }

    /** @test */
    public function restoring_a_cancelled_child_reallocates_voucher_back(): void
    {
        $user = User::factory()->create(['phone' => '0913000001']);
        $this->voucher($user, 'V10', 10);

        // A 01→02 (200k) + B 05→06 (200k) → tổng 400k, voucher 10% = 40k.
        $this->actingAs($user)->post(route('order.store'), [
            'name' => $user->name, 'phone' => $user->phone, 'voucher_codes' => ['V10'],
            'items' => [
                ['product_id' => $this->tent->id, 'quantity' => 1, 'start' => '2030-09-01', 'end' => '2030-09-02'],
                ['product_id' => $this->tent->id, 'quantity' => 1, 'start' => '2030-09-05', 'end' => '2030-09-06'],
            ],
        ])->assertSessionHas('order_code');

        $parent = Order::where('is_parent', true)->firstOrFail();
        [$c1, $c2] = [$parent->children()->orderBy('start_date')->first(), $parent->children()->orderBy('start_date')->get()[1]];
        $this->assertSame(40000, (int) $parent->discount_total);

        // Huỷ con 2 → voucher dồn về con 1 (10% của 200k còn lại = 20k).
        $this->actingAs($this->admin)->patch(route('admin.orders.update', $c2), ['status' => 'cancelled'])->assertSessionHas('success');
        $parent->refresh();
        $this->assertSame(200000, (int) $parent->total_price);
        $this->assertSame(20000, (int) $parent->discount_total);
        $this->assertSame(20000, (int) $c1->fresh()->discount_total);
        $this->assertSame(0, (int) $c2->fresh()->discount_total);

        // KHÔI PHỤC con 2 (cancelled → confirmed) → voucher chia lại 2 con (40k tổng).
        $this->actingAs($this->admin)->patch(route('admin.orders.update', $c2), ['status' => 'confirmed'])->assertSessionHas('success');
        $parent->refresh();
        $this->assertSame(400000, (int) $parent->total_price);
        $this->assertSame(40000, (int) $parent->discount_total);
        $this->assertSame(40000, (int) $parent->children()->whereIn('status', ['pending', 'confirmed'])->sum('discount_total'));
    }

    /** @test */
    public function voucher_allocation_across_three_children_sums_exactly(): void
    {
        $user = User::factory()->create(['phone' => '0913000002']);
        $this->voucher($user, 'V15', 15); // 15% → dễ lệch làm tròn khi chia 3

        // 3 khoảng, tiền lẻ để ép làm tròn: 100k, 300k, 200k (tổng 600k) — dùng qty/ngày khác nhau.
        $this->actingAs($user)->post(route('order.store'), [
            'name' => $user->name, 'phone' => $user->phone, 'voucher_codes' => ['V15'],
            'items' => [
                ['product_id' => $this->tent->id, 'quantity' => 1, 'start' => '2030-10-01', 'end' => '2030-10-01'], // 100k
                ['product_id' => $this->tent->id, 'quantity' => 1, 'start' => '2030-10-05', 'end' => '2030-10-07'], // 300k
                ['product_id' => $this->tent->id, 'quantity' => 1, 'start' => '2030-10-10', 'end' => '2030-10-11'], // 200k
            ],
        ])->assertSessionHas('order_code');

        $parent = Order::where('is_parent', true)->firstOrFail();
        $this->assertSame(600000, (int) $parent->total_price);
        $this->assertSame(90000, (int) $parent->discount_total); // 15% × 600k
        // BẤT BIẾN: Σ giảm con === giảm cha (không lệch đồng do làm tròn floor + dồn con cuối).
        $this->assertSame(90000, (int) $parent->children()->sum('discount_total'));
        // Mỗi con không âm và ≤ tiền thuê con.
        foreach ($parent->children as $c) {
            $this->assertGreaterThanOrEqual(0, (int) $c->discount_total);
            $this->assertLessThanOrEqual((int) $c->total_price, (int) $c->discount_total);
        }
    }

    /** @test */
    public function combo_on_a_separate_range_becomes_its_own_child(): void
    {
        // Lẻ (lều) 01→02 + COMBO 05→07 → tách cha + 2 con; con combo giữ combo_group_uuid + allocated_price.
        $this->post(route('order.store'), [
            'name' => 'Khách', 'phone' => '0913000003',
            'items' => [['product_id' => $this->tent->id, 'quantity' => 1, 'start' => '2030-11-01', 'end' => '2030-11-02']],
            'combos' => [['combo_id' => $this->combo->id, 'quantity' => 1, 'start' => '2030-11-05', 'end' => '2030-11-07']],
        ])->assertSessionHas('order_code');

        $parent = Order::where('is_parent', true)->with('children.items')->firstOrFail();
        $this->assertSame(2, $parent->children->count());

        $comboChild = $parent->children->first(fn ($c) => $c->items->whereNotNull('combo_id')->isNotEmpty());
        $leChild = $parent->children->first(fn ($c) => $c->items->whereNull('combo_id')->isNotEmpty());

        $this->assertNotNull($comboChild);
        $this->assertNotNull($leChild);
        // Con combo: 2 món con cùng combo_group_uuid, Σ allocated == combo_price × ngày (3 ngày = 450k).
        $this->assertSame(2, $comboChild->items->count());
        $this->assertSame(1, $comboChild->items->pluck('combo_group_uuid')->unique()->count());
        $this->assertSame(450000, (int) $comboChild->items->sum('subtotal')); // 150k × 3 ngày, chưa đạt bậc
        $this->assertSame(400000, (int) $comboChild->deposit_total); // cọc combo
        // Con lẻ: 1 món, 100k × 2 = 200k.
        $this->assertSame(200000, (int) $leChild->total_price);
        // Cha gom đúng.
        $this->assertSame(650000, (int) $parent->total_price);
    }
}

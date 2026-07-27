<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromotionSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-jrh8 (QA gap-fill) — giá nửa ngày ở các ca khó: đơn TÁCH cha/con
 * (chỉ đợt cùng ngày mới nửa ngày), bất biến CỌC (chỉ giảm tiền thuê), và COMBO
 * KHÔNG nhận ưu đãi trả sớm (chỉ sản phẩm lẻ).
 */
class HalfDayCheckoutQaTest extends TestCase
{
    use RefreshDatabase;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        PromotionSetting::current()->update(['email_bonus_enabled' => false, 'max_discount_percent_per_order' => 50]);
        $this->cat = Category::create(['name' => 'Đồ', 'slug' => 'do']);
    }

    private function product(int $price, int $deposit, int $earlyPct): Product
    {
        return Product::create([
            'category_id' => $this->cat->id, 'name' => 'SP '.uniqid(), 'slug' => 'sp-'.uniqid(),
            'price_per_day' => $price, 'quantity' => 10, 'deposit' => $deposit,
            'early_return_discount_pct' => $earlyPct,
        ]);
    }

    /** @test */
    public function split_order_only_same_day_child_is_half_day(): void
    {
        $p = $this->product(price: 100000, deposit: 50000, earlyPct: 10);
        $user = User::factory()->create(['phone' => '0912000001']);

        // Giỏ 2 khoảng: đợt cùng ngày (trả sớm) + đợt nhiều ngày → cha + 2 con (bopcamping-wtuv).
        $this->actingAs($user)->post(route('order.store'), [
            'name' => $user->name, 'phone' => $user->phone,
            'items' => [
                ['product_id' => $p->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-01', 'session' => 'morning'],
                ['product_id' => $p->id, 'quantity' => 1, 'start' => '2030-07-05', 'end' => '2030-07-07', 'session' => 'full'],
            ],
        ])->assertSessionHas('order_code');

        $parent = Order::where('is_parent', true)->firstOrFail();
        $this->assertFalse($parent->is_half_day);                 // cha là envelope, không nửa ngày
        $this->assertSame(390000, $parent->total_price);          // 90k + 300k
        $this->assertSame(100000, $parent->deposit_total);        // cọc 50k + 50k, KHÔNG giảm

        $children = $parent->children()->get();
        $sameDay = $children->first(fn ($c) => $c->start_date->equalTo($c->end_date));
        $multiDay = $children->first(fn ($c) => ! $c->start_date->equalTo($c->end_date));

        $this->assertTrue($sameDay->is_half_day);
        $this->assertSame(90000, $sameDay->total_price);          // 100k − 10%
        $this->assertFalse($multiDay->is_half_day);
        $this->assertSame(300000, $multiDay->total_price);        // 3 ngày, không ưu đãi
    }

    /** @test */
    public function half_day_discount_never_touches_deposit(): void
    {
        $p = $this->product(price: 100000, deposit: 50000, earlyPct: 10);
        $user = User::factory()->create(['phone' => '0912000002']);

        $this->actingAs($user)->post(route('order.store'), [
            'name' => $user->name, 'phone' => $user->phone,
            'items' => [['product_id' => $p->id, 'quantity' => 2, 'start' => '2030-07-01', 'end' => '2030-07-01', 'session' => 'morning']],
        ])->assertSessionHas('order_code');

        $order = Order::latest('id')->first();
        $this->assertTrue($order->is_half_day);
        $this->assertSame(180000, $order->total_price);   // 100k×2 − 10% = 180k
        $this->assertSame(100000, $order->deposit_total); // cọc 50k×2 = 100k, nguyên vẹn
    }

    /** @test */
    public function combo_lines_get_no_early_return_even_same_day_half_day_order(): void
    {
        // Đợt cùng ngày gồm 1 sản phẩm lẻ (ưu đãi 10%) + 1 combo. Combo KHÔNG được giảm trả sớm.
        $standalone = $this->product(price: 100000, deposit: 50000, earlyPct: 10);
        $inCombo = $this->product(price: 100000, deposit: 0, earlyPct: 50); // pct cao nhưng combo bỏ qua
        $combo = Combo::create(['name' => 'Combo QA', 'slug' => 'combo-qa-'.uniqid(), 'combo_price' => 80000, 'is_active' => true]);
        $combo->items()->create(['product_id' => $inCombo->id, 'quantity' => 1]);

        $user = User::factory()->create(['phone' => '0912000003']);
        $this->actingAs($user)->post(route('order.store'), [
            'name' => $user->name, 'phone' => $user->phone,
            'items' => [['product_id' => $standalone->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-01', 'session' => 'morning']],
            'combos' => [['combo_id' => $combo->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-01']],
        ])->assertSessionHas('order_code');

        $order = Order::latest('id')->with('items')->first();
        $this->assertTrue($order->is_half_day);

        $standaloneItem = $order->items->firstWhere('product_id', $standalone->id);
        $comboItem = $order->items->firstWhere('combo_id', $combo->id);

        $this->assertSame(90000, (int) $standaloneItem->subtotal);  // sản phẩm lẻ: −10%
        $this->assertSame(80000, (int) $comboItem->subtotal);       // combo: giữ nguyên combo_price, KHÔNG −50%
    }
}

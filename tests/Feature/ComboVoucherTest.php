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
 * bopcamping-6he (Combo P2) — AC-8 / PRD mục 7: voucher thường KHÔNG giảm phần
 * giá trị combo (tránh double-discount); voucher có applicable_to_combos mới giảm được.
 */
class ComboVoucherTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $tent;   // 100k/ngày

    private Combo $combo;    // giá 150k/ngày

    protected function setUp(): void
    {
        parent::setUp();

        // Cô lập số học voucher: tắt email bonus (mặc định bật 5% đơn đầu),
        // nâng trần giảm để không đụng cap trong các phép tính dưới.
        PromotionSetting::current()->update([
            'email_bonus_enabled' => false,
            'max_discount_percent_per_order' => 50,
        ]);

        $this->user = User::factory()->create();

        $cat = Category::create(['name' => 'Lều', 'slug' => 'leu']);
        $this->tent = Product::create([
            'category_id' => $cat->id,
            'name' => 'Lều Test',
            'slug' => 'leu-test',
            'price_per_day' => 100000,
            'quantity' => 5,
        ]);
        $bag = Product::create([
            'category_id' => $cat->id,
            'name' => 'Túi Test',
            'slug' => 'tui-test',
            'price_per_day' => 50000,
            'quantity' => 5,
        ]);

        $this->combo = Combo::create([
            'name' => 'Combo Test',
            'slug' => 'combo-test',
            'combo_price' => 150000,
        ]);
        $this->combo->items()->create(['product_id' => $this->tent->id, 'quantity' => 1]);
        $this->combo->items()->create(['product_id' => $bag->id, 'quantity' => 1]);
    }

    private function voucher(int|float $value, string $type = 'percent', bool $forCombos = false): Voucher
    {
        return Voucher::create([
            'user_id' => $this->user->id,
            'code' => 'VC'.strtoupper(uniqid()),
            'type' => $type,
            'value' => $value,
            'source' => 'manual_admin',
            'status' => 'active',
            'max_uses' => 1,
            'applicable_to_combos' => $forCombos,
        ]);
    }

    /**
     * Đơn hỗn hợp: lẻ 200k (100k×2 ngày) + combo 300k (150k×2 ngày) = 500k.
     * Voucher 10% thường → chỉ giảm trên 200k lẻ = 20k (KHÔNG phải 50k).
     *
     * @test
     */
    public function regular_voucher_applies_only_to_non_combo_part(): void
    {
        $v = $this->voucher(10);

        $this->actingAs($this->user)->post(route('order.store'), [
            'name' => 'Khách',
            'phone' => '0912345678',
            'items' => [['product_id' => $this->tent->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-02']],
            'combos' => [['combo_id' => $this->combo->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-02']],
            'voucher_codes' => [$v->code],
        ])->assertSessionHasNoErrors()->assertSessionHas('order_code');

        $order = Order::first();
        $this->assertSame(500000, (int) $order->total_price);
        $this->assertSame(20000, (int) $order->discount_total);
    }

    /**
     * Cùng đơn hỗn hợp 500k, voucher 10% có applicable_to_combos → giảm trên cả 500k = 50k.
     *
     * @test
     */
    public function combo_enabled_voucher_applies_to_full_order(): void
    {
        $v = $this->voucher(10, forCombos: true);

        $this->actingAs($this->user)->post(route('order.store'), [
            'name' => 'Khách',
            'phone' => '0912345678',
            'items' => [['product_id' => $this->tent->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-02']],
            'combos' => [['combo_id' => $this->combo->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-02']],
            'voucher_codes' => [$v->code],
        ])->assertSessionHasNoErrors()->assertSessionHas('order_code');

        $order = Order::first();
        $this->assertSame(50000, (int) $order->discount_total);
    }

    /**
     * Đơn CHỈ có combo + voucher fixed thường → phần lẻ = 0 → không giảm đồng nào,
     * voucher cũng không bị tiêu (đốt voucher mà không giảm = mất tiền khách).
     *
     * @test
     */
    public function regular_fixed_voucher_gives_zero_on_combo_only_order(): void
    {
        $v = $this->voucher(50000, type: 'fixed');

        $this->actingAs($this->user)->post(route('order.store'), [
            'name' => 'Khách',
            'phone' => '0912345678',
            'combos' => [['combo_id' => $this->combo->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-02']],
            'voucher_codes' => [$v->code],
        ])->assertSessionHasNoErrors()->assertSessionHas('order_code');

        $order = Order::first();
        $this->assertSame(0, (int) $order->discount_total);
        // Voucher không bị tiêu — khách còn dùng được cho đơn sau
        $this->assertSame('active', $v->fresh()->status);
        $this->assertSame(0, (int) $v->fresh()->used_count);
    }

    /**
     * Stack 1 voucher thường + 1 voucher combo trên đơn hỗn hợp: mỗi cái tính trên đúng base của nó.
     *
     * @test
     */
    public function stacked_vouchers_use_their_own_base(): void
    {
        $regular = $this->voucher(10);                    // 10% × 200k lẻ = 20k
        $forCombo = $this->voucher(10, forCombos: true);  // 10% × 500k    = 50k

        $this->actingAs($this->user)->post(route('order.store'), [
            'name' => 'Khách',
            'phone' => '0912345678',
            'items' => [['product_id' => $this->tent->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-02']],
            'combos' => [['combo_id' => $this->combo->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-02']],
            'voucher_codes' => [$regular->code, $forCombo->code],
        ])->assertSessionHasNoErrors()->assertSessionHas('order_code');

        $order = Order::first();
        $this->assertSame(70000, (int) $order->discount_total);
    }

    /**
     * Hành vi cũ không đổi: đơn toàn items lẻ, voucher thường giảm trên cả đơn như trước.
     *
     * @test
     */
    public function orders_without_combo_keep_existing_behaviour(): void
    {
        $v = $this->voucher(10);

        $this->actingAs($this->user)->post(route('order.store'), [
            'name' => 'Khách',
            'phone' => '0912345678',
            'items' => [['product_id' => $this->tent->id, 'quantity' => 2, 'start' => '2030-07-01', 'end' => '2030-07-02']],
            'voucher_codes' => [$v->code],
        ])->assertSessionHasNoErrors()->assertSessionHas('order_code');

        $order = Order::first();
        $this->assertSame(400000, (int) $order->total_price); // 2 bộ × 2 ngày × 100k
        $this->assertSame(40000, (int) $order->discount_total);
    }
}

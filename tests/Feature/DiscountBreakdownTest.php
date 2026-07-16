<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromotionSetting;
use App\Models\ReferralCode;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-3ag — chủ shop phát hiện trên BOP-494B3D: đơn giảm 51k (email bonus)
 * nhưng admin ghi nhãn "voucher" và panel "Ưu đãi đã dùng" rỗng, vì nguồn giảm
 * không được lưu vết — orders chỉ có discount_total tổng.
 *
 * Fix: cột JSON orders.discount_breakdown — MỖI nguồn giảm ghi dòng
 * {source, amount thực áp, code?} tại thời điểm áp: voucher (từng mã),
 * referral (đơn đầu), email_bonus (đơn đầu), cap (điều chỉnh trần, số âm).
 * Bất biến: sum(breakdown.amount) === discount_total.
 */
class DiscountBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private Product $tent; // 100k/ngày, kho 5

    protected function setUp(): void
    {
        parent::setUp();

        // Mặc định tắt email bonus để từng test tự bật thứ nó cần (cô lập nguồn giảm)
        PromotionSetting::current()->update([
            'email_bonus_enabled' => false,
            'max_discount_percent_per_order' => 50,
        ]);

        $cat = Category::create(['name' => 'Lều', 'slug' => 'leu']);
        $this->tent = Product::create([
            'category_id' => $cat->id, 'name' => 'Lều Test', 'slug' => 'leu-test',
            'price_per_day' => 100000, 'quantity' => 5,
        ]);
    }

    /** Đặt 1 đơn lẻ 3 ngày (base 300k) cho $user với payload phụ $extra. */
    private function checkout(User $user, array $extra = [])
    {
        return $this->actingAs($user)->post(route('order.store'), array_merge([
            'name' => $user->name,
            'phone' => $user->phone,
            'items' => [['product_id' => $this->tent->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-03']],
        ], $extra));
    }

    /** @test */
    public function email_bonus_is_recorded_with_source_line(): void
    {
        PromotionSetting::current()->update(['email_bonus_enabled' => true, 'email_bonus_discount_value' => 5]);
        $user = User::factory()->create(['phone' => '0911000001']); // email thật + verified (factory mặc định)

        $this->checkout($user)->assertSessionHas('order_code');

        $order = Order::latest('id')->first();
        $this->assertSame(15000, (int) $order->discount_total); // 5% × 300k
        // assertEquals: MySQL JSON chuẩn hoá thứ tự key (sqlite giữ nguyên) — nội dung mới là thứ cần so
        $this->assertEquals([['source' => 'email_bonus', 'amount' => 15000, 'percent' => true]], $order->discount_breakdown);
    }

    /** @test */
    public function each_voucher_records_its_actual_applied_amount(): void
    {
        $user = User::factory()->create(['phone' => '0911000002']);
        $v1 = $this->voucher($user, 'fixed', 30000);
        $v2 = $this->voucher($user, 'percent', 10);

        $this->checkout($user, ['voucher_codes' => [$v1->code, $v2->code]])->assertSessionHas('order_code');

        $order = Order::latest('id')->first();
        $this->assertSame(60000, (int) $order->discount_total); // 30k + 10%×300k
        $lines = collect($order->discount_breakdown);
        $this->assertSame(60000, (int) $lines->sum('amount'));
        $this->assertSame(30000, (int) $lines->firstWhere('code', $v1->code)['amount']);
        $this->assertSame(30000, (int) $lines->firstWhere('code', $v2->code)['amount']);
        $this->assertTrue($lines->every(fn ($l) => $l['source'] === 'voucher'));
    }

    /**
     * AC-8: voucher thường trên đơn TOÀN combo áp 0đ → không ghi dòng rác,
     * breakdown rỗng và discount_total = 0.
     *
     * @test
     */
    public function zero_applied_voucher_leaves_no_breakdown_line(): void
    {
        $user = User::factory()->create(['phone' => '0911000003']);
        $v = $this->voucher($user, 'percent', 10); // voucher thường

        $combo = Combo::create(['name' => 'Combo Test', 'slug' => 'combo-test', 'combo_price' => 80000]);
        $combo->items()->create(['product_id' => $this->tent->id, 'quantity' => 1]);

        $this->actingAs($user)->post(route('order.store'), [
            'name' => $user->name, 'phone' => $user->phone,
            'combos' => [['combo_id' => $combo->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-03']],
            'voucher_codes' => [$v->code],
        ])->assertSessionHas('order_code');

        $order = Order::latest('id')->first();
        $this->assertSame(0, (int) $order->discount_total);
        $this->assertEmpty($order->discount_breakdown ?? []);
    }

    /** @test */
    public function referral_first_order_discount_is_recorded(): void
    {
        PromotionSetting::current()->update([
            'referral_enabled' => true,
            'referee_discount_type' => 'percent',
            'referee_discount_value' => 10,
        ]);
        $referrer = User::factory()->create(['phone' => '0911000004']);
        ReferralCode::create(['user_id' => $referrer->id, 'code' => 'REFTEST']);
        $referee = User::factory()->create(['phone' => '0911000005']);

        $this->checkout($referee, ['referral_code' => 'REFTEST'])->assertSessionHas('order_code');

        $order = Order::latest('id')->first();
        $this->assertSame(30000, (int) $order->discount_total); // 10% × 300k
        $this->assertEquals([['source' => 'referral', 'amount' => 30000, 'code' => 'REFTEST', 'percent' => true]], $order->discount_breakdown);
    }

    /**
     * Van an toàn trần tổng: các nguồn cộng dồn vượt trần % → clamp và ghi
     * dòng điều chỉnh ÂM; sum(breakdown) vẫn khớp discount_total.
     *
     * @test
     */
    public function cap_clamp_is_recorded_as_negative_adjustment_line(): void
    {
        // Trần 10% (30k trên đơn 300k); email bonus 10% + voucher 10% → mỗi nguồn
        // tự cap 30k nhưng TỔNG 60k vượt trần → clamp về 30k
        PromotionSetting::current()->update([
            'max_discount_percent_per_order' => 10,
            'email_bonus_enabled' => true,
            'email_bonus_discount_value' => 10,
        ]);
        $user = User::factory()->create(['phone' => '0911000006']);
        $v = $this->voucher($user, 'percent', 10);

        $this->checkout($user, ['voucher_codes' => [$v->code]])->assertSessionHas('order_code');

        $order = Order::latest('id')->first();
        $this->assertSame(30000, (int) $order->discount_total);
        $lines = collect($order->discount_breakdown);
        $this->assertSame(30000, (int) $lines->sum('amount')); // bất biến sum = total
        $cap = $lines->firstWhere('source', 'cap');
        $this->assertNotNull($cap, 'Phải có dòng điều chỉnh trần');
        $this->assertSame(-30000, (int) $cap['amount']);
    }

    /** @test */
    public function admin_orders_payload_exposes_breakdown(): void
    {
        PromotionSetting::current()->update(['email_bonus_enabled' => true, 'email_bonus_discount_value' => 5]);
        $user = User::factory()->create(['phone' => '0911000007']);
        $this->checkout($user)->assertSessionHas('order_code');

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.orders'))->assertInertia(fn (Assert $page) => $page
            ->where('orders.0.discount_breakdown.0.source', 'email_bonus')
            ->where('orders.0.discount_breakdown.0.amount', 15000));
    }

    /** Đơn cũ (trước fix) không có breakdown → payload null, FE hiện fallback. */

    /** @test */
    public function legacy_order_without_breakdown_returns_null(): void
    {
        Order::create([
            'customer_name' => 'X', 'customer_phone' => '0900000000',
            'start_date' => '2030-07-10', 'end_date' => '2030-07-12',
            'total_price' => 300000, 'discount_total' => 51000, 'status' => 'pending',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.orders'))->assertInertia(fn (Assert $page) => $page
            ->where('orders.0.discount_total', 51000)
            ->where('orders.0.discount_breakdown', null));
    }

    // -------------------------------------------------------------------------

    private function voucher(User $user, string $type, int|float $value): Voucher
    {
        return Voucher::create([
            'user_id' => $user->id,
            'code' => 'VC'.strtoupper(uniqid()),
            'type' => $type,
            'value' => $value,
            'source' => 'manual_admin',
            'status' => 'active',
            'max_uses' => 1,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromotionSetting;
use App\Models\ReferralCode;
use App\Models\User;
use App\Services\OrderLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-wtuv (T8) — sweep các nơi đọc orders phải hiểu cha/con:
 * account (ẩn cha, hiện con), lookup (mã cha → các đợt), dashboard/stats/badge
 * (không đếm trùng cha), first-order eligibility (con cùng cụm không tính là "đơn trước").
 */
class ParentChildSweepTest extends TestCase
{
    use RefreshDatabase;

    private Product $p;

    protected function setUp(): void
    {
        parent::setUp();
        PromotionSetting::current()->update(['email_bonus_enabled' => false, 'max_discount_percent_per_order' => 50]);
        $cat = Category::create(['name' => 'Lều', 'slug' => 'leu']);
        $this->p = Product::create(['category_id' => $cat->id, 'name' => 'A', 'slug' => 'a', 'price_per_day' => 100000, 'quantity' => 9, 'deposit' => 0]);
    }

    private function checkoutMulti(User $user, array $extra = []): Order
    {
        $this->actingAs($user)->post(route('order.store'), array_merge([
            'name' => $user->name, 'phone' => $user->phone,
            'items' => [
                ['product_id' => $this->p->id, 'quantity' => 1, 'start' => '2030-09-01', 'end' => '2030-09-02'],
                ['product_id' => $this->p->id, 'quantity' => 1, 'start' => '2030-09-05', 'end' => '2030-09-06'],
            ],
        ], $extra))->assertSessionHas('order_code');

        return Order::where('is_parent', true)->latest('id')->firstOrFail();
    }

    /** @test */
    public function account_hides_parent_and_shows_children_as_installments(): void
    {
        $user = User::factory()->create(['phone' => '0912000001']);
        $parent = $this->checkoutMulti($user);

        $props = $this->actingAs($user)->get(route('account'))->inertiaProps();
        $codes = collect($props['orders'])->pluck('code')->sort()->values()->all();
        $this->assertCount(2, $props['orders']); // 2 con, KHÔNG có cha
        $this->assertSame([$parent->code.'-1', $parent->code.'-2'], $codes);
        // Cha (is_parent) không xuất hiện trong danh sách tài khoản.
        $this->assertNotContains($parent->code, collect($props['orders'])->pluck('code')->all());
    }

    /** @test */
    public function lookup_by_parent_code_returns_installments(): void
    {
        $user = User::factory()->create(['phone' => '0912000002']);
        $parent = $this->checkoutMulti($user);

        $result = app(OrderLookupService::class)->find($parent->code, '0912000002');
        $this->assertNotNull($result);
        $this->assertCount(2, $result['installments']);
        $this->assertSame($parent->code.'-1', $result['installments'][0]['code']);

        // Tra mã CON đi nhánh thường (đơn đầy đủ, không có installments).
        $child = app(OrderLookupService::class)->find($parent->code.'-1', '0912000002');
        $this->assertArrayNotHasKey('installments', $child);
    }

    /** @test */
    public function dashboard_and_stats_do_not_double_count_parent(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->checkoutMulti(User::factory()->create(['phone' => '0912000003'])); // 1 cha + 2 con

        // Dashboard: total = 2 con (không tính cha).
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('stats.total', 2)->where('stats.pending', 2));

        // Stats: order_counts.total = 2.
        $this->actingAs($admin)->get(route('admin.stats'))
            ->assertInertia(fn (Assert $page) => $page->where('order_counts.total', 2));
    }

    /** @test */
    public function child_orders_in_same_group_do_not_block_first_order_referral(): void
    {
        // Referrer + mã giới thiệu.
        $referrer = User::factory()->create(['phone' => '0912000009']);
        $code = ReferralCode::create(['user_id' => $referrer->id, 'code' => 'REF123']);
        PromotionSetting::current()->update(['referral_enabled' => true, 'referee_discount_type' => 'percent', 'referee_discount_value' => 10]);

        // Referee đặt đơn GỘP ĐẦU TIÊN kèm mã giới thiệu → phải được giảm (con cùng cụm
        // KHÔNG bị coi là "đơn trước đó").
        $referee = User::factory()->create(['phone' => '0912000010']);
        $parent = $this->checkoutMulti($referee, ['referral_code' => 'REF123']);

        // Referral được ghi nhận + cha có giảm (10% tổng) phân bổ xuống con.
        $this->assertGreaterThan(0, (int) $parent->fresh()->discount_total);
        $this->assertSame((int) $parent->fresh()->discount_total, (int) $parent->children()->sum('discount_total'));
    }
}

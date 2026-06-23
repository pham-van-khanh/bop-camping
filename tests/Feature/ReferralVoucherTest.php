<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromotionSetting;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Promotion\VoucherService;
use App\Services\Referral\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReferralVoucherTest extends TestCase
{
    use RefreshDatabase;

    // ---- VAN AN TOÀN: trần giảm (mục 4 của spec) ----------------------------

    /** @test */
    public function voucher_cap_examples_from_spec(): void
    {
        $settings = PromotionSetting::current(); // max_discount_percent_per_order = 25
        $svc = new VoucherService;

        // 200k, 1× 30k fixed → 30k (dưới trần 50k)
        $this->assertSame(30000, $svc->quote(200000, $this->fixedVouchers([30000]), $settings)['total']);

        // 200k, 2× 30k → trần 50k
        $this->assertSame(50000, $svc->quote(200000, $this->fixedVouchers([30000, 30000]), $settings)['total']);

        // 100k, 2× 30k → trần 25k
        $this->assertSame(25000, $svc->quote(100000, $this->fixedVouchers([30000, 30000]), $settings)['total']);
    }

    /** @test */
    public function voucher_total_never_exceeds_order_value(): void
    {
        $settings = PromotionSetting::current();
        $settings->max_discount_percent_per_order = 100;
        $svc = new VoucherService;

        // Giảm 999k trên đơn 100k → clamp về 100k (không cho đơn âm)
        $this->assertSame(100000, $svc->quote(100000, $this->fixedVouchers([999000]), $settings)['total']);
    }

    // ---- Voucher apply / hết hạn --------------------------------------------

    /** @test */
    public function voucher_is_applied_and_marked_used_at_checkout(): void
    {
        $user = User::create(['name' => 'Khách', 'phone' => '0900000001']);
        $order = $this->order($user, 200000);
        $voucher = $this->voucher($user, 'VC-AAA111', 30000);

        $result = (new VoucherService)->apply($order, ['vc-aaa111']); // case-insensitive

        $this->assertSame(30000, $result['total']);
        $order->refresh();
        $this->assertSame(30000, (int) $order->discount_total);

        $voucher->refresh();
        $this->assertSame('used', $voucher->status);
        $this->assertSame(1, $voucher->used_count);
        $this->assertSame($order->id, $voucher->order_id);
    }

    /** @test */
    public function expired_voucher_is_not_applied(): void
    {
        $user = User::create(['name' => 'Khách', 'phone' => '0900000002']);
        $order = $this->order($user, 200000);
        $this->voucher($user, 'VC-OLD', 30000, expiresAt: Carbon::now()->subDay());

        $result = (new VoucherService)->apply($order, ['VC-OLD']);

        $this->assertSame(0, $result['total']);
        $this->assertSame(0, (int) $order->fresh()->discount_total);
    }

    /** @test */
    public function stacking_is_limited_to_setting(): void
    {
        $settings = PromotionSetting::current(); // max_vouchers_stack_per_order = 2
        $user = User::create(['name' => 'Khách', 'phone' => '0900000003']);
        $this->voucher($user, 'VC-1', 10000, expiresAt: Carbon::now()->addDays(5));
        $this->voucher($user, 'VC-2', 10000, expiresAt: Carbon::now()->addDays(10));
        $this->voucher($user, 'VC-3', 10000, expiresAt: Carbon::now()->addDays(20));

        $eligible = (new VoucherService)->eligibleVouchers($user, 500000, $settings, ['VC-1', 'VC-2', 'VC-3']);

        $this->assertCount(2, $eligible); // chỉ 2, ưu tiên hết hạn sớm
        $this->assertSame(['VC-1', 'VC-2'], $eligible->pluck('code')->all());
    }

    // ---- Referee đơn đầu -----------------------------------------------------

    /** @test */
    public function referee_first_order_gets_percent_discount(): void
    {
        [$referrer, $referee] = $this->pair();
        $order = $this->order($referee, 200000);

        $result = app(ReferralService::class)->applyRefereeFirstOrderDiscount($order, $this->codeOf($referrer));

        $this->assertSame('ok', $result['reason']);
        $this->assertSame(20000, $result['discount']); // 10% của 200k
        $this->assertDatabaseHas('referrals', [
            'referrer_id' => $referrer->id, 'referee_id' => $referee->id, 'status' => 'pending',
        ]);
    }

    /** @test */
    public function self_referral_is_rejected(): void
    {
        $user = User::create(['name' => 'Tự kỷ', 'phone' => '0900000010']);
        $order = $this->order($user, 200000);

        $result = app(ReferralService::class)->applyRefereeFirstOrderDiscount($order, $this->codeOf($user));

        $this->assertSame('self_referral', $result['reason']);
        $this->assertSame(0, $result['discount']);
    }

    /** @test */
    public function discount_rejected_when_not_first_order(): void
    {
        [$referrer, $referee] = $this->pair();
        $this->order($referee, 150000); // đơn cũ
        $order = $this->order($referee, 200000);

        $result = app(ReferralService::class)->applyRefereeFirstOrderDiscount($order, $this->codeOf($referrer));

        $this->assertSame('not_first_order', $result['reason']);
    }

    // ---- Conversion + thưởng referrer ---------------------------------------

    /** @test */
    public function conversion_on_returned_issues_referrer_voucher(): void
    {
        [$referrer, $referee] = $this->pair();
        $order = $this->order($referee, 200000);
        app(ReferralService::class)->applyRefereeFirstOrderDiscount($order, $this->codeOf($referrer));

        $order->update(['status' => 'returned']); // observer → conversion

        $this->assertDatabaseHas('referrals', ['referee_id' => $referee->id, 'status' => 'converted']);
        $voucher = Voucher::where('user_id', $referrer->id)->where('source', 'referral_reward')->first();
        $this->assertNotNull($voucher);
        $this->assertSame('30000.00', $voucher->value); // fixed 30k mặc định
        $this->assertSame('active', $voucher->status);
    }

    /** @test */
    public function reward_is_cumulative_per_referral(): void
    {
        $referrer = User::create(['name' => 'Mời', 'phone' => '0900000020']);
        $code = $this->codeOf($referrer);

        foreach (['0900000021', '0900000022'] as $phone) {
            $referee = User::create(['name' => 'Khách', 'phone' => $phone]);
            $order = $this->order($referee, 200000);
            app(ReferralService::class)->applyRefereeFirstOrderDiscount($order, $code);
            $order->update(['status' => 'returned']);
        }

        $this->assertSame(2, Voucher::where('user_id', $referrer->id)->where('source', 'referral_reward')->count());
    }

    /** @test */
    public function clawback_revokes_unused_voucher_when_order_cancelled(): void
    {
        [$referrer, $referee] = $this->pair();
        $order = $this->order($referee, 200000);
        app(ReferralService::class)->applyRefereeFirstOrderDiscount($order, $this->codeOf($referrer));
        $order->update(['status' => 'returned']); // convert + thưởng

        $order->update(['status' => 'cancelled']); // huỷ sau khi convert

        $this->assertDatabaseHas('referrals', ['referee_id' => $referee->id, 'status' => 'rejected']);
        $this->assertSame(1, Voucher::where('user_id', $referrer->id)->where('status', 'revoked')->count());
    }

    // ---- HTTP: checkout + trang tài khoản ------------------------------------

    /** @test */
    public function login_with_ref_stores_code_in_session_for_checkout(): void
    {
        $referrer = User::create(['name' => 'Người mời', 'phone' => '0900000200']);
        $code = $referrer->getReferralCode();

        $this->post(route('guest.login'), [
            'name' => 'Khách Mới', 'phone' => '0911112222', 'ref' => $code,
        ])->assertRedirect();

        $this->assertSame($code, session('referral_ref'));
    }

    /** @test */
    public function checkout_applies_referral_code_for_first_order(): void
    {
        [$referrer, $referee] = $this->pair();
        $product = $this->product(); // 100k/ngày
        $day = Carbon::today()->addDays(3)->toDateString();

        $this->actingAs($referee)->post(route('order.store'), [
            'name' => 'Khách được mời',
            'phone' => $referee->phone,
            'address' => 'Số 1 Đường ABC',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'start' => $day, 'end' => $day]],
            'referral_code' => $this->codeOf($referrer),
        ])->assertSessionHas('order_discount', 10000); // 10% của 100k

        $this->assertDatabaseHas('referrals', ['referrer_id' => $referrer->id, 'referee_id' => $referee->id]);
    }

    /** @test */
    public function guests_cannot_view_account_but_users_see_code_and_vouchers(): void
    {
        $this->get(route('account'))->assertRedirect();

        $user = User::create(['name' => 'Khách', 'phone' => '0900000030']);
        $this->voucher($user, 'VC-SHOW', 30000);

        $this->actingAs($user)->get(route('account'))->assertInertia(fn (Assert $page) => $page
            ->component('Account')
            ->has('referralCode')
            ->has('vouchers', 1)
            ->where('vouchers.0.code', 'VC-SHOW'));
    }

    // ---- Helpers -------------------------------------------------------------

    /** @return Collection<int, Voucher> */
    private function fixedVouchers(array $amounts): Collection
    {
        return collect($amounts)->map(fn ($a, $i) => new Voucher([
            'code' => 'X'.$i, 'type' => 'fixed', 'value' => $a,
        ]))->values();
    }

    /** @return array{0: User, 1: User} */
    private function pair(): array
    {
        $referrer = User::create(['name' => 'Người mời', 'phone' => '0900000100']);
        $referee = User::create(['name' => 'Khách mới', 'phone' => '0900000101']);

        return [$referrer, $referee];
    }

    private function codeOf(User $user): string
    {
        return $user->getReferralCode();
    }

    private function order(User $user, int $total): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'customer_name' => $user->name,
            'customer_phone' => $user->phone,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-02',
            'total_price' => $total,
            'status' => 'pending',
            'payment_method' => 'cod',
        ]);
    }

    private function voucher(User $user, string $code, int $value, ?Carbon $expiresAt = null): Voucher
    {
        return Voucher::create([
            'user_id' => $user->id,
            'code' => $code,
            'type' => 'fixed',
            'value' => $value,
            'source' => 'manual_admin',
            'status' => 'active',
            'max_uses' => 1,
            'expires_at' => $expiresAt,
        ]);
    }

    private function product(): Product
    {
        $category = Category::create(['name' => 'Test', 'slug' => 'test-'.uniqid()]);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Lều Test',
            'slug' => 'leu-'.uniqid(),
            'price_per_day' => 100000,
            'quantity' => 10,
        ]);
    }
}

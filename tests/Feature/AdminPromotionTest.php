<?php

namespace Tests\Feature;

use App\Models\PromotionSetting;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminPromotionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'phone' => '0900000001', 'is_admin' => true]);
    }

    /** @test */
    public function guest_and_non_admin_cannot_access_promotion_pages(): void
    {
        $this->get(route('admin.promotion'))->assertRedirect();
        $this->get(route('admin.vouchers'))->assertRedirect();
        $this->get(route('admin.referrals'))->assertRedirect();

        $customer = User::create(['name' => 'Khách', 'phone' => '0911111111']);
        $this->actingAs($customer)->get(route('admin.promotion'))->assertRedirect();
    }

    /** @test */
    public function admin_can_view_and_update_promotion_settings(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.promotion'))
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Promotion')->has('settings'));

        $payload = array_merge(PromotionSetting::current()->only([
            'referral_enabled', 'referee_discount_type', 'referee_discount_value',
            'referrer_reward_type', 'referrer_reward_value', 'referrer_reward_per_referral',
            'max_discount_percent_per_order', 'max_vouchers_stack_per_order', 'voucher_validity_days',
            'min_order_amount', 'discount_applies_to', 'conversion_trigger_status',
            'max_referrals_per_user_per_month', 'reward_clawback_enabled',
            'email_bonus_enabled', 'email_bonus_discount_type', 'email_bonus_discount_value',
        ]), ['referee_discount_value' => 15, 'max_discount_percent_per_order' => 30]);

        $this->actingAs($admin)->put(route('admin.promotion.update'), $payload)
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame('15.00', PromotionSetting::current()->referee_discount_value);
        $this->assertSame('30.00', PromotionSetting::current()->max_discount_percent_per_order);
    }

    /** @test */
    public function update_rejects_cap_over_100_percent(): void
    {
        $admin = $this->admin();
        $payload = array_merge(PromotionSetting::current()->toArray(), ['max_discount_percent_per_order' => 150]);

        $this->actingAs($admin)->put(route('admin.promotion.update'), $payload)
            ->assertSessionHasErrors('max_discount_percent_per_order');
    }

    /** @test */
    public function admin_can_create_and_revoke_voucher(): void
    {
        $admin = $this->admin();
        $customer = User::create(['name' => 'Khách', 'phone' => '0922222222']);

        $this->actingAs($admin)->post(route('admin.vouchers.store'), [
            'phone' => '0922222222', 'type' => 'fixed', 'value' => 25000,
        ])->assertRedirect()->assertSessionHas('success');

        $voucher = Voucher::where('user_id', $customer->id)->first();
        $this->assertNotNull($voucher);
        $this->assertSame('manual_admin', $voucher->source);

        $this->actingAs($admin)->patch(route('admin.vouchers.revoke', $voucher))->assertRedirect();
        $this->assertSame('revoked', $voucher->fresh()->status);
    }

    /** @test Regression: validity_days gửi dạng string (như form thật) không làm vỡ addDays(). */
    public function admin_can_create_voucher_with_validity_days_from_form(): void
    {
        $admin = $this->admin();
        $customer = User::create(['name' => 'Khách', 'phone' => '0933333333']);

        $this->actingAs($admin)->post(route('admin.vouchers.store'), [
            'phone' => '0933333333', 'type' => 'fixed', 'value' => '30000', 'validity_days' => '45',
        ])->assertRedirect()->assertSessionHas('success')->assertSessionHasNoErrors();

        $voucher = Voucher::where('user_id', $customer->id)->firstOrFail();
        $this->assertNotNull($voucher->expires_at);
        $this->assertSame(45, (int) round(now()->diffInDays($voucher->expires_at, false)));
    }

    /**
     * AC-8 (bopcamping-1od): admin bật "Áp dụng cả combo" → voucher giảm được
     * phần combo; không gửi cờ → mặc định false (voucher thường).
     *
     * @test
     */
    public function admin_can_create_combo_applicable_voucher(): void
    {
        $admin = $this->admin();
        User::create(['name' => 'Khách', 'phone' => '0944444444']);

        $this->actingAs($admin)->post(route('admin.vouchers.store'), [
            'phone' => '0944444444', 'type' => 'percent', 'value' => 10, 'applicable_to_combos' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue((bool) Voucher::latest('id')->first()->applicable_to_combos);

        $this->actingAs($admin)->post(route('admin.vouchers.store'), [
            'phone' => '0944444444', 'type' => 'percent', 'value' => 10,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertFalse((bool) Voucher::latest('id')->first()->applicable_to_combos);
    }
}

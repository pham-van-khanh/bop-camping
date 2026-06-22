<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromotionSetting;
use App\Models\Referral;
use App\Models\Voucher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Promotion', [
            'settings' => PromotionSetting::current(),
            'stats' => [
                'referrals_this_month' => Referral::where('status', 'converted')
                    ->whereBetween('converted_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
                'vouchers_active' => Voucher::where('status', 'active')->count(),
                'vouchers_used' => Voucher::where('status', 'used')->count(),
                'voucher_value_issued' => (int) Voucher::sum('value'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'referral_enabled' => ['required', 'boolean'],
            'referee_discount_type' => ['required', 'in:fixed,percent'],
            'referee_discount_value' => ['required', 'numeric', 'min:0'],
            'referrer_reward_type' => ['required', 'in:fixed,percent'],
            'referrer_reward_value' => ['required', 'numeric', 'min:0'],
            'referrer_reward_per_referral' => ['required', 'boolean'],
            'max_discount_percent_per_order' => ['required', 'numeric', 'min:0', 'max:100'],
            'max_vouchers_stack_per_order' => ['required', 'integer', 'min:1', 'max:10'],
            'voucher_validity_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'min_order_amount' => ['required', 'numeric', 'min:0'],
            'discount_applies_to' => ['required', 'in:rental_fee,total'],
            'conversion_trigger_status' => ['required', 'in:pending,confirmed,renting,returned'],
            'max_referrals_per_user_per_month' => ['required', 'integer', 'min:0'],
            'reward_clawback_enabled' => ['required', 'boolean'],
        ]);

        PromotionSetting::current()->update($data);

        return back()->with('success', 'Đã lưu cấu hình khuyến mãi.');
    }
}

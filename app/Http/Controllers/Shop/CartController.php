<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PromotionSetting;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $settings = PromotionSetting::current();

        $vouchers = $user
            ? $user->vouchers()->usable()->orderByRaw('expires_at IS NULL')->orderBy('expires_at')->get()
                ->map(fn (Voucher $v) => [
                    'code' => $v->code,
                    'type' => $v->type,
                    'value' => (float) $v->value,
                    'source' => $v->source,
                    'expires_at' => $v->expires_at?->toDateString(),
                ])->values()
            : [];

        // Khách đủ điều kiện nhập mã giới thiệu nếu CHƯA có đơn nào (đơn đầu).
        $firstOrderEligible = $user
            ? ! Order::where('user_id', $user->id)->exists()
            : false;

        return Inertia::render('Cart', [
            'availableVouchers' => $vouchers,
            'referralRef' => $request->session()->get('referral_ref', ''),
            'firstOrderEligible' => $firstOrderEligible,
            'promo' => [
                'enabled' => (bool) $settings->referral_enabled,
                'maxDiscountPercent' => (float) $settings->max_discount_percent_per_order,
                'maxStack' => (int) $settings->max_vouchers_stack_per_order,
                'minOrderAmount' => (float) $settings->min_order_amount,
                'refereeDiscountType' => $settings->referee_discount_type,
                'refereeDiscountValue' => (float) $settings->referee_discount_value,
            ],
        ]);
    }
}

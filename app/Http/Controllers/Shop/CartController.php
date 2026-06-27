<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromotionSetting;
use App\Models\ServiceLocation;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
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
            // Thông tin chuyển khoản (khách trả trước cọc / toàn bộ).
            'bank' => [
                'name' => config('shop.bank.name'),
                'account_number' => config('shop.bank.account_number'),
                'account_holder' => config('shop.bank.account_holder'),
            ],
        ]);
    }

    /**
     * GET /gio-thue/lam-tuoi?ids[]=1&ids[]=2 — làm tươi giỏ.
     *
     * Giỏ nằm ở localStorage nên lưu "ảnh chụp" giá/vị trí lúc thêm món. Admin có thể đổi
     * giá/vị trí/ẩn sản phẩm sau đó → trả dữ liệu MỚI NHẤT để client đồng bộ lại. Sản phẩm
     * đã ẩn/xoá sẽ KHÔNG có trong kết quả → client gỡ khỏi giỏ.
     */
    public function refresh(Request $request): JsonResponse
    {
        $ids = collect($request->query('ids', []))
            ->map(fn ($x) => (int) $x)
            ->filter()
            ->unique()
            ->take(100)
            ->values();

        if ($ids->isEmpty()) {
            return response()->json(['products' => (object) []]);
        }

        $openCount = ServiceLocation::open()->count();

        $products = Product::active()
            ->with('serviceLocations')
            ->whereIn('id', $ids)
            ->get()
            ->mapWithKeys(function (Product $p) use ($openCount) {
                $open = $p->serviceLocations->where('status', 'open');

                return [$p->id => [
                    'name' => $p->name,
                    'price_per_day' => (int) $p->price_per_day,
                    'deposit' => (int) ($p->deposit ?? 0),
                    'locations' => $open->map(fn (ServiceLocation $l) => ['slug' => $l->slug, 'name' => $l->name])->values(),
                    'all_locations' => $openCount > 0 && $open->count() === $openCount,
                ]];
            });

        return response()->json(['products' => $products]);
    }
}

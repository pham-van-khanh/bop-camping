<?php

namespace App\Http\Middleware;

use App\Models\ReferralCode;
use App\Models\Review;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                // Chỉ chia sẻ field cần thiết — tránh lộ cả model ra client (CWE-200).
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                ] : null,
            ],
            'flash' => [
                'order_code' => session('order_code'),
                'order_name' => session('order_name'),
                'order_phone' => session('order_phone'),
                'order_pay' => session('order_pay'),
                'order_discount' => session('order_discount'),
                'order_items' => session('order_items'),
                'success' => session('success'),
                // Đăng nhập OTP: tín hiệu cho LoginModal chuyển sang bước nhập mã.
                'otp_sent' => session('otp_sent'),
                'otp_email' => session('otp_email'),
            ],
            // Mã giới thiệu đang chờ (từ link ?ref= hoặc nhập tay) — để hiện popup + prefill.
            // Lazy: resolve khi render (SAU khi CaptureReferralCode lưu session) — nếu eager sẽ rỗng ở request đầu.
            'referral' => fn () => $this->sharedReferral($request),
            // Số đánh giá chờ duyệt — badge sidebar admin (chỉ tính cho admin).
            'pending_reviews' => fn () => $request->user()?->is_admin
                ? Review::where('status', 'pending')->count()
                : null,
        ];
    }

    /** @return array{code: string, referrer_name: string|null}|null */
    private function sharedReferral(Request $request): ?array
    {
        $ref = $request->session()->get('referral_ref');
        if (! $ref) {
            return null;
        }

        $rc = ReferralCode::with('user:id,name')
            ->whereRaw('UPPER(code) = ?', [strtoupper($ref)])
            ->first();

        return [
            'code' => $rc?->code ?? strtoupper($ref),
            'referrer_name' => $rc?->user?->name,
        ];
    }
}

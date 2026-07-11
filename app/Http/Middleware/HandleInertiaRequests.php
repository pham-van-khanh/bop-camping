<?php

namespace App\Http\Middleware;

use App\Models\Feedback;
use App\Models\Order;
use App\Models\PromotionSetting;
use App\Models\ReferralCode;
use App\Models\Review;
use App\Models\ServiceLocation;
use App\Models\SiteSetting;
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
            // Ưu đãi khuyến khích thêm email (đơn đầu) — LoginModal hiện % lấy từ đây, KHÔNG hardcode.
            'emailBonus' => fn () => $this->sharedEmailBonus(),
            // Số đánh giá chờ duyệt — badge sidebar admin (chỉ tính cho admin).
            'pending_reviews' => fn () => $request->user()?->is_admin
                ? Review::where('status', 'pending')->count()
                : null,
            // Số đơn mới (chờ xác nhận) — badge sidebar admin ở mục Đơn thuê.
            'pending_orders' => fn () => $request->user()?->is_admin
                ? Order::where('status', 'pending')->count()
                : null,
            // Số góp ý chưa phản hồi — badge sidebar admin ở mục Góp ý (Epic 2).
            'pending_feedback' => fn () => $request->user()?->is_admin
                ? Feedback::where('status', 'new')->count()
                : null,
            // Thông tin liên hệ/mạng xã hội (footer + dải Zalo đọc chung) — lazy, 1 row.
            'site' => fn () => $this->sharedSite(),
            // SEO mặc định site-wide (controller có thể ghi đè bằng prop 'seo'); blade dựng meta head.
            'seo' => [
                'url' => $request->url(),
            ],
        ];
    }

    /**
     * Thông tin liên hệ dùng chung cho footer + dải Zalo. Zalo url đã resolve sẵn
     * (áp zalo.me/<sđt> khi trống); địa chỉ lấy từ ServiceLocation đang mở.
     *
     * @return array<string, mixed>
     */
    private function sharedSite(): array
    {
        $s = SiteSetting::current();

        return [
            'hotline_primary' => $s->hotline_primary,
            'hotline_secondary' => $s->hotline_secondary,
            'zalo_1' => ['label' => $s->zalo1_label, 'phone' => $s->zalo1_phone, 'url' => $s->zaloUrl(1)],
            'zalo_2' => ['label' => $s->zalo2_label, 'phone' => $s->zalo2_phone, 'url' => $s->zaloUrl(2)],
            'facebook_url' => $s->facebook_url,
            'tiktok_url' => $s->tiktok_url,
            'working_hours' => $s->working_hours,
            'addresses' => ServiceLocation::open()->ordered()->get(['name', 'area'])
                ->map(fn (ServiceLocation $l) => ['name' => $l->name, 'area' => $l->area])
                ->values(),
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

    /** @return array{enabled: bool, type: string, value: float} */
    private function sharedEmailBonus(): array
    {
        $settings = PromotionSetting::current();

        return [
            'enabled' => $settings->email_bonus_enabled,
            'type' => $settings->email_bonus_discount_type,
            'value' => (float) $settings->email_bonus_discount_value,
        ];
    }
}

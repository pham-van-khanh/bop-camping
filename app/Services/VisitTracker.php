<?php

namespace App\Services;

use App\Models\Product;
use App\Models\VisitEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Ghi nhận truy cập của khách (visit_events) và báo Telegram khi họ bấm liên hệ.
 *
 * KHÔNG BAO GIỜ ném lỗi: thống kê hỏng không được làm hỏng trang khách. Không đếm bot,
 * admin, người đang xem-hộ khách (impersonation).
 */
class VisitTracker
{
    public const COOKIE = 'bop_vid';

    private const COOKIE_MINUTES = 525600; // 1 năm

    /** Cùng 1 khách bấm cùng 1 kênh trong khoảng này chỉ báo Telegram 1 lần. */
    private const NOTIFY_COOLDOWN_MINUTES = 10;

    private const BOT_UA = '/bot|crawl|spider|slurp|facebookexternalhit|preview|headless|lighthouse|pingdom|uptime|monitor|curl|wget|python|axios|go-http|java\//i';

    private const METHOD_LABEL = ['zalo' => 'Zalo', 'phone' => 'gọi điện', 'messenger' => 'Messenger'];

    public function __construct(
        private AttributionService $attribution,
        private TelegramNotifier $telegram,
    ) {}

    /** Mã khách ẩn danh từ cookie; chưa có thì cấp mới (đặt cookie 1 năm). */
    public function visitorId(Request $request): string
    {
        $existing = $request->attributes->get('visitor_id') ?? $request->cookie(self::COOKIE);
        if (is_string($existing) && Str::isUuid($existing)) {
            return $existing;
        }

        $id = (string) Str::uuid();
        $request->attributes->set('visitor_id', $id);
        Cookie::queue(cookie(self::COOKIE, $id, self::COOKIE_MINUTES));

        return $id;
    }

    public function countable(Request $request): bool
    {
        if (preg_match(self::BOT_UA, (string) $request->userAgent()) || $request->userAgent() === null) {
            return false;
        }
        if ($request->session()->has('impersonator_id')) {
            return false;
        }
        $user = Auth::user();

        return ! ($user && ($user->is_admin || $user->is_shipper));
    }

    public function recordPage(Request $request): void
    {
        $this->safely(function () use ($request) {
            // ProductController::show nhận slug dạng chuỗi (không phải model đã bind).
            $slug = $request->route('product');
            $productId = $request->route()?->getName() === 'products.show' && is_string($slug)
                ? Product::where('slug', $slug)->value('id')
                : null;

            VisitEvent::create([
                'visitor_id' => $this->visitorId($request),
                'type' => $productId ? VisitEvent::TYPE_PRODUCT : VisitEvent::TYPE_PAGE,
                'path' => '/'.ltrim($request->path(), '/'),
                'product_id' => $productId,
                'source' => $this->source($request),
                'user_id' => Auth::id(),
                'created_at' => now(),
            ]);
        });
    }

    public function recordContact(Request $request, string $method, ?string $placement, ?string $path): void
    {
        $this->safely(function () use ($request, $method, $placement, $path) {
            $path = $this->cleanPath($path) ?? $this->cleanPath(parse_url((string) $request->headers->get('referer'), PHP_URL_PATH)) ?? '/';
            $product = preg_match('#^/thiet-bi/([^/]+)$#', $path, $m) ? Product::where('slug', $m[1])->first() : null;
            $visitor = $this->visitorId($request);
            $user = Auth::user();

            VisitEvent::create([
                'visitor_id' => $visitor,
                'type' => VisitEvent::TYPE_CONTACT,
                'path' => $path,
                'product_id' => $product?->id,
                'method' => $method,
                'placement' => $placement !== null ? Str::limit($placement, 40, '') : null,
                'source' => $this->source($request),
                'user_id' => $user?->id,
                'created_at' => now(),
            ]);

            if (! Cache::add("contact-notify:{$visitor}:{$method}", 1, now()->addMinutes(self::NOTIFY_COOLDOWN_MINUTES))) {
                return;
            }

            $who = $user ? '<b>'.e($user->name).'</b> · '.e($user->phone).' (đã đăng nhập)' : 'Khách ẩn danh (chưa đăng nhập)';
            $this->telegram->send(
                '💬 <b>Có người bấm '.self::METHOD_LABEL[$method].'</b>'."\n"
                .'Đang xem: '.e($product?->name ?? $path)."\n"
                .'Nguồn: '.e($this->source($request))."\n"
                .'Khách: '.$who."\n"
                .'<i>Mới bấm nút, chưa chắc đã nhắn.</i>'
            );
        });
    }

    private function source(Request $request): string
    {
        $data = $this->attribution->fromCookie($request) ?? $this->attribution->fromRequest($request) ?? [];

        return AttributionService::label($data['utm_source'] ?? null, $data['referrer_host'] ?? null, $data['click_id_type'] ?? null);
    }

    private function cleanPath(mixed $path): ?string
    {
        if (! is_string($path) || ! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return null;
        }

        return Str::limit(strtok($path, '?#') ?: '/', 255, '');
    }

    private function safely(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}

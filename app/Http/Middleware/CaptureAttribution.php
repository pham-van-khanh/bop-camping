<?php

namespace App\Http\Middleware;

use App\Services\AttributionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bắt nguồn khách (UTM / click id quảng cáo / referrer ngoài) lúc vào trang và lưu
 * FIRST-TOUCH vào cookie `bop_attr` 30 ngày (bopcamping-wi6h). Lúc đặt đơn,
 * Shop\OrderController đọc lại qua AttributionService::forOrder().
 *
 * - Đã có cookie → giữ nguyên (first-touch: nguồn đầu tiên đưa khách tới mới được ghi công).
 * - Chỉ GET, bỏ /admin và /shipper (nhân viên không phải khách).
 */
class CaptureAttribution
{
    public function __construct(private AttributionService $attribution) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET')
            || $request->is('admin', 'admin/*', 'shipper', 'shipper/*')
            // Đọc được cookie hợp lệ thì giữ (first-touch). Cookie hỏng/không giải mã được
            // (vd đổi APP_KEY → EncryptCookies trả null) coi như chưa có, để còn ghi lại.
            || $this->attribution->fromCookie($request) !== null) {
            return $response;
        }

        $data = $this->attribution->fromRequest($request);
        if ($data !== null) {
            $response->headers->setCookie(cookie(
                AttributionService::COOKIE,
                $this->attribution->encode($data),
                AttributionService::TTL_MINUTES,
            ));
        }

        return $response;
    }
}

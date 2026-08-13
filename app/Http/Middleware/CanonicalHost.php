<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ép mọi request về đúng MỘT tên miền — cái khai trong APP_URL (bopcamping-1xja).
 *
 * VẤN ĐỀ ĐÃ ĐO trên production 13/08/2026: https://www.bopcamping.com phục vụ trọn vẹn
 * cả website, và canonical của nó tự trỏ về chính www (vì url()->current() lấy host từ
 * request). Google thấy HAI bản sao độc lập của toàn bộ site: tín hiệu xếp hạng bị chia
 * đôi, crawl budget tiêu gấp đôi cho cùng một nội dung.
 *
 * Chuẩn hoá ở tầng ứng dụng thay vì chờ sửa Nginx: cách này đi theo repo, chạy giống
 * nhau ở mọi môi trường, và có test.
 *
 * 301 (vĩnh viễn) chứ không phải 302 — đây là quyết định lâu dài, và chỉ 301 mới dồn
 * được sức mạnh liên kết của www về non-www.
 *
 * CHỈ áp cho GET/HEAD: chuyển hướng một POST sẽ làm mất body, khách đang đặt đơn mà gõ
 * nhầm www thì đơn bay mất. Với method khác, để request chạy tiếp — canonical trong
 * <head> vẫn trỏ đúng nhờ APP_URL.
 */
class CanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $canonical = parse_url((string) config('app.url'), PHP_URL_HOST);

        // Không cấu hình APP_URL tử tế (vd 'http://localhost' lúc dev) thì đứng yên —
        // ép sai host còn tệ hơn không ép.
        if (! $canonical || $canonical === 'localhost' || ! $request->isMethodSafe()) {
            return $next($request);
        }

        if (strcasecmp($request->getHost(), $canonical) === 0) {
            return $next($request);
        }

        // Giữ nguyên đường dẫn + query, chỉ đổi host.
        $target = $request->getSchemeAndHttpHost();
        $target = str_ireplace('://'.$request->getHost(), '://'.$canonical, $target);

        return redirect()->away($target.$request->getRequestUri(), 301);
    }
}

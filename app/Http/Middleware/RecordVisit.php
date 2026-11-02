<?php

namespace App\Http\Middleware;

use App\Services\VisitTracker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ghi lượt xem trang khách vào visit_events. Chạy cho cả lần tải đầu lẫn điều hướng SPA
 * của Inertia (cũng là GET). Bỏ: không phải GET, trang /admin /shipper /track, JSON thuần
 * (API tồn kho…), partial reload, prefetch, response không phải 200, bot, admin.
 */
class RecordVisit
{
    public function __construct(private VisitTracker $tracker) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldRecord($request, $response) && $this->tracker->countable($request)) {
            $this->tracker->recordPage($request);
        }

        return $response;
    }

    private function shouldRecord(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || $response->getStatusCode() !== 200) {
            return false;
        }
        if ($request->is('admin', 'admin/*', 'shipper', 'shipper/*', 'track/*', 'up', 'sitemap.xml')) {
            return false;
        }
        $inertia = $request->headers->has('X-Inertia');

        return ! (
            ($request->expectsJson() && ! $inertia)
            || $request->headers->has('X-Inertia-Partial-Data')
            || in_array(strtolower((string) ($request->headers->get('Purpose') ?? $request->headers->get('Sec-Purpose'))), ['prefetch', 'prefetch;prerender'], true)
        );
    }
}

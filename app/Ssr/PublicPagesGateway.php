<?php

namespace App\Ssr;

use Exception;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;
use Inertia\Ssr\HttpGateway;
use Inertia\Ssr\Response;
use Tighten\Ziggy\Ziggy;

/**
 * Render phía server CHỈ cho trang khách cần index (bopcamping-zvrx).
 *
 * Khác HttpGateway gốc ở ba điểm:
 *  1. Danh sách trắng component: admin/shipper/giỏ/tài khoản không cần SEO, render
 *     server chỉ thêm rủi ro và tốn CPU. Thêm trang khách công khai mới → thêm vào
 *     PAGES (skill seo có ghi trong checklist).
 *  2. Timeout ngắn: bản gốc dùng mặc định 30s của HTTP client — process SSR treo là
 *     mọi request treo theo. Ở đây quá hạn thì bỏ, trang rơi về render phía client
 *     như trước khi có SSR, khách không thấy gì khác.
 *  3. Gửi kèm cấu hình Ziggy để route() chạy được trong Node (ssr.tsx tách ra trước
 *     khi render, không lọt vào HTML).
 */
class PublicPagesGateway extends HttpGateway
{
    /** Component Inertia của các trang công khai trong sitemap. */
    public const PAGES = [
        'Welcome',
        'Products',
        'ProductDetail',
        'Combos',
        'ComboDetail',
        'About',
        'Policy',
        'Posts',
        'PostDetail',
        'AreaLanding',
        'CampingSpotIndex',
        'CampingSpotDetail',
    ];

    private const CONNECT_TIMEOUT = 1;

    private const TIMEOUT = 3;

    public function dispatch(array $page): ?Response
    {
        if (! in_array($page['component'] ?? null, self::PAGES, true) || ! $this->shouldDispatch()) {
            return null;
        }

        $request = request();
        $payload = $page + [
            'ziggy' => [
                ...(new Ziggy)->toArray(),
                'location' => $request->fullUrl(),
            ],
        ];

        try {
            $response = Http::connectTimeout(self::CONNECT_TIMEOUT)
                ->timeout(self::TIMEOUT)
                ->post($this->getUrl('/render'), $payload)
                ->throw()
                ->json();
        } catch (Exception $e) {
            if ($e instanceof StrayRequestException) {
                throw $e;
            }

            return null;
        }

        // Component lỗi khi render → Node trả rỗng → json() = null → render client.
        if (! is_array($response) || ! isset($response['body'], $response['head'])) {
            return null;
        }

        return new Response(implode("\n", $response['head']), $response['body']);
    }
}

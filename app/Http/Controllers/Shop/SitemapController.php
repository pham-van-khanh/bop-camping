<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Combo;
use App\Models\Product;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Sitemap động (Epic 3): sinh từ DB, cache 1 giờ. Trang mới/sản phẩm mới
 * tự xuất hiện sau tối đa 1h — không phải cập nhật tay.
 */
class SitemapController extends Controller
{
    public function index(): Response
    {
        $xml = Cache::remember('sitemap.xml', 3600, fn () => $this->build());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function build(): string
    {
        $urls = [
            ['loc' => url('/'), 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => url('/thiet-bi'), 'changefreq' => 'daily', 'priority' => '0.9'],
            ['loc' => url('/combos'), 'changefreq' => 'weekly', 'priority' => '0.8'],
            ['loc' => url('/gioi-thieu'), 'changefreq' => 'monthly', 'priority' => '0.6'],
        ];

        foreach (Category::orderBy('name')->get(['slug']) as $c) {
            $urls[] = ['loc' => url('/thiet-bi').'?cat='.$c->slug, 'changefreq' => 'weekly', 'priority' => '0.7'];
        }

        foreach (Product::active()->orderBy('slug')->get(['slug', 'updated_at']) as $p) {
            $urls[] = [
                'loc' => url('/thiet-bi/'.$p->slug),
                'lastmod' => $p->updated_at?->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ];
        }

        foreach (Combo::active()->orderBy('slug')->get(['slug', 'updated_at']) as $c) {
            $urls[] = [
                'loc' => url('/combos/'.$c->slug),
                'lastmod' => $c->updated_at?->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ];
        }

        $body = '';
        foreach ($urls as $u) {
            $body .= "  <url>\n    <loc>".htmlspecialchars($u['loc'], ENT_XML1)."</loc>\n";
            if (! empty($u['lastmod'])) {
                $body .= '    <lastmod>'.$u['lastmod']."</lastmod>\n";
            }
            $body .= '    <changefreq>'.$u['changefreq']."</changefreq>\n";
            $body .= '    <priority>'.$u['priority']."</priority>\n  </url>\n";
        }

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            ."<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n"
            .$body
            .'</urlset>';
    }
}

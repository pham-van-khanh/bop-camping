<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Combo;
use App\Models\Product;
use App\Models\StaticPage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Sitemap động (Epic 3): sinh từ DB, cache 1 giờ. Trang mới/sản phẩm mới
 * tự xuất hiện sau tối đa 1h — không phải cập nhật tay.
 */
class SitemapController extends Controller
{
    public function index(Request $request): Response
    {
        // Khoá cache TÁCH THEO HOST: nội dung chứa URL tuyệt đối dựng từ host của
        // request. Dùng chung một khoá thì bot vào bằng host phụ (vd www) sẽ nạp cache
        // toàn URL host đó, và mọi người nhận bản sai suốt 1 giờ (bopcamping-1xja).
        // CanonicalHost đã chuyển hướng www rồi, nhưng khoá cache vẫn phải đúng để lỗi
        // không quay lại nếu sau này thêm tên miền.
        $xml = Cache::remember('sitemap.xml:'.$request->getHost(), 3600, fn () => $this->build());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function build(): string
    {
        // lastmod phải LẤY TỪ DỮ LIỆU THẬT (bopcamping-10x2). Google chỉ tin trường này khi
        // nó nhất quán với nội dung; đặt now() cho mọi URL là cách nhanh nhất để bị bỏ qua
        // toàn bộ sitemap. Nên mỗi URL soi đúng thứ nó hiển thị: trang danh sách lấy mốc
        // mới nhất của tập nó liệt kê, trang tĩnh lấy từ chính bản ghi StaticPage.
        $productMax = Product::active()->max('updated_at');
        $comboMax = Combo::active()->max('updated_at');
        $pageMax = StaticPage::pluck('updated_at', 'slug');

        // Mốc mới nhất theo danh mục — GOM MỘT TRUY VẤN, không lặp query trong vòng foreach.
        // Category nào không có mặt ở đây tức là 0 sản phẩm đang bán → không vào sitemap.
        $catMax = Product::active()
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->groupBy('categories.slug')
            ->selectRaw('categories.slug as slug, MAX(products.updated_at) as m')
            // get() rồi mới pluck: pluck() của query builder tự thay lại mệnh đề SELECT
            // bằng đúng 2 cột nó cần, làm bay luôn MAX() ở trên → lỗi "unknown column m".
            ->get()
            ->pluck('m', 'slug');

        $urls = [
            ['loc' => url('/'), 'lastmod' => $this->atom(max($productMax, $comboMax)), 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => url('/thiet-bi'), 'lastmod' => $this->atom($productMax), 'changefreq' => 'daily', 'priority' => '0.9'],
            ['loc' => url('/combos'), 'lastmod' => $this->atom($comboMax), 'changefreq' => 'weekly', 'priority' => '0.8'],
            ['loc' => url('/gioi-thieu'), 'lastmod' => $this->atom($pageMax['gioi-thieu'] ?? null), 'changefreq' => 'monthly', 'priority' => '0.6'],
        ];

        // Trang chính sách (bopcamping-12n9) — đều trả 200 và có canonical, tức là CÓ Ý
        // cho index, nhưng trước đây không khai nên Google không biết. Đây cũng là nhóm
        // trang dùng để đánh giá độ tin cậy (E-E-A-T) của một site thương mại.
        // Duyệt từ StaticPage::POLICIES để thêm chính sách mới là sitemap tự có.
        foreach (array_keys(StaticPage::POLICIES) as $slug) {
            $urls[] = [
                'loc' => url('/'.$slug),
                'lastmod' => $this->atom($pageMax[$slug] ?? null),
                'changefreq' => 'yearly',
                'priority' => '0.3',
            ];
        }

        // Danh mục RỖNG bị loại: trang chỉ hiện "không tìm thấy sản phẩm nào" là thin
        // content, mời Google vào đọc chỉ tổ hạ chất lượng chung của site. Danh mục có
        // hàng trở lại thì tự vào lại sau tối đa 1 giờ (hết cache).
        foreach (Category::orderBy('name')->get(['slug']) as $c) {
            if (! isset($catMax[$c->slug])) {
                continue;
            }
            $urls[] = [
                'loc' => url('/thiet-bi').'?cat='.$c->slug,
                'lastmod' => $this->atom($catMax[$c->slug]),
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ];
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

    /**
     * Chuẩn hoá mốc thời gian về W3C Datetime — dạng duy nhất sitemaps.org chấp nhận.
     * Nhận cả Carbon (Eloquent đã cast) lẫn chuỗi thô từ aggregate MAX(), và trả null
     * khi chưa có dữ liệu để bên gọi bỏ hẳn thẻ <lastmod> thay vì in mốc bịa.
     */
    private function atom(Carbon|string|null $value): ?string
    {
        return $value ? Carbon::parse($value)->toAtomString() : null;
    }
}

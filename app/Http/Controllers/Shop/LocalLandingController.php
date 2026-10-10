<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\CampingSpot;
use App\Models\CampingSpotMedia;
use App\Models\Category;
use App\Models\Combo;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Models\SiteSetting;
use App\Services\CatalogCardPresenter;
use App\Services\MediaVariantService;
use App\Services\SeoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Landing page SEO theo địa phương (bopcamping-y05c).
 *
 * Bắt truy vấn có ý định địa phương — "thuê đồ cắm trại Vinh", "thuê lều Hà Nội", "cắm trại
 * ở Hồ Đồng Mô" — mà trước đây site không có trang nào trả lời: cơ sở và điểm cắm trại chỉ
 * hiện thành một khối/modal ở trang chủ.
 *
 *   /thue-do-cam-trai/{slug}  — một trang cho mỗi CƠ SỞ đang mở (Vinh, Hà Nội)
 *   /diem-cam-trai            — danh sách điểm cắm trại theo tỉnh/thành
 *   /diem-cam-trai/{slug}     — một trang cho mỗi điểm cắm trại
 *
 * Nguyên tắc nội dung: MỌI câu chữ sinh ra đều dựng từ dữ liệu thật trong DB (số món đang cho
 * thuê ở kho, danh mục, điểm cắm trại gần, phương thức giao nhận). Không bịa FAQ, không bịa
 * toạ độ, schema chỉ khai thứ đang hiện trên trang (SKILL seo — Cạm bẫy #1).
 */
class LocalLandingController extends Controller
{
    public function __construct(
        private SeoService $seo,
        private CatalogCardPresenter $cards,
    ) {}

    /** GET /thue-do-cam-trai/{slug} — trang khu vực của một cơ sở. */
    public function area(string $slug): Response
    {
        // Chỉ cơ sở ĐANG MỞ: cơ sở "sắp mở" chưa có hàng, trang của nó chỉ là thin content.
        $location = ServiceLocation::open()->where('slug', $slug)->firstOrFail();

        $productQuery = Product::active()->servedAt($location->id);
        $productCount = (clone $productQuery)->count();
        $productModels = (clone $productQuery)
            ->with('category', 'images', 'serviceLocations')
            ->orderBy('name')
            ->limit(8)
            ->get();
        $this->cards->warmProducts($productModels);

        $comboModels = Combo::active()
            ->whereHas('items')
            ->whereHas('serviceLocations', fn (Builder $q) => $q->whereKey($location->id))
            ->with(['items.product', 'images', 'serviceLocations'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(4)
            ->get();
        $this->cards->warmCombos($comboModels);

        $spots = $location->campingSpots()->ordered()->with('media')->get();
        $this->warmSpotCovers($spots);

        $categories = $this->categoriesAt($location);
        $intro = filled($location->intro)
            ? $location->intro
            : $this->autoIntro($location, $productCount, $categories, $spots);

        $title = 'Thuê đồ cắm trại tại '.$location->name.' theo ngày';
        $url = url('/thue-do-cam-trai/'.$location->slug);
        $hotline = $this->hotline();

        $jsonld = [$this->seo->breadcrumb([
            ['Trang chủ', url('/')],
            ['Thuê đồ cắm trại tại '.$location->name, $url],
        ])];
        // LocalBusiness của RIÊNG cơ sở này — cùng hàm với khối site-wide nên không lệch.
        // Chưa có hotline thì bỏ, đúng như khối site-wide (không đủ dữ liệu tối thiểu).
        if ($hotline) {
            $jsonld[] = $this->seo->localBusinessFor($location, $hotline, $this->setting()->working_hours);
        }

        return Inertia::render('AreaLanding', [
            'seo' => $this->seo->page(
                $title,
                $this->areaDescription($location, $productCount, $categories),
                url('/images/og-cover.jpg'),
                $url,
                $jsonld,
            ),
            'location' => [
                'name' => $location->name,
                'slug' => $location->slug,
                'area' => $location->area,
                'address' => $location->address,
                'map_url' => $location->map_url,
            ],
            'intro' => $intro,
            'product_count' => $productCount,
            'categories' => $categories->map(fn (array $c) => [
                'name' => $c['name'],
                'slug' => $c['slug'],
                'count' => $c['count'],
            ])->values(),
            'products' => $productModels->map(fn (Product $p) => $this->cards->product($p))->values(),
            'combos' => $comboModels->map(fn (Combo $c) => $this->cards->combo($c))->values(),
            'spots' => $spots->map(fn (CampingSpot $s) => $this->spotCard($s))->values(),
            'delivery_methods' => Order::deliveryMethodOptions(),
            'other_locations' => ServiceLocation::open()->ordered()
                ->whereKeyNot($location->id)
                ->get(['name', 'slug'])
                ->map(fn (ServiceLocation $l) => ['name' => $l->name, 'slug' => $l->slug])
                ->values(),
        ]);
    }

    /** GET /diem-cam-trai — mọi điểm cắm trại, gom theo tỉnh/thành. */
    public function spots(): Response
    {
        $spots = CampingSpot::with(['media', 'nearestServiceLocation'])
            ->orderBy('province')->ordered()->get();
        $this->warmSpotCovers($spots);

        $openLocations = ServiceLocation::open()->ordered()->get(['name', 'slug']);
        $cities = $openLocations->pluck('name')->implode(' & ');

        $title = 'Điểm cắm trại đẹp theo tỉnh thành';
        $description = $spots->isEmpty()
            ? 'Gợi ý điểm cắm trại đẹp theo tỉnh thành kèm mùa đẹp nhất và đồ cần mang. Thuê đồ cắm trại theo ngày, trả tiền khi nhận (COD).'
            : 'Gợi ý '.$spots->count().' điểm cắm trại đẹp ở '.$spots->pluck('province')->unique()->count()
                .' tỉnh thành: địa hình, mùa đẹp nhất, đồ cần mang'
                .($cities !== '' ? ' — thuê đồ theo ngày tại '.$cities : '')
                .', trả tiền khi nhận (COD).';

        return Inertia::render('CampingSpotIndex', [
            'seo' => $this->seo->page(
                $title,
                $description,
                url('/images/og-cover.jpg'),
                url('/diem-cam-trai'),
                $this->seo->breadcrumb([
                    ['Trang chủ', url('/')],
                    ['Điểm cắm trại', url('/diem-cam-trai')],
                ]),
            ),
            'provinces' => $spots->groupBy('province')->map(fn (Collection $group, string $province) => [
                'province' => $province,
                'spots' => $group->map(fn (CampingSpot $s) => $this->spotCard($s))->values(),
            ])->values(),
            'locations' => $openLocations->map(fn (ServiceLocation $l) => ['name' => $l->name, 'slug' => $l->slug])->values(),
        ]);
    }

    /** GET /diem-cam-trai/{slug} — trang một điểm cắm trại. */
    public function spot(string $slug): Response
    {
        $spot = CampingSpot::with(['media', 'nearestServiceLocation'])->where('slug', $slug)->firstOrFail();
        $store = $spot->nearestServiceLocation?->status === 'open' ? $spot->nearestServiceLocation : null;

        $imagePaths = $spot->media->where('type', 'image')->pluck('path');
        MediaVariantService::warm($imagePaths);
        $media = $spot->media->map(fn (CampingSpotMedia $m) => $this->mediaPayload($m))->values();

        // "Gợi ý đồ cần mang": combo thuê trọn bộ CÓ SẴN ở cơ sở gần nhất. Không thêm bảng nối
        // spot<->sản phẩm: combo đã là "bộ đồ đi cắm trại" do chủ shop gói, lại có kho riêng
        // theo cơ sở, nên vừa đúng hàng thật vừa không bắt admin nhập thêm gì. Điểm không có
        // cơ sở gần -> combo đang bán ở mọi nơi (khách thuê ở cơ sở rồi mang đi).
        $comboModels = Combo::active()
            ->whereHas('items')
            ->when($store, fn (Builder $q) => $q->whereHas('serviceLocations', fn (Builder $q2) => $q2->whereKey($store->id)))
            ->with(['items.product', 'images', 'serviceLocations'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(3)
            ->get();
        $this->cards->warmCombos($comboModels);

        // Điểm khác cùng tỉnh hoặc cùng cơ sở gần — liên kết nội bộ giữa các trang điểm.
        $nearby = CampingSpot::with('media')
            ->whereKeyNot($spot->id)
            ->where(fn (Builder $q) => $q->where('province', $spot->province)
                ->when($spot->nearest_service_location_id, fn (Builder $q2) => $q2->orWhere('nearest_service_location_id', $spot->nearest_service_location_id)))
            ->ordered()
            ->limit(4)
            ->get();
        $this->warmSpotCovers($nearby);

        $url = url('/diem-cam-trai/'.$spot->slug);
        $cover = $media->firstWhere('type', 'image');

        $seo = $this->seo->page(
            $this->spotTitle($spot),
            $this->spotDescription($spot, $store),
            $cover['url'] ?? url('/images/og-cover.jpg'),
            $url,
            [
                $this->seo->breadcrumb([
                    ['Trang chủ', url('/')],
                    ['Điểm cắm trại', url('/diem-cam-trai')],
                    [$spot->name, $url],
                ]),
                $this->touristAttraction($spot, $media, $url),
            ],
        );
        // Điểm chỉ có một câu mô tả và không ảnh = thin content: vẫn mở cho khách nhưng không
        // cho index (và SitemapController cũng bỏ qua) — cùng một hàm quyết định cả hai chỗ.
        if ($this->seo->isThinContent($spot->description, $imagePaths->count())) {
            $seo = $this->seo->noindex($seo);
        }

        return Inertia::render('CampingSpotDetail', [
            'seo' => $seo,
            'spot' => [
                'name' => $spot->name,
                'slug' => $spot->slug,
                'province' => $spot->province,
                'district' => $spot->district,
                'terrain_tag' => $spot->terrain_tag,
                'description' => $spot->description,
                'season_label' => $spot->seasonLabel(),
                'map_url' => $spot->map_url,
                'media' => $media,
            ],
            'store' => $store ? [
                'name' => $store->name,
                'slug' => $store->slug,
                'area' => $store->area,
            ] : null,
            'combos' => $comboModels->map(fn (Combo $c) => $this->cards->combo($c))->values(),
            'nearby' => $nearby->map(fn (CampingSpot $s) => $this->spotCard($s))->values(),
            'locations' => ServiceLocation::open()->ordered()->get(['name', 'slug'])
                ->map(fn (ServiceLocation $l) => ['name' => $l->name, 'slug' => $l->slug])->values(),
        ]);
    }

    /* ------------------------------------------------------------------ */

    /**
     * Danh mục có hàng ở cơ sở, nhiều món nhất trước — dùng cho intro, description và chip
     * lọc. MỘT truy vấn gom nhóm, không lặp query theo danh mục.
     *
     * @return Collection<int, array{name: string, slug: string, count: int}>
     */
    private function categoriesAt(ServiceLocation $location): Collection
    {
        $counts = Product::active()->servedAt($location->id)
            ->selectRaw('category_id, COUNT(*) as n')
            ->groupBy('category_id')
            ->pluck('n', 'category_id');

        return Category::whereIn('id', $counts->keys())->get(['id', 'name', 'slug'])
            ->map(fn (Category $c) => ['name' => $c->name, 'slug' => $c->slug, 'count' => (int) $counts[$c->id]])
            ->sortBy([['count', 'desc'], ['name', 'asc']])
            ->values();
    }

    /**
     * Intro dự phòng khi admin chưa viết — chỉ ghép từ dữ liệu thật (số món, danh mục, điểm
     * cắm trại gần, cách giao nhận), không có câu quảng cáo chung chung nào bịa ra.
     *
     * @param  Collection<int, array{name: string, slug: string, count: int}>  $categories
     * @param  Collection<int, CampingSpot>  $spots
     */
    private function autoIntro(ServiceLocation $location, int $productCount, Collection $categories, Collection $spots): string
    {
        $where = $location->name.($location->area ? ' ('.$location->area.')' : '');
        $parts = [];

        $parts[] = $productCount > 0
            ? SeoService::BRAND.' có kho đồ cắm trại tại '.$where.' với '.$productCount.' món đang cho thuê'
                .($categories->isNotEmpty() ? ' — '.Str::lower($categories->take(4)->pluck('name')->implode(', ')) : '').'.'
            : SeoService::BRAND.' có kho đồ cắm trại tại '.$where.'.';

        $parts[] = 'Thuê theo ngày, đặt online rồi tụi mình gọi xác nhận; cọc và tiền thuê trả khi nhận đồ (COD).';

        if ($spots->isNotEmpty()) {
            $parts[] = 'Gần '.$location->name.' có '.$spots->count().' điểm cắm trại tụi mình đã giới thiệu: '
                .$spots->take(3)->pluck('name')->implode(', ').($spots->count() > 3 ? '…' : '.');
        }

        return implode(' ', $parts);
    }

    /**
     * Meta description của trang khu vực (120–160 ký tự). Admin đã viết intro đủ dài thì dùng
     * intro; không thì dựng từ dữ liệu thật.
     *
     * @param  Collection<int, array{name: string, slug: string, count: int}>  $categories
     */
    private function areaDescription(ServiceLocation $location, int $productCount, Collection $categories): string
    {
        $fromIntro = $this->seo->plainText($location->intro);
        if ($fromIntro && mb_strlen($fromIntro) >= 120) {
            return $fromIntro;
        }

        $what = $categories->isNotEmpty()
            ? Str::lower($categories->take(3)->pluck('name')->implode(', '))
            : 'lều, bếp, túi ngủ, đèn trại';
        $where = $location->name.($location->area ? ' ('.$location->area.')' : '');

        return 'Thuê '.$what.' tại '.$where.' theo ngày'
            .($productCount > 0 ? ': '.$productCount.' món sẵn kho' : '')
            .'. Tự đến lấy hoặc nhận tại điểm hẹn, cọc linh hoạt, trả tiền khi nhận (COD).';
    }

    /** Title trang điểm: "Cắm trại ở <tên>, <tỉnh> — mùa đẹp & đồ cần mang | BRAND" (~60 ký tự). */
    private function spotTitle(CampingSpot $spot): string
    {
        $place = Str::contains(Str::lower($spot->name), Str::lower($spot->province))
            ? $spot->name
            : $spot->name.', '.$spot->province;
        $long = $this->seo->withBrand('Cắm trại ở '.$place.': mùa đẹp, đồ cần mang');

        return mb_strlen($long) <= 65 ? $long : $this->seo->withBrand('Cắm trại ở '.$place);
    }

    private function spotDescription(CampingSpot $spot, ?ServiceLocation $store): string
    {
        $where = $spot->name.' ('.($spot->district ? $spot->district.', ' : '').$spot->province.')';
        $lead = $where.': '.Str::lower($spot->terrain_tag).', mùa đẹp '.Str::lower($spot->seasonLabel()).'.';
        $tail = $store
            ? ' Gợi ý đồ cần mang, thuê lều, bếp, túi ngủ theo ngày tại kho '.$store->name.', trả tiền khi nhận (COD).'
            : ' Gợi ý đồ cần mang và thuê lều, bếp, túi ngủ theo ngày, trả tiền khi nhận (COD).';

        $desc = $this->seo->plainText($spot->description, 400);
        $text = $desc ? $lead.' '.$desc : $lead;

        // Mô tả ngắn -> nối thêm câu "thuê đồ" cho đủ 120+ ký tự; dài thì cắt ở 160.
        return (string) $this->seo->plainText(mb_strlen($text) < 120 ? $text.$tail : $text, 160);
    }

    /**
     * TouristAttraction — chỉ khai thứ đang hiện trên trang: tên, mô tả, ảnh, tỉnh/huyện,
     * link bản đồ. KHÔNG khai `geo`: DB chưa có toạ độ, mà toạ độ bịa còn hại hơn không có.
     *
     * @param  Collection<int, array{type: string, url: string, srcset: string|null}>  $media
     * @return array<string, mixed>
     */
    private function touristAttraction(CampingSpot $spot, Collection $media, string $url): array
    {
        // Disk local trả URL tương đối (/storage/...) — schema cần URL tuyệt đối.
        $images = $media->where('type', 'image')->pluck('url')
            ->map(fn (string $u) => Str::startsWith($u, ['http://', 'https://']) ? $u : url($u))
            ->values()->all();

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'TouristAttraction',
            'name' => $spot->name,
            'description' => $this->seo->plainText($spot->description, 5000),
            'url' => $url,
            'image' => $images ?: null,
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'addressLocality' => $spot->district,
                'addressRegion' => $spot->province,
                'addressCountry' => 'VN',
            ], fn ($v) => filled($v)),
            'hasMap' => $spot->map_url ?: null,
            'touristType' => $spot->terrain_tag ? 'Cắm trại · '.$spot->terrain_tag : null,
        ], fn ($v) => $v !== null);
    }

    /** Thẻ điểm cắm trại dùng chung (trang khu vực, danh sách, "điểm gần đây"). */
    private function spotCard(CampingSpot $s): array
    {
        $cover = $s->media->firstWhere('type', 'image');
        $payload = $cover ? MediaVariantService::payload($cover->path) : null;

        return [
            'id' => $s->id,
            'name' => $s->name,
            'slug' => $s->slug,
            'province' => $s->province,
            'district' => $s->district,
            'terrain_tag' => $s->terrain_tag,
            'season_label' => $s->seasonLabel(),
            'description' => $s->description,
            'nearest_name' => $s->relationLoaded('nearestServiceLocation') ? $s->nearestServiceLocation?->name : null,
            'image' => $payload['url'] ?? null,
            'image_srcset' => $payload['srcset'] ?? null,
        ];
    }

    /** @param  iterable<CampingSpot>  $spots */
    private function warmSpotCovers(iterable $spots): void
    {
        $paths = [];
        foreach ($spots as $s) {
            $paths[] = $s->media->firstWhere('type', 'image')?->path;
        }
        MediaVariantService::warm($paths);
    }

    /** @return array{type: string, url: string, srcset: string|null} */
    private function mediaPayload(CampingSpotMedia $m): array
    {
        if ($m->type !== 'image') {
            return ['type' => $m->type, 'url' => Storage::disk('media')->url($m->path), 'srcset' => null];
        }
        $p = MediaVariantService::payload($m->path);

        return ['type' => 'image', 'url' => $p['url'], 'srcset' => $p['srcset']];
    }

    private function setting(): SiteSetting
    {
        return SiteSetting::current();
    }

    private function hotline(): ?string
    {
        $s = $this->setting();

        return $s->hotline_primary ?: ($s->hotline_secondary ?: null);
    }
}

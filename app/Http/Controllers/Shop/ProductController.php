<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\CampingSpot;
use App\Models\Category;
use App\Models\Combo;
use App\Models\Product;
use App\Models\Review;
use App\Models\ServiceLocation;
use App\Services\AvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(private AvailabilityService $availability) {}

    /** Số vị trí đang mở (memoize) — biết sản phẩm có phục vụ "toàn hệ thống" không. */
    private ?int $openLocationCount = null;

    private function openLocationCount(): int
    {
        return $this->openLocationCount ??= ServiceLocation::open()->count();
    }

    /** GET / — trang chủ với 4 sản phẩm nổi bật */
    public function home(): Response
    {
        $featured = Product::active()
            ->with('category', 'images', 'serviceLocations')
            ->limit(4)
            ->get()
            ->map(fn ($p) => $this->shape($p));

        $systemQuery = Review::where('status', 'approved')->where('category', 'system');

        $spots = CampingSpot::ordered()->with(['media', 'nearestServiceLocation'])->get();

        // Section "Combo tiết kiệm": 3–4 combo nổi bật theo sort_order (PRD combo mục 6)
        $featuredCombos = Combo::active()
            ->whereHas('items')
            ->with(['items.product', 'images'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(4)
            ->get()
            ->map(fn (Combo $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'combo_price' => (int) $c->combo_price,
                'sum_individual' => $c->sumIndividualPrice(),
                'savings_amount' => $c->savingsAmount(),
                'savings_percent' => $c->savingsPercent(),
                'suitable_for' => $c->suitable_for,
                'items_count' => $c->items->count(),
                'image' => $c->images->first()
                    ? Storage::disk('media')->url($c->images->first()->path)
                    : null,
            ])->values();

        return Inertia::render('Welcome', [
            'featured' => $featured,
            'featured_combos' => $featuredCombos,
            // Banner quản lý ở admin: hero (slideshow) + promo (dải khuyến mãi)
            'hero_banners' => Banner::active()->placement('hero')->ordered()->get()->map(fn (Banner $b) => [
                'src' => $b->imageUrl(),
                'title' => $b->title ?? '',
            ])->values(),
            'promo_banners' => Banner::active()->placement('promo')->ordered()->get()->map(fn (Banner $b) => [
                'id' => $b->id,
                'image' => $b->imageUrl(),
                'title' => $b->title,
                'subtitle' => $b->subtitle,
                'href' => $b->link_url,
            ])->values(),
            'system_reviews' => (clone $systemQuery)->latest()->limit(10)->get()->map(fn (Review $r) => [
                'id' => $r->id,
                'reviewer_name' => $r->reviewer_name,
                'rating' => $r->rating,
                'content' => $r->content,
                'meta' => 'Tháng '.$r->created_at->format('n, Y'),
            ])->values(),
            'review_stat' => [
                'avg' => round((float) (clone $systemQuery)->avg('rating'), 1),
                'count' => (clone $systemQuery)->count(),
            ],
            // Cẩm nang cắm trại: vị trí phục vụ + điểm gợi ý + tất cả điểm gom theo tỉnh/thành
            'service_locations' => ServiceLocation::ordered()->get()->map(fn (ServiceLocation $l) => [
                'name' => $l->name,
                'area' => $l->area,
                'status' => $l->status,
            ])->values(),
            'suggested_spots' => $spots->where('is_suggested', true)->map(fn (CampingSpot $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'terrain_tag' => $s->terrain_tag,
                'province' => $s->province,
                'nearest_name' => $s->nearestServiceLocation?->name,
            ])->values(),
            'camping_provinces' => $spots->groupBy('province')->map(fn ($group, $province) => [
                'province' => $province,
                'spots' => $group->map(fn (CampingSpot $s) => $this->shapeSpot($s))->values(),
            ])->values(),
        ]);
    }

    /** Biến đổi điểm cắm trại -> array cho cẩm nang (kèm ảnh/video + map). */
    private function shapeSpot(CampingSpot $s): array
    {
        return [
            'id' => $s->id,
            'name' => $s->name,
            'terrain_tag' => $s->terrain_tag,
            'province' => $s->province,
            'district' => $s->district,
            'description' => $s->description,
            'season_label' => $s->seasonLabel(),
            'map_url' => $s->map_url,
            'nearest_name' => $s->nearestServiceLocation?->name,
            'media' => $s->media->map(fn ($m) => [
                'type' => $m->type,
                'url' => Storage::disk('media')->url($m->path),
            ])->values(),
        ];
    }

    /** GET /thiet-bi — danh sách sản phẩm, hỗ trợ filter ?cat=, ?q=, ?sort= */
    public function index(Request $request): Response
    {
        $query = Product::active()->with('category', 'images', 'serviceLocations');

        if ($cat = $request->query('cat')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $cat));
        }

        if ($q = $request->query('q')) {
            $query->search($q); // tìm có dấu + không dấu (xem Product::scopeSearch)
        }

        // Lọc theo vị trí phục vụ (?vi-tri=vinh) — resolve slug -> id rồi whereHas.
        $openLocations = ServiceLocation::open()->ordered()->get();
        $locParam = $request->query('vi-tri', '');
        $activeLocation = $locParam ? $openLocations->firstWhere('slug', $locParam) : null;
        if ($activeLocation) {
            $query->servedAt($activeLocation->id);
        }

        $sort = $request->query('sort', 'pop');
        if ($sort === 'low') {
            $query->orderBy('price_per_day');
        } elseif ($sort === 'high') {
            $query->orderByDesc('price_per_day');
        } else {
            $query->orderBy('name');
        }

        $products = $query->get()->map(fn ($p) => $this->shape($p));

        $categories = Category::orderBy('name')
            ->get()
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug]);

        return Inertia::render('Products', [
            'products' => $products,
            'categories' => $categories,
            // Vị trí đang mở để render thanh lọc "Thuê tại" (chỉ hiện khi có >1 vị trí).
            'service_locations' => $openLocations->map(fn (ServiceLocation $l) => [
                'name' => $l->name,
                'slug' => $l->slug,
            ])->values(),
            'filters' => [
                'cat' => $request->query('cat', ''),
                'q' => $request->query('q', ''),
                'sort' => $sort,
                'vi_tri' => $activeLocation ? $activeLocation->slug : '',
            ],
        ]);
    }

    /** GET /thiet-bi/{product} — chi tiết sản phẩm */
    public function show(Request $request, string $product): Response
    {
        $p = Product::active()->with('category', 'images', 'serviceLocations')->where('slug', $product)->firstOrFail();

        $from = Carbon::today();
        $to = Carbon::today()->addDays(90);

        $unavailableDates = $this->availability->unavailableDates($p, $from, $to);

        $user = $request->user();

        $reviewCount = $p->reviews()->where('status', 'approved')->count();
        $reviewAvg = $p->averageRating();
        $seoImage = $p->thumbnail ? url(Storage::disk('media')->url($p->thumbnail)) : url('/images/album/forest-camp-aerial.jpg');
        $seoDesc = Str::limit(trim(strip_tags((string) $p->description)), 155)
            ?: 'Cho thuê '.$p->name.' theo ngày tại BỐP CAMPING.';

        $bannerCombo = $this->bannerCombo($p);

        return Inertia::render('ProductDetail', [
            'product' => $this->shape($p),
            'unavailable_dates' => $unavailableDates,
            // Case 2 (US-03): "thường thuê cùng" — FE lọc còn hàng theo khoảng ngày (AC-9)
            'accessories' => $this->activeAccessories($p)
                ->map(fn (Product $a) => [
                    'id' => $a->id,
                    'slug' => $a->slug,
                    'name' => $a->name,
                    'price_per_day' => (int) $a->price_per_day,
                    'deposit' => (int) ($a->deposit ?? 0),
                    'quantity' => (int) $a->quantity,
                    'thumbnail' => $a->thumbnail ? Storage::disk('media')->url($a->thumbnail) : null,
                    'category' => ['name' => $a->category->name, 'slug' => $a->category->slug],
                    'locations' => $this->shapeLocations($a),
                ])->values(),
            // PRD 5.6: banner "thuộc combo" — ưu tiên hiển thị hơn gợi ý lẻ
            'combo_banner' => $bannerCombo ? [
                'id' => $bannerCombo->id,
                'name' => $bannerCombo->name,
                'slug' => $bannerCombo->slug,
                'combo_price' => (int) $bannerCombo->combo_price,
                'sum_individual' => $bannerCombo->sumIndividualPrice(),
                'savings_amount' => $bannerCombo->savingsAmount(),
                'savings_percent' => $bannerCombo->savingsPercent(),
                'items_count' => $bannerCombo->items->count(),
            ] : null,
            'reviews' => $this->reviews($p),
            'review_summary' => ['count' => $reviewCount, 'avg' => $reviewAvg],
            'can_review' => $user !== null && $user->reviewableOrderItemId($p->id) !== null,
            // SEO riêng cho sản phẩm — share đẹp + Product schema (giá/tồn/sao) cho Google.
            'seo' => [
                'title' => $p->name.' — Thuê tại BỐP CAMPING',
                'description' => $seoDesc,
                'image' => $seoImage,
                'url' => url()->current(),
                'jsonld' => $this->productJsonLd($p, $seoImage, $seoDesc, $reviewCount, $reviewAvg),
            ],
        ]);
    }

    /**
     * GET /thiet-bi/{product}/kha-dung?start=&end= — tồn kho theo khoảng ngày.
     *
     * bopcamping-1z1: trang chi tiết từng hiện quantity tĩnh nên combo/đơn khác
     * đã chiếm kho mà khách vẫn thấy đủ. Endpoint đi qua AvailabilityService
     * (single source of truth) — FE fetch mỗi khi chọn xong khoảng ngày.
     */
    public function availability(Request $request, string $product): JsonResponse
    {
        $data = $request->validate([
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start'],
        ]);

        $p = Product::active()->where('slug', $product)->firstOrFail();

        return response()->json([
            'available' => $this->availability->availableQuantity(
                $p,
                Carbon::parse($data['start']),
                Carbon::parse($data['end']),
            ),
        ]);
    }

    /**
     * GET /thiet-bi/{product}/goi-y-kha-dung?start=&end= — tồn kho theo khoảng
     * ngày của các gợi ý trên trang sản phẩm: từng phụ kiện (AC-9) + combo banner.
     * Cùng đi qua AvailabilityService như mọi check tồn kho khác (AC-10).
     */
    public function suggestionAvailability(Request $request, string $product): JsonResponse
    {
        $data = $request->validate([
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start'],
        ]);

        $p = Product::active()->where('slug', $product)->firstOrFail();
        $start = Carbon::parse($data['start']);
        $end = Carbon::parse($data['end']);

        $bannerCombo = $this->bannerCombo($p);

        return response()->json([
            'accessories' => $this->activeAccessories($p)
                ->map(fn (Product $a) => [
                    'id' => $a->id,
                    'available' => $this->availability->availableQuantity($a, $start, $end),
                ])->values(),
            // null = không có banner; 0 = combo hết trong khoảng này → FE ẩn banner
            'combo_available' => $bannerCombo
                ? $this->availability->comboAvailable($bannerCombo, $start, $end)
                : null,
        ]);
    }

    /** Phụ kiện "thường thuê cùng" đang bán, theo sort_order admin đã xếp. */
    private function activeAccessories(Product $p)
    {
        return $p->accessories()->where('status', 'active')
            ->with('category', 'serviceLocations')
            ->get();
    }

    /**
     * Combo active tiết kiệm nhiều nhất chứa sản phẩm — nguồn cho banner PRD 5.6.
     * Trang chi tiết và endpoint gợi ý dùng chung để banner/tồn kho luôn cùng 1 combo.
     */
    private function bannerCombo(Product $p): ?Combo
    {
        return Combo::active()
            ->whereHas('items', fn ($q) => $q->where('product_id', $p->id))
            ->with('items.product')
            ->get()
            ->sortByDesc(fn (Combo $c) => $c->savingsAmount())
            ->first();
    }

    /** Product structured data (Google rich result: giá thuê/ngày, tồn kho, sao đánh giá). */
    private function productJsonLd(Product $p, string $image, string $desc, int $reviewCount, float $reviewAvg): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $p->name,
            'description' => $desc,
            'image' => $image,
            'category' => $p->category?->name,
            'brand' => ['@type' => 'Brand', 'name' => 'BỐP CAMPING'],
            'offers' => [
                '@type' => 'Offer',
                'price' => (int) $p->price_per_day,
                'priceCurrency' => 'VND',
                'availability' => $p->quantity > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'url' => url()->current(),
                'description' => 'Giá thuê theo ngày',
            ],
        ];

        if ($reviewCount > 0) {
            $data['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $reviewAvg,
                'reviewCount' => $reviewCount,
            ];
        }

        return $data;
    }

    /** Đánh giá đã duyệt cho carousel (kèm ảnh/video + meta). */
    private function reviews(Product $p): array
    {
        return $p->approvedReviews()->with(['images', 'orderItem'])->limit(20)->get()
            ->map(fn (Review $r) => [
                'id' => $r->id,
                'reviewer_name' => $r->reviewer_name,
                'rating' => $r->rating,
                'content' => $r->content,
                'meta' => trim(($r->orderItem ? $r->orderItem->days.' ngày · ' : '').'Tháng '.$r->created_at->format('n, Y')),
                'media' => $r->images->map(fn ($m) => [
                    'type' => $m->type,
                    'url' => Storage::disk('media')->url($m->path),
                ])->values(),
            ])->values()->all();
    }

    /** Biến đổi Product Eloquent -> array trả về Inertia */
    private function shape(Product $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'description' => $p->description,
            'price_per_day' => $p->price_per_day,
            'quantity' => $p->quantity,
            'deposit' => $p->deposit ?? 0,
            'thumbnail' => $p->thumbnail ? Storage::disk('media')->url($p->thumbnail) : null,
            'status' => $p->status,
            'category' => [
                'id' => $p->category->id,
                'name' => $p->category->name,
                'slug' => $p->category->slug,
            ],
            'images' => $p->images->map(fn ($i) => [
                'url' => Storage::disk('media')->url($i->path),
                'sort_order' => $i->sort_order,
                'type' => $i->type,
            ])->values()->all(),
            'featured' => false,
            // Badge vị trí: chỉ tính vị trí đang mở. all_locations = phục vụ toàn bộ
            // vị trí đang mở -> hiện gộp "Toàn hệ thống"; ngược lại liệt kê từng nơi.
            'locations' => $this->shapeLocations($p),
            'all_locations' => $this->servesAllOpenLocations($p),
        ];
    }

    /** Danh sách vị trí đang mở mà sản phẩm phục vụ (cho badge thẻ sản phẩm). */
    private function shapeLocations(Product $p): array
    {
        return $p->serviceLocations
            ->where('status', 'open')
            ->map(fn (ServiceLocation $l) => ['name' => $l->name, 'slug' => $l->slug])
            ->values()
            ->all();
    }

    /** True nếu sản phẩm phủ hết các vị trí đang mở (>=1) -> badge "Toàn hệ thống". */
    private function servesAllOpenLocations(Product $p): bool
    {
        $total = $this->openLocationCount();

        return $total > 0 && $p->serviceLocations->where('status', 'open')->count() === $total;
    }
}

<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Concerns\ParsesRentalRange;
use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\CampingSpot;
use App\Models\Category;
use App\Models\Combo;
use App\Models\Faq;
use App\Models\Product;
use App\Models\Review;
use App\Models\ServiceLocation;
use App\Services\AvailabilityService;
use App\Services\SeoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    use ParsesRentalRange;

    private const BRAND_TITLE = 'BỐP CAMPING — Cho thuê thiết bị cắm trại theo ngày';

    public function __construct(private AvailabilityService $availability, private SeoService $seo) {}

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
            'seo' => $this->seo->page(
                self::BRAND_TITLE,
                'Cho thuê lều, bếp, túi ngủ, đèn trại... theo ngày. Giao nhận tận nơi tại Vinh & Hà Nội, cọc linh hoạt, trả tiền khi nhận (COD).',
            ),
            'featured' => $featured,
            'featured_combos' => $featuredCombos,
            // FAQ hiển thị ở trang chủ (ADR home_faq_contact) — chỉ câu đang bật, theo thứ tự
            'faqs' => Faq::active()->ordered()->get(['id', 'question', 'answer']),
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
                // slug để mục đặt lịch ở trang chủ gửi đúng ?vi-tri= mà /thiet-bi resolve được
                // (bopcamping-aqkr — trước đây chỉ có name nên picker phải gửi tên, không khớp filter).
                'slug' => $l->slug,
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

        // Khoảng ngày thuê (?start=&end=) — ngày bẩn bị bỏ qua, KHÔNG 422 (FR-4).
        [$start, $end] = $this->parseRange($request);
        $hasRange = $start && $end;

        $productModels = $query->get();

        // 1 query duy nhất cho cả danh sách. /thiet-bi không phân trang nên gọi availableQuantity()
        // trong vòng lặp sẽ là N query — xem NFR-1 trong artifacts/prd_date_first_booking.md.
        $availability = $hasRange
            ? $this->availability->availableQuantitiesFor($productModels, $start, $end, $activeLocation)
            : [];

        $products = $productModels->map(function (Product $p) use ($hasRange, $availability) {
            $shaped = $this->shape($p);
            // null khi khách chưa chọn ngày — FE phân biệt "chưa lọc" với "hết hàng".
            $shaped['available'] = $hasRange ? ($availability[$p->id] ?? 0) : null;
            $shaped['in_range'] = $hasRange ? ($shaped['available'] >= 1) : null;

            return $shaped;
        });

        if ($hasRange) {
            // Món đặt được lên trước, giữ thứ tự phụ theo ?sort= — sort của PHP 8 là stable.
            // ⚠️ Chỉ đúng vì listing chưa phân trang (get()); thêm phân trang thì phải đẩy xuống SQL.
            $products = $products->sortByDesc(fn (array $p) => $p['in_range'] ? 1 : 0)->values();
        }

        $categoryModels = Category::orderBy('name')->get();
        $categories = $categoryModels->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug]);

        // SEO: title/desc theo bộ lọc danh mục + breadcrumb (Trang chủ → Thuê đồ [→ danh mục]).
        $activeCategory = $cat ? $categoryModels->firstWhere('slug', $cat) : null;
        $crumbs = [['Trang chủ', url('/')], ['Thuê đồ', url('/thiet-bi')]];
        if ($activeCategory) {
            $crumbs[] = [$activeCategory->name, url('/thiet-bi').'?cat='.$activeCategory->slug];
        }
        $seoProp = $this->seo->page(
            $activeCategory
                ? 'Thuê '.$activeCategory->name.' tại BỐP CAMPING'
                : 'Thuê thiết bị cắm trại — Danh sách đồ cho thuê | BỐP CAMPING',
            $activeCategory
                ? 'Cho thuê '.$activeCategory->name.' theo ngày tại BỐP CAMPING — giao nhận tận nơi, cọc linh hoạt, COD.'
                : 'Danh sách thiết bị cắm trại cho thuê: lều, bếp, túi ngủ, đèn trại... Thuê theo ngày, giao tận nơi tại Vinh & Hà Nội.',
            jsonld: $this->seo->breadcrumb($crumbs),
        );

        return Inertia::render('Products', [
            'seo' => $seoProp,
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
                'start' => $start?->toDateString() ?? '',
                'end' => $end?->toDateString() ?? '',
            ],
            'range_summary' => $hasRange ? [
                'days' => $start->diffInDays($end) + 1,
                'unavailable_count' => $products->where('in_range', false)->count(),
            ] : null,
        ]);
    }

    /** GET /thiet-bi/{product} — chi tiết sản phẩm */
    public function show(Request $request, string $product): Response
    {
        $p = Product::active()->with('category', 'images', 'serviceLocations')->where('slug', $product)->firstOrFail();

        $from = Carbon::today();
        $to = Carbon::today()->addDays(90);

        // Per-store: cửa hàng phục vụ (open) + tồn tĩnh mỗi nơi (FE fetch available động theo ngày).
        $openServed = $p->serviceLocations->where('status', 'open')->sortBy('sort_order')->values();
        $primaryLocation = $openServed->first();
        $stockByLocation = $openServed->map(fn (ServiceLocation $l) => [
            'id' => $l->id,
            'name' => $l->name,
            'slug' => $l->slug,
            'quantity' => (int) $l->pivot->quantity,
        ])->values();

        // Lịch tô màu: toàn cục (sản phẩm chưa cấu hình store) — fallback cho FE khi chưa chọn/legacy.
        $unavailableDates = $this->availability->unavailableDates($p, $from, $to, $primaryLocation);
        // Ngày hết theo TỪNG cửa hàng — khách chọn store nào thì lịch chặn ngày hết của store đó.
        $unavailableByLocation = $openServed->mapWithKeys(fn (ServiceLocation $l) => [
            $l->id => $this->availability->unavailableDates($p, $from, $to, $l),
        ]);

        $user = $request->user();

        $reviewCount = $p->reviews()->where('status', 'approved')->count();
        $reviewAvg = $p->averageRating();
        $seoImage = $p->thumbnail ? url(Storage::disk('media')->url($p->thumbnail)) : url('/images/album/forest-camp-aerial.jpg');
        $seoDesc = Str::limit(trim(strip_tags((string) $p->description)), 155)
            ?: 'Cho thuê '.$p->name.' theo ngày tại BỐP CAMPING.';

        $bannerCombo = $this->bannerCombo($p);

        return Inertia::render('ProductDetail', [
            // specs/setup_content chỉ cần ở trang chi tiết — merge ngoài shape()
            // để card danh sách (shape dùng chung) không phải chở thêm payload.
            'product' => array_merge($this->shape($p), [
                'specs' => $p->specs ?? [],
                'setup_content' => $p->setup_content,
            ]),
            // "You may also like" (Epic 1, 1.6) — admin tự chọn, chỉ sản phẩm đang bán
            'related_products' => $p->related()->where('status', 'active')
                ->with('category', 'images', 'serviceLocations')
                ->get()
                ->map(fn (Product $r) => $this->shape($r))
                ->values(),
            'unavailable_dates' => $unavailableDates,
            'unavailable_by_location' => $unavailableByLocation,
            // Per-store: tồn theo từng cửa hàng phục vụ — trang SP hiện "Vinh: N / Hà Nội: M"
            'stock_by_location' => $stockByLocation,
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
            // SEO riêng cho sản phẩm — share đẹp + Product schema (giá/tồn/sao) + breadcrumb.
            'seo' => [
                'title' => $p->name.' — Thuê tại BỐP CAMPING',
                'description' => $seoDesc,
                'image' => $seoImage,
                'url' => url()->current(),
                // Mảng nhiều node JSON-LD trong 1 thẻ script (Google chấp nhận): Product + Breadcrumb.
                'jsonld' => [
                    $this->productJsonLd($p, $seoImage, $seoDesc, $reviewCount, $reviewAvg),
                    $this->seo->breadcrumb([
                        ['Trang chủ', url('/')],
                        ['Thuê đồ', url('/thiet-bi')],
                        [$p->name, url()->current()],
                    ]),
                ],
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
            // Per-store: có location_id → tồn cửa hàng đó; không → map tất cả cửa hàng phục vụ.
            'location_id' => ['nullable', 'integer', 'exists:service_locations,id'],
        ]);

        $p = Product::active()->with('serviceLocations')->where('slug', $product)->firstOrFail();
        $start = Carbon::parse($data['start']);
        $end = Carbon::parse($data['end']);

        if (! empty($data['location_id'])) {
            $location = $p->serviceLocations->firstWhere('id', (int) $data['location_id']);

            return response()->json([
                'available' => $location ? $this->availability->availableQuantity($p, $start, $end, $location) : 0,
            ]);
        }

        // Có cửa hàng phục vụ → map theo store; chưa cấu hình vị trí → tồn toàn cục (legacy).
        $openServed = $p->serviceLocations->where('status', 'open');
        if ($openServed->isEmpty()) {
            return response()->json(['available' => $this->availability->availableQuantity($p, $start, $end)]);
        }

        return response()->json(['by_location' => $this->availability->availableByLocations($p, $start, $end)]);
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
            // Per-store: gợi ý theo cửa hàng khách đang chọn (null = toàn cục)
            'location_id' => ['nullable', 'integer', 'exists:service_locations,id'],
        ]);

        $p = Product::active()->where('slug', $product)->firstOrFail();
        $start = Carbon::parse($data['start']);
        $end = Carbon::parse($data['end']);
        $location = ! empty($data['location_id'])
            ? ServiceLocation::find((int) $data['location_id'])
            : null;

        $bannerCombo = $this->bannerCombo($p);

        return response()->json([
            'accessories' => $this->activeAccessories($p)
                ->map(fn (Product $a) => [
                    'id' => $a->id,
                    'available' => $this->availability->availableQuantity($a, $start, $end, $location),
                ])->values(),
            // null = không có banner; 0 = combo hết trong khoảng này → FE ẩn banner
            'combo_available' => $bannerCombo
                ? $this->availability->comboAvailable($bannerCombo, $start, $end, $location)
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
            'early_return_discount_pct' => (int) $p->early_return_discount_pct,
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

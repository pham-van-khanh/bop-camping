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
use App\Services\CatalogCardPresenter;
use App\Services\SeoService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    use ParsesRentalRange;

    private const BRAND_TITLE = 'BỐP CAMPING — Cho thuê thiết bị cắm trại theo ngày';

    public function __construct(
        private AvailabilityService $availability,
        private SeoService $seo,
        private CatalogCardPresenter $cards,
    ) {}

    /**
     * Kho nào đang giữ con số "còn N" khi khách CHƯA chọn kho (bopcamping-kvcc).
     *
     * VẤN ĐỀ: chưa chọn kho thì badge lấy max qua các kho. Khách thấy "Còn 3 bộ", thêm 3 vào
     * giỏ, rồi tới checkout mới biết KHÔNG kho nào có đủ 3 — vì StoreResolver buộc cả giỏ phải
     * nằm trong MỘT kho. Thất bại muộn và khó hiểu.
     *
     * CÁCH SỬA: nói luôn con số đó ở kho nào. CHỈ nói khi các kho thật sự lệch nhau — bằng nhau
     * thì con số đúng ở mọi kho, thêm tên kho chỉ làm rối. Món chưa gắn kho nào (dữ liệu cũ,
     * dùng tồn toàn cục) cũng trả null vì không có kho nào để chỉ.
     *
     * @param  EloquentCollection<int, Product>  $products
     * @return array<int, string> [product_id => tên kho], chỉ chứa món có kho lệch nhau
     */
    private function bestLocationNames(EloquentCollection $products, Carbon $start, Carbon $end): array
    {
        $matrix = $this->availability->availabilityMatrix($products, $start, $end);
        $names = ServiceLocation::open()->pluck('name', 'id');
        $out = [];

        foreach ($matrix as $productId => $row) {
            $byLocation = $row['by_location'];

            // Cần ít nhất 2 kho và chúng phải lệch nhau mới đáng nói tên kho.
            if (count($byLocation) < 2 || min($byLocation) === max($byLocation)) {
                continue;
            }

            $bestId = array_search(max($byLocation), $byLocation, true);
            if ($bestId !== false && isset($names[$bestId])) {
                $out[$productId] = $names[$bestId];
            }
        }

        return $out;
    }

    /** GET / — trang chủ với 4 sản phẩm nổi bật */
    public function home(Request $request): Response
    {
        $featuredModels = Product::active()
            ->with('category', 'images', 'serviceLocations')
            ->limit(4)
            ->get();
        $this->cards->warmProducts($featuredModels);
        $featured = $featuredModels->map(fn ($p) => $this->cards->product($p));

        $systemQuery = Review::where('status', 'approved')->where('category', 'system');

        $spots = CampingSpot::ordered()->with(['media', 'nearestServiceLocation'])->get();

        // Section "Combo tiết kiệm": 3–4 combo nổi bật theo sort_order (PRD combo mục 6)
        $comboModels = Combo::active()
            ->whereHas('items')
            // serviceLocations: thẻ combo hiện badge cơ sở như thẻ sản phẩm (bopcamping-daet).
            // Không eager load thì badge trống ở trang chủ + N+1 một query mỗi combo.
            ->with(['items.product', 'images', 'serviceLocations'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(4)
            ->get();
        // Ảnh thẻ combo qua MediaVariantService như ProductCard (bopcamping-hjde).
        $this->cards->warmCombos($comboModels);
        $featuredCombos = $comboModels->map(fn (Combo $c) => $this->cards->combo($c))->values();

        // FAQ hiển thị ở trang chủ (ADR home_faq_contact) — chỉ câu đang bật, theo thứ tự.
        // Lấy MỘT LẦN rồi dùng cho cả React lẫn JSON-LD: hai bên phải là cùng một dữ liệu,
        // query riêng cho schema là mở đường cho markup lệch nội dung (bopcamping-s5ct).
        $faqs = Faq::active()->ordered()->get(['id', 'question', 'answer']);

        return Inertia::render('Welcome', [
            'seo' => $this->seo->page(
                self::BRAND_TITLE,
                // Danh mục lấy từ DB thay vì gõ tay; bỏ đoạn địa điểm vì cơ sở là dữ
                // liệu admin quản lý, hardcode là sai ngay khi mở cơ sở mới (bopcamping-gyg8).
                'Cho thuê '.$this->seo->categoryPhrase().' theo ngày. Cọc linh hoạt, trả tiền khi nhận (COD).',
                jsonld: $this->seo->faqPage($faqs),
            ),
            'featured' => $featured,
            'featured_combos' => $featuredCombos,
            'faqs' => $faqs,
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
            // Cổng viết đánh giá tổng thể ngay trang chủ (bopcamping-saeb): phải đăng nhập
            // và đã thuê–trả đồ ít nhất một lần. Trang chủ là chỗ dễ bị spam nhất nên
            // không mở cho khách vãng lai như đánh giá sản phẩm.
            'can_review_system' => $request->user()?->hasReturnedOrder() ?? false,
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
                // Link sang trang riêng /diem-cam-trai/{slug} (bopcamping-y05c).
                'slug' => $s->slug,
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
            'slug' => $s->slug,
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

    /**
     * Canonical cho /thiet-bi: CHỈ bộ lọc danh mục mới được tự trỏ về mình (bopcamping-10x2).
     *
     * Mặc định SeoService::page() lấy url()->current(), thứ này cắt sạch query string.
     * Với ?q=/?sort=/?vi-tri=/ngày thì đúng — vô số tổ hợp trả về gần như cùng tập sản
     * phẩm, để mỗi cái tự canonical là tự đẻ ra hàng trăm trang trùng nội dung. Nhưng nó
     * gom nhầm cả ?cat=, thứ DUY NHẤT tạo ra trang thật sự khác (khác cả title lẫn
     * description). Đo trên production 12/08/2026: 7 URL danh mục nằm trong sitemap, có
     * title riêng "Thuê Lều cắm trại...", mà canonical lại trỏ /thiet-bi — canonical
     * thắng, Google loại sạch, không index nổi một từ khoá danh mục nào.
     *
     * Bỏ qua param rỗng và sort mặc định trước khi so: FE gửi nguyên bộ filter kể cả khi
     * trống (?cat=x&q=&sort=pop&vi-tri=&start=&end=), so thô thì link trong UI không bao
     * giờ khớp và người dùng lại đáp xuống bản canonical về /thiet-bi.
     */
    private function canonicalFor(Request $request, ?Category $activeCategory): string
    {
        $base = url('/thiet-bi');
        if (! $activeCategory) {
            return $base;
        }

        $meaningful = array_keys(array_filter(
            $request->query(),
            fn ($v, $k) => $v !== '' && $v !== null && $v !== [] && ! ($k === 'sort' && $v === 'pop'),
            ARRAY_FILTER_USE_BOTH
        ));

        return $meaningful === ['cat'] ? $base.'?cat='.$activeCategory->slug : $base;
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

        // Khách CHƯA chọn kho: con số hiển thị là max qua các kho (quyết định #2, prd_date_first_booking).
        // Nếu các kho lệch nhau thì phải nói rõ số đó ở kho NÀO — xem bestLocationNames().
        $bestAt = $hasRange && $activeLocation === null
            ? $this->bestLocationNames($productModels, $start, $end)
            : [];

        $this->cards->warmProducts($productModels);

        $products = $productModels->map(function (Product $p) use ($hasRange, $availability, $bestAt) {
            $shaped = $this->cards->product($p);
            // null khi khách chưa chọn ngày — FE phân biệt "chưa lọc" với "hết hàng".
            $shaped['available'] = $hasRange ? ($availability[$p->id] ?? 0) : null;
            $shaped['in_range'] = $hasRange ? ($shaped['available'] >= 1) : null;
            // Tên kho giữ con số đó, chỉ khi các kho lệch nhau; null = con số đúng ở mọi kho.
            $shaped['available_at'] = $bestAt[$p->id] ?? null;

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
                ? 'Cho thuê '.$activeCategory->name.' theo ngày tại BỐP CAMPING — cọc linh hoạt, trả tiền khi nhận (COD).'
                // Gạch ngang chứ không phải dấu chấm: categoryPhrase() có thể kết thúc
                // bằng "..." khi còn danh mục chưa liệt kê, nối thêm '.' thành "....".
                : 'Danh sách thiết bị cắm trại cho thuê: '.$this->seo->categoryPhrase().' — thuê theo ngày, cọc linh hoạt, trả tiền khi nhận (COD).',
            url: $this->canonicalFor($request, $activeCategory),
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

        $this->cards->warmProducts([$p]);

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
        // Một lần query, dùng cho cả carousel lẫn JSON-LD `review` — markup phải đúng
        // những đánh giá đang hiện trên trang.
        $approvedReviews = $p->approvedReviews()->with(['images', 'orderItem'])->limit(20)->get();
        // Sản phẩm chưa có ảnh thì rơi về bìa thương hiệu 1200x630 — đúng tỉ lệ bóc link,
        // hơn là một tấm ảnh album bị mạng xã hội tự cắt (bopcamping-marf).
        $seoImage = $p->thumbnail ? url(Storage::disk('media')->url($p->thumbnail)) : url('/images/og-cover.jpg');
        // Qua SeoService::plainText, KHÔNG tự strip_tags: gỡ thẻ trần làm hai khối dính
        // chữ và để lọt \r\n vào content="…" (bopcamping-1xja).
        $seoDesc = $this->seo->plainText($p->description, 155)
            ?: 'Cho thuê '.$p->name.' theo ngày tại BỐP CAMPING.';

        $bannerCombo = $this->bannerCombo($p);

        return Inertia::render('ProductDetail', [
            // specs/setup_content chỉ cần ở trang chi tiết — merge ngoài shape()
            // để card danh sách (shape dùng chung) không phải chở thêm payload.
            'product' => array_merge($this->cards->product($p), [
                'specs' => $p->specs ?? [],
                'setup_content' => $p->setup_content,
            ]),
            // "You may also like" (Epic 1, 1.6) — admin tự chọn, chỉ sản phẩm đang bán
            'related_products' => $p->related()->where('status', 'active')
                ->with('category', 'images', 'serviceLocations')
                ->get()
                ->map(fn (Product $r) => $this->cards->product($r))
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
                    'locations' => $this->cards->productLocations($a),
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
            'reviews' => $this->reviews($approvedReviews),
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
                    $this->productJsonLd($p, $seoImage, $seoDesc, $reviewCount, $reviewAvg, $approvedReviews),
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

    /**
     * Product structured data (Google rich result: giá thuê/ngày, tồn kho, sao đánh giá).
     *
     * @param  EloquentCollection<int, Review>  $approvedReviews
     */
    private function productJsonLd(Product $p, string $image, string $desc, int $reviewCount, float $reviewAvg, EloquentCollection $approvedReviews): array
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

        return $data + $this->seo->reviewMarkup($reviewCount, $reviewAvg, $approvedReviews);
    }

    /**
     * Đánh giá đã duyệt cho carousel (kèm ảnh/video + meta).
     *
     * @param  EloquentCollection<int, Review>  $approved
     */
    private function reviews(EloquentCollection $approved): array
    {
        return $approved
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
}

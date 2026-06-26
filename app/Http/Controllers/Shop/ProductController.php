<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\CampingSpot;
use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Models\ServiceLocation;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(private AvailabilityService $availability) {}

    /** GET / — trang chủ với 4 sản phẩm nổi bật */
    public function home(): Response
    {
        $featured = Product::active()
            ->with('category', 'images')
            ->limit(4)
            ->get()
            ->map(fn ($p) => $this->shape($p));

        $systemQuery = Review::where('status', 'approved')->where('category', 'system');

        $spots = CampingSpot::ordered()->with(['media', 'nearestServiceLocation'])->get();

        return Inertia::render('Welcome', [
            'featured' => $featured,
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
                'url' => Storage::url($m->path),
            ])->values(),
        ];
    }

    /** GET /thiet-bi — danh sách sản phẩm, hỗ trợ filter ?cat=, ?q=, ?sort= */
    public function index(Request $request): Response
    {
        $query = Product::active()->with('category', 'images');

        if ($cat = $request->query('cat')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $cat));
        }

        if ($q = $request->query('q')) {
            $query->search($q); // tìm có dấu + không dấu (xem Product::scopeSearch)
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
            'filters' => [
                'cat' => $request->query('cat', ''),
                'q' => $request->query('q', ''),
                'sort' => $sort,
            ],
        ]);
    }

    /** GET /thiet-bi/{product} — chi tiết sản phẩm */
    public function show(Request $request, int $product): Response
    {
        $p = Product::active()->with('category', 'images')->findOrFail($product);

        $from = Carbon::today();
        $to = Carbon::today()->addDays(90);

        $unavailableDates = $this->availability->unavailableDates($p, $from, $to);

        $user = $request->user();

        return Inertia::render('ProductDetail', [
            'product' => $this->shape($p),
            'unavailable_dates' => $unavailableDates,
            'reviews' => $this->reviews($p),
            'review_summary' => ['count' => $p->reviews()->where('status', 'approved')->count(), 'avg' => $p->averageRating()],
            'can_review' => $user !== null && $user->reviewableOrderItemId($p->id) !== null,
            // SEO riêng cho sản phẩm — share lên Facebook/Zalo hiện đúng tên + ảnh + mô tả.
            'seo' => [
                'title' => $p->name.' — Thuê tại BỐP CAMPING',
                'description' => Str::limit(trim(strip_tags((string) $p->description)), 155)
                    ?: 'Cho thuê '.$p->name.' theo ngày tại BỐP CAMPING.',
                'image' => $p->thumbnail ? url(Storage::url($p->thumbnail)) : url('/images/album/forest-camp-aerial.jpg'),
                'url' => url()->current(),
            ],
        ]);
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
                    'url' => Storage::url($m->path),
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
            'thumbnail' => $p->thumbnail,
            'status' => $p->status,
            'category' => [
                'id' => $p->category->id,
                'name' => $p->category->name,
                'slug' => $p->category->slug,
            ],
            'images' => $p->images->map(fn ($i) => [
                'path' => $i->path,
                'sort_order' => $i->sort_order,
            ])->values()->all(),
            'featured' => false,
        ];
    }
}

<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        return Inertia::render('Welcome', ['featured' => $featured]);
    }

    /** GET /thiet-bi — danh sách sản phẩm, hỗ trợ filter ?cat=, ?q=, ?sort= */
    public function index(Request $request): Response
    {
        $query = Product::active()->with('category', 'images');

        if ($cat = $request->query('cat')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $cat));
        }

        if ($q = $request->query('q')) {
            $query->where(function ($sq) use ($q) {
                $sq->where('name', 'like', "%{$q}%")
                   ->orWhere('description', 'like', "%{$q}%");
            });
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
            'products'   => $products,
            'categories' => $categories,
            'filters'    => [
                'cat'  => $request->query('cat', ''),
                'q'    => $request->query('q', ''),
                'sort' => $sort,
            ],
        ]);
    }

    /** GET /thiet-bi/{product} — chi tiết sản phẩm */
    public function show(int $product): Response
    {
        $p = Product::active()->with('category', 'images')->findOrFail($product);

        $from = Carbon::today();
        $to   = Carbon::today()->addDays(90);

        $unavailableDates = $this->availability->unavailableDates($p, $from, $to);

        return Inertia::render('ProductDetail', [
            'product'           => $this->shape($p),
            'unavailable_dates' => $unavailableDates,
        ]);
    }

    /** Biến đổi Product Eloquent -> array trả về Inertia */
    private function shape(Product $p): array
    {
        return [
            'id'            => $p->id,
            'name'          => $p->name,
            'slug'          => $p->slug,
            'description'   => $p->description,
            'price_per_day' => $p->price_per_day,
            'quantity'      => $p->quantity,
            'deposit'       => $p->deposit ?? 0,
            'thumbnail'     => $p->thumbnail,
            'status'        => $p->status,
            'category'      => [
                'id'   => $p->category->id,
                'name' => $p->category->name,
                'slug' => $p->category->slug,
            ],
            'images'        => $p->images->map(fn ($i) => [
                'path'       => $i->path,
                'sort_order' => $i->sort_order,
            ])->values()->all(),
            'featured'      => false,
        ];
    }
}

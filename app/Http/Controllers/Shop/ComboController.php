<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Combo;
use App\Models\ComboItem;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Services\AvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ComboController extends Controller
{
    public function __construct(private AvailabilityService $availability) {}

    /** Combo bán được: đang active và có ít nhất 1 món. */
    private function sellable()
    {
        return Combo::active()
            ->whereHas('items')
            ->with(['items.product.serviceLocations', 'images'])
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /** GET /combos — danh sách combo + date-picker chung (?start=&end=). */
    public function index(Request $request): Response
    {
        [$start, $end] = $this->parseRange($request);

        $sellable = $this->sellable()->get();
        // Tồn kho tất cả combo trong 1 query gộp (chống N+1) — null khi khách chưa chọn ngày.
        $availability = ($start && $end)
            ? $this->availability->combosAvailable($sellable, $start, $end)
            : [];

        $combos = $sellable->map(function (Combo $combo) use ($start, $end, $availability) {
            $shaped = $this->shape($combo);
            $shaped['available'] = ($start && $end) ? ($availability[$combo->id] ?? 0) : null;

            return $shaped;
        });

        return Inertia::render('Combos', [
            'combos' => $combos,
            'filters' => [
                'start' => $start?->toDateString() ?? '',
                'end' => $end?->toDateString() ?? '',
            ],
        ]);
    }

    /** GET /combos/{slug} — chi tiết combo: gallery, so sánh giá, check tồn kho. */
    public function show(string $slug): Response
    {
        /** @var Combo $combo */
        $combo = $this->sellable()->where('slug', $slug)->firstOrFail();

        $shaped = $this->shape($combo);

        $seoDesc = Str::limit(trim(strip_tags((string) $combo->description)), 155)
            ?: 'Thuê trọn bộ '.$combo->name.' — tiết kiệm '.$combo->savingsPercent().'% so với thuê lẻ tại BỐP CAMPING.';

        return Inertia::render('ComboDetail', [
            'combo' => $shaped,
            'seo' => [
                'title' => $combo->name.' — Thuê trọn bộ tại BỐP CAMPING',
                'description' => $seoDesc,
                'image' => $shaped['images'][0]['url'] ?? url('/images/album/forest-camp-aerial.jpg'),
                'url' => url()->current(),
            ],
        ]);
    }

    /**
     * GET /combos/{slug}/kha-dung?start=&end= — check tồn kho realtime (Case 4).
     *
     * Trả: số combo còn thuê được; món nào hết (tên + còn/cần); khoảng gần nhất
     * còn đủ trong 30 ngày tới; sản phẩm thay thế cùng danh mục (chỉ tham khảo).
     */
    public function availability(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate([
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start'],
        ]);

        /** @var Combo $combo */
        $combo = $this->sellable()->where('slug', $slug)->firstOrFail();

        $start = Carbon::parse($data['start']);
        $end = Carbon::parse($data['end']);

        $available = $this->availability->comboAvailable($combo, $start, $end);

        $insufficient = [];
        $nextWindow = null;
        $substitutes = [];

        if ($available < 1) {
            $rows = $this->availability->comboInsufficientItems($combo, $start, $end);
            $insufficient = array_map(fn (array $row) => [
                'product_id' => $row['product']->id,
                'name' => $row['product']->name,
                'available' => $row['available'],
                'required' => $row['required'],
            ], $rows);

            $nextWindow = $this->availability->nextComboWindow($combo, $start, $end);

            // Thay thế cùng danh mục với các món hết, còn hàng trong khoảng — v1 chỉ hiển thị
            // tham khảo, chưa cho swap trong combo (PRD 5.5).
            $categoryIds = collect($rows)->map(fn (array $row) => $row['product']->category_id)->unique();
            $excludeIds = $combo->items->pluck('product_id');
            $substitutes = Product::active()
                ->whereIn('category_id', $categoryIds)
                ->whereNotIn('id', $excludeIds)
                ->orderBy('price_per_day')
                ->limit(6)
                ->get()
                ->filter(fn (Product $p) => $this->availability->isAvailable($p, $start, $end))
                ->take(4)
                ->map(fn (Product $p) => [
                    'id' => $p->id,
                    'slug' => $p->slug,
                    'name' => $p->name,
                    'price_per_day' => (int) $p->price_per_day,
                    'thumbnail' => $p->thumbnail ? Storage::disk('media')->url($p->thumbnail) : null,
                ])
                ->values()
                ->all();
        }

        return response()->json([
            'available' => $available,
            'insufficient' => $insufficient,
            'next_window' => $nextWindow,
            'substitutes' => $substitutes,
        ]);
    }

    /** Combo Eloquent → array cho Inertia (card + detail dùng chung). */
    private function shape(Combo $combo): array
    {
        $sumIndividual = $combo->sumIndividualPrice();

        $data = [
            'id' => $combo->id,
            'name' => $combo->name,
            'slug' => $combo->slug,
            'description' => $combo->description,
            'combo_price' => (int) $combo->combo_price,
            'deposit' => (int) ($combo->deposit ?? 0),
            'suitable_for' => $combo->suitable_for,
            'sum_individual' => $sumIndividual,
            'savings_amount' => $combo->savingsAmount(),
            'savings_percent' => $combo->savingsPercent(),
            'items' => $combo->items->map(fn (ComboItem $item) => [
                'product_id' => $item->product_id,
                'slug' => $item->product?->slug,
                'name' => $item->product?->name,
                'quantity' => $item->quantity,
                'price_per_day' => (int) ($item->product?->price_per_day ?? 0),
                'thumbnail' => $item->product?->thumbnail
                    ? Storage::disk('media')->url($item->product->thumbnail)
                    : null,
            ])->values()->all(),
            'images' => $combo->images->map(fn ($img) => [
                'url' => Storage::disk('media')->url($img->path),
                'type' => $img->type,
            ])->values()->all(),
            'locations' => $combo->commonOpenLocations(),
        ];

        $data['all_locations'] = count($data['locations']) > 0
            && count($data['locations']) === ServiceLocation::open()->count();

        return $data;
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} */
    private function parseRange(Request $request): array
    {
        $start = $request->query('start', '');
        $end = $request->query('end', '');

        $valid = is_string($start) && is_string($end)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)
            && $start <= $end;

        return $valid ? [Carbon::parse($start), Carbon::parse($end)] : [null, null];
    }
}

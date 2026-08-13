<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Concerns\ParsesRentalRange;
use App\Http\Controllers\Controller;
use App\Models\Combo;
use App\Models\ComboItem;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Services\AvailabilityService;
use App\Services\SeoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ComboController extends Controller
{
    use ParsesRentalRange;

    public function __construct(private AvailabilityService $availability, private SeoService $seo) {}

    /** Combo bán được: đang active và có ít nhất 1 món. */
    private function sellable()
    {
        return Combo::active()
            ->whereHas('items')
            ->with(['items.product.serviceLocations', 'serviceLocations', 'images'])
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /** GET /combos — danh sách combo + date-picker chung (?start=&end=). */
    public function index(Request $request): Response
    {
        [$start, $end] = $this->parseRange($request);

        // ?vi-tri= vừa LỌC combo (chỉ trả combo được gán kho đó) vừa dùng để tính khả dụng đúng kho
        // (bopcamping-zdeh — combo giờ có kho riêng, không còn dùng chung logic "không lọc" cũ).
        $openLocations = ServiceLocation::open()->ordered()->get();
        $locParam = $request->query('vi-tri', '');
        $activeLocation = is_string($locParam) && $locParam !== ''
            ? $openLocations->firstWhere('slug', $locParam)
            : null;

        $comboModels = $this->sellable()
            ->when(
                $activeLocation,
                fn ($q) => $q->whereHas('serviceLocations', fn ($q2) => $q2->whereKey($activeLocation->id))
            )
            ->get();
        $hasRange = $start && $end;

        // 1 query cho toàn bộ món con của mọi combo (trước đây N combo × M món = N×M query).
        $availability = $hasRange
            ? $this->availability->comboQuantitiesFor($comboModels, $start, $end, $activeLocation)
            : [];

        // Đếm 1 lần cho cả danh sách — trong shape() sẽ thành 1 query/combo (N+1).
        $openLocationCount = $openLocations->count();

        $combos = $comboModels->map(function (Combo $combo) use ($hasRange, $availability, $openLocationCount) {
            $shaped = $this->shape($combo, $openLocationCount);
            // Còn/hết theo khoảng ngày đã chọn — null khi khách chưa chọn ngày
            $shaped['available'] = $hasRange ? ($availability[$combo->id] ?? 0) : null;
            $shaped['in_range'] = $hasRange ? ($shaped['available'] >= 1) : null;

            return $shaped;
        });

        if ($hasRange) {
            // Combo đặt được lên trước, giữ thứ tự phụ (sort_order, name) — sort của PHP 8 là stable.
            $combos = $combos->sortByDesc(fn (array $c) => $c['in_range'] ? 1 : 0)->values();
        }

        return Inertia::render('Combos', [
            // Không khai seo riêng thì rơi vào mặc định site-wide, tức là với Google trang
            // này trùng hệt trang chủ (bopcamping-u3u3).
            'seo' => $this->seo->page(
                'Combo thuê đồ cắm trại trọn bộ — tiết kiệm hơn thuê lẻ',
                // Danh mục lấy từ DB, KHÔNG gõ tay: thêm/đổi tên danh mục trong admin thì
                // câu này tự theo. Đã bỏ đoạn "giao nhận tận nơi tại Vinh & Hà Nội" — địa
                // điểm là dữ liệu admin quản lý, hardcode vào đây là sai ngay khi mở cơ sở
                // mới (đã đo: mở Đà Nẵng thì description vẫn ghi Vinh & Hà Nội).
                'Combo '.$this->seo->categoryPhrase().' gói sẵn theo nhu cầu. Thuê trọn bộ rẻ hơn thuê lẻ, cọc linh hoạt, trả tiền khi nhận (COD).',
                jsonld: [
                    $this->seo->breadcrumb([
                        ['Trang chủ', url('/')],
                        ['Combo', url('/combos')],
                    ]),
                    // ItemList để Google hiểu đây là trang DANH SÁCH và biết có gì trong
                    // đó, thay vì chỉ thấy một trang chữ (bopcamping-gyg8). Dùng thứ tự
                    // đang render — khách thấy sao thì khai vậy.
                    $this->comboListJsonLd($combos),
                ],
            ),
            'combos' => $combos,
            'service_locations' => $openLocations->map(fn (ServiceLocation $l) => [
                'name' => $l->name,
                'slug' => $l->slug,
            ])->values(),
            'filters' => [
                'start' => $start?->toDateString() ?? '',
                'end' => $end?->toDateString() ?? '',
                'vi_tri' => $activeLocation ? $activeLocation->slug : '',
            ],
            'range_summary' => $hasRange ? [
                'days' => $start->diffInDays($end) + 1,
                'unavailable_count' => $combos->where('in_range', false)->count(),
            ] : null,
        ]);
    }

    /** GET /combos/{slug} — chi tiết combo: gallery, so sánh giá, check tồn kho. */
    public function show(string $slug): Response
    {
        /** @var Combo $combo */
        $combo = $this->sellable()->where('slug', $slug)->firstOrFail();

        $shaped = $this->shape($combo, ServiceLocation::open()->count());

        // Qua SeoService::plainText — xem ghi chú ở ProductController (bopcamping-1xja).
        $seoDesc = $this->seo->plainText($combo->description, 155)
            ?: 'Thuê trọn bộ '.$combo->name.' — tiết kiệm '.$combo->savingsPercent().'% so với thuê lẻ tại BỐP CAMPING.';

        // Combo chưa có ảnh thì rơi về bìa thương hiệu 1200x630 (bopcamping-marf).
        $seoImage = $shaped['images'][0]['url'] ?? url('/images/og-cover.jpg');

        return Inertia::render('ComboDetail', [
            'combo' => $shaped,
            'stores' => $this->storesFor($combo),
            'seo' => $this->seo->page(
                $combo->name.' — Thuê trọn bộ tại BỐP CAMPING',
                $seoDesc,
                $seoImage,
                // Trước đây chi tiết combo chỉ có title/desc, thiếu hẳn Product + Breadcrumb
                // mà chi tiết sản phẩm đã có (bopcamping-u3u3).
                jsonld: [
                    $this->comboJsonLd($combo, $shaped, $seoImage, $seoDesc),
                    $this->seo->breadcrumb([
                        ['Trang chủ', url('/')],
                        ['Combo', url('/combos')],
                        [$combo->name, url()->current()],
                    ]),
                ],
            ),
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

    /**
     * Combo Eloquent → array cho Inertia (card + detail dùng chung).
     *
     * @param  int  $openLocationCount  số kho đang mở — TRUYỀN VÀO, đếm sẵn 1 lần ở phía gọi.
     *                                  Đếm bên trong đây = 1 query/combo (N+1) khi map danh sách.
     */
    /**
     * Danh sách cơ sở để hiện trên trang combo.
     *
     * Chủ shop chốt: mỗi combo dựng ở MỘT địa điểm cố định, nhưng vẫn hiện đủ các cơ sở và
     * KHOÁ những nơi không có — để khách (và admin) nhìn phát biết combo nằm ở đâu, thay vì
     * phải đoán từ một nhãn chữ.
     *
     * Cố ý KHÔNG ép "đúng một cơ sở" trong dữ liệu: hiện đã có combo gắn 2 cơ sở (chủ shop
     * tự chỉnh). Hàm này đúng cho cả hai — 1 nơi thì khoá cái còn lại, 2 nơi thì không khoá
     * cái nào.
     *
     * @return array<int, array{id: int, name: string, slug: string, served: bool}>
     */
    /**
     * ItemList cho trang /combos (bopcamping-gyg8).
     *
     * Trang danh sách trước đây chỉ có BreadcrumbList nên với Google nó là một trang chữ
     * không rõ chứa gì. ItemList nêu tên + link + giá từng combo, giúp Google hiểu cấu
     * trúc và có cửa hiện dạng danh sách trên SERP.
     *
     * Khai theo ĐÚNG thứ tự đang render (đã sắp: combo đặt được lên trước) — khách thấy
     * thứ tự nào thì markup nói thứ tự đó.
     *
     * @param  Collection<int, array<string, mixed>>  $combos
     * @return array<string, mixed>
     */
    private function comboListJsonLd($combos): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => 'Combo thuê đồ cắm trại',
            'numberOfItems' => $combos->count(),
            'itemListElement' => $combos->values()->map(fn (array $c, int $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'item' => array_filter([
                    '@type' => 'Product',
                    'name' => $c['name'] ?? null,
                    'url' => ! empty($c['slug']) ? url('/combos/'.$c['slug']) : null,
                    'offers' => ! empty($c['combo_price']) ? [
                        '@type' => 'Offer',
                        'price' => (int) $c['combo_price'],
                        'priceCurrency' => 'VND',
                    ] : null,
                ], fn ($v) => $v !== null),
            ])->all(),
        ];
    }

    /**
     * Product JSON-LD cho combo (bopcamping-u3u3, mở rộng ở bopcamping-gyg8).
     *
     * Dùng `Product` chứ không `ProductCollection`: combo bán như MỘT món — một giá,
     * một mức cọc, không cho khách tách lẻ. `price` là giá thuê/ngày trọn bộ, cùng đơn
     * vị với sản phẩm lẻ (KHÔNG phải tổng nhiều ngày).
     *
     * KHÔNG khai `aggregateRating`: review trong dự án chỉ gắn vào `product_id`, combo
     * không có review riêng. Mượn điểm của món bên trong rồi gán cho combo là bịa số —
     * Google phạt nặng chuyện này.
     *
     * @param  array<string, mixed>  $shaped
     * @return array<string, mixed>
     */
    private function comboJsonLd(Combo $combo, array $shaped, string $image, string $desc): array
    {
        // Nhiều ảnh giúp Google chọn được ảnh hợp ngữ cảnh; ảnh chính vẫn đứng đầu.
        $images = collect($shaped['images'] ?? [])
            ->pluck('url')->filter()->values()->all();

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $combo->name,
            'description' => $desc,
            'image' => $images ?: $image,
            'sku' => 'COMBO-'.$combo->slug,
            'category' => 'Combo thuê đồ cắm trại',
            'brand' => ['@type' => 'Brand', 'name' => SeoService::BRAND],
            'offers' => [
                '@type' => 'Offer',
                'price' => (int) $combo->combo_price,
                'priceCurrency' => 'VND',
                // Trang này không tra tồn theo ngày (khách chưa chọn ngày ở đây) nên khai
                // InStock khi combo còn bán — đừng bịa OutOfStock lúc chưa biết.
                'availability' => 'https://schema.org/InStock',
                'url' => url()->current(),
                'description' => 'Giá thuê trọn bộ theo ngày',
                'seller' => ['@type' => 'Organization', 'name' => SeoService::BRAND],
            ],
            // `hasPart` = combo GỒM những món này. Trước dùng `isSimilarTo` là sai nghĩa
            // (nó có nghĩa "sản phẩm tương tự"), tức là đang nói với Google rằng combo
            // giống mấy món đó chứ không phải chứa chúng.
            'hasPart' => collect($shaped['items'] ?? [])
                ->filter(fn (array $it) => ! empty($it['name']))
                ->map(fn (array $it) => array_filter([
                    '@type' => 'Product',
                    'name' => $it['name'],
                    'url' => ! empty($it['slug']) ? url('/thiet-bi/'.$it['slug']) : null,
                ]))
                ->values()
                ->all(),
        ];

        // Mức tiết kiệm là lợi thế bán hàng chính của combo — khai ra để Google đọc được.
        $props = [];
        if (($shaped['savings_percent'] ?? 0) > 0) {
            $props[] = [
                '@type' => 'PropertyValue',
                'name' => 'Tiết kiệm so với thuê lẻ',
                'value' => $shaped['savings_percent'].'%',
            ];
        }
        if (($shaped['deposit'] ?? 0) > 0) {
            $props[] = [
                '@type' => 'PropertyValue',
                'name' => 'Tiền cọc (hoàn lại)',
                'value' => $shaped['deposit'].' VND',
            ];
        }
        if ($props) {
            $data['additionalProperty'] = $props;
        }

        // suitable_for là SỐ NGƯỜI (không phải nhãn đối tượng) — map sang suggestedMinAge
        // thì sai, dùng PeopleAudience.suggestedMaxAge cũng sai. Diễn đạt bằng audience
        // có tên rõ ràng là an toàn và vẫn đúng nghĩa.
        if (($combo->suitable_for ?? 0) > 0) {
            $data['audience'] = [
                '@type' => 'PeopleAudience',
                'name' => 'Nhóm '.$combo->suitable_for.' người',
            ];
        }

        return $data;
    }

    private function storesFor(Combo $combo): array
    {
        $servedIds = $combo->openLocationIds();

        return ServiceLocation::open()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (ServiceLocation $l) => [
                'id' => $l->id,
                'name' => $l->name,
                'slug' => $l->slug,
                'served' => in_array($l->id, $servedIds, true),
            ])
            ->values()
            ->all();
    }

    private function shape(Combo $combo, int $openLocationCount): array
    {
        $sumIndividual = $combo->sumIndividualPrice();

        $data = [
            'id' => $combo->id,
            'name' => $combo->name,
            'slug' => $combo->slug,
            'description' => $combo->description,
            'combo_price' => (int) $combo->combo_price,
            'deposit' => (int) ($combo->deposit ?? 0),
            'early_return_pct' => (int) $combo->early_return_discount_pct,
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
            'locations' => $combo->openLocations(),
        ];

        $data['all_locations'] = count($data['locations']) > 0
            && count($data['locations']) === $openLocationCount;

        return $data;
    }
}

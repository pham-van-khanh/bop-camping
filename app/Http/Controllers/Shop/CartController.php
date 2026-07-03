<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Combo;
use App\Models\ComboEvent;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromotionSetting;
use App\Models\ServiceLocation;
use App\Models\Voucher;
use App\Services\ComboDetectionService;
use App\Services\ComboSuggestion;
use App\Services\Promotion\VoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function __construct(
        private ComboDetectionService $detector,
        private VoucherService $voucherService,
    ) {}

    public function index(Request $request): Response
    {
        $user = Auth::user();
        $settings = PromotionSetting::current();

        $vouchers = $user
            ? $user->vouchers()->usable()->orderByRaw('expires_at IS NULL')->orderBy('expires_at')->get()
                ->map(fn (Voucher $v) => [
                    'code' => $v->code,
                    'type' => $v->type,
                    'value' => (float) $v->value,
                    'source' => $v->source,
                    'expires_at' => $v->expires_at?->toDateString(),
                ])->values()
            : [];

        // Khách đủ điều kiện nhập mã giới thiệu nếu CHƯA có đơn nào (đơn đầu).
        $firstOrderEligible = $user
            ? ! Order::where('user_id', $user->id)->exists()
            : false;

        return Inertia::render('Cart', [
            'availableVouchers' => $vouchers,
            'referralRef' => $request->session()->get('referral_ref', ''),
            'firstOrderEligible' => $firstOrderEligible,
            'promo' => [
                'enabled' => (bool) $settings->referral_enabled,
                'maxDiscountPercent' => (float) $settings->max_discount_percent_per_order,
                'maxStack' => (int) $settings->max_vouchers_stack_per_order,
                'minOrderAmount' => (float) $settings->min_order_amount,
                'refereeDiscountType' => $settings->referee_discount_type,
                'refereeDiscountValue' => (float) $settings->referee_discount_value,
            ],
        ]);
    }

    /**
     * GET /gio-thue/lam-tuoi?ids[]=1&combo_ids[]=2 — làm tươi giỏ.
     *
     * Giỏ nằm ở localStorage nên lưu "ảnh chụp" giá/vị trí lúc thêm món. Admin có thể đổi
     * giá/vị trí/ẩn sản phẩm hoặc combo sau đó → trả dữ liệu MỚI NHẤT để client đồng bộ
     * lại. Sản phẩm/combo đã ẩn/xoá sẽ KHÔNG có trong kết quả → client gỡ khỏi giỏ.
     */
    public function refresh(Request $request): JsonResponse
    {
        $ids = $this->idsFromQuery($request, 'ids');
        $comboIds = $this->idsFromQuery($request, 'combo_ids');

        if ($ids->isEmpty() && $comboIds->isEmpty()) {
            return response()->json(['products' => (object) [], 'combos' => (object) []]);
        }

        $openCount = ServiceLocation::open()->count();

        $products = Product::active()
            ->with('serviceLocations')
            ->whereIn('id', $ids)
            ->get()
            ->mapWithKeys(function (Product $p) use ($openCount) {
                $open = $p->serviceLocations->where('status', 'open');

                return [$p->id => [
                    'name' => $p->name,
                    'price_per_day' => (int) $p->price_per_day,
                    'deposit' => (int) ($p->deposit ?? 0),
                    'locations' => $open->map(fn (ServiceLocation $l) => ['slug' => $l->slug, 'name' => $l->name])->values(),
                    'all_locations' => $openCount > 0 && $open->count() === $openCount,
                ]];
            });

        // Combo trong giỏ: giá/cọc/vị trí mới nhất; combo ẩn (vd US-07) không trả về → client gỡ.
        $combos = Combo::active()
            ->whereHas('items')
            ->with('items.product.serviceLocations')
            ->whereIn('id', $comboIds)
            ->get()
            ->mapWithKeys(function (Combo $c) use ($openCount) {
                $locations = $c->commonOpenLocations();

                return [$c->id => [
                    'name' => $c->name,
                    'combo_price' => (int) $c->combo_price,
                    'deposit' => (int) ($c->deposit ?? 0),
                    'items' => $c->items->map(fn ($i) => ['name' => $i->product?->name ?? '', 'qty' => $i->quantity])->values(),
                    'locations' => $locations,
                    'all_locations' => $openCount > 0 && count($locations) === $openCount,
                ]];
            });

        return response()->json(['products' => $products, 'combos' => $combos]);
    }

    /** @return Collection<int, int> */
    private function idsFromQuery(Request $request, string $key)
    {
        return collect($request->query($key, []))
            ->map(fn ($x) => (int) $x)
            ->filter()
            ->unique()
            ->take(100)
            ->values();
    }

    /**
     * POST /gio-thue/goi-y-combo — cart combo detection (PRD 5.4, Case 3).
     *
     * Nhận snapshot giỏ (items lẻ + combo đã có + voucher đã chọn), trả tối đa
     * 1 gợi ý (exact/superset/upsell) kèm đủ dữ liệu để FE convert 1 click.
     * Giỏ nhiều khoảng ngày → detect từng khoảng, chọn gợi ý tốt nhất.
     * Log combo_suggestion_shown (US-09), khử trùng lặp theo session.
     */
    public function suggestion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['sometimes', 'array', 'max:50'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.start' => ['required', 'date_format:Y-m-d'],
            'items.*.end' => ['required', 'date_format:Y-m-d'],
            'combos' => ['sometimes', 'array', 'max:20'],
            'combos.*.combo_id' => ['required', 'integer'],
            'combos.*.quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'combos.*.start' => ['required', 'date_format:Y-m-d'],
            'combos.*.end' => ['required', 'date_format:Y-m-d'],
            'voucher_codes' => ['sometimes', 'array', 'max:10'],
            'voucher_codes.*' => ['string', 'max:30'],
        ]);

        $items = collect($data['items'] ?? []);

        // Detection chạy trên items lẻ CÙNG khoảng ngày (PRD 5.4)
        $best = null;
        $bestRange = null;
        foreach ($items->groupBy(fn (array $i) => $i['start'].'|'.$i['end']) as $key => $group) {
            [$start, $end] = explode('|', (string) $key);
            if ($end < $start) {
                continue;
            }
            $s = $this->detector->detect($group->values(), $start, $end);
            if (! $s) {
                continue;
            }
            // Cùng tiêu chí xếp hạng với service: khớp đủ trước upsell, rồi tiết kiệm nhất
            $beats = $best === null
                || ($best->type === 'upsell' && $s->type !== 'upsell')
                || (($best->type === 'upsell') === ($s->type === 'upsell') && $s->savings > $best->savings);
            if ($beats) {
                $best = $s;
                $bestRange = [$start, $end];
            }
        }

        if (! $best) {
            return response()->json(['suggestion' => null]);
        }

        [$start, $end] = $bestRange;
        $days = $this->days($start, $end);

        // Ràng buộc PRD 5.4 + mục 7: convert phải rẻ hơn CẢ SAU khi tính voucher
        if (! $this->cheaperAfterVouchers($request, $best, $items, collect($data['combos'] ?? []), $days)) {
            return response()->json(['suggestion' => null]);
        }

        $this->logShownOnce($request, $best, $start, $end);

        $combo = $best->combo;

        return response()->json(['suggestion' => [
            'type' => $best->type,
            'savings' => $best->savings,
            'savings_total' => $best->savings * $days,
            'days' => $days,
            'start' => $start,
            'end' => $end,
            'combo' => [
                'id' => $combo->id,
                'name' => $combo->name,
                'slug' => $combo->slug,
                'combo_price' => (int) $combo->combo_price,
                'deposit' => (int) ($combo->deposit ?? 0),
                'sum_individual' => $combo->sumIndividualPrice(),
                'items' => $combo->items->map(fn ($i) => [
                    'product_id' => $i->product_id,
                    'name' => $i->product?->name ?? '',
                    'qty' => (int) $i->quantity,
                ])->values(),
                'locations' => $combo->commonOpenLocations(),
            ],
            // upsell: món thiếu kèm đủ dữ liệu để FE "thêm nhanh" thành CartLine
            'missing' => $best->missingItems->map(fn ($i) => [
                'product_id' => $i->product_id,
                'name' => $i->product?->name ?? '',
                'qty' => (int) $i->missing,
                'price_per_day' => (int) ($i->product?->price_per_day ?? 0),
                'deposit' => (int) ($i->product?->deposit ?? 0),
                'category_slug' => $i->product?->category?->slug ?? '',
                'locations' => $i->product?->serviceLocations
                    ?->where('status', 'open')
                    ->map(fn (ServiceLocation $l) => ['slug' => $l->slug, 'name' => $l->name])
                    ->values() ?? [],
            ])->values(),
        ]]);
    }

    /**
     * POST /gio-thue/goi-y-combo/da-chuyen — khách bấm convert/thêm nhanh (US-09).
     */
    public function suggestionConverted(Request $request): JsonResponse
    {
        $data = $request->validate([
            'combo_id' => ['required', 'integer', 'exists:combos,id'],
            'suggestion_type' => ['required', 'in:exact,superset,upsell'],
        ]);

        ComboEvent::create([
            'combo_id' => (int) $data['combo_id'],
            'event' => ComboEvent::CONVERTED,
            'suggestion_type' => $data['suggestion_type'],
            'user_id' => $request->user()?->id,
        ]);

        // Gợi ý này đã chốt — banner sau (nếu có) được log shown như gợi ý mới
        $request->session()->forget('combo_suggestion_shown');

        return response()->json(['ok' => true]);
    }

    /** Số ngày thuê (inclusive) — cùng cách tính với dayCount phía FE. */
    private function days(string $start, string $end): int
    {
        return (int) abs(Carbon::parse($start)->diffInDays(Carbon::parse($end))) + 1;
    }

    /**
     * PRD 5.4 + mục 7: chỉ gợi ý khi tổng phải trả SAU voucher của phương án combo
     * thấp hơn phương án thuê lẻ. Voucher thường không giảm phần combo (AC-8) nên
     * convert làm giảm base của voucher thường — có thể khiến convert đắt hơn.
     * Không đăng nhập / không chọn voucher → savings > 0 là đủ.
     */
    private function cheaperAfterVouchers(
        Request $request,
        ComboSuggestion $s,
        Collection $items,
        Collection $comboLines,
        int $days,
    ): bool {
        $user = $request->user();
        $codes = array_values(array_filter((array) $request->input('voucher_codes', [])));
        if (! $user || $codes === []) {
            return true;
        }

        // Tính tiền từ giá DB — payload client không đáng tin
        $products = Product::whereIn('id', $items->pluck('product_id'))->get()->keyBy('id');
        $itemsRent = (int) $items->sum(function (array $i) use ($products) {
            $p = $products->get((int) $i['product_id']);

            return $p ? (int) $p->price_per_day * (int) $i['quantity'] * $this->days($i['start'], $i['end']) : 0;
        });

        $cartCombos = Combo::whereIn('id', $comboLines->pluck('combo_id'))->get()->keyBy('id');
        $comboRent = (int) $comboLines->sum(function (array $c) use ($cartCombos) {
            $cb = $cartCombos->get((int) $c['combo_id']);

            return $cb ? (int) $cb->combo_price * (int) $c['quantity'] * $this->days($c['start'], $c['end']) : 0;
        });

        // upsell: phương án lẻ = giỏ hiện tại + thuê thêm món thiếu theo giá lẻ
        $missingRent = (int) $s->missingItems->sum(
            fn ($i) => (int) ($i->product?->price_per_day ?? 0) * (int) $i->missing * $days
        );

        $baseIndividual = $itemsRent + $comboRent + $missingRent;
        $baseCombo = $baseIndividual - $s->savings * $days;

        $settings = PromotionSetting::current();

        $dIndividual = $this->voucherService->quote(
            $baseIndividual,
            $this->voucherService->eligibleVouchers($user, $baseIndividual, $settings, $codes),
            $settings,
            $baseIndividual - $comboRent, // phần lẻ của kịch bản giữ nguyên
        )['total'];

        $comboPartAfter = $comboRent + (int) $s->combo->combo_price * $days;
        $dCombo = $this->voucherService->quote(
            $baseCombo,
            $this->voucherService->eligibleVouchers($user, $baseCombo, $settings, $codes),
            $settings,
            max(0, $baseCombo - $comboPartAfter),
        )['total'];

        return ($baseCombo - $dCombo) < ($baseIndividual - $dIndividual);
    }

    /**
     * Log combo_suggestion_shown — chỉ khi gợi ý KHÁC lần gần nhất trong session,
     * tránh đếm trùng mỗi lần giỏ đổi mà banner vẫn vậy (US-09 cần convert-rate thật).
     */
    private function logShownOnce(Request $request, ComboSuggestion $s, string $start, string $end): void
    {
        $key = $s->combo->id.'|'.$s->type.'|'.$start.'|'.$end;
        if ($request->session()->get('combo_suggestion_shown') === $key) {
            return;
        }

        $request->session()->put('combo_suggestion_shown', $key);

        ComboEvent::create([
            'combo_id' => $s->combo->id,
            'event' => ComboEvent::SHOWN,
            'suggestion_type' => $s->type,
            'user_id' => $request->user()?->id,
        ]);
    }
}

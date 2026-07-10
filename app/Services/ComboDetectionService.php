<?php

namespace App\Services;

use App\Models\Combo;
use App\Models\ComboItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Cart combo detection (PRD combo 5.4, Case 3 — AC-5/AC-6): so items lẻ trong giỏ
 * (cùng khoảng ngày) với combo_items của các combo active.
 *
 *   exact    — giỏ khớp đúng combo (đủ loại, đủ quantity, không thừa)
 *   superset — khớp đủ combo + có món/quantity thừa (phần thừa giữ nguyên lẻ)
 *   upsell   — thiếu đúng 1 loại sản phẩm (thiếu hẳn hoặc thiếu quantity)
 *
 * Ràng buộc PRD: combo còn hàng trong khoảng ngày (qua AvailabilityService — AC-10),
 * tiết kiệm > 0 (convert không rẻ hơn thì không gợi ý), tối đa 1 gợi ý —
 * ưu tiên giỏ ĐÃ khớp (exact/superset) hơn upsell, cùng nhóm thì tiết kiệm nhất.
 * So sánh "rẻ hơn sau voucher" nằm ở tầng HTTP (cần user + mã voucher đã chọn).
 */
class ComboDetectionService
{
    public function __construct(private AvailabilityService $availability) {}

    /**
     * @param  Collection<int, array{product_id: int, quantity: int}>  $cartItems  items LẺ cùng khoảng ngày
     */
    public function detect(Collection $cartItems, Carbon|string $start, Carbon|string $end): ?ComboSuggestion
    {
        $start = Carbon::parse($start);
        $end = Carbon::parse($end);

        // Gom số lượng theo sản phẩm (giỏ có thể tách nhiều dòng cùng sản phẩm)
        $have = $cartItems
            ->groupBy(fn (array $i) => (int) $i['product_id'])
            ->map(fn (Collection $g) => (int) $g->sum('quantity'))
            ->filter(fn (int $q) => $q > 0);

        if ($have->isEmpty()) {
            return null;
        }

        $candidates = Combo::active()
            ->whereHas('items')
            ->with('items.product')
            ->get()
            ->map(fn (Combo $combo) => $this->evaluate($combo, $have))
            ->filter()
            ->values();

        if ($candidates->isEmpty()) {
            return null;
        }

        // Gợi ý combo hết hàng = trải nghiệm ngược (AC-6). Lọc tồn kho các combo ứng viên
        // trong 1 query gộp (chống N+1) thay vì gọi isComboAvailable từng combo.
        $availability = $this->availability->combosAvailable(
            $candidates->map(fn (ComboSuggestion $s) => $s->combo),
            $start,
            $end,
        );

        return $candidates
            ->filter(fn (ComboSuggestion $s) => ($availability[$s->combo->id] ?? 0) >= 1)
            ->sortBy([
                fn (ComboSuggestion $a, ComboSuggestion $b) => (int) ($a->type === 'upsell') <=> (int) ($b->type === 'upsell'),
                fn (ComboSuggestion $a, ComboSuggestion $b) => $b->savings <=> $a->savings,
            ])
            ->first();
    }

    /**
     * Đánh giá 1 combo với giỏ — null nếu không đủ điều kiện gợi ý.
     *
     * @param  Collection<int, int>  $have  [product_id => tổng quantity trong giỏ]
     */
    private function evaluate(Combo $combo, Collection $have): ?ComboSuggestion
    {
        $savings = $combo->sumIndividualPrice() - (int) $combo->combo_price;
        if ($savings <= 0) {
            return null;
        }

        $missing = $combo->items
            ->filter(fn (ComboItem $i) => ($have[$i->product_id] ?? 0) < $i->quantity)
            ->map(function (ComboItem $i) use ($have) {
                $i->setAttribute('missing', $i->quantity - (int) ($have[$i->product_id] ?? 0));

                return $i;
            })
            ->values();

        if ($missing->count() > 1) {
            return null; // thiếu ≥2 loại → không gợi ý (PRD 5.4)
        }

        if ($missing->count() === 1) {
            return new ComboSuggestion('upsell', $combo, $savings, $missing);
        }

        // Đủ hết: exact nếu giỏ không có gì ngoài combo, ngược lại superset
        $needed = $combo->items->keyBy('product_id')->map(fn (ComboItem $i) => (int) $i->quantity);
        $extra = $have->contains(fn (int $qty, int $productId) => $qty > ($needed[$productId] ?? 0));

        return new ComboSuggestion($extra ? 'superset' : 'exact', $combo, $savings, collect());
    }
}

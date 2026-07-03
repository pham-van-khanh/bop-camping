<?php

namespace App\Services;

use App\Models\Combo;
use App\Models\ComboItem;
use Illuminate\Support\Collection;

/**
 * Phân bổ giá/cọc combo vào từng món con — single source of truth cho PRD 5.3.
 *
 * allocated_price_i = combo_price × (price_i × qty_i) / sum_individual,
 * làm tròn XUỐNG bội số 100₫; món cuối nhận phần dư để tổng khớp đúng combo_price
 * (không lệch một đồng nào — AC-3). Cọc phân bổ cùng công thức từ combo.deposit.
 */
class ComboPricingService
{
    /**
     * @return array<int, array{product_id:int, quantity:int, price_per_day:int, allocated_price:int, allocated_deposit:int}>
     */
    public function allocate(Combo $combo): array
    {
        $combo->loadMissing('items.product');

        /** @var Collection<int, ComboItem> $items */
        $items = $combo->items->values();

        if ($items->isEmpty()) {
            return [];
        }

        $weights = $items->map(
            fn (ComboItem $item) => (int) ($item->product?->price_per_day ?? 0) * $item->quantity
        );
        $sumIndividual = (int) $weights->sum();

        $priceLines = $this->split((int) $combo->combo_price, $weights->all(), $sumIndividual);
        $depositLines = $this->split((int) ($combo->deposit ?? 0), $weights->all(), $sumIndividual);

        return $items->map(fn (ComboItem $item, int $i) => [
            'product_id' => $item->product_id,
            'quantity' => $item->quantity,
            'price_per_day' => (int) ($item->product?->price_per_day ?? 0),
            'allocated_price' => $priceLines[$i],
            'allocated_deposit' => $depositLines[$i],
        ])->all();
    }

    /**
     * Chia $total theo trọng số, floor bội 100 cho mọi dòng trừ dòng cuối (nhận dư).
     * Trọng số toàn 0 (giá lẻ = 0 — dữ liệu lỗi) → dồn hết vào dòng cuối.
     *
     * @param  array<int, int>  $weights
     * @return array<int, int>
     */
    private function split(int $total, array $weights, int $sumWeights): array
    {
        $count = count($weights);
        $lines = [];
        $allocated = 0;

        foreach ($weights as $i => $weight) {
            if ($i === $count - 1) {
                $lines[] = $total - $allocated; // món cuối nhận dư — tổng khớp từng đồng
            } else {
                $share = $sumWeights > 0
                    ? intdiv((int) floor($total * $weight / $sumWeights), 100) * 100
                    : 0;
                $lines[] = $share;
                $allocated += $share;
            }
        }

        return $lines;
    }
}

<?php

namespace App\Services;

use App\Models\Combo;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Tách đơn theo khoảng ngày thuê (bopcamping-wtuv).
 *
 * Giỏ 1 khoảng ngày → 1 đơn THƯỜNG (như trước). Giỏ ≥2 khoảng → 1 đơn CHA
 * (is_parent, không món, gom envelope + tổng) + N đơn CON (mỗi khoảng 1 con, mã
 * cha-i, có món + ngày + tổng + cọc riêng, status pending). Voucher/giảm giá do
 * controller xử lý SAU (đơn thường: như cũ; cha: bopcamping-wtuv T3).
 *
 * Giá net theo bậc giảm dài ngày tính qua RentalPricingService (nguồn chân lý).
 */
class OrderSplitter
{
    public function __construct(
        private RentalPricingService $pricing,
        private ComboPricingService $comboPricing,
    ) {}

    /**
     * @param  array<string,mixed>  $base  các field chung (khách, store, user_id, note…)
     * @param  array<int,array<string,mixed>>  $itemLines
     * @param  array<int,array<string,mixed>>  $comboLines
     * @param  Collection<int,Product>  $productsById
     * @param  Collection<int,Combo>  $combos
     * @return Order đơn top-level: đơn thường hoặc đơn CHA
     */
    public function create(array $base, array $itemLines, array $comboLines, Collection $productsById, Collection $combos): Order
    {
        $groups = $this->groupByRange($itemLines, $comboLines);

        // 1 khoảng → đơn thường (giữ hành vi hiện tại).
        if (count($groups) === 1) {
            $g = $groups[0];
            $order = Order::create($base + [
                'start_date' => $g['start'],
                'end_date' => $g['end'],
                'status' => 'pending',
                'payment_method' => 'cod',
            ]);
            $t = $this->buildItems($order, $g['items'], $g['combos'], $productsById, $combos);
            $order->update(['total_price' => $t['total'], 'deposit_total' => $t['deposit']]);

            return $order;
        }

        // ≥2 khoảng → cha + con.
        $starts = array_column($groups, 'start');
        $ends = array_column($groups, 'end');
        $parent = Order::create($base + [
            'is_parent' => true,
            'start_date' => min($starts),
            'end_date' => max($ends),
            'status' => 'pending',
            'payment_method' => 'cod',
        ]);

        $total = 0;
        $deposit = 0;
        foreach ($groups as $i => $g) {
            $child = Order::create($base + [
                'parent_id' => $parent->id,
                'code' => $parent->code.'-'.($i + 1),
                'start_date' => $g['start'],
                'end_date' => $g['end'],
                'status' => 'pending',
                'payment_method' => 'cod',
            ]);
            $t = $this->buildItems($child, $g['items'], $g['combos'], $productsById, $combos);
            $child->update(['total_price' => $t['total'], 'deposit_total' => $t['deposit']]);
            $total += $t['total'];
            $deposit += $t['deposit'];
        }

        $parent->update(['total_price' => $total, 'deposit_total' => $deposit]);

        return $parent;
    }

    /**
     * Gom item/combo lines theo (start,end), sắp xếp theo ngày bắt đầu.
     *
     * @return array<int,array{start:string,end:string,items:array,combos:array}>
     */
    private function groupByRange(array $itemLines, array $comboLines): array
    {
        $groups = [];
        foreach ($itemLines as $line) {
            $key = $line['start'].'|'.$line['end'];
            $groups[$key] ??= ['start' => $line['start'], 'end' => $line['end'], 'items' => [], 'combos' => []];
            $groups[$key]['items'][] = $line;
        }
        foreach ($comboLines as $line) {
            $key = $line['start'].'|'.$line['end'];
            $groups[$key] ??= ['start' => $line['start'], 'end' => $line['end'], 'items' => [], 'combos' => []];
            $groups[$key]['combos'][] = $line;
        }

        return collect($groups)->sortBy('start')->values()->all();
    }

    /**
     * Tạo order_items (lẻ + combo) vào 1 đơn; trả tổng thuê (net) + tổng cọc.
     *
     * @return array{total:int, deposit:int}
     */
    private function buildItems(Order $order, array $itemLines, array $comboLines, Collection $productsById, Collection $combos): array
    {
        $total = 0;
        $deposit = 0;

        foreach ($itemLines as $item) {
            $product = $productsById->get($item['product_id']);
            $days = Carbon::parse($item['start'])->diffInDays(Carbon::parse($item['end'])) + 1;
            $line = $this->pricing->priceLine((int) $product->price_per_day, (int) $item['quantity'], $days);

            $order->items()->create([
                'product_id' => $product->id,
                'quantity' => $item['quantity'],
                'price_per_day' => $product->price_per_day,
                'days' => $days,
                // Ngày riêng từng món (bopcamping-u1nb) — availability tính theo ngày món.
                'start_date' => $item['start'],
                'end_date' => $item['end'],
                'subtotal' => $line['net'],
                'duration_discount_percent' => $line['percent'],
            ]);

            $total += $line['net'];
            $deposit += ($product->deposit ?? 0) * $item['quantity'];
        }

        foreach ($comboLines as $line) {
            $combo = $combos->get($line['combo_id']);
            $days = Carbon::parse($line['start'])->diffInDays(Carbon::parse($line['end'])) + 1;
            $allocation = $this->comboPricing->allocate($combo);
            $comboPercent = $this->pricing->tierPercentForDays($days);

            for ($instance = 0; $instance < $line['quantity']; $instance++) {
                $groupUuid = (string) Str::uuid();
                foreach ($allocation as $alloc) {
                    $lineNet = $this->pricing->priceLine((int) $alloc['allocated_price'], 1, $days)['net'];
                    $order->items()->create([
                        'product_id' => $alloc['product_id'],
                        'combo_id' => $combo->id,
                        'combo_group_uuid' => $groupUuid,
                        'quantity' => $alloc['quantity'],
                        'price_per_day' => $alloc['price_per_day'],
                        'days' => $days,
                        'start_date' => $line['start'],
                        'end_date' => $line['end'],
                        'subtotal' => $lineNet,
                        'duration_discount_percent' => $comboPercent,
                        'allocated_price' => $alloc['allocated_price'],
                        'allocated_deposit' => $alloc['allocated_deposit'],
                    ]);
                    $total += $lineNet;
                }
                $deposit += (int) ($combo->deposit ?? 0);
            }
        }

        return ['total' => $total, 'deposit' => $deposit];
    }
}

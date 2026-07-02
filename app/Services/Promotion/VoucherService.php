<?php

namespace App\Services\Promotion;

use App\Models\Order;
use App\Models\PromotionSetting;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Áp voucher vào đơn — stacking + VAN AN TOÀN trần giảm tối đa theo % giá trị đơn.
 * Khuyến mãi chỉ áp trên phí thuê (rental_fee = orders.total_price), KHÔNG đụng tiền cọc.
 */
class VoucherService
{
    /**
     * Tính tổng giảm + breakdown từ tập voucher, đã áp trần margin.
     * Hàm thuần (dễ test — xem mục 4 của spec).
     *
     * AC-8 (PRD combo mục 7): voucher thường KHÔNG áp lên giá trị combo — tính trên
     * $regularBase (= đơn trừ phần combo); voucher có applicable_to_combos tính trên
     * $base đầy đủ. Đơn không có combo → $regularBase null → hành vi cũ giữ nguyên.
     *
     * @param  Collection<int, Voucher>  $vouchers  đã lọc hợp lệ, đã giới hạn số lượng stack
     * @return array{total:int, cap:int, raw_total:int, capped:bool, breakdown:array<int,array{code:string,raw:int,applied:int}>}
     */
    public function quote(int $base, Collection $vouchers, PromotionSetting $settings, ?int $regularBase = null): array
    {
        $base = max(0, $base);
        $regularBase = $regularBase === null ? $base : max(0, min($regularBase, $base));

        $lines = $vouchers->map(function (Voucher $v) use ($base, $regularBase) {
            $vBase = $v->applicable_to_combos ? $base : $regularBase;
            $raw = $v->type === 'percent'
                ? (int) floor($vBase * (float) $v->value / 100)
                : (int) round((float) $v->value);

            return ['code' => $v->code, 'raw' => max(0, min($raw, $vBase))];
        })->values();

        $rawTotal = (int) $lines->sum('raw');
        $cap = (int) floor($base * (float) $settings->max_discount_percent_per_order / 100);
        $total = min($rawTotal, $cap, $base); // không vượt trần, không vượt giá trị đơn

        // Chia tỉ lệ phần giảm thực tế cho từng voucher khi bị trần.
        $breakdown = [];
        $allocated = 0;
        $count = $lines->count();
        foreach ($lines as $i => $line) {
            if ($rawTotal === 0) {
                $applied = 0;
            } elseif ($i === $count - 1) {
                $applied = $total - $allocated; // dồn phần dư vào dòng cuối, tránh lệch do làm tròn
            } else {
                $applied = (int) floor($line['raw'] / $rawTotal * $total);
            }
            $allocated += $applied;
            $breakdown[] = ['code' => $line['code'], 'raw' => $line['raw'], 'applied' => $applied];
        }

        return [
            'total' => $total,
            'cap' => $cap,
            'raw_total' => $rawTotal,
            'capped' => $total < $rawTotal,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Lọc voucher đủ điều kiện áp cho đơn (sở hữu, còn hiệu lực, đủ min_order),
     * ưu tiên voucher sắp hết hạn trước, giới hạn theo max_vouchers_stack_per_order.
     *
     * @param  array<int,string>|null  $codes  chỉ lấy các mã này; null = mọi voucher đủ điều kiện
     * @return Collection<int, Voucher>
     */
    public function eligibleVouchers(User $user, int $base, PromotionSetting $settings, ?array $codes = null): Collection
    {
        $query = $user->vouchers()->usable()
            ->where(fn ($q) => $q->whereNull('min_order_amount')->orWhere('min_order_amount', '<=', $base));

        if ($codes !== null) {
            $upper = array_map(fn ($c) => strtoupper(trim($c)), array_filter($codes));
            if (empty($upper)) {
                return collect();
            }
            $query->whereIn(DB::raw('UPPER(code)'), $upper);
        }

        return $query->orderByRaw('expires_at IS NULL') // có hạn xếp trước
            ->orderBy('expires_at')
            ->get()
            ->take($settings->max_vouchers_stack_per_order)
            ->values();
    }

    /**
     * Áp voucher vào đơn (đã tạo): khoá hàng, tính giảm, ghi discount, đánh dấu đã dùng.
     * Trả về breakdown để hiển thị.
     *
     * @param  array<int,string>  $codes
     */
    public function apply(Order $order, array $codes, ?PromotionSetting $settings = null): array
    {
        $settings ??= PromotionSetting::current();

        if (! $order->user_id || empty(array_filter($codes))) {
            return ['total' => 0, 'breakdown' => []];
        }

        $base = (int) $order->total_price;
        // Phần giá trị combo trong đơn — voucher thường không được giảm phần này (AC-8).
        $comboPart = (int) $order->items()->whereNotNull('combo_id')->sum('subtotal');
        $regularBase = $base - $comboPart;

        return DB::transaction(function () use ($order, $codes, $settings, $base, $regularBase) {
            $vouchers = $this->eligibleVouchers($order->user, $base, $settings, $codes);

            // Khoá để chống double-spend (bấm thanh toán nhiều lần).
            $vouchers = $vouchers->map(fn (Voucher $v) => Voucher::whereKey($v->id)->lockForUpdate()->first())
                ->filter(fn (?Voucher $v) => $v && $v->isUsable())
                ->values();

            $quote = $this->quote($base, $vouchers, $settings, $regularBase);

            $order->update(['discount_total' => $order->discount_total + $quote['total']]);

            $appliedByCode = collect($quote['breakdown'])->keyBy('code');

            foreach ($vouchers as $v) {
                // Voucher không giảm được đồng nào (vd voucher thường trên đơn toàn combo)
                // → KHÔNG đánh dấu đã dùng, khách giữ lại cho đơn sau.
                if ((int) ($appliedByCode->get($v->code)['applied'] ?? 0) <= 0) {
                    continue;
                }
                $v->used_count++;
                if ($v->used_count >= $v->max_uses) {
                    $v->status = 'used';
                }
                $v->used_at = now();
                $v->order_id = $order->id;
                $v->save();
            }

            return $quote;
        });
    }
}

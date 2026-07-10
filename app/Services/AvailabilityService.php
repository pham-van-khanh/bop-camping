<?php

namespace App\Services;

use App\Models\Combo;
use App\Models\ComboItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AvailabilityService
{
    /**
     * Số lượng đã đặt (qty) của NHIỀU sản phẩm trong khoảng [start, end] — GỘP 1 query.
     * Đây là nơi duy nhất chạm DB để đếm "đã đặt"; quy tắc chồng lịch nằm ở
     * Order::scopeActiveOverlapping (single source of truth — AC-10).
     *
     * @param  array<int, int>  $productIds
     * @return array<int, int> [ product_id => booked_qty ]  (sp không có booking → 0)
     */
    public function bookedQuantities(array $productIds, Carbon $start, Carbon $end): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if (empty($productIds)) {
            return [];
        }

        $rows = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->activeOverlapping($start, $end))
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as booked')
            ->pluck('booked', 'product_id');

        $result = [];
        foreach ($productIds as $id) {
            $result[$id] = (int) ($rows[$id] ?? 0);
        }

        return $result;
    }

    /**
     * Số lượng còn có thể thuê của một sản phẩm trong khoảng [start, end].
     *
     * Logic: quantity tồn - tổng qty đã đặt trong các đơn chồng lịch (chưa huỷ).
     * Uỷ quyền cho bookedQuantities() để không lặp lại công thức.
     */
    public function availableQuantity(Product $product, Carbon $start, Carbon $end): int
    {
        $booked = $this->bookedQuantities([$product->id], $start, $end)[$product->id] ?? 0;

        return max(0, $product->quantity - $booked);
    }

    /**
     * Kiểm tra có đủ số lượng cần thuê không.
     */
    public function isAvailable(Product $product, Carbon $start, Carbon $end, int $needed = 1): bool
    {
        return $this->availableQuantity($product, $start, $end) >= $needed;
    }

    /**
     * Số combo còn có thể thuê trong khoảng [start, end] (PRD combo 5.1).
     *
     * KHÔNG có logic tồn kho mới — mỗi món con đi qua availableQuantity() hiện có:
     * comboAvailable = min( intdiv(available(product_i), quantity_i) ).
     * Combo chưa có món nào → 0 (không bao giờ cho thuê combo rỗng).
     */
    public function comboAvailable(Combo $combo, Carbon $start, Carbon $end): int
    {
        $combo->loadMissing('items.product');

        if ($combo->items->isEmpty()) {
            return 0;
        }

        // 1 query cho MỌI món con của combo (trước đây 1 query/món → N+1).
        $booked = $this->bookedQuantities($combo->items->pluck('product_id')->all(), $start, $end);

        return $this->comboAvailableFromBooked($combo, $booked);
    }

    /**
     * Số combo còn thuê cho NHIỀU combo cùng lúc trong 1 khoảng — GỘP booked của mọi
     * món con vào đúng 1 query (chống N+1 ở trang danh sách combo / gợi ý giỏ).
     *
     * @param  Collection<int, Combo>  $combos
     * @return array<int, int> [ combo_id => available ]
     */
    public function combosAvailable(Collection $combos, Carbon $start, Carbon $end): array
    {
        // loadMissing trên từng model (nhận cả base- lẫn Eloquent-Collection); callers đã eager-load.
        $combos->each(fn (Combo $c) => $c->loadMissing('items.product'));

        $productIds = $combos->flatMap(fn (Combo $c) => $c->items->pluck('product_id'))->all();
        $booked = $this->bookedQuantities($productIds, $start, $end);

        $out = [];
        foreach ($combos as $combo) {
            $out[$combo->id] = $this->comboAvailableFromBooked($combo, $booked);
        }

        return $out;
    }

    /**
     * comboAvailable = min( intdiv(available_i, qty_i) ), tính từ map booked đã có sẵn
     * (không chạm DB). Combo rỗng → 0; món thiếu product hoặc qty<1 → 0 (an toàn).
     *
     * @param  array<int, int>  $booked  [ product_id => booked_qty ]
     */
    private function comboAvailableFromBooked(Combo $combo, array $booked): int
    {
        if ($combo->items->isEmpty()) {
            return 0;
        }

        return (int) $combo->items->map(function (ComboItem $item) use ($booked) {
            if (! $item->product || $item->quantity < 1) {
                return 0;
            }
            $available = max(0, $item->product->quantity - ($booked[$item->product_id] ?? 0));

            return intdiv($available, $item->quantity);
        })->min();
    }

    /**
     * Kiểm tra có đủ số combo cần thuê không (mirror isAvailable cho product).
     */
    public function isComboAvailable(Combo $combo, Carbon $start, Carbon $end, int $needed = 1): bool
    {
        return $this->comboAvailable($combo, $start, $end) >= $needed;
    }

    /**
     * Case 4 — các món con làm combo hết trong khoảng [start, end]:
     * món có intdiv(available, qty) < needed, kèm số còn/số cần để hiển thị.
     *
     * @return array<int, array{product: Product, available: int, required: int}>
     */
    public function comboInsufficientItems(Combo $combo, Carbon $start, Carbon $end, int $needed = 1): array
    {
        $combo->loadMissing('items.product');

        $booked = $this->bookedQuantities($combo->items->pluck('product_id')->all(), $start, $end);

        $insufficient = [];
        foreach ($combo->items as $item) {
            if (! $item->product || $item->quantity < 1) {
                continue;
            }
            $available = max(0, $item->product->quantity - ($booked[$item->product_id] ?? 0));
            if (intdiv($available, $item->quantity) < $needed) {
                $insufficient[] = [
                    'product' => $item->product,
                    'available' => $available,
                    'required' => $item->quantity * $needed,
                ];
            }
        }

        return $insufficient;
    }

    /**
     * Case 4 — khoảng gần nhất còn đủ combo, giữ nguyên độ dài, dịch tối đa
     * $scanDays ngày kể từ start. Null nếu không có (PRD 5.5: scan tối đa 30 ngày).
     *
     * @return array{start: string, end: string}|null
     */
    public function nextComboWindow(Combo $combo, Carbon $start, Carbon $end, int $scanDays = 30): ?array
    {
        for ($offset = 1; $offset <= $scanDays; $offset++) {
            $s = $start->copy()->addDays($offset);
            $e = $end->copy()->addDays($offset);
            if ($this->comboAvailable($combo, $s, $e) >= 1) {
                return ['start' => $s->toDateString(), 'end' => $e->toDateString()];
            }
        }

        return null;
    }

    /**
     * Kiểm tra nhiều sản phẩm cùng lúc (dùng khi validate giỏ hàng).
     *
     * @param  array<int, int>  $items  [ product_id => quantity ]
     * @return array<int, int> [ product_id => available_qty ]  (chỉ trả các sp thiếu hàng)
     */
    public function checkCart(array $items, Carbon $start, Carbon $end): array
    {
        $productIds = array_keys($items);

        /** @var Collection<int, Product> $products */
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
        $booked = $this->bookedQuantities($productIds, $start, $end); // 1 query cho cả giỏ

        $insufficient = [];

        foreach ($items as $productId => $needed) {
            $product = $products->get($productId);
            if (! $product) {
                $insufficient[$productId] = 0;

                continue;
            }
            $available = max(0, $product->quantity - ($booked[$productId] ?? 0));
            if ($available < $needed) {
                $insufficient[$productId] = $available;
            }
        }

        return $insufficient;
    }

    /**
     * Trả về tất cả ngày bị "hết hàng" (available = 0) của một sản phẩm
     * trong khoảng tháng — dùng để tô màu calendar phía FE.
     *
     * Cách tiếp cận: lấy các đơn đang hoạt động, gom lịch bận, tính từng ngày.
     * Chỉ dùng cho khoảng ngắn (≤ 90 ngày) để tránh loop lớn.
     */
    public function unavailableDates(Product $product, Carbon $from, Carbon $to): array
    {
        if ($product->quantity === 0) {
            return [];
        }

        // Lấy tất cả đơn chồng lịch (cùng quy tắc với availableQuantity — 1 nguồn: AC-10).
        $orders = Order::query()
            ->activeOverlapping($from, $to)
            ->with(['items' => fn ($q) => $q->where('product_id', $product->id)])
            ->get()
            ->filter(fn ($o) => $o->items->isNotEmpty());

        $unavailable = [];
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $booked = 0;
            foreach ($orders as $order) {
                if ($cursor->between($order->start_date, $order->end_date)) {
                    $booked += $order->items->sum('quantity');
                }
            }
            if ($booked >= $product->quantity) {
                $unavailable[] = $cursor->toDateString();
            }
            $cursor->addDay();
        }

        return $unavailable;
    }
}

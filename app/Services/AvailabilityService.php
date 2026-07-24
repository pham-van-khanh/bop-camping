<?php

namespace App\Services;

use App\Models\Combo;
use App\Models\ComboItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ServiceLocation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AvailabilityService
{
    /**
     * Số lượng còn có thể thuê của một sản phẩm trong khoảng [start, end] TẠI 1 CỬA HÀNG.
     *
     * Logic (per-store, KHÔNG cộng xuyên cửa hàng):
     *   tồn tại store − tổng qty đã đặt của đơn active chồng lịch GẮN store đó.
     * Đơn chưa gắn store (service_location_id NULL — dữ liệu cũ) tính vào mọi store cho an toàn.
     * Hai khoảng chồng nhau khi: start_A <= end_B AND start_B <= end_A.
     *
     * $location = null → hành vi TOÀN CỤC cũ (products.quantity − mọi đơn chồng lịch) cho
     * caller chưa chuyển per-store. Truyền $location → tính riêng cửa hàng đó (không cộng xuyên store).
     *
     * $excludeOrderId: loại chính đơn này khỏi phần "đã đặt" — dùng khi đổi lịch/đổi cửa hàng
     * để đơn KHÔNG tự chặn mình. Trừ NGAY trong query (trước max(0)), tránh over-credit khi
     * nhiều đơn khác đã đẩy "đã đặt" vượt tồn (cộng bù sau max(0) sẽ cho phép đặt trùng).
     */
    public function availableQuantity(Product $product, Carbon $start, Carbon $end, ?ServiceLocation $location = null, ?int $excludeOrderId = null): int
    {
        $booked = $this->bookedQuantity($product, $start, $end, $location, $excludeOrderId);
        $stock = $location === null ? (int) $product->quantity : (int) $product->stockAt($location->id);

        return max(0, $stock - $booked);
    }

    /**
     * Tổng số lượng đã đặt của sản phẩm chồng khoảng [start,end], tính theo NGÀY TỪNG MÓN
     * (bopcamping-u1nb) — đơn nhiều khoảng ngày không còn khoá tồn dư trên cả envelope.
     * Fallback ngày ĐƠN khi món chưa có ngày (dữ liệu cũ chưa backfill / một số path test).
     * DATE() chuẩn hoá datetime→ngày để so biên cùng ngày chính xác (MySQL + SQLite).
     */
    private function bookedQuantity(Product $product, Carbon $start, Carbon $end, ?ServiceLocation $location, ?int $excludeOrderId): int
    {
        // Đệm quay vòng: nới biên "đã đặt" thêm buffer ngày sau ngày trả (đồ còn giặt/phơi).
        // Theo KHO đang hỏi; nhánh toàn cục lấy max buffer các kho (adr_turnaround_buffer).
        $buffer = $location === null ? $product->maxBufferAcrossLocations() : $product->bufferAt($location->id);

        $q = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', Order::activeStatuses())
            ->where('order_items.product_id', $product->id)
            ->whereRaw('COALESCE(DATE(order_items.start_date), DATE(orders.start_date)) <= ?', [$end->toDateString()])
            ->whereRaw('COALESCE(DATE(order_items.end_date), DATE(orders.end_date)) >= ?', [$start->copy()->subDays($buffer)->toDateString()]);

        // Per-store: chỉ đếm đơn GẮN store đó (đơn NULL store — dữ liệu cũ — tính vào mọi store).
        if ($location !== null) {
            $q->where(fn ($sq) => $sq->where('orders.service_location_id', $location->id)->orWhereNull('orders.service_location_id'));
        }
        // Loại chính đơn này (đổi lịch/đổi cửa hàng — không tự chặn mình).
        if ($excludeOrderId !== null) {
            $q->where('orders.id', '!=', $excludeOrderId);
        }

        return (int) $q->sum('order_items.quantity');
    }

    /**
     * Khả dụng theo TỪNG cửa hàng phục vụ (open) — dùng cho trang sản phẩm hiện "Vinh: N / Hà Nội: M".
     *
     * @return array<int, int> [ service_location_id => available ]
     */
    public function availableByLocations(Product $product, Carbon $start, Carbon $end): array
    {
        $product->loadMissing('serviceLocations');

        return $product->serviceLocations
            ->where('status', 'open')
            ->mapWithKeys(fn (ServiceLocation $loc) => [$loc->id => $this->availableQuantity($product, $start, $end, $loc)])
            ->all();
    }

    /**
     * Kiểm tra có đủ số lượng cần thuê không (tại 1 cửa hàng).
     */
    public function isAvailable(Product $product, Carbon $start, Carbon $end, int $needed = 1, ?ServiceLocation $location = null): bool
    {
        return $this->availableQuantity($product, $start, $end, $location) >= $needed;
    }

    /**
     * Số combo còn có thể thuê trong khoảng [start, end] (PRD combo 5.1).
     *
     * KHÔNG có logic tồn kho mới — mỗi món con đi qua availableQuantity() hiện có:
     * comboAvailable = min( intdiv(available(product_i), quantity_i) ).
     * Combo chưa có món nào → 0 (không bao giờ cho thuê combo rỗng).
     */
    public function comboAvailable(Combo $combo, Carbon $start, Carbon $end, ?ServiceLocation $location = null): int
    {
        $combo->loadMissing('items.product');

        if ($combo->items->isEmpty()) {
            return 0;
        }

        return (int) $combo->items->map(function (ComboItem $item) use ($start, $end, $location) {
            if (! $item->product || $item->quantity < 1) {
                return 0;
            }

            return intdiv($this->availableQuantity($item->product, $start, $end, $location), $item->quantity);
        })->min();
    }

    /**
     * Kiểm tra có đủ số combo cần thuê không (mirror isAvailable cho product).
     */
    public function isComboAvailable(Combo $combo, Carbon $start, Carbon $end, int $needed = 1, ?ServiceLocation $location = null): bool
    {
        return $this->comboAvailable($combo, $start, $end, $location) >= $needed;
    }

    /**
     * Case 4 — các món con làm combo hết trong khoảng [start, end]:
     * món có intdiv(available, qty) < needed, kèm số còn/số cần để hiển thị.
     *
     * @return array<int, array{product: Product, available: int, required: int}>
     */
    public function comboInsufficientItems(Combo $combo, Carbon $start, Carbon $end, int $needed = 1, ?ServiceLocation $location = null): array
    {
        $combo->loadMissing('items.product');

        $insufficient = [];
        foreach ($combo->items as $item) {
            if (! $item->product || $item->quantity < 1) {
                continue;
            }
            $available = $this->availableQuantity($item->product, $start, $end, $location);
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
    public function nextComboWindow(Combo $combo, Carbon $start, Carbon $end, int $scanDays = 30, ?ServiceLocation $location = null): ?array
    {
        for ($offset = 1; $offset <= $scanDays; $offset++) {
            $s = $start->copy()->addDays($offset);
            $e = $end->copy()->addDays($offset);
            if ($this->comboAvailable($combo, $s, $e, $location) >= 1) {
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
    public function checkCart(array $items, Carbon $start, Carbon $end, ?ServiceLocation $location = null): array
    {
        $productIds = array_keys($items);

        /** @var Collection<int, Product> $products */
        $products = Product::with('serviceLocations')->whereIn('id', $productIds)->get()->keyBy('id');

        $insufficient = [];

        foreach ($items as $productId => $needed) {
            $product = $products->get($productId);
            if (! $product) {
                $insufficient[$productId] = 0;

                continue;
            }
            $available = $this->availableQuantity($product, $start, $end, $location);
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
    public function unavailableDates(Product $product, Carbon $from, Carbon $to, ?ServiceLocation $location = null): array
    {
        // $location = null → toàn cục cũ (products.quantity). Truyền store → tồn store đó.
        $stock = $location === null ? (int) $product->quantity : $product->stockAt($location->id);
        if ($stock === 0) {
            return [];
        }

        // Đệm quay vòng: mỗi đơn chiếm [start, end + buffer] (đồ còn giặt/phơi sau ngày trả).
        // Theo kho đang hỏi; nhánh toàn cục lấy max buffer các kho (adr_turnaround_buffer mục 3.3).
        $buffer = $location === null ? $product->maxBufferAcrossLocations() : $product->bufferAt($location->id);

        // Đơn chồng lịch — nới điều kiện end_date về trước theo buffer để bắt cả đơn mà
        // cửa sổ phơi mới chạm vào [from, to].
        $orders = Order::query()
            ->whereIn('status', Order::activeStatuses())
            ->where('start_date', '<=', $to)
            ->where('end_date', '>=', $from->copy()->subDays($buffer))
            ->when($location !== null, fn ($q) => $q->where(fn ($sq) => $sq->where('service_location_id', $location->id)->orWhereNull('service_location_id')))
            ->with(['items' => fn ($q) => $q->where('product_id', $product->id)])
            ->get()
            ->filter(fn ($o) => $o->items->isNotEmpty());

        $unavailable = [];
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $booked = 0;
            foreach ($orders as $order) {
                if ($cursor->between($order->start_date, $order->end_date->copy()->addDays($buffer))) {
                    $booked += $order->items->sum('quantity');
                }
            }
            if ($booked >= $stock) {
                $unavailable[] = $cursor->toDateString();
            }
            $cursor->addDay();
        }

        return $unavailable;
    }
}

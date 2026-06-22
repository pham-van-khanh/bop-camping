<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AvailabilityService
{
    /**
     * Số lượng còn có thể thuê của một sản phẩm trong khoảng [start, end].
     *
     * Logic: quantity tồn - tổng qty đã đặt trong các đơn chồng lịch (chưa huỷ).
     * Hai khoảng chồng nhau khi: start_A <= end_B AND start_B <= end_A
     */
    public function availableQuantity(Product $product, Carbon $start, Carbon $end): int
    {
        $booked = OrderItem::query()
            ->whereHas('order', function ($q) use ($start, $end) {
                $q->whereIn('status', Order::activeStatuses())
                    ->where('start_date', '<=', $end)
                    ->where('end_date', '>=', $start);
            })
            ->where('product_id', $product->id)
            ->sum('quantity');

        return max(0, $product->quantity - (int) $booked);
    }

    /**
     * Kiểm tra có đủ số lượng cần thuê không.
     */
    public function isAvailable(Product $product, Carbon $start, Carbon $end, int $needed = 1): bool
    {
        return $this->availableQuantity($product, $start, $end) >= $needed;
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

        $insufficient = [];

        foreach ($items as $productId => $needed) {
            $product = $products->get($productId);
            if (! $product) {
                $insufficient[$productId] = 0;

                continue;
            }
            $available = $this->availableQuantity($product, $start, $end);
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

        // Lấy tất cả đơn chồng lịch
        $orders = Order::query()
            ->whereIn('status', Order::activeStatuses())
            ->where('start_date', '<=', $to)
            ->where('end_date', '>=', $from)
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

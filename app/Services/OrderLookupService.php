<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;

/**
 * Tra cứu đơn theo mã + SĐT và shape dữ liệu cho FE (bopcamping-7w8).
 * Dùng chung cho trang /tra-cuu (khách vãng lai, không cần đăng nhập)
 * và section "Tra cứu đơn" trong /tai-khoan — một nguồn chân lý cho shape/timeline.
 */
class OrderLookupService
{
    /** Tìm đơn khớp cả mã lẫn SĐT — null nếu không khớp. */
    public function find(string $code, string $phone): ?array
    {
        $order = Order::with(['items.product'])
            ->where('code', strtoupper(trim($code)))
            ->where('customer_phone', trim($phone))
            ->first();

        return $order ? $this->shape($order) : null;
    }

    public function shape(Order $o): array
    {
        return [
            'code' => $o->code,
            'customer_name' => $o->customer_name,
            'customer_phone' => $o->customer_phone,
            'start_date' => $o->start_date->format('d/m/Y'),
            'end_date' => $o->end_date->format('d/m/Y'),
            'total_price' => $o->total_price,
            'deposit_total' => $o->deposit_total,
            'discount_total' => $o->discount_total,
            'amount_due' => $o->amount_due,
            'status' => $o->status,
            'status_label' => $this->statusLabel($o->status),
            'note' => $o->note,
            'created_at' => $o->created_at->format('d/m · H:i'),
            'items' => $o->items->map(fn (OrderItem $item) => [
                'name' => $item->product?->name ?? '(sản phẩm đã xoá)',
                'quantity' => $item->quantity,
                'days' => $item->days,
                'subtotal' => $item->subtotal,
            ])->values()->all(),
            'timeline' => $this->buildTimeline($o),
        ];
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Chờ xác nhận',
            'confirmed' => 'Đã xác nhận',
            'renting' => 'Đang thuê',
            'returned' => 'Đã trả',
            'cancelled' => 'Đã huỷ',
            default => $status,
        };
    }

    private function buildTimeline(Order $o): array
    {
        $stepOf = ['pending' => 0, 'confirmed' => 1, 'renting' => 2, 'returned' => 3];
        $current = $stepOf[$o->status] ?? -1;

        // Đơn bị huỷ — timeline riêng
        if ($o->status === 'cancelled') {
            return [
                ['title' => 'Đã gửi yêu cầu thuê', 'note' => $o->created_at->format('d/m · H:i'), 'state' => 'done'],
                ['title' => 'Đã huỷ đơn',      'note' => 'Đơn thuê đã bị huỷ',                'state' => 'current'],
            ];
        }

        $state = fn (int $i): string => match (true) {
            $i < $current => 'done',
            $i === $current => 'current',
            default => 'todo',
        };

        return [
            ['title' => 'Đã gửi yêu cầu thuê', 'note' => $o->created_at->format('d/m · H:i'),         'state' => $state(0)],
            ['title' => 'Đã xác nhận',        'note' => 'Shop đã liên hệ xác nhận đơn',               'state' => $state(1)],
            ['title' => 'Đang thuê',           'note' => 'Đã giao đồ · chúc chuyến đi vui 🏕',         'state' => $state(2)],
            ['title' => 'Đã trả · hoàn cọc',  'note' => 'Dự kiến '.$o->end_date->format('d/m/Y'),  'state' => $state(3)],
        ];
    }
}

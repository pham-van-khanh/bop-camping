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
    public function __construct(private PaymentQrService $paymentQr) {}

    /** Tìm đơn khớp cả mã lẫn SĐT — null nếu không khớp. */
    public function find(string $code, string $phone): ?array
    {
        $order = Order::with(['items.product'])
            ->where('code', strtoupper(trim($code)))
            ->where('customer_phone', trim($phone))
            ->first();

        if (! $order) {
            return null;
        }

        // Đơn gộp (bopcamping-wtuv T8): tra mã CHA → trả cha (trạng thái suy từ con)
        // + installments = từng ĐỢT giao. Tra mã CON (BOP-XXX-1) đi nhánh thường (con là đơn đầy đủ).
        if ($order->is_parent) {
            $order->loadMissing('children.items.product');
            // Gán in-memory (KHÔNG save) để shape/timeline dùng trạng thái suy từ con.
            $order->status = $order->aggregateStatus();
            $shaped = $this->shape($order);
            $shaped['installments'] = $order->children->map(fn (Order $c) => $this->shape($c))->values()->all();

            return $shaped;
        }

        return $this->shape($order);
    }

    public function shape(Order $o): array
    {
        return [
            'code' => $o->code,
            'customer_name' => $o->customer_name,
            'customer_phone' => $o->customer_phone,
            'start_date' => $o->start_date->format('d/m/Y'),
            'end_date' => $o->end_date->format('d/m/Y'),
            // Giờ shop đã chốt (spec 2026-07-28) — null nếu chưa chốt. KHÔNG đưa schedule_note ra đây (nội bộ shipper).
            'confirmed_pickup_time' => $o->confirmed_pickup_time,
            'confirmed_return_time' => $o->confirmed_return_time,
            'delivery_method' => $o->delivery_method,
            'delivery_method_label' => $o->deliveryMethodLabel(),
            'total_price' => $o->total_price,
            // Phụ phí từng khoản (bopcamping-j6hc): amount_due ĐÃ cộng khoản này, không
            // trả ra thì các dòng khách thấy cộng lại không bằng tổng — đo được lệch 50k.
            'extra_fees' => $o->extraFeeLines(),
            'deposit_total' => $o->deposit_total,
            'discount_total' => $o->discount_total,
            'amount_due' => $o->amount_due,
            // QR chuyển khoản (bopcamping-55rh) — KHÔNG kèm download_url: nút tải ảnh là
            // công cụ của admin để gửi khách qua Zalo, khách đã xem trực tiếp thì cần gì.
            'payment_qr' => $this->paymentQr->payloadFor($o),
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

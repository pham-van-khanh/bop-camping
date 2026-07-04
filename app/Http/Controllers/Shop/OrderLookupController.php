<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderLookupController extends Controller
{
    public function index(Request $request): Response
    {
        $order = null;
        $notFound = false;

        if ($request->filled('code') && $request->filled('phone')) {
            $code = strtoupper(trim((string) $request->input('code')));
            $phone = trim((string) $request->input('phone'));

            $o = Order::with(['items.product'])
                ->where('code', $code)
                ->where('customer_phone', $phone)
                ->first();

            $order = $o ? $this->shape($o) : null;
            $notFound = $o === null;
        }

        return Inertia::render('OrderLookup', [
            'order' => $order,
            'not_found' => $notFound,
            // Trả lại giá trị form để giữ lại sau redirect
            // SĐT mặc định lấy từ tài khoản đang đăng nhập (auto-fill — KE_HOACH 8.3).
            'query' => [
                'code' => $request->input('code', ''),
                'phone' => $request->input('phone', $request->user()?->phone ?? ''),
            ],
        ]);
    }

    /* ---------- helpers ---------- */

    private function shape(Order $o): array
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

    private function statusLabel(string $status): string
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

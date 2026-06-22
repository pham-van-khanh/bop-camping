<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    private const VALID_STATUSES = ['pending', 'confirmed', 'renting', 'returned', 'cancelled'];

    public function index(Request $request): Response
    {
        $status = $request->query('status', 'all');

        $query = Order::with(['items.product'])->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $orders = $query->get()->map(fn ($o) => [
            'id' => $o->id,
            'code' => $o->code,
            'customer_name' => $o->customer_name,
            'customer_phone' => $o->customer_phone,
            'start_date' => $o->start_date->format('d/m/Y'),
            'end_date' => $o->end_date->format('d/m/Y'),
            'total_price' => $o->total_price,
            'deposit_total' => $o->deposit_total,
            'status' => $o->status,
            'note' => $o->note,
            'created_at' => $o->created_at->format('d/m/Y H:i'),
            'items' => $o->items->map(fn ($i) => [
                'name' => $i->product?->name ?? '(đã xoá)',
                'quantity' => $i->quantity,
                'days' => $i->days,
                'subtotal' => $i->subtotal,
            ]),
        ]);

        // Thống kê
        $all = Order::selectRaw('status, count(*) as cnt')->groupBy('status')->pluck('cnt', 'status');
        $stats = [
            'total' => Order::count(),
            'pending' => $all['pending'] ?? 0,
            'confirmed' => $all['confirmed'] ?? 0,
            'renting' => $all['renting'] ?? 0,
            'returned' => $all['returned'] ?? 0,
            'cancelled' => $all['cancelled'] ?? 0,
        ];

        // Tồn kho
        $inventory = Product::with('category')->orderBy('name')->get()->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'category' => $p->category->name,
            'quantity' => $p->quantity,
            'status' => $p->status,
        ]);

        return Inertia::render('Admin/Orders', [
            'orders' => $orders,
            'stats' => $stats,
            'inventory' => $inventory,
            'filters' => ['status' => $status],
        ]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:'.implode(',', self::VALID_STATUSES)],
        ]);

        $order->update(['status' => $validated['status']]);

        return back()->with('success', "Đơn {$order->code} → {$validated['status']}");
    }
}

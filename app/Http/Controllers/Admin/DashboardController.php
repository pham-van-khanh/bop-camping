<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /** Tổng quan cho chủ shop: số đơn theo trạng thái, doanh thu, đơn mới. */
    public function index(): Response
    {
        $byStatus = Order::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        // Doanh thu = (tiền thuê − giảm giá) của đơn đã hoàn thành (returned). Cọc không tính.
        $revenue = (int) Order::where('status', 'returned')
            ->selectRaw('COALESCE(SUM(total_price - discount_total), 0) as s')->value('s');
        $revenueMonth = (int) Order::where('status', 'returned')
            ->where('updated_at', '>=', now()->startOfMonth())
            ->selectRaw('COALESCE(SUM(total_price - discount_total), 0) as s')->value('s');

        $recent = Order::latest()->take(8)->get()->map(fn (Order $o) => [
            'id' => $o->id,
            'code' => $o->code,
            'customer_name' => $o->customer_name,
            'status' => $o->status,
            'amount_due' => $o->amount_due,
            'created_at' => $o->created_at->format('d/m/Y H:i'),
        ]);

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total' => Order::count(),
                'pending' => $byStatus['pending'] ?? 0,
                'confirmed' => $byStatus['confirmed'] ?? 0,
                'renting' => $byStatus['renting'] ?? 0,
                'returned' => $byStatus['returned'] ?? 0,
                'cancelled' => $byStatus['cancelled'] ?? 0,
                'revenue' => $revenue,
                'revenue_month' => $revenueMonth,
            ],
            'recent' => $recent,
        ]);
    }
}

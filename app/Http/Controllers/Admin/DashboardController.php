<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Combo;
use App\Models\ComboEvent;
use App\Models\Order;
use App\Models\OrderItem;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /** Tổng quan cho chủ shop: số đơn theo trạng thái, doanh thu, đơn mới. */
    public function index(): Response
    {
        // Đơn gộp (bopcamping-wtuv T8): loại đơn CHA khỏi mọi đếm/danh sách — cha là container
        // (tổng = Σ con) nên tính cả cha lẫn con sẽ ĐÔI. Đơn = đơn thường + từng đợt (con).
        $byStatus = Order::where('is_parent', false)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        // Doanh thu = (tiền thuê − giảm giá) của đơn đã hoàn thành (returned). Cọc không tính.
        // (Cha không bao giờ 'returned' nên không double, vẫn lọc cho tường minh.)
        $revenue = (int) Order::where('is_parent', false)->where('status', 'returned')
            ->selectRaw('COALESCE(SUM(total_price - discount_total), 0) as s')->value('s');
        $revenueMonth = (int) Order::where('is_parent', false)->where('status', 'returned')
            ->where('updated_at', '>=', now()->startOfMonth())
            ->selectRaw('COALESCE(SUM(total_price - discount_total), 0) as s')->value('s');

        $recent = Order::where('is_parent', false)->latest()->take(8)->get()->map(fn (Order $o) => [
            'id' => $o->id,
            'code' => $o->code,
            'customer_name' => $o->customer_name,
            'status' => $o->status,
            'amount_due' => $o->amount_due,
            'created_at' => $o->created_at->format('d/m/Y H:i'),
        ]);

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total' => Order::where('is_parent', false)->count(),
                'pending' => $byStatus['pending'] ?? 0,
                'confirmed' => $byStatus['confirmed'] ?? 0,
                'renting' => $byStatus['renting'] ?? 0,
                'returned' => $byStatus['returned'] ?? 0,
                'cancelled' => $byStatus['cancelled'] ?? 0,
                'revenue' => $revenue,
                'revenue_month' => $revenueMonth,
            ],
            'recent' => $recent,
            'combo_stats' => $this->comboStats(),
        ]);
    }

    /**
     * US-09 — widget combo: top theo lượt thuê (mỗi combo_group_uuid = 1 lượt,
     * bỏ đơn huỷ) + shown/converted/convert-rate từ event log gợi ý trong giỏ.
     *
     * @return array<int, array{id:int, name:string, rentals:int, shown:int, converted:int, convert_rate:int|null}>
     */
    private function comboStats(): array
    {
        $rentals = OrderItem::query()
            ->whereNotNull('combo_id')
            ->whereHas('order', fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->selectRaw('combo_id, COUNT(DISTINCT combo_group_uuid) as rentals')
            ->groupBy('combo_id')
            ->pluck('rentals', 'combo_id');

        $events = ComboEvent::query()
            ->selectRaw('combo_id, event, COUNT(*) as c')
            ->groupBy('combo_id', 'event')
            ->get()
            ->groupBy('combo_id');

        $comboIds = $rentals->keys()->merge($events->keys())->unique()->values();
        $names = Combo::whereIn('id', $comboIds)->pluck('name', 'id');

        return $comboIds
            ->map(function ($id) use ($rentals, $events, $names) {
                $ev = $events->get($id, collect());
                $shown = (int) ($ev->firstWhere('event', ComboEvent::SHOWN)->c ?? 0);
                $converted = (int) ($ev->firstWhere('event', ComboEvent::CONVERTED)->c ?? 0);

                return [
                    'id' => (int) $id,
                    'name' => $names[$id] ?? ('Combo #'.$id),
                    'rentals' => (int) ($rentals[$id] ?? 0),
                    'shown' => $shown,
                    'converted' => $converted,
                    'convert_rate' => $shown > 0 ? (int) round($converted * 100 / $shown) : null,
                ];
            })
            ->sortByDesc('rentals')
            ->take(5)
            ->values()
            ->all();
    }
}

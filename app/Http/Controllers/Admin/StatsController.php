<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\FinanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * bopcamping-h1s — Thống kê admin: số đơn theo thời gian + tóm tắt thu-chi + doanh thu
 * theo ngày.
 *
 * Quản lý khoản chi đã chuyển sang màn Tài chính (bopcamping-n4qy) — một bảng thì chỉ
 * nên có một form nhập. Con số thu/chi ở đây lấy từ FinanceService, dùng chung công
 * thức với màn đó để hai màn không bao giờ báo lệch nhau.
 */
class StatsController extends Controller
{
    private const PERIODS = ['today', 'week', 'month', 'all'];

    public function __construct(private FinanceService $finance) {}

    /** Tháng sớm nhất bảng doanh thu theo ngày nhận — trước mốc này không tổng hợp. */
    private const REVENUE_FROM_MONTH = '2026-08';

    public function index(Request $request): Response
    {
        $period = in_array($request->query('period'), self::PERIODS, true) ? $request->query('period') : 'month';

        // Mốc bắt đầu của kỳ (null = tất cả). Dùng cho cả thu (đơn returned) lẫn chi.
        $from = match ($period) {
            'today' => Carbon::today(),
            'week' => Carbon::now()->startOfWeek(),
            'all' => null,
            default => Carbon::now()->startOfMonth(),
        };

        // Số đơn theo created_at (luôn hiển thị, không phụ thuộc kỳ).
        // Đơn gộp (bopcamping-wtuv T8): loại đơn CHA — cha là container (tổng = Σ con),
        // đếm cả cha lẫn con sẽ ĐÔI. "Đơn" = đơn thường + từng đợt giao (con).
        $orderCounts = [
            'today' => Order::where('is_parent', false)->where('created_at', '>=', Carbon::today())->count(),
            'week' => Order::where('is_parent', false)->where('created_at', '>=', Carbon::now()->startOfWeek())->count(),
            'month' => Order::where('is_parent', false)->where('created_at', '>=', Carbon::now()->startOfMonth())->count(),
            'total' => Order::where('is_parent', false)->count(),
        ];

        // Thu/chi lấy từ FinanceService — KHÔNG tự viết công thức ở đây. Bản cũ tính
        // total_price - discount_total, bỏ mất extra_fee, nên phụ phí giao hàng/ngoài
        // khung giờ không vào doanh thu và lợi nhuận báo thiếu (bopcamping-n4qy).
        $revenue = $this->finance->revenue($from);
        $returnedCount = $this->finance->returnedCount($from);
        $expenseTotal = $this->finance->expenseTotal($from);

        $revenueMonth = $this->resolveRevenueMonth($request->query('month'));

        return Inertia::render('Admin/Stats', [
            'period' => $period,
            'order_counts' => $orderCounts,
            'chart' => $this->ordersPerDay(30),
            'revenue_month' => $revenueMonth->format('Y-m'),
            'revenue_months' => $this->revenueMonthOptions(),
            'revenue_by_day' => $this->revenueByDay($revenueMonth),
            'finance' => [
                'revenue' => $revenue,
                'expense' => $expenseTotal,
                'profit' => $revenue - $expenseTotal,
                'returned_count' => $returnedCount,
            ],
        ]);
    }

    /**
     * Tháng đang xem của bảng doanh thu — kẹp trong [REVENUE_FROM_MONTH, tháng hiện tại],
     * mặc định là tháng hiện tại. Input sai định dạng cũng rơi về mặc định.
     */
    private function resolveRevenueMonth(?string $month): Carbon
    {
        $first = Carbon::createFromFormat('Y-m', self::REVENUE_FROM_MONTH)->startOfMonth();
        $last = Carbon::now()->startOfMonth();
        $default = $last->lt($first) ? $first : $last;

        if (! is_string($month) || preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
            return $default;
        }

        try {
            $picked = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable) {
            return $default;
        }

        return $picked->lt($first) || $picked->gt($last) ? $default : $picked;
    }

    /**
     * Các tháng chọn được: từ REVENUE_FROM_MONTH đến tháng hiện tại, mới nhất trước.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function revenueMonthOptions(): array
    {
        $cursor = Carbon::createFromFormat('Y-m', self::REVENUE_FROM_MONTH)->startOfMonth();
        $last = Carbon::now()->startOfMonth();

        $out = [];
        while ($cursor->lte($last)) {
            $out[] = [
                'value' => $cursor->format('Y-m'),
                'label' => 'Tháng '.$cursor->month.'/'.$cursor->year,
            ];
            $cursor->addMonth();
        }

        return array_reverse($out);
    }

    /**
     * Doanh thu từng ngày trong tháng + chi tiết đơn của ngày đó.
     *
     * Liệt kê MỌI ngày của tháng (ngày không có đơn vẫn có dòng, total = 0) để đọc như
     * lịch. Tháng hiện tại chỉ chạy đến hôm nay — không hiện ngày tương lai.
     * Thu = đơn con status=returned, tính theo mốc trả (updated_at), ĐÃ GỒM phụ phí và
     * đã trừ giảm giá — phải khớp từng đồng với ô "Tổng thu" (FinanceService::revenue),
     * nếu không admin thấy tổng tháng không bằng tổng các ngày cộng lại.
     * Gom ở PHP như ordersPerDay() vì DATE() khác cú pháp giữa sqlite và MySQL.
     *
     * @return array<int, array{date: string, label: string, total: int, orders: array<int, array{id: int, code: string, amount: int}>}>
     */
    private function revenueByDay(Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $today = Carbon::today()->endOfDay();
        if ($end->gt($today)) {
            $end = $today;
        }

        $grouped = Order::where('is_parent', false)->where('status', 'returned')
            ->whereBetween('updated_at', [$start->copy()->startOfDay(), $end])
            ->orderBy('updated_at')->orderBy('id')
            ->get(['id', 'code', 'updated_at', 'total_price', 'extra_fee', 'discount_total'])
            ->groupBy(fn (Order $o) => $o->updated_at->toDateString());

        // rental_due = total_price + extra_fee − discount_total (Order::getRentalDueAttribute).
        $due = fn (Order $o) => (int) $o->total_price + (int) $o->extra_fee - (int) $o->discount_total;

        $out = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $orders = $grouped[$d->toDateString()] ?? collect();
            $out[] = [
                'date' => $d->toDateString(),
                'label' => $d->format('d/m/Y'),
                'total' => (int) $orders->sum($due),
                'orders' => $orders->map(fn (Order $o) => [
                    'id' => $o->id,
                    'code' => $o->code,
                    'amount' => $due($o),
                ])->values()->all(),
            ];
        }

        return $out;
    }

    /**
     * Số đơn mỗi ngày trong N ngày gần nhất (gom ở PHP để chạy được trên cả
     * sqlite lẫn MySQL — không dùng hàm DATE() khác cú pháp giữa 2 DB).
     *
     * @return array<int, array{date: string, label: string, count: int}>
     */
    private function ordersPerDay(int $days): array
    {
        $start = Carbon::today()->subDays($days - 1);
        $counts = Order::where('is_parent', false)->where('created_at', '>=', $start)
            ->get(['created_at'])
            ->groupBy(fn (Order $o) => $o->created_at->toDateString())
            ->map->count();

        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $start->copy()->addDays($i);
            $out[] = [
                'date' => $d->toDateString(),
                'label' => $d->format('d/m'),
                'count' => (int) ($counts[$d->toDateString()] ?? 0),
            ];
        }

        return $out;
    }
}

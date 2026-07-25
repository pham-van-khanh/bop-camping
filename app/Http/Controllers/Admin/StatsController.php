<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * bopcamping-h1s — Thống kê admin: số đơn theo thời gian + bảng thu-chi
 * (thu = tiền thuê đơn đã trả, chi = chi phí phát sinh nhập tay) + quản lý chi phí.
 */
class StatsController extends Controller
{
    private const PERIODS = ['today', 'week', 'month', 'all'];

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

        // Thu = tiền thuê (đã trừ giảm giá) của đơn ĐÃ TRẢ, tính theo mốc trả (updated_at).
        // (Cha không bao giờ 'returned' — lọc thêm cho tường minh.)
        $revenueQuery = Order::where('is_parent', false)->where('status', 'returned')
            ->when($from, fn ($q) => $q->where('updated_at', '>=', $from))
            ->selectRaw('COALESCE(SUM(total_price - discount_total), 0) as s');
        $revenue = (int) $revenueQuery->value('s');
        $returnedCount = Order::where('is_parent', false)->where('status', 'returned')
            ->when($from, fn ($q) => $q->where('updated_at', '>=', $from))
            ->count();

        // Chi = chi phí phát sinh trong kỳ.
        $expenseBase = fn () => Expense::query()->when($from, fn ($q) => $q->where('spent_on', '>=', $from->toDateString()));
        $expenseTotal = (int) $expenseBase()->sum('amount');

        $byCategory = $expenseBase()
            ->selectRaw('category, SUM(amount) as total, COUNT(*) as cnt')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'category' => $r->category,
                'label' => Expense::CATEGORY_LABELS[$r->category] ?? $r->category,
                'total' => (int) $r->total,
                'count' => (int) $r->cnt,
            ])->values();

        $expenses = $expenseBase()
            ->orderByDesc('spent_on')->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (Expense $e) => [
                'id' => $e->id,
                'spent_on' => $e->spent_on->format('Y-m-d'),
                'spent_on_label' => $e->spent_on->format('d/m/Y'),
                'amount' => $e->amount,
                'category' => $e->category,
                'category_label' => Expense::CATEGORY_LABELS[$e->category] ?? $e->category,
                'note' => $e->note,
            ])->values();

        return Inertia::render('Admin/Stats', [
            'period' => $period,
            'order_counts' => $orderCounts,
            'chart' => $this->ordersPerDay(30),
            'finance' => [
                'revenue' => $revenue,
                'expense' => $expenseTotal,
                'profit' => $revenue - $expenseTotal,
                'returned_count' => $returnedCount,
            ],
            'by_category' => $byCategory,
            'expenses' => $expenses,
            'categories' => collect(Expense::CATEGORIES)->map(fn ($c) => [
                'value' => $c,
                'label' => Expense::CATEGORY_LABELS[$c],
            ])->values(),
        ]);
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

    public function storeExpense(Request $request): RedirectResponse
    {
        Expense::create($this->validateExpense($request));

        return back()->with('success', 'Đã thêm khoản chi.');
    }

    public function updateExpense(Request $request, Expense $expense): RedirectResponse
    {
        $expense->update($this->validateExpense($request));

        return back()->with('success', 'Đã cập nhật khoản chi.');
    }

    public function destroyExpense(Expense $expense): RedirectResponse
    {
        $expense->delete();

        return back()->with('success', 'Đã xoá khoản chi.');
    }

    /** @return array{spent_on: string, amount: int, category: string, note: ?string} */
    private function validateExpense(Request $request): array
    {
        return $request->validate([
            'spent_on' => ['required', 'date'],
            'amount' => ['required', 'integer', 'min:1'],
            'category' => ['required', 'in:'.implode(',', Expense::CATEGORIES)],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'spent_on.required' => 'Chọn ngày chi.',
            'amount.required' => 'Nhập số tiền.',
            'amount.min' => 'Số tiền phải lớn hơn 0.',
            'category.in' => 'Loại chi phí không hợp lệ.',
        ]);
    }
}

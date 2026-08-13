<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Toàn bộ số liệu tài chính của shop (bopcamping-n4qy) — NGUỒN DUY NHẤT.
 *
 * Trước đây StatsController tự viết công thức doanh thu và viết SAI: nó lấy
 * total_price - discount_total, bỏ mất extra_fee, nên mọi phụ phí giao hàng / ngoài
 * khung giờ không được tính vào thu, lợi nhuận báo thiếu. Bài học: công thức tiền chỉ
 * được tồn tại ở MỘT chỗ. Mọi màn (Tài chính, Thống kê) đều gọi service này.
 */
class FinanceService
{
    /**
     * Vốn ban đầu chủ shop bỏ ra, VND.
     *
     * ⚠️ ĐÂY LÀ CHỖ DUY NHẤT khai con số này — góp thêm vốn thì sửa ở đây rồi deploy
     * lại. (Cân nhắc chuyển vào Cài đặt shop nếu về sau phải đổi thường xuyên.)
     */
    public const INITIAL_CAPITAL = 70_000_000;

    /**
     * Doanh thu = TIỀN THUÊ đã thu, KHÔNG gồm cọc.
     *
     * Cọc là tiền giữ hộ, trả lại khách khi nhận đồ nguyên vẹn — cộng vào doanh thu là
     * tự huyễn hoặc mình đang lãi. Xem heldDeposit() cho phần cọc đang cầm.
     *
     * Công thức bám đúng Order::getRentalDueAttribute() (total_price + extra_fee −
     * discount_total). Đơn CHA của đơn gộp bị loại: cha chỉ là vỏ chứa, tổng của nó
     * bằng Σ đơn con nên cộng cả hai là nhân đôi doanh thu.
     *
     * Ghi nhận theo thời điểm TRẢ ĐỒ (status='returned', mốc updated_at) — kiểu thực
     * thu, khớp với việc shop chỉ cầm được tiền khi khách trả đồ xong.
     */
    public function revenueQuery(?Carbon $from = null, ?Carbon $to = null)
    {
        return Order::where('is_parent', false)
            ->where('status', 'returned')
            ->when($from, fn ($q) => $q->where('updated_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('updated_at', '<=', $to));
    }

    public function revenue(?Carbon $from = null, ?Carbon $to = null): int
    {
        return (int) $this->revenueQuery($from, $to)
            ->selectRaw('COALESCE(SUM(total_price + extra_fee - discount_total), 0) as s')
            ->value('s');
    }

    public function returnedCount(?Carbon $from = null, ?Carbon $to = null): int
    {
        return $this->revenueQuery($from, $to)->count();
    }

    public function expenseTotal(?Carbon $from = null, ?Carbon $to = null): int
    {
        return (int) Expense::query()
            ->when($from, fn ($q) => $q->where('spent_on', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->where('spent_on', '<=', $to->toDateString()))
            ->sum('amount');
    }

    /**
     * Tiền cọc đang CẦM của khách — nợ phải trả, không phải tài sản của shop.
     *
     * Tách riêng vì đây là cái bẫy dễ nhất của mô hình cho thuê: tài khoản đang có
     * nhiều tiền không có nghĩa là đang lãi, phần lớn có thể là cọc sắp phải trả lại.
     *
     * Đang cầm = đơn khách đang thuê (đã giao, đã thu cọc) + đơn đã trả đồ nhưng chưa
     * hoàn cọc. Đơn 'confirmed' chưa giao nên chưa thu đồng nào.
     */
    public function heldDeposit(): int
    {
        return (int) Order::where('is_parent', false)
            ->where(fn ($q) => $q->where('status', 'renting')
                ->orWhere(fn ($q2) => $q2->where('status', 'returned')
                    ->where('deposit_refund_status', 'pending')))
            ->sum('deposit_total');
    }

    /**
     * Tiền thuê của đơn đang chạy — sẽ thu khi khách trả đồ. Không phải doanh thu (chưa
     * thu), nhưng chủ shop cần thấy để biết dòng tiền sắp về.
     */
    public function pipelineRevenue(): int
    {
        return (int) Order::where('is_parent', false)
            ->whereIn('status', ['confirmed', 'renting'])
            ->selectRaw('COALESCE(SUM(total_price + extra_fee - discount_total), 0) as s')
            ->value('s');
    }

    /**
     * Bảng tổng hợp toàn cảnh. `spent` là chi TÍCH LUỸ từ đầu, không theo kỳ — vốn còn
     * lại và tiến độ hoàn vốn chỉ có nghĩa khi tính trên toàn bộ lịch sử.
     *
     * @return array<string, int|float>
     */
    public function overview(): array
    {
        $revenue = $this->revenue();
        $spent = $this->expenseTotal();
        $profit = $revenue - $spent;

        return [
            'capital' => self::INITIAL_CAPITAL,
            'spent' => $spent,
            // Âm = đã tiêu quá vốn ban đầu, tức đang tái đầu tư bằng tiền thu được.
            'capital_left' => self::INITIAL_CAPITAL - $spent,
            'revenue' => $revenue,
            'profit' => $profit,
            // "Đã thu về bao nhiêu % số tiền đã bỏ ra" — chưa chi đồng nào thì chưa có
            // gì để hoàn, trả 0 thay vì chia cho 0.
            'payback_percent' => $spent > 0 ? round($revenue / $spent * 100, 1) : 0.0,
            'held_deposit' => $this->heldDeposit(),
            'pipeline_revenue' => $this->pipelineRevenue(),
            'returned_count' => $this->returnedCount(),
        ];
    }

    /**
     * Chuỗi thu–chi theo tháng, kèm luỹ kế để vẽ điểm hoà vốn.
     *
     * Gom ở PHP chứ không dùng DATE_FORMAT/strftime vì hai hàm đó khác cú pháp giữa
     * sqlite và MySQL — dự án chạy test trên cả hai (xem CLAUDE.md).
     *
     * Khoảng thời gian chạy từ tháng có hoạt động ĐẦU TIÊN (đơn trả hoặc khoản chi sớm
     * nhất) đến tháng hiện tại, tối đa $maxMonths tháng gần nhất để bảng không dài vô tận.
     *
     * @return list<array{month: string, label: string, revenue: int, expense: int, profit: int, cum_revenue: int, cum_expense: int}>
     */
    public function monthlySeries(int $maxMonths = 24): array
    {
        $revenueByMonth = $this->revenueQuery()
            ->get(['updated_at', 'total_price', 'extra_fee', 'discount_total'])
            ->groupBy(fn (Order $o) => $o->updated_at->format('Y-m'))
            ->map(fn (Collection $rows) => (int) $rows->sum(
                fn (Order $o) => (int) $o->total_price + (int) $o->extra_fee - (int) $o->discount_total
            ));

        $expenseByMonth = Expense::query()
            ->get(['spent_on', 'amount'])
            ->groupBy(fn (Expense $e) => $e->spent_on->format('Y-m'))
            ->map(fn (Collection $rows) => (int) $rows->sum('amount'));

        $months = $revenueByMonth->keys()->merge($expenseByMonth->keys())->unique()->sort()->values();
        if ($months->isEmpty()) {
            return [];
        }

        $cursor = Carbon::createFromFormat('Y-m', $months->first())->startOfMonth();
        $last = Carbon::now()->startOfMonth();
        // Dữ liệu tương lai (nhập nhầm ngày) vẫn phải hiện, nếu không tiền biến mất khỏi bảng.
        $tail = Carbon::createFromFormat('Y-m', $months->last())->startOfMonth();
        if ($tail->gt($last)) {
            $last = $tail;
        }

        $all = [];
        $cumRevenue = 0;
        $cumExpense = 0;
        while ($cursor->lte($last)) {
            $key = $cursor->format('Y-m');
            $r = (int) ($revenueByMonth[$key] ?? 0);
            $e = (int) ($expenseByMonth[$key] ?? 0);
            $cumRevenue += $r;
            $cumExpense += $e;

            $all[] = [
                'month' => $key,
                'label' => 'T'.$cursor->month.'/'.$cursor->year,
                'revenue' => $r,
                'expense' => $e,
                'profit' => $r - $e,
                'cum_revenue' => $cumRevenue,
                'cum_expense' => $cumExpense,
            ];
            $cursor->addMonth();
        }

        // Cắt phần đuôi NHƯNG giữ nguyên luỹ kế đã cộng từ đầu — cắt trước khi cộng thì
        // đường luỹ kế bắt đầu lại từ 0 và điểm hoà vốn hiện sai.
        return array_slice($all, -$maxMonths);
    }

    /**
     * Chi phí theo loại (toàn thời gian), kèm tỉ trọng để vẽ thanh phân bổ.
     *
     * @return list<array{category: string, label: string, total: int, count: int, percent: float}>
     */
    public function expenseByCategory(?Carbon $from = null, ?Carbon $to = null): array
    {
        $rows = Expense::query()
            ->when($from, fn ($q) => $q->where('spent_on', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->where('spent_on', '<=', $to->toDateString()))
            ->selectRaw('category, SUM(amount) as total, COUNT(*) as cnt')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $sum = (int) $rows->sum('total');

        return $rows->map(fn ($r) => [
            'category' => $r->category,
            'label' => Expense::CATEGORY_LABELS[$r->category] ?? $r->category,
            'total' => (int) $r->total,
            'count' => (int) $r->cnt,
            'percent' => $sum > 0 ? round((int) $r->total / $sum * 100, 1) : 0.0,
        ])->values()->all();
    }
}

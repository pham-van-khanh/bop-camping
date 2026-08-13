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
     * Các thành viên góp vốn, VND (bopcamping-n4qy).
     *
     * ⚠️ ĐÂY LÀ CHỖ DUY NHẤT khai vốn — góp thêm/đổi tên thì sửa ở đây rồi deploy lại.
     *
     * Lưu SỐ TIỀN GÓP chứ không lưu phần trăm: 40/70 = 57,142857…% chứ không phải 57%
     * chẵn. Ghi 57/43 rồi nhân lên thì mỗi tháng lệch vài nghìn và tổng không bao giờ
     * khớp với số đem chia. Phần trăm luôn suy ra từ hai con số này.
     */
    public const PARTNERS = [
        'a' => ['name' => 'Admin A', 'capital' => 40_000_000],
        'b' => ['name' => 'Admin B', 'capital' => 30_000_000],
    ];

    /**
     * Phần lợi nhuận giữ lại làm QUỸ DỰ PHÒNG, phần còn lại chia theo tỉ lệ góp vốn.
     *
     * Lưu ý phân biệt với loại chi phí 'contingency' (nhãn "Chi dự phòng") — cái đó là
     * tiền ĐÃ TIÊU, còn quỹ này là tiền GIỮ LẠI. Hai thứ ngược nhau.
     */
    public const RESERVE_RATE = 0.55;

    /**
     * Tổng vốn — SUY RA từ PARTNERS, không khai riêng.
     *
     * Khai thành hằng số thứ hai là sớm muộn cũng lệch: sửa vốn của một người mà quên
     * sửa tổng thì mọi tỉ lệ sai âm thầm.
     */
    public static function capital(): int
    {
        return array_sum(array_column(self::PARTNERS, 'capital'));
    }

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
            'capital' => self::capital(),
            'spent' => $spent,
            // Âm = đã tiêu quá vốn ban đầu, tức đang tái đầu tư bằng tiền thu được.
            'capital_left' => self::capital() - $spent,
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
    public function monthlySeries(?int $maxMonths = 24): array
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
        // null = lấy trọn lịch sử, dùng cho profitSharing() (phải bù lỗ từ tháng đầu tiên).
        return $maxMonths === null ? $all : array_slice($all, -$maxMonths);
    }

    /**
     * Chia lợi nhuận giữa các thành viên góp vốn (bopcamping-n4qy).
     *
     * LUẬT (chủ shop chốt 13/08/2026):
     *   1. Lãi tháng phải BÙ HẾT LỖ LUỸ KẾ còn treo trước đã. Chỉ phần vượt ra mới đem chia.
     *      Không có bước này thì tháng nào lãi là rút tiền ngay, kể cả khi tính chung
     *      shop vẫn đang âm — rút vào chính tiền vốn.
     *   2. Phần đem chia: 55% giữ lại làm quỹ dự phòng, 45% chia theo tỉ lệ góp vốn.
     *
     * TIỀN KHÔNG ĐƯỢC BỐC HƠI: quỹ + các phần chia phải cộng đúng bằng số đem chia. Nên
     * chỉ làm tròn ở các khoản đầu, khoản CUỐI lấy phần dư — làm tròn từng khoản độc lập
     * thì tổng lệch 1–2 đồng và bảng không bao giờ khớp.
     *
     * @return array{
     *     partners: list<array{key: string, name: string, capital: int, capital_percent: float, profit_percent: float, total: int}>,
     *     reserve_percent: float, reserve_total: int, distributed_total: int, deficit: int,
     *     rows: list<array{month: string, label: string, profit: int, offset: int, distributable: int, reserve: int, shares: array<string, int>}>
     * }
     */
    public function profitSharing(): array
    {
        $capital = self::capital();
        $keys = array_keys(self::PARTNERS);
        $lastKey = end($keys);

        $deficit = 0;              // lỗ luỹ kế còn phải bù
        $reserveTotal = 0;
        $totals = array_fill_keys($keys, 0);
        $rows = [];

        foreach ($this->monthlySeries(null) as $m) {
            $profit = $m['profit'];
            $offset = 0;           // phần lãi tháng này dùng để bù lỗ cũ

            if ($profit <= 0) {
                $deficit += -$profit;
                $distributable = 0;
            } else {
                $offset = min($deficit, $profit);
                $deficit -= $offset;
                $distributable = $profit - $offset;
            }

            $reserve = (int) round($distributable * self::RESERVE_RATE);
            $toPartners = $distributable - $reserve;

            $shares = [];
            $allocated = 0;
            foreach ($keys as $k) {
                if ($k === $lastKey) {
                    // Người cuối nhận phần dư → Σ shares == $toPartners tuyệt đối.
                    $shares[$k] = $toPartners - $allocated;
                } else {
                    $shares[$k] = (int) round($toPartners * self::PARTNERS[$k]['capital'] / $capital);
                    $allocated += $shares[$k];
                }
                $totals[$k] += $shares[$k];
            }

            $reserveTotal += $reserve;
            $rows[] = [
                'month' => $m['month'],
                'label' => $m['label'],
                'profit' => $profit,
                'offset' => $offset,
                'distributable' => $distributable,
                'reserve' => $reserve,
                'shares' => $shares,
            ];
        }

        $partners = [];
        foreach (self::PARTNERS as $k => $p) {
            $capitalPercent = round($p['capital'] / $capital * 100, 2);
            $partners[] = [
                'key' => $k,
                'name' => $p['name'],
                'capital' => $p['capital'],
                'capital_percent' => $capitalPercent,
                // Phần thực nhận trên TỔNG lợi nhuận = 45% × tỉ lệ góp vốn.
                'profit_percent' => round((1 - self::RESERVE_RATE) * $p['capital'] / $capital * 100, 2),
                'total' => $totals[$k],
            ];
        }

        return [
            'partners' => $partners,
            'reserve_percent' => round(self::RESERVE_RATE * 100, 2),
            'reserve_total' => $reserveTotal,
            'distributed_total' => array_sum($totals),
            // Lỗ còn treo — phải bù hết chỗ này rồi mới chia tiếp được đồng nào.
            'deficit' => $deficit,
            // Mới nhất lên đầu cho bảng; thuật toán bù lỗ đã chạy theo đúng thứ tự thời gian.
            'rows' => array_reverse($rows),
        ];
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

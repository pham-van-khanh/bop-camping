<?php

namespace App\Services;

use App\Models\CapitalContribution;
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
     * Phần lợi nhuận giữ lại làm QUỸ DỰ PHÒNG, phần còn lại chia theo tỉ lệ góp vốn.
     *
     * Lưu ý phân biệt với loại chi phí 'contingency' (nhãn "Chi dự phòng") — cái đó là
     * tiền ĐÃ TIÊU, còn quỹ này là tiền GIỮ LẠI. Hai thứ ngược nhau.
     */
    public const RESERVE_RATE = 0.55;

    /** @var Collection<int, object>|null Vốn góp gom theo người, memoize trong 1 request. */
    private ?Collection $capitalRows = null;

    /**
     * Vốn góp gom theo từng người, nhiều nhất trước (bopcamping-n4qy).
     *
     * Đọc từ bảng capital_contributions — trước đây ghi cứng trong hằng số PARTNERS,
     * chuyển sang DB để chủ shop tự nâng vốn / thêm thành viên mà không phải sửa code.
     * Thêm người thứ ba chỉ là thêm dòng, mọi tỉ lệ tự tính lại.
     *
     * @return Collection<int, object>
     */
    public function capitalRows(): Collection
    {
        return $this->capitalRows ??= CapitalContribution::query()
            ->join('users', 'users.id', '=', 'capital_contributions.user_id')
            ->groupBy('users.id', 'users.name')
            ->selectRaw('users.id as user_id, users.name as name, SUM(capital_contributions.amount) as total')
            ->orderByDesc('total')
            ->orderBy('users.name')
            ->get()
            ->map(fn ($r) => (object) ['user_id' => (int) $r->user_id, 'name' => $r->name, 'total' => (int) $r->total]);
    }

    /**
     * Tổng vốn — SUY RA từ sổ góp vốn, không lưu riêng.
     *
     * Lưu thành cột/hằng số thứ hai là sớm muộn cũng lệch: thêm một dòng góp mà quên
     * cập nhật tổng thì mọi tỉ lệ sai âm thầm.
     */
    public function capital(): int
    {
        return (int) $this->capitalRows()->sum('total');
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
     * Đang cầm = đơn đang thuê hoặc đã trả đồ, VÀ cọc chưa hoàn.
     *
     * Điều kiện "chưa hoàn" áp cho CẢ HAI trạng thái, không riêng 'returned':
     * Shipper\ScheduleController::refundDeposit() cho phép trả cọc ngay lúc đang thu đồ,
     * lúc đó đơn vẫn là 'renting' nhưng tiền đã đưa lại khách. Bản cũ cộng mọi đơn
     * 'renting' bất kể trạng thái hoàn nên ô này đếm cả tiền không còn cầm.
     *
     * Đơn 'confirmed' chưa giao đồ nên chưa thu đồng cọc nào.
     */
    public function heldDeposit(): int
    {
        return (int) Order::where('is_parent', false)
            ->whereIn('status', ['renting', 'returned'])
            ->where('deposit_refund_status', 'pending')
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
            'capital' => $this->capital(),
            'spent' => $spent,
            // Âm = đã tiêu quá vốn ban đầu, tức đang tái đầu tư bằng tiền thu được.
            'capital_left' => $this->capital() - $spent,
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
     * Tháng ĐẦU TIÊN thu luỹ kế đuổi kịp chi luỹ kế — điểm hoà vốn thật.
     *
     * Phải tính trên TOÀN BỘ lịch sử, không phải trên 24 tháng gửi cho chart. Shop hoà
     * vốn trước khoảng hiển thị thì dòng đầu tiên của bảng đã thoả điều kiện, dò trong
     * mảng đó sẽ báo nhầm tháng đó là tháng hoà vốn (bopcamping-n4qy).
     *
     * @return array{month: string, label: string}|null null = chưa bao giờ hoà vốn
     */
    public function breakEvenMonth(): ?array
    {
        foreach ($this->monthlySeries(null) as $m) {
            if ($m['cum_revenue'] >= $m['cum_expense']) {
                return ['month' => $m['month'], 'label' => $m['label']];
            }
        }

        return null;
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
        $capital = $this->capital();
        $members = $this->capitalRows();
        // Khoá dùng ở FE là user_id dạng chuỗi — tên có thể trùng nhau, id thì không.
        $keys = $members->pluck('user_id')->map(fn (int $id) => (string) $id)->all();
        $lastKey = end($keys);

        $deficit = 0;              // lỗ luỹ kế còn phải bù
        $reserveTotal = 0;
        $totals = array_fill_keys($keys, 0);
        $rows = [];

        // Chưa ai khai vốn góp thì không có gì để chia — và quan trọng hơn, chia cho
        // $capital = 0 sẽ nổ. Vẫn phải trả về cấu trúc đầy đủ để màn hình render được
        // trạng thái rỗng thay vì trắng trang.
        if ($capital <= 0) {
            return [
                'partners' => [],
                'reserve_percent' => round(self::RESERVE_RATE * 100, 2),
                'reserve_total' => 0,
                'distributed_total' => 0,
                'deficit' => max(0, $this->expenseTotal() - $this->revenue()),
                'rows' => [],
            ];
        }

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
            foreach ($members as $member) {
                $k = (string) $member->user_id;
                if ($k === $lastKey) {
                    // Người cuối nhận phần dư → Σ shares == $toPartners tuyệt đối.
                    $shares[$k] = $toPartners - $allocated;
                } else {
                    $shares[$k] = (int) round($toPartners * $member->total / $capital);
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
        foreach ($members as $member) {
            $k = (string) $member->user_id;
            $partners[] = [
                'key' => $k,
                'name' => $member->name,
                'capital' => $member->total,
                'capital_percent' => round($member->total / $capital * 100, 2),
                // Phần thực nhận trên TỔNG lợi nhuận = 45% × tỉ lệ góp vốn.
                'profit_percent' => round((1 - self::RESERVE_RATE) * $member->total / $capital * 100, 2),
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

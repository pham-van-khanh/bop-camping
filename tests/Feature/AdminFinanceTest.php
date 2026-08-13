<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Order;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-n4qy — màn Tài chính: vốn, thu chi, lợi nhuận, hoàn vốn, chart.
 */
class AdminFinanceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /** @param  array<string, mixed>  $extra */
    private function order(string $status, int $price, array $extra = []): Order
    {
        return Order::create(array_merge([
            'customer_name' => 'Khách', 'customer_phone' => '0900000001',
            'start_date' => '2030-07-01', 'end_date' => '2030-07-03',
            'total_price' => $price, 'discount_total' => 0, 'extra_fee' => 0,
            'deposit_total' => 100000, 'status' => $status, 'payment_method' => 'cod',
        ], $extra));
    }

    private function spend(int $amount, string $category = 'equipment', ?string $on = null): Expense
    {
        return Expense::create([
            'spent_on' => $on ?? now()->toDateString(),
            'amount' => $amount,
            'category' => $category,
        ]);
    }

    private function finance(): FinanceService
    {
        return app(FinanceService::class);
    }

    /** @test */
    public function page_renders_with_overview_and_categories(): void
    {
        $this->actingAs($this->admin())->get(route('admin.finance'))
            ->assertInertia(fn (Assert $p) => $p
                ->component('Admin/Finance')
                ->where('overview.capital', FinanceService::capital())
                ->has('categories', count(Expense::CATEGORIES))
                ->has('overview')
                ->has('monthly')
                ->has('by_category')
                ->has('expenses.rows'));
    }

    /** @test */
    public function non_admin_cannot_open_finance(): void
    {
        $this->get(route('admin.finance'))->assertRedirect();

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.finance'))->assertRedirect();
    }

    /**
     * Doanh thu PHẢI gồm phụ phí — đây là lỗi đã đo được ở bản cũ (bopcamping-n4qy).
     *
     * @test
     */
    public function revenue_includes_extra_fee_and_subtracts_discount(): void
    {
        $this->order('returned', 300_000, ['extra_fee' => 40_000, 'discount_total' => 50_000]);

        $this->assertSame(290_000, $this->finance()->revenue());
    }

    /**
     * Cọc KHÔNG được lẫn vào doanh thu. Đây là cái bẫy lớn nhất của mô hình cho thuê:
     * cọc là tiền của khách, cộng vào thu là tự báo lãi ảo.
     *
     * @test
     */
    public function deposit_never_counts_as_revenue(): void
    {
        $this->order('returned', 300_000, ['deposit_total' => 900_000]);

        $this->assertSame(300_000, $this->finance()->revenue());
    }

    /**
     * Cọc đang cầm = đơn đang thuê + đơn đã trả nhưng chưa hoàn cọc. Đơn mới xác nhận
     * chưa giao đồ nên chưa thu đồng cọc nào.
     *
     * @test
     */
    public function held_deposit_covers_renting_and_unrefunded_returns_only(): void
    {
        $this->order('renting', 100_000, ['deposit_total' => 500_000]);
        $this->order('returned', 100_000, ['deposit_total' => 300_000, 'deposit_refund_status' => 'pending']);
        $this->order('returned', 100_000, ['deposit_total' => 700_000, 'deposit_refund_status' => 'refunded']);
        $this->order('confirmed', 100_000, ['deposit_total' => 400_000]);

        $this->assertSame(800_000, $this->finance()->heldDeposit());
    }

    /**
     * Đơn CHA của đơn gộp là vỏ chứa (tổng = Σ đơn con) — cộng cả cha lẫn con là nhân
     * đôi doanh thu.
     *
     * @test
     */
    public function parent_order_is_excluded_so_revenue_is_not_doubled(): void
    {
        $parent = $this->order('returned', 200_000, ['is_parent' => true]);
        $this->order('returned', 200_000, ['parent_id' => $parent->id]);

        $this->assertSame(200_000, $this->finance()->revenue());
    }

    /** @test */
    public function overview_reports_capital_usage_and_payback(): void
    {
        $this->spend(20_000_000, 'equipment');
        $this->spend(10_000_000, 'shipping');
        $this->order('returned', 15_000_000);

        $o = $this->finance()->overview();

        $this->assertSame(70_000_000, $o['capital']);
        $this->assertSame(30_000_000, $o['spent']);
        $this->assertSame(40_000_000, $o['capital_left']);
        $this->assertSame(15_000_000, $o['revenue']);
        $this->assertSame(-15_000_000, $o['profit']);       // còn lỗ
        $this->assertSame(50.0, $o['payback_percent']);      // thu 15tr / chi 30tr
    }

    /**
     * Chi quá vốn thì vốn còn lại ÂM chứ không kẹp về 0 — che con số đó đi là giấu mất
     * việc shop đang tiêu vào tiền thu được.
     *
     * @test
     */
    public function capital_left_goes_negative_when_overspending(): void
    {
        $this->spend(90_000_000);

        $this->assertSame(-20_000_000, $this->finance()->overview()['capital_left']);
    }

    /** Chưa chi đồng nào thì không có gì để hoàn — không được chia cho 0. */
    /** @test */
    public function payback_is_zero_when_nothing_spent_yet(): void
    {
        $this->order('returned', 5_000_000);

        $this->assertSame(0.0, $this->finance()->overview()['payback_percent']);
    }

    /**
     * Chuỗi theo tháng phải liền mạch: tháng ở GIỮA không có giao dịch vẫn phải có dòng,
     * nếu không chart bị co lại và hai tháng cách nhau nửa năm trông như liền kề.
     *
     * @test
     */
    public function monthly_series_fills_gap_months_and_accumulates(): void
    {
        $this->spend(10_000_000, 'equipment', '2026-06-10');
        $order = $this->order('returned', 4_000_000);
        $order->forceFill(['updated_at' => Carbon::parse('2026-08-15')])->saveQuietly();

        $series = collect($this->finance()->monthlySeries());
        $byMonth = $series->keyBy('month');

        $this->assertTrue($byMonth->has('2026-07'), 'tháng trống ở giữa phải có dòng');
        $this->assertSame(0, $byMonth['2026-07']['revenue']);
        $this->assertSame(0, $byMonth['2026-07']['expense']);

        // Luỹ kế cộng dồn qua cả tháng trống.
        $this->assertSame(10_000_000, $byMonth['2026-07']['cum_expense']);
        $this->assertSame(4_000_000, $byMonth['2026-08']['cum_revenue']);
        $this->assertSame(10_000_000, $byMonth['2026-08']['cum_expense']);
    }

    /**
     * Cắt bớt tháng cũ KHÔNG được reset luỹ kế — cắt trước khi cộng thì đường luỹ kế
     * bắt đầu lại từ 0 và điểm hoà vốn hiện sai tháng.
     *
     * @test
     */
    public function trimming_old_months_keeps_cumulative_from_the_very_beginning(): void
    {
        $this->spend(10_000_000, 'equipment', '2026-06-10');
        $this->spend(5_000_000, 'marketing', now()->toDateString());

        $series = $this->finance()->monthlySeries(1); // chỉ giữ tháng gần nhất

        $this->assertCount(1, $series);
        $this->assertSame(15_000_000, $series[0]['cum_expense']);
    }

    /** @test */
    public function expense_breakdown_reports_share_per_category(): void
    {
        $this->spend(30_000_000, 'equipment');
        $this->spend(10_000_000, 'shipping');

        $rows = collect($this->finance()->expenseByCategory())->keyBy('category');

        $this->assertSame(75.0, $rows['equipment']['percent']);
        $this->assertSame(25.0, $rows['shipping']['percent']);
        $this->assertSame('Mua thiết bị', $rows['equipment']['label']);
    }

    /** Loại chi mới phải dùng được thật, không chỉ có trong hằng số. */
    /** @test */
    public function new_categories_contingency_and_operation_are_accepted(): void
    {
        $admin = $this->admin();

        foreach (['contingency', 'operation'] as $category) {
            $this->actingAs($admin)->post(route('admin.expenses.store'), [
                'spent_on' => now()->toDateString(),
                'amount' => 1_000_000,
                'category' => $category,
            ])->assertRedirect();
        }

        $this->assertSame(2, Expense::whereIn('category', ['contingency', 'operation'])->count());
    }

    /** @test */
    public function admin_can_add_update_and_delete_expense_from_finance_screen(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.expenses.store'), [
            'spent_on' => '2026-08-01', 'amount' => 20_000_000,
            'category' => 'equipment', 'note' => 'Mua lều đợt 1',
        ])->assertRedirect();

        $expense = Expense::firstOrFail();
        $this->assertSame(20_000_000, $expense->amount);

        $this->actingAs($admin)->put(route('admin.expenses.update', $expense), [
            'spent_on' => '2026-08-02', 'amount' => 22_000_000, 'category' => 'equipment',
        ])->assertRedirect();
        $this->assertSame(22_000_000, $expense->fresh()->amount);

        $this->actingAs($admin)->delete(route('admin.expenses.destroy', $expense))->assertRedirect();
        $this->assertSame(0, Expense::count());
    }

    /** @test */
    public function expense_validation_rejects_bad_input(): void
    {
        $this->actingAs($this->admin())->post(route('admin.expenses.store'), [
            'spent_on' => '', 'amount' => 0, 'category' => 'khong-ton-tai',
        ])->assertSessionHasErrors(['spent_on', 'amount', 'category']);
    }

    /* ───────────────── Chia lợi nhuận (bopcamping-n4qy) ───────────────── */

    /** Đưa doanh thu/chi phí vào ĐÚNG một tháng để dựng kịch bản chia. */
    private function revenueIn(string $month, int $amount): void
    {
        $this->order('returned', $amount)
            ->forceFill(['updated_at' => Carbon::parse($month.'-15')])->saveQuietly();
    }

    /** @test */
    public function partner_percentages_derive_from_contributed_capital(): void
    {
        $s = $this->finance()->profitSharing();
        $a = collect($s['partners'])->firstWhere('key', 'a');
        $b = collect($s['partners'])->firstWhere('key', 'b');

        // 40/70 và 30/70, KHÔNG phải 57/43 làm tròn.
        $this->assertSame(57.14, $a['capital_percent']);
        $this->assertSame(42.86, $b['capital_percent']);
        // Phần thực nhận trên tổng lãi = 45% × tỉ lệ góp.
        $this->assertSame(25.71, $a['profit_percent']);
        $this->assertSame(19.29, $b['profit_percent']);
        $this->assertSame(55.0, $s['reserve_percent']);
        $this->assertSame(70_000_000, FinanceService::capital());
    }

    /**
     * Chia đúng luật: 55% vào quỹ, 45% còn lại theo tỉ lệ góp vốn.
     *
     * @test
     */
    public function profit_splits_55_percent_to_reserve_and_the_rest_by_capital(): void
    {
        $this->revenueIn('2026-08', 10_000_000); // không có chi phí -> lãi trọn 10tr

        $row = collect($this->finance()->profitSharing()['rows'])->firstWhere('month', '2026-08');

        $this->assertSame(10_000_000, $row['profit']);
        $this->assertSame(10_000_000, $row['distributable']);
        $this->assertSame(5_500_000, $row['reserve']);          // 55%
        $this->assertSame(2_571_429, $row['shares']['a']);      // 4,5tr × 40/70
        $this->assertSame(1_928_571, $row['shares']['b']);      // 4,5tr × 30/70
    }

    /**
     * TIỀN KHÔNG ĐƯỢC BỐC HƠI: quỹ + các phần chia phải cộng đúng bằng số đem chia, với
     * MỌI con số — kể cả số lẻ không chia hết cho 70. Làm tròn từng khoản độc lập thì
     * tổng lệch vài đồng và bảng không khớp.
     *
     * @test
     */
    public function reserve_plus_shares_always_equals_the_distributable_amount(): void
    {
        foreach ([1, 7, 999, 1_000_001, 3_333_333, 12_345_678] as $i => $profit) {
            Expense::query()->delete();
            Order::query()->delete();
            $this->revenueIn('2026-0'.($i + 1), $profit);

            $row = collect($this->finance()->profitSharing()['rows'])->firstWhere('profit', $profit);

            $sum = $row['reserve'] + $row['shares']['a'] + $row['shares']['b'];
            $this->assertSame($profit, $sum, "lệch ở mức lãi $profit");
        }
    }

    /**
     * Lãi phải BÙ HẾT LỖ LUỸ KẾ rồi mới chia (luật chủ shop chốt).
     *
     * Không có bước này thì tháng nào lãi là hai người rút tiền ngay, kể cả khi tính
     * chung shop vẫn âm — tức là rút vào chính tiền vốn.
     *
     * @test
     */
    public function profit_must_first_cover_the_accumulated_loss(): void
    {
        $this->spend(8_000_000, 'equipment', '2026-06-10'); // T6 lỗ 8tr
        $this->revenueIn('2026-07', 5_000_000);             // T7 lãi 5tr -> bù hết, còn nợ 3tr
        $this->revenueIn('2026-08', 5_000_000);             // T8 lãi 5tr -> bù 3tr, dư 2tr

        $rows = collect($this->finance()->profitSharing()['rows'])->keyBy('month');

        $this->assertSame(-8_000_000, $rows['2026-06']['profit']);
        $this->assertSame(0, $rows['2026-06']['distributable']);

        // T7: lãi 5tr nhưng đang nợ 8tr -> bù sạch, không chia đồng nào.
        $this->assertSame(5_000_000, $rows['2026-07']['offset']);
        $this->assertSame(0, $rows['2026-07']['distributable']);
        $this->assertSame(0, $rows['2026-07']['shares']['a']);

        // T8: bù nốt 3tr, còn 2tr mới đem chia.
        $this->assertSame(3_000_000, $rows['2026-08']['offset']);
        $this->assertSame(2_000_000, $rows['2026-08']['distributable']);
        $this->assertSame(1_100_000, $rows['2026-08']['reserve']);
        $this->assertSame(514_286, $rows['2026-08']['shares']['a']);
        $this->assertSame(385_714, $rows['2026-08']['shares']['b']);
    }

    /**
     * Lỗ mới phát sinh SAU khi đã chia thì lại thành nợ phải bù cho lần chia kế tiếp —
     * không được quên, nếu không mỗi đợt lỗ là một lần rút quá tay.
     *
     * @test
     */
    public function a_later_loss_becomes_deficit_again(): void
    {
        $this->revenueIn('2026-06', 10_000_000);              // lãi 10tr, chia hết
        $this->spend(4_000_000, 'repair', '2026-07-05');      // lỗ 4tr

        $s = $this->finance()->profitSharing();

        $this->assertSame(4_000_000, $s['deficit']);
        $this->assertSame(0, collect($s['rows'])->firstWhere('month', '2026-07')['distributable']);
    }

    /** Đang lỗ luỹ kế thì tổng chia phải bằng 0 — không ai được đồng nào. */
    /** @test */
    public function nothing_is_distributed_while_still_in_the_red(): void
    {
        $this->spend(30_000_000, 'equipment', '2026-06-10');
        $this->revenueIn('2026-07', 5_000_000);

        $s = $this->finance()->profitSharing();

        $this->assertSame(0, $s['distributed_total']);
        $this->assertSame(0, $s['reserve_total']);
        $this->assertSame(25_000_000, $s['deficit']);
    }

    /** @test */
    public function totals_match_the_sum_of_every_month(): void
    {
        $this->revenueIn('2026-06', 10_000_000);
        $this->revenueIn('2026-07', 7_000_000);

        $s = $this->finance()->profitSharing();
        $rows = collect($s['rows']);

        $this->assertSame((int) $rows->sum('reserve'), $s['reserve_total']);
        $this->assertSame(
            (int) $rows->sum(fn ($r) => $r['shares']['a'] + $r['shares']['b']),
            $s['distributed_total']
        );
        // Quỹ + chia = đúng phần lãi đã đem chia, không thừa không thiếu.
        $this->assertSame(17_000_000, $s['reserve_total'] + $s['distributed_total']);
    }

    /** @test */
    public function sharing_prop_is_exposed_to_the_screen(): void
    {
        $this->actingAs($this->admin())->get(route('admin.finance'))
            ->assertInertia(fn (Assert $p) => $p
                ->has('sharing.partners', 2)
                // 55.0 qua JSON thành số nguyên 55 — assert theo đúng thứ FE nhận được.
                ->where('sharing.reserve_percent', 55)
                ->has('sharing.rows')
                ->has('sharing.deficit'));
    }

    /**
     * Bộ lọc kỳ chỉ áp cho khối "trong kỳ". Khối Vốn phải giữ nguyên toàn thời gian —
     * vốn còn lại mà tính theo tháng thì tháng nào cũng báo còn nguyên 70tr.
     *
     * @test
     */
    public function period_filter_narrows_the_period_block_but_not_the_capital_block(): void
    {
        $this->spend(8_000_000, 'equipment', now()->subMonths(6)->toDateString());
        $this->spend(2_000_000, 'marketing', now()->toDateString());

        $this->actingAs($this->admin())->get(route('admin.finance', ['period' => 'month']))
            ->assertInertia(fn (Assert $p) => $p
                ->where('period_summary.expense', 2_000_000)
                ->where('overview.spent', 10_000_000)
                ->where('overview.capital_left', 60_000_000));
    }
}

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
                ->where('overview.capital', FinanceService::INITIAL_CAPITAL)
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

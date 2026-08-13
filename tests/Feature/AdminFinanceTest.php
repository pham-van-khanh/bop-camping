<?php

namespace Tests\Feature;

use App\Models\CapitalContribution;
use App\Models\Expense;
use App\Models\Order;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

    /** Ghi một lần góp vốn cho $user. */
    private function contribute(User $user, int $amount, ?string $on = null): CapitalContribution
    {
        return CapitalContribution::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'contributed_on' => $on ?? '2026-06-01',
        ]);
    }

    /**
     * Hai thành viên góp 40tr/30tr như thoả thuận thật — dùng cho test chia lợi nhuận.
     *
     * @return array{0: User, 1: User}
     */
    private function twoPartners(): array
    {
        $a = User::factory()->create(['is_admin' => true, 'name' => 'Admin A']);
        $b = User::factory()->create(['is_admin' => true, 'name' => 'Admin B']);
        $this->contribute($a, 40_000_000);
        $this->contribute($b, 30_000_000);

        return [$a, $b];
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
                ->where('overview.capital', $this->finance()->capital())
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
     * Cọc đã TRẢ LẠI khách thì không còn "đang giữ", kể cả khi đơn chưa kịp chuyển sang
     * 'returned'.
     *
     * Shipper\ScheduleController::refundDeposit() cho phép hoàn cọc ngay lúc đang thu đồ
     * (status vẫn 'renting'). Bản cũ cộng MỌI đơn 'renting' bất kể deposit_refund_status
     * nên ô "Cọc đang giữ" vẫn đếm số tiền đã đưa lại khách.
     *
     * @test
     */
    public function deposit_refunded_before_the_status_flips_is_no_longer_held(): void
    {
        // Shipper đã trả cọc tại chỗ, đơn chưa kịp đóng.
        $this->order('renting', 100_000, ['deposit_total' => 500_000, 'deposit_refund_status' => 'refunded']);
        // Đơn đang thuê bình thường, cọc vẫn cầm.
        $this->order('renting', 100_000, ['deposit_total' => 300_000]);

        $this->assertSame(300_000, $this->finance()->heldDeposit());
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
        $this->twoPartners();   // 40tr + 30tr = 70tr vốn
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
        $this->twoPartners();
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

    /**
     * Điểm hoà vốn phải lấy từ TOÀN BỘ lịch sử, không phải từ 24 tháng đang hiển thị.
     *
     * Bảng chart chỉ gửi 24 tháng gần nhất. Shop hoà vốn trước khoảng đó thì dòng ĐẦU
     * TIÊN của bảng đã thoả cum_revenue >= cum_expense — FE dò trong mảng nhận được sẽ
     * báo nhầm tháng đó là tháng hoà vốn, trong khi thực tế hoà vốn từ lâu rồi.
     *
     * @test
     */
    public function break_even_month_comes_from_full_history_not_the_visible_window(): void
    {
        // Hoà vốn từ 2024-02, cách hôm nay hơn 24 tháng.
        $this->spend(1_000_000, 'equipment', '2024-01-10');
        $this->revenueIn('2024-02', 3_000_000);
        // Hoạt động gần đây để chuỗi kéo tới hôm nay.
        $this->revenueIn('2026-08', 500_000);

        $visible = $this->finance()->monthlySeries();      // 24 tháng gần nhất
        $this->assertLessThanOrEqual(24, count($visible));
        $this->assertNotSame('2024-02', $visible[0]['month'], 'tháng hoà vốn thật phải nằm NGOÀI khoảng hiển thị');

        $this->assertSame('2024-02', $this->finance()->breakEvenMonth()['month']);
    }

    /** Chưa bao giờ thu bù nổi chi thì không có tháng hoà vốn nào. */
    /** @test */
    public function break_even_is_null_while_revenue_never_catches_up(): void
    {
        $this->spend(30_000_000, 'equipment', '2026-06-10');
        $this->revenueIn('2026-07', 5_000_000);

        $this->assertNull($this->finance()->breakEvenMonth());
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
     * MỌI admin đều sửa được số liệu thu chi (bopcamping-xlmy) — phân quyền super admin
     * đã bỏ theo yêu cầu chủ shop. Test này khoá lại để không ai vô tình cắm lại rào.
     *
     * @test
     */
    public function every_admin_can_write_finance_data(): void
    {
        $admin = $this->admin();
        $expense = $this->spend(1_000_000);
        $capital = $this->contribute($admin, 5_000_000);

        $this->actingAs($admin)->post(route('admin.expenses.store'), [
            'spent_on' => '2026-08-01', 'amount' => 1_000, 'category' => 'other',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->put(route('admin.expenses.update', $expense), [
            'spent_on' => '2026-08-01', 'amount' => 9_999, 'category' => 'other',
        ])->assertSessionHasNoErrors();
        $this->assertSame(9_999, $expense->fresh()->amount);

        $this->actingAs($admin)->post(route('admin.capital.store'), [
            'user_id' => $admin->id, 'amount' => 1_000, 'contributed_on' => '2026-08-01',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->put(route('admin.capital.update', $capital), [
            'user_id' => $admin->id, 'amount' => 7_777, 'contributed_on' => '2026-08-01',
        ])->assertSessionHasNoErrors();
        $this->assertSame(7_777, $capital->fresh()->amount);

        $this->actingAs($admin)->delete(route('admin.expenses.destroy', $expense))->assertRedirect();
        $this->actingAs($admin)->delete(route('admin.capital.destroy', $capital))->assertRedirect();
    }

    /* ──────────────── Sổ vốn góp (bopcamping-n4qy) ──────────────── */

    /** @test */
    public function admin_can_add_update_and_delete_capital(): void
    {
        $super = $this->admin();

        $this->actingAs($super)->post(route('admin.capital.store'), [
            'user_id' => $super->id, 'amount' => 40_000_000,
            'contributed_on' => '2026-06-01', 'note' => 'Vốn ban đầu',
        ])->assertRedirect();

        $row = CapitalContribution::firstOrFail();
        $this->assertSame(40_000_000, $row->amount);

        $this->actingAs($super)->put(route('admin.capital.update', $row), [
            'user_id' => $super->id, 'amount' => 45_000_000, 'contributed_on' => '2026-06-02',
        ])->assertRedirect();
        $this->assertSame(45_000_000, $row->fresh()->amount);

        $this->actingAs($super)->delete(route('admin.capital.destroy', $row))->assertRedirect();
        $this->assertSame(0, CapitalContribution::count());
    }

    /**
     * Vốn góp chỉ gắn được vào tài khoản QUẢN TRỊ — trỏ vào khách hàng là sai mô hình.
     *
     * @test
     */
    public function capital_can_only_belong_to_an_admin_account(): void
    {
        $customer = User::factory()->create(['is_admin' => false]);

        $this->actingAs($this->admin())->post(route('admin.capital.store'), [
            'user_id' => $customer->id, 'amount' => 1_000_000, 'contributed_on' => '2026-06-01',
        ])->assertSessionHasErrors('user_id');

        $this->assertSame(0, CapitalContribution::count());
    }

    /** @test */
    public function capital_validation_rejects_bad_input(): void
    {
        $this->actingAs($this->admin())->post(route('admin.capital.store'), [
            'user_id' => '', 'amount' => 0, 'contributed_on' => '',
        ])->assertSessionHasErrors(['user_id', 'amount', 'contributed_on']);
    }

    /**
     * GÓP THÊM cộng dồn, không ghi đè — đây là lý do dùng sổ thay vì một con số mỗi người.
     *
     * @test
     */
    public function extra_contributions_add_up_and_reshuffle_the_percentages(): void
    {
        [$a, $b] = $this->twoPartners();          // 40tr / 30tr
        $this->contribute($a, 10_000_000, '2026-09-01');  // A góp thêm 10tr

        $this->assertSame(80_000_000, $this->finance()->capital());

        $partners = collect($this->finance()->profitSharing()['partners'])->keyBy('name');
        $this->assertSame(50_000_000, $partners['Admin A']['capital']);
        $this->assertSame(62.5, $partners['Admin A']['capital_percent']);
        $this->assertSame(37.5, $partners['Admin B']['capital_percent']);
    }

    /**
     * Thêm THÀNH VIÊN THỨ BA là tỉ lệ tự tính lại — không phải sửa code.
     *
     * @test
     */
    public function a_third_partner_reshapes_the_split_without_touching_code(): void
    {
        [$a, $b] = $this->twoPartners();
        $c = User::factory()->create(['is_admin' => true, 'name' => 'Admin C']);
        $this->contribute($c, 30_000_000, '2026-10-01');

        $s = $this->finance()->profitSharing();
        $this->assertCount(3, $s['partners']);
        $this->assertSame(100_000_000, $this->finance()->capital());

        $byName = collect($s['partners'])->keyBy('name');
        $this->assertSame(40.0, $byName['Admin A']['capital_percent']);
        $this->assertSame(30.0, $byName['Admin B']['capital_percent']);
        $this->assertSame(30.0, $byName['Admin C']['capital_percent']);

        // Tổng chia vẫn khớp tuyệt đối khi có 3 người.
        $this->revenueIn('2026-02', 10_000_000);
        $row = collect($this->finance()->profitSharing()['rows'])->firstWhere('quarter', '2026-Q1');
        $this->assertSame(
            $row['distributable'],
            $row['reserve'] + array_sum($row['shares']),
            'quỹ + 3 phần chia phải bằng đúng số đem chia'
        );
    }

    /**
     * Chưa khai vốn góp thì KHÔNG được nổ vì chia cho 0 — màn phải render trạng thái rỗng.
     *
     * @test
     */
    public function no_capital_yet_does_not_divide_by_zero(): void
    {
        $this->spend(5_000_000);
        $this->revenueIn('2026-08', 1_000_000);

        $s = $this->finance()->profitSharing();

        $this->assertSame(0, $this->finance()->capital());
        $this->assertSame([], $s['partners']);
        $this->assertSame([], $s['rows']);

        $this->actingAs($this->admin())->get(route('admin.finance'))->assertOk();
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
        $this->twoPartners();

        $s = $this->finance()->profitSharing();
        $a = collect($s['partners'])->firstWhere('name', 'Admin A');
        $b = collect($s['partners'])->firstWhere('name', 'Admin B');

        // 40/70 và 30/70, KHÔNG phải 57/43 làm tròn.
        $this->assertSame(57.14, $a['capital_percent']);
        $this->assertSame(42.86, $b['capital_percent']);
        // Phần thực nhận trên tổng lãi = 45% × tỉ lệ góp.
        $this->assertSame(25.71, $a['profit_percent']);
        $this->assertSame(19.29, $b['profit_percent']);
        $this->assertSame(55.0, $s['reserve_percent']);
        $this->assertSame(70_000_000, $this->finance()->capital());
    }

    /**
     * Chia đúng luật: 55% vào quỹ, 45% còn lại theo tỉ lệ góp vốn.
     *
     * @test
     */
    public function profit_splits_55_percent_to_reserve_and_the_rest_by_capital(): void
    {
        [$a, $b] = $this->twoPartners();
        // Quý ĐÃ khép sổ — quý đang chạy chỉ tính tạm nên không dùng để kiểm luật chia.
        $this->revenueIn('2026-02', 10_000_000); // không có chi phí -> lãi trọn 10tr

        $row = collect($this->finance()->profitSharing()['rows'])->firstWhere('quarter', '2026-Q1');

        $this->assertSame(10_000_000, $row['profit']);
        $this->assertSame(10_000_000, $row['distributable']);
        $this->assertSame(5_500_000, $row['reserve']);          // 55%
        $this->assertSame(2_571_429, $row['shares'][(string) $a->id]);  // 4,5tr × 40/70
        $this->assertSame(1_928_571, $row['shares'][(string) $b->id]);  // 4,5tr × 30/70
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
        [$a, $b] = $this->twoPartners();
        foreach ([1, 7, 999, 1_000_001, 3_333_333, 12_345_678] as $i => $profit) {
            Expense::query()->delete();
            Order::query()->delete();
            $this->revenueIn('2026-0'.($i + 1), $profit);

            $row = collect($this->finance()->profitSharing()['rows'])->firstWhere('profit', $profit);

            $sum = $row['reserve'] + $row['shares'][(string) $a->id] + $row['shares'][(string) $b->id];
            $this->assertSame($profit, $sum, "lệch ở mức lãi $profit");
        }
    }

    /**
     * KHÔNG ai được nhận số ÂM, dù có bao nhiêu thành viên và số đem chia lẻ thế nào.
     *
     * Cách chia cũ (làm tròn từng người rồi để người CUỐI nhận phần dư) cho ra số âm:
     * 4 người góp đều nhau, chia 2đ → [1, 1, 1, −1]; 5 người đều nhau, chia 3đ →
     * [1, 1, 1, 1, −1]. Quét mọi mức tiền nhỏ với 2–6 thành viên để khoá lại.
     *
     * @test
     */
    public function no_partner_ever_gets_a_negative_share(): void
    {
        foreach ([2, 3, 4, 5, 6] as $n) {
            $members = collect(range(1, $n))->map(fn (int $i) => (object) [
                'user_id' => $i, 'name' => "P$i", 'total' => 25_000_000,
            ]);
            $capital = 25_000_000 * $n;

            foreach (range(0, 60) as $amount) {
                $shares = $this->invokeSplit($amount, $members, $capital);

                $this->assertSame($amount, array_sum($shares), "$n người, chia $amount: tổng lệch");
                $this->assertGreaterThanOrEqual(0, min($shares), "$n người, chia $amount: có phần ÂM");
            }
        }
    }

    /**
     * Phần dư phải đi tới người có phần thập phân lớn nhất, KHÔNG dồn hết cho người góp
     * ít nhất. capitalRows() sắp vốn giảm dần nên cách cũ luôn bắt đúng một người gánh.
     *
     * @test
     */
    public function the_rounding_remainder_goes_to_the_largest_fraction_not_always_the_last(): void
    {
        // 3 người 50 / 30 / 20 trên tổng 100, chia 10đ → đúng 5 / 3 / 2, không lẻ.
        $members = collect([
            (object) ['user_id' => 1, 'name' => 'A', 'total' => 50],
            (object) ['user_id' => 2, 'name' => 'B', 'total' => 30],
            (object) ['user_id' => 3, 'name' => 'C', 'total' => 20],
        ]);

        $this->assertSame(['1' => 5, '2' => 3, '3' => 2], $this->invokeSplit(10, $members, 100));

        // Chia 7đ: chính xác là 3,5 / 2,1 / 1,4. Phần nguyên 3/2/1 = 6, còn 1đ phải về
        // người có phần thập phân lớn nhất (A: 0,5) — không phải người cuối.
        $this->assertSame(['1' => 4, '2' => 2, '3' => 1], $this->invokeSplit(7, $members, 100));
    }

    /** Gọi splitByCapital() (private) — thuật toán chia là chỗ tiền dễ sai nhất. */
    private function invokeSplit(int $amount, Collection $members, int $capital): array
    {
        $m = new \ReflectionMethod(FinanceService::class, 'splitByCapital');

        return $m->invoke($this->finance(), $amount, $members, $capital);
    }

    /**
     * Chuỗi tháng chỉ nạp DB một lần cho mỗi request, dù được dùng 3 chỗ (chart,
     * điểm hoà vốn, chia lợi nhuận). Không memoize thì mỗi lần lại select cả bảng đơn.
     *
     * @test
     */
    public function the_monthly_series_hits_the_database_only_once_per_request(): void
    {
        $this->revenueIn('2026-02', 1_000_000);
        $this->spend(500_000, 'other', '2026-02-01');

        $finance = $this->finance();
        DB::enableQueryLog();

        $finance->monthlySeries();
        $finance->monthlySeries(null);
        $finance->breakEvenMonth();

        $loaded = collect(DB::getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'from `expenses`'))
            ->count();
        DB::disableQueryLog();

        $this->assertSame(1, $loaded, 'chuỗi tháng phải nạp DB đúng 1 lần');
    }

    /**
     * MỖI QUÝ CHIA ĐỘC LẬP — không bù lỗ quý trước (bopcamping-qipx).
     *
     * Chủ shop bỏ luật bù lỗ ngày 13/08/2026: quý nào lãi bao nhiêu chia bấy nhiêu, kể
     * cả số nhỏ. Quý lỗ thì chia 0 và KHÔNG để lại nợ cho quý sau.
     *
     * @test
     */
    public function each_quarter_shares_its_own_profit_without_covering_past_losses(): void
    {
        [$a, $b] = $this->twoPartners();
        $this->spend(8_000_000, 'equipment', '2025-11-10'); // Q4/2025 lỗ 8tr
        $this->revenueIn('2026-02', 5_000_000);             // Q1/2026 lãi 5tr
        $this->revenueIn('2026-05', 500_000);               // Q2/2026 lãi 500k

        $rows = collect($this->finance()->profitSharing()['rows'])->keyBy('quarter');

        // Quý lỗ: không chia gì.
        $this->assertSame(-8_000_000, $rows['2025-Q4']['profit']);
        $this->assertSame(0, $rows['2025-Q4']['distributable']);

        // Q1 chia TRỌN 5tr dù quý trước lỗ 8tr — đây là điểm khác luật cũ.
        $this->assertSame(5_000_000, $rows['2026-Q1']['distributable']);
        $this->assertSame(2_750_000, $rows['2026-Q1']['reserve']);
        $this->assertSame(1_285_714, $rows['2026-Q1']['shares'][(string) $a->id]);
        $this->assertSame(964_286, $rows['2026-Q1']['shares'][(string) $b->id]);

        // Quý lãi ít cũng chia — ví dụ chủ shop đưa ra: 500k thì cũng chia.
        $this->assertSame(500_000, $rows['2026-Q2']['distributable']);
        $this->assertSame(275_000, $rows['2026-Q2']['reserve']);
        $this->assertSame(
            225_000,
            $rows['2026-Q2']['shares'][(string) $a->id] + $rows['2026-Q2']['shares'][(string) $b->id]
        );
    }

    /**
     * Lỗ quý này KHÔNG kéo theo quý sau — quý sau vẫn chia trọn phần lãi của nó.
     *
     * @test
     */
    public function a_loss_quarter_does_not_reduce_the_next_quarter(): void
    {
        $this->twoPartners();
        $this->revenueIn('2026-02', 10_000_000);          // Q1 lãi 10tr
        $this->spend(4_000_000, 'repair', '2026-05-05');  // Q2 lỗ 4tr

        $rows = collect($this->finance()->profitSharing()['rows'])->keyBy('quarter');

        $this->assertSame(10_000_000, $rows['2026-Q1']['distributable']);
        $this->assertSame(0, $rows['2026-Q2']['distributable']);
    }

    /**
     * Tiền THỰC TRẢ cho thành viên ghi thành khoản chi 'profit_share' và trừ vào lãi của
     * quý ghi nhận — như mọi khoản chi khác (bopcamping-qipx).
     *
     * @test
     */
    public function the_payout_recorded_as_an_expense_reduces_that_quarters_profit(): void
    {
        $this->twoPartners();
        $this->revenueIn('2026-02', 10_000_000);                        // Q1 lãi 10tr
        $this->revenueIn('2026-05', 4_000_000);                         // Q2 thu 4tr
        $this->spend(4_500_000, 'profit_share', '2026-04-10');          // trả tiền chia Q1, ghi ở Q2

        $rows = collect($this->finance()->profitSharing()['rows'])->keyBy('quarter');

        $this->assertSame(10_000_000, $rows['2026-Q1']['distributable']);
        // Q2: 4tr thu − 4,5tr trả = lỗ 500k → không chia.
        $this->assertSame(-500_000, $rows['2026-Q2']['profit']);
        $this->assertSame(0, $rows['2026-Q2']['distributable']);
    }

    /** Loại chi 'Chia lợi nhuận' phải nhập được thật, không chỉ có trong hằng số. */
    /** @test */
    public function the_profit_share_expense_category_is_accepted(): void
    {
        $this->actingAs($this->admin())->post(route('admin.expenses.store'), [
            'spent_on' => '2026-04-10', 'amount' => 4_500_000,
            'category' => 'profit_share', 'note' => 'Chia lãi Q1',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Expense::where('category', 'profit_share')->count());
    }

    /**
     * Tháng lỗ và tháng lãi TRONG CÙNG MỘT QUÝ tự triệt tiêu — đây chính là điểm khác
     * so với chia theo tháng, và là lý do chia theo quý sát thực tế hơn.
     *
     * @test
     */
    public function months_inside_one_quarter_net_off_against_each_other(): void
    {
        $this->twoPartners();
        $this->spend(3_000_000, 'equipment', '2026-04-10');  // T4 lỗ 3tr
        $this->revenueIn('2026-05', 8_000_000);              // T5 lãi 8tr

        $q2 = collect($this->finance()->profitSharing()['rows'])->firstWhere('quarter', '2026-Q2');

        // Chia theo tháng thì T4 chia 0 rồi T5 chia trọn 8tr; theo quý thì lãi quý là
        // 5tr — phần lỗ của T4 đã trừ vào T5 trong cùng quý.
        $this->assertSame(5_000_000, $q2['profit']);
        $this->assertSame(5_000_000, $q2['distributable']);
    }

    /**
     * QUÝ ĐANG CHẠY chưa khép sổ: hiện để xem trước nhưng KHÔNG cộng vào tổng đã chia.
     * Gộp vào tổng là mời người ta rút tiền dựa trên con số còn đổi tới cuối quý.
     *
     * @test
     */
    public function the_running_quarter_is_provisional_and_excluded_from_totals(): void
    {
        [$a, $b] = $this->twoPartners();
        $this->revenueIn('2026-02', 10_000_000);   // Q1/2026 — đã khép sổ
        $this->revenueIn(now()->format('Y-m'), 6_000_000); // quý hiện tại — đang mở

        $s = $this->finance()->profitSharing();
        $rows = collect($s['rows'])->keyBy('quarter');
        $openKey = now()->year.'-Q'.(int) ceil(now()->month / 3);

        $this->assertTrue($rows[$openKey]['is_open'], 'quý hiện tại phải được đánh dấu đang mở');
        $this->assertFalse($rows['2026-Q1']['is_open']);

        // Quý mở vẫn tính ra số để xem trước…
        $this->assertSame(6_000_000, $rows[$openKey]['profit']);
        $this->assertSame(3_300_000, $rows[$openKey]['reserve']);

        // …nhưng tổng CHỈ gồm quý đã khép sổ (10tr), không phải 16tr.
        $this->assertSame(5_500_000, $s['reserve_total']);
        $this->assertSame(4_500_000, $s['distributed_total']);
        $this->assertSame(2_571_429, collect($s['partners'])->firstWhere('key', (string) $a->id)['total']);
        $this->assertSame(1_928_571, collect($s['partners'])->firstWhere('key', (string) $b->id)['total']);
    }

    /**
     * Toàn bộ lịch sử đang lỗ nặng nhưng quý này có lãi thì VẪN chia — luật mới bỏ hẳn
     * điều kiện "phải hết lỗ luỹ kế".
     *
     * @test
     */
    public function a_profitable_quarter_is_shared_even_while_the_shop_is_down_overall(): void
    {
        $this->twoPartners();
        $this->spend(30_000_000, 'equipment', '2025-11-10'); // Q4/2025 lỗ 30tr
        $this->revenueIn('2026-02', 5_000_000);              // Q1/2026 lãi 5tr

        $s = $this->finance()->profitSharing();

        $this->assertSame(2_750_000, $s['reserve_total']);
        $this->assertSame(2_250_000, $s['distributed_total']);
        // Toàn cảnh vẫn báo lỗ 25tr — hai con số này nói hai chuyện khác nhau.
        $this->assertSame(-25_000_000, $this->finance()->overview()['profit']);
    }

    /** @test */
    public function totals_match_the_sum_of_closed_quarters(): void
    {
        [$a, $b] = $this->twoPartners();
        $this->revenueIn('2026-02', 10_000_000);   // Q1
        $this->revenueIn('2026-05', 7_000_000);    // Q2

        $s = $this->finance()->profitSharing();
        $closed = collect($s['rows'])->reject(fn ($r) => $r['is_open']);

        $this->assertSame((int) $closed->sum('reserve'), $s['reserve_total']);
        $this->assertSame(
            (int) $closed->sum(fn ($r) => $r['shares'][(string) $a->id] + $r['shares'][(string) $b->id]),
            $s['distributed_total']
        );
        // Quỹ + chia = đúng phần lãi đã đem chia, không thừa không thiếu.
        $this->assertSame(17_000_000, $s['reserve_total'] + $s['distributed_total']);
    }

    /** @test */
    public function sharing_prop_is_exposed_to_the_screen(): void
    {
        $this->twoPartners();
        $this->actingAs($this->admin())->get(route('admin.finance'))
            ->assertInertia(fn (Assert $p) => $p
                ->has('sharing.partners', 2)
                // 55.0 qua JSON thành số nguyên 55 — assert theo đúng thứ FE nhận được.
                ->where('sharing.reserve_percent', 55)
                ->has('sharing.rows'));
    }

    /**
     * Bộ lọc kỳ chỉ áp cho khối "trong kỳ". Khối Vốn phải giữ nguyên toàn thời gian —
     * vốn còn lại mà tính theo tháng thì tháng nào cũng báo còn nguyên 70tr.
     *
     * @test
     */
    public function period_filter_narrows_the_period_block_but_not_the_capital_block(): void
    {
        $this->twoPartners();
        $this->spend(8_000_000, 'equipment', now()->subMonths(6)->toDateString());
        $this->spend(2_000_000, 'marketing', now()->toDateString());

        $this->actingAs($this->admin())->get(route('admin.finance', ['period' => 'month']))
            ->assertInertia(fn (Assert $p) => $p
                ->where('period_summary.expense', 2_000_000)
                ->where('overview.spent', 10_000_000)
                ->where('overview.capital_left', 60_000_000));
    }
}

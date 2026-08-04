<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-h1s — trang Thống kê admin: số đơn, thu chi, chi phí phát sinh.
 * bopcamping-trc — badge số đơn mới (pending_orders) chia sẻ cho sidebar admin.
 */
class AdminStatsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function order(string $status, int $price, int $discount = 0): Order
    {
        return Order::create([
            'customer_name' => 'Khách', 'customer_phone' => '0900000001',
            'start_date' => '2030-07-01', 'end_date' => '2030-07-03',
            'total_price' => $price, 'discount_total' => $discount, 'deposit_total' => 100000,
            'status' => $status, 'payment_method' => 'cod',
        ]);
    }

    /** @test */
    public function stats_page_renders_with_counts_and_chart(): void
    {
        $this->order('pending', 100000);
        $this->order('returned', 200000);

        $this->actingAs($this->admin())->get(route('admin.stats'))->assertInertia(fn (Assert $p) => $p
            ->component('Admin/Stats')
            ->where('order_counts.total', 2)
            ->where('order_counts.today', 2)
            ->has('chart', 30)
            ->has('categories', 5));
    }

    /** @test */
    public function revenue_counts_only_returned_orders_minus_discount(): void
    {
        $this->order('returned', 300000, 50000); // thu 250k
        $this->order('confirmed', 500000);       // KHÔNG tính (chưa trả)
        Expense::create(['spent_on' => now()->toDateString(), 'amount' => 80000, 'category' => 'repair']);

        $this->actingAs($this->admin())->get(route('admin.stats'))->assertInertia(fn (Assert $p) => $p
            ->where('finance.revenue', 250000)
            ->where('finance.expense', 80000)
            ->where('finance.profit', 170000)
            ->where('finance.returned_count', 1));
    }

    /** @test */
    public function admin_can_add_update_delete_expense(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.expenses.store'), [
            'spent_on' => now()->toDateString(), 'amount' => 120000, 'category' => 'equipment', 'note' => 'Mua lều',
        ])->assertSessionHasNoErrors();
        $expense = Expense::firstOrFail();
        $this->assertSame(120000, $expense->amount);
        $this->assertSame('equipment', $expense->category);

        $this->actingAs($admin)->put(route('admin.expenses.update', $expense), [
            'spent_on' => now()->toDateString(), 'amount' => 90000, 'category' => 'shipping',
        ])->assertSessionHasNoErrors();
        $this->assertSame(90000, $expense->fresh()->amount);
        $this->assertSame('shipping', $expense->fresh()->category);

        $this->actingAs($admin)->delete(route('admin.expenses.destroy', $expense))->assertSessionHasNoErrors();
        $this->assertSame(0, Expense::count());
    }

    /** @test */
    public function expense_validation_rejects_bad_input(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.expenses.store'), ['spent_on' => now()->toDateString(), 'amount' => 0, 'category' => 'equipment'])
            ->assertSessionHasErrors('amount');
        $this->actingAs($admin)->post(route('admin.expenses.store'), ['spent_on' => now()->toDateString(), 'amount' => 1000, 'category' => 'bogus'])
            ->assertSessionHasErrors('category');
        $this->assertSame(0, Expense::count());
    }

    /** @test */
    public function period_filter_narrows_finance_window(): void
    {
        // Chi cũ (2 tháng trước) không nằm trong "tháng này".
        Expense::create(['spent_on' => now()->subMonths(2)->toDateString(), 'amount' => 500000, 'category' => 'other']);
        Expense::create(['spent_on' => now()->toDateString(), 'amount' => 70000, 'category' => 'other']);

        $this->actingAs($this->admin())->get(route('admin.stats', ['period' => 'month']))->assertInertia(fn (Assert $p) => $p
            ->where('period', 'month')
            ->where('finance.expense', 70000));

        $this->actingAs($this->admin())->get(route('admin.stats', ['period' => 'all']))->assertInertia(fn (Assert $p) => $p
            ->where('finance.expense', 570000));
    }

    /** @test */
    public function non_admin_cannot_access_stats_or_expenses(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get(route('admin.stats'))->assertRedirect(route('admin.login'));
        $this->actingAs($user)->post(route('admin.expenses.store'), ['spent_on' => now()->toDateString(), 'amount' => 1000, 'category' => 'other'])
            ->assertRedirect(route('admin.login'));
        $this->assertSame(0, Expense::count());
    }

    /**
     * @test
     *
     * bopcamping-wlee — bảng doanh thu: gom đơn đã trả theo ngày trả (updated_at),
     * liệt kê MỌI ngày trong tháng kể cả ngày không có đơn.
     */
    public function revenue_by_day_lists_every_day_of_month_including_empty_ones(): void
    {
        $this->travelTo(Carbon::parse('2026-08-05 10:00'));

        $a = $this->order('returned', 300000, 50000); // 250k — 03/08
        $b = $this->order('returned', 120000);        // 120k — 03/08
        $c = $this->order('returned', 90000);         //  90k — 01/08
        $this->order('confirmed', 999000);            // chưa trả → không được lọt vào

        $this->returnedOn($a, Carbon::parse('2026-08-03 09:00'));
        $this->returnedOn($b, Carbon::parse('2026-08-03 15:00'));
        $this->returnedOn($c, Carbon::parse('2026-08-01 08:00'));

        $days = $this->statsProps()['revenue_by_day'];

        // 01/08 → 05/08 (hôm nay), không có ngày tương lai.
        $this->assertCount(5, $days);
        $this->assertSame(['2026-08-01', '2026-08-02', '2026-08-03', '2026-08-04', '2026-08-05'], array_column($days, 'date'));

        $this->assertSame('01/08/2026', $days[0]['label']);
        $this->assertSame(90000, $days[0]['total']);
        $this->assertSame($c->code, $days[0]['orders'][0]['code']);

        // Ngày trống vẫn có dòng, tổng 0, không đơn nào.
        $this->assertSame(0, $days[1]['total']);
        $this->assertSame([], $days[1]['orders']);

        $this->assertSame(370000, $days[2]['total']);
        $this->assertEqualsCanonicalizing([$a->code, $b->code], array_column($days[2]['orders'], 'code'));
    }

    /**
     * @test
     *
     * bopcamping-wlee — lọc theo tháng: chỉ đơn trả trong tháng đó, tháng cũ hiện đủ ngày.
     */
    public function revenue_by_day_filters_by_selected_month(): void
    {
        $this->travelTo(Carbon::parse('2026-10-10 10:00'));

        $this->returnedOn($this->order('returned', 500000), Carbon::parse('2026-08-20 10:00'));
        $this->returnedOn($this->order('returned', 70000), Carbon::parse('2026-09-02 10:00'));

        $aug = $this->statsProps(['month' => '2026-08']);
        $this->assertSame('2026-08', $aug['revenue_month']);
        $this->assertCount(31, $aug['revenue_by_day']); // tháng cũ → đủ 31 ngày
        $this->assertSame(500000, collect($aug['revenue_by_day'])->sum('total'));

        $sep = $this->statsProps(['month' => '2026-09']);
        $this->assertCount(30, $sep['revenue_by_day']);
        $this->assertSame(70000, collect($sep['revenue_by_day'])->sum('total'));

        // Tháng hiện tại là mặc định, chỉ chạy tới hôm nay.
        $now = $this->statsProps();
        $this->assertSame('2026-10', $now['revenue_month']);
        $this->assertCount(10, $now['revenue_by_day']);
    }

    /**
     * @test
     *
     * bopcamping-wlee — chỉ tổng hợp từ 08/2026 trở đi; tháng ngoài khoảng rơi về mặc định.
     */
    public function revenue_month_options_start_at_august_2026_and_clamp_bad_input(): void
    {
        $this->travelTo(Carbon::parse('2026-10-10 10:00'));

        $props = $this->statsProps();
        $this->assertSame(
            ['2026-10', '2026-09', '2026-08'],
            array_column($props['revenue_months'], 'value'),
        );
        $this->assertSame('Tháng 8/2026', $props['revenue_months'][2]['label']);

        // Trước mốc, sau mốc, và rác → đều về tháng hiện tại.
        foreach (['2026-07', '2027-01', 'linh-tinh', '2026-13'] as $bad) {
            $this->assertSame('2026-10', $this->statsProps(['month' => $bad])['revenue_month'], "month=$bad");
        }
    }

    /** Đặt mốc trả đơn (updated_at) — ghi thẳng DB để không bị timestamp tự ghi đè. */
    private function returnedOn(Order $order, \DateTimeInterface $at): void
    {
        \DB::table('orders')->where('id', $order->id)->update(['updated_at' => $at]);
    }

    /** @return array<string, mixed> */
    private function statsProps(array $query = []): array
    {
        return $this->actingAs($this->admin())->get(route('admin.stats', $query))
            ->original->getData()['page']['props'];
    }

    /** @test bopcamping-trc — badge đơn mới: shared prop pending_orders cho admin. */
    public function pending_orders_shared_prop_reflects_pending_count(): void
    {
        $this->order('pending', 100000);
        $this->order('pending', 100000);
        $this->order('returned', 100000);

        $this->actingAs($this->admin())->get(route('admin.stats'))->assertInertia(fn (Assert $p) => $p
            ->where('pending_orders', 2));
    }
}

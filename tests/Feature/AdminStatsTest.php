<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
     * bopcamping-wlee — bảng doanh thu theo ngày: gom đơn đã trả theo ngày trả (updated_at).
     */
    public function revenue_by_day_groups_returned_orders_by_return_date(): void
    {
        $a = $this->order('returned', 300000, 50000); // 250k — hôm nay
        $b = $this->order('returned', 120000);        // 120k — hôm nay
        $old = $this->order('returned', 90000);       // 90k  — 3 ngày trước
        $this->order('confirmed', 999000);            // chưa trả → không được lọt vào

        $this->returnedOn($old, now()->subDays(3));

        $this->actingAs($this->admin())->get(route('admin.stats'))->assertInertia(fn (Assert $p) => $p
            ->has('revenue_by_day', 2)
            // Ngày mới nhất đứng trước.
            ->where('revenue_by_day.0.date', now()->toDateString())
            ->where('revenue_by_day.0.total', 370000)
            ->has('revenue_by_day.0.orders', 2)
            ->where('revenue_by_day.1.date', now()->subDays(3)->toDateString())
            ->where('revenue_by_day.1.total', 90000)
            ->where('revenue_by_day.1.orders.0.code', $old->code)
            ->where('revenue_by_day.1.orders.0.amount', 90000)
            ->where('has_more_days', false));

        // Mã đơn của ngày hôm nay xuất hiện đủ (thứ tự trong ngày không ràng buộc).
        $codes = collect($this->statsProps()['revenue_by_day'][0]['orders'])->pluck('code')->all();
        $this->assertEqualsCanonicalizing([$a->code, $b->code], $codes);
    }

    /**
     * @test
     *
     * bopcamping-wlee — ràng buộc quan trọng nhất: tổng bảng phải KHỚP ô "Tổng thu",
     * nếu không màn hình sẽ có 2 con số đá nhau.
     */
    public function revenue_by_day_total_matches_finance_revenue(): void
    {
        $this->returnedOn($this->order('returned', 300000, 50000), now()->subDays(1));
        $this->returnedOn($this->order('returned', 120000), now()->subDays(1));
        $this->returnedOn($this->order('returned', 90000), now()->subDays(5));
        $this->order('pending', 400000);

        $props = $this->statsProps(['period' => 'all']);
        $sum = collect($props['revenue_by_day'])->sum('total');

        $this->assertSame($props['finance']['revenue'], $sum);
        $this->assertSame(460000, $sum);
    }

    /**
     * @test
     *
     * bopcamping-wlee — đơn trả ngoài kỳ đang chọn thì không hiện trong bảng.
     */
    public function revenue_by_day_respects_period_filter(): void
    {
        $this->returnedOn($this->order('returned', 500000), now()->subMonths(2));
        $this->returnedOn($this->order('returned', 70000), now()->startOfMonth()->addHours(2));

        $this->assertSame(70000, collect($this->statsProps(['period' => 'month'])['revenue_by_day'])->sum('total'));
        $this->assertSame(570000, collect($this->statsProps(['period' => 'all'])['revenue_by_day'])->sum('total'));
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

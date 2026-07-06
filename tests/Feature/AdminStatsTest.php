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

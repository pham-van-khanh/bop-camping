<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-tac — trang Admin Dashboard (số đơn + doanh thu + đơn mới).
 */
class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function guest_and_non_admin_cannot_access_dashboard(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));

        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user)->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    /** @test */
    public function admin_sees_stats_and_recent_orders(): void
    {
        $this->order('returned', 300000, 0);       // doanh thu 300k
        $this->order('returned', 200000, 50000);   // doanh thu 150k
        $this->order('pending', 120000, 0);
        $this->order('cancelled', 90000, 0);

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->where('stats.total', 4)
            ->where('stats.returned', 2)
            ->where('stats.pending', 1)
            ->where('stats.cancelled', 1)
            ->where('stats.revenue', 450000) // (300k-0)+(200k-50k)
            ->has('recent', 4)
        );
    }

    /** @test */
    public function admin_root_redirects_to_dashboard(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin')->assertRedirect(route('admin.dashboard'));
    }

    private function order(string $status, int $total, int $discount): Order
    {
        return Order::create([
            'customer_name' => 'Khách',
            'customer_phone' => '0900000001',
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-12',
            'total_price' => $total,
            'deposit_total' => 100000,
            'discount_total' => $discount,
            'status' => $status,
        ]);
    }
}

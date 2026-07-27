<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromotionSetting;
use App\Models\ServiceLocation;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * QA gap-fill (spec 2026-07-26) cho các thay đổi trên nhánh session-picker + order-detail
 * chưa được test trực tiếp: (1) trang Tài khoản khách phản ánh buổi + giờ; (2) admin sửa
 * giờ chia buổi trong Cài đặt shop; (3) buổi bám đúng ĐƠN CON cùng ngày khi tách cha/con;
 * (4) shared prop `site` expose khung giờ buổi (morning_end/afternoon_start) cho FE.
 */
class SessionOrderDetailQaTest extends TestCase
{
    use RefreshDatabase;

    private Product $chair;

    protected function setUp(): void
    {
        parent::setUp();
        PromotionSetting::current()->update(['email_bonus_enabled' => false, 'max_discount_percent_per_order' => 50]);
        $cat = Category::create(['name' => 'Ghế', 'slug' => 'ghe']);
        $this->chair = Product::create([
            'category_id' => $cat->id, 'name' => 'Ghế QA', 'slug' => 'ghe-qa',
            'price_per_day' => 100000, 'quantity' => 10, 'deposit' => 50000, 'early_return_discount_pct' => 10,
        ]);
        $loc = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1]);
        $this->chair->serviceLocations()->attach($loc->id, ['quantity' => 10]);
    }

    private function returnedOrderFor(User $user, ?string $session, string $start, string $end): Order
    {
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'BOP-'.strtoupper(uniqid()),
            'customer_name' => $user->name, 'customer_phone' => $user->phone,
            'start_date' => $start, 'end_date' => $end, 'status' => 'returned', 'payment_method' => 'cod',
            'total_price' => 90000, 'deposit_total' => 50000, 'session' => $session,
            'requested_pickup_time' => $session ? '13:00' : null,
            'requested_return_time' => $session ? '20:00' : null,
            'is_half_day' => $session === 'afternoon' || $session === 'morning',
        ]);
        $order->items()->create([
            'product_id' => $this->chair->id, 'quantity' => 1, 'price_per_day' => 100000, 'days' => 1,
            'start_date' => $start, 'end_date' => $end, 'subtotal' => 90000, 'duration_discount_percent' => 10,
        ]);

        return $order;
    }

    /** @test */
    public function account_page_reflects_session_and_times(): void
    {
        $user = User::factory()->create(['phone' => '0912999001']);
        $this->returnedOrderFor($user, 'afternoon', '2030-07-01', '2030-07-01');

        $this->actingAs($user)->get(route('account'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account')
                ->where('orders.0.session', 'afternoon')
                ->where('orders.0.requested_pickup_time', '13:00')
                ->where('orders.0.requested_return_time', '20:00')
                // shared prop site expose khung giờ buổi cho FE dựng nhãn buổi.
                ->where('site.morning_end_hour', 12)
                ->where('site.afternoon_start_hour', 13));
    }

    /** @test */
    public function account_multi_day_order_has_no_session(): void
    {
        $user = User::factory()->create(['phone' => '0912999002']);
        $this->returnedOrderFor($user, null, '2030-07-01', '2030-07-03');

        $this->actingAs($user)->get(route('account'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('orders.0.session', null)
                ->where('orders.0.requested_pickup_time', null));
    }

    /** @test */
    public function admin_updates_session_window_hours(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->put(route('admin.settings.update'), ['morning_end_hour' => 11, 'afternoon_start_hour' => 14])
            ->assertRedirect();

        $this->assertSame(11, (int) SiteSetting::current()->morning_end_hour);
        $this->assertSame(14, (int) SiteSetting::current()->afternoon_start_hour);
    }

    /** @test */
    public function admin_rejects_out_of_range_window_hour(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->put(route('admin.settings.update'), ['morning_end_hour' => 25])
            ->assertSessionHasErrors('morning_end_hour');

        $this->assertSame(12, (int) SiteSetting::current()->morning_end_hour); // giữ mặc định
    }

    /** @test */
    public function admin_rejects_inverted_session_window(): void
    {
        // đầu chiều (5) < cuối sáng (mặc định 12) → sai thứ tự (feedback 2026-07-27).
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->put(route('admin.settings.update'), ['afternoon_start_hour' => 5])
            ->assertSessionHasErrors('afternoon_start_hour');

        $this->assertSame(13, (int) SiteSetting::current()->afternoon_start_hour); // giữ mặc định
    }

    /** @test */
    public function combo_only_same_day_order_has_no_session(): void
    {
        // Combo không mang buổi (không có ưu đãi trả sớm) → đơn combo-only cùng ngày = full/null.
        $combo = Combo::create(['name' => 'Combo QA', 'slug' => 'combo-qa-'.uniqid(), 'combo_price' => 80000, 'is_active' => true]);
        $combo->items()->create(['product_id' => $this->chair->id, 'quantity' => 1]);
        $user = User::factory()->create(['phone' => '0912999009']);

        $this->actingAs($user)->post(route('order.store'), [
            'name' => $user->name, 'phone' => $user->phone,
            'combos' => [['combo_id' => $combo->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-01']],
        ])->assertSessionHas('order_code');

        $order = Order::latest('id')->first();
        $this->assertNull($order->session);
        $this->assertFalse($order->is_half_day);
    }

    /** @test */
    public function session_binds_to_same_day_child_only_in_split_order(): void
    {
        $user = User::factory()->create(['phone' => '0912999003']);
        // Giỏ 2 khoảng: đợt cùng ngày (buổi sáng) + đợt nhiều ngày → cha + 2 con (bopcamping-wtuv).
        $this->actingAs($user)->post(route('order.store'), [
            'name' => $user->name, 'phone' => $user->phone,
            'items' => [
                ['product_id' => $this->chair->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-01', 'session' => 'morning'],
                ['product_id' => $this->chair->id, 'quantity' => 1, 'start' => '2030-07-05', 'end' => '2030-07-07', 'session' => 'morning'],
            ],
        ])->assertSessionHas('order_code');

        $parent = Order::where('is_parent', true)->firstOrFail();
        $this->assertNull($parent->session); // cha là envelope

        $children = $parent->children()->get();
        $sameDay = $children->first(fn ($c) => $c->start_date->equalTo($c->end_date));
        $multiDay = $children->first(fn ($c) => ! $c->start_date->equalTo($c->end_date));

        $this->assertSame('morning', $sameDay->session);
        $this->assertSame('08:00', $sameDay->requested_pickup_time);
        $this->assertSame('12:00', $sameDay->requested_return_time); // cuối buổi sáng (mặc định 12)
        $this->assertNull($multiDay->session); // nhiều ngày → server ép null dù client gửi buổi
        $this->assertNull($multiDay->requested_pickup_time);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * F3 (feedback 2026-07-27) — modal "Đặt lại": endpoint ngày bận theo store + store_options.
 */
class ReorderAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private Product $chair;

    private ServiceLocation $vinh;

    private ServiceLocation $hanoi;

    protected function setUp(): void
    {
        parent::setUp();
        $cat = Category::create(['name' => 'Ghế', 'slug' => 'ghe']);
        $this->chair = Product::create([
            'category_id' => $cat->id, 'name' => 'Ghế QA', 'slug' => 'ghe-qa',
            'price_per_day' => 100000, 'quantity' => 1, 'deposit' => 50000,
        ]);
        $this->vinh = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1]);
        $this->hanoi = ServiceLocation::create(['name' => 'Hà Nội', 'area' => 'Nội thành', 'status' => 'open', 'sort_order' => 2]);
        $this->chair->serviceLocations()->attach($this->vinh->id, ['quantity' => 1]);
        $this->chair->serviceLocations()->attach($this->hanoi->id, ['quantity' => 1]);
    }

    /** Đơn của khách $user (để relatedOrders nhận) chứa 1 ghế — dùng để đặt lại. */
    private function orderFor(User $user): Order
    {
        $o = Order::create([
            'user_id' => $user->id, 'code' => 'BOP-'.strtoupper(uniqid()),
            'customer_name' => $user->name, 'customer_phone' => $user->phone,
            'start_date' => '2030-06-01', 'end_date' => '2030-06-01', 'status' => 'returned', 'payment_method' => 'cod',
            'total_price' => 100000, 'deposit_total' => 50000, 'service_location_id' => $this->vinh->id,
        ]);
        $o->items()->create([
            'product_id' => $this->chair->id, 'quantity' => 1, 'price_per_day' => 100000, 'days' => 1,
            'start_date' => '2030-06-01', 'end_date' => '2030-06-01', 'subtotal' => 100000,
        ]);

        return $o;
    }

    /** Đơn ĐÃ XÁC NHẬN chiếm ghế ở Vinh ngày 2030-07-10 (làm ngày đó hết). */
    private function bookVinh(string $date): void
    {
        $b = Order::create([
            'code' => 'BOP-'.strtoupper(uniqid()), 'customer_name' => 'Y', 'customer_phone' => '0900000001',
            'start_date' => $date, 'end_date' => $date, 'status' => 'confirmed', 'payment_method' => 'cod',
            'service_location_id' => $this->vinh->id,
        ]);
        $b->items()->create([
            'product_id' => $this->chair->id, 'quantity' => 1, 'price_per_day' => 100000, 'days' => 1,
            'start_date' => $date, 'end_date' => $date, 'subtotal' => 100000,
        ]);
    }

    /** @test */
    public function reorder_availability_reflects_confirmed_booking_at_store(): void
    {
        $date = Carbon::today()->addDays(30)->toDateString(); // trong cửa sổ quét (today..+120)
        $user = User::factory()->create(['phone' => '0912777001']);
        $order = $this->orderFor($user);
        $this->bookVinh($date);

        $res = $this->actingAs($user)->getJson(route('account.reorder.availability', $order).'?location_id='.$this->vinh->id)
            ->assertOk()->json('unavailable');

        $this->assertContains($date, $res);        // Vinh hết ngày này
    }

    /** @test */
    public function reorder_availability_isolated_per_store(): void
    {
        $date = Carbon::today()->addDays(30)->toDateString();
        $user = User::factory()->create(['phone' => '0912777002']);
        $order = $this->orderFor($user);
        $this->bookVinh($date); // chỉ chiếm ở Vinh

        $hanoi = $this->actingAs($user)->getJson(route('account.reorder.availability', $order).'?location_id='.$this->hanoi->id)
            ->assertOk()->json('unavailable');

        $this->assertNotContains($date, $hanoi);   // Hà Nội không bị ảnh hưởng
    }

    /** @test */
    public function other_user_cannot_query_reorder_availability(): void
    {
        $owner = User::factory()->create(['phone' => '0912777003']);
        $order = $this->orderFor($owner);

        // Khách chưa đăng nhập → 401 (kiểm TRƯỚC, vì actingAs giữ trạng thái sang request sau).
        $this->getJson(route('account.reorder.availability', $order))->assertUnauthorized();

        // Đăng nhập nhưng không phải chủ đơn → 403.
        $stranger = User::factory()->create(['phone' => '0912777004']);
        $this->actingAs($stranger)->getJson(route('account.reorder.availability', $order))->assertForbidden();
    }

    /** @test */
    public function account_payload_exposes_store_options_and_early_return(): void
    {
        $this->chair->update(['early_return_discount_pct' => 15]);
        $user = User::factory()->create(['phone' => '0912777005']);
        $this->orderFor($user);

        $this->actingAs($user)->get(route('account'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('orders.0.reorder.store_options', fn ($opts) => collect($opts)->pluck('name')->contains('Vinh')
                    && collect($opts)->pluck('name')->contains('Hà Nội'))
                ->where('orders.0.reorder.products.0.early_return_pct', 15));
    }
}

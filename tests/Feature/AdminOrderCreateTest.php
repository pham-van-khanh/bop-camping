<?php

namespace Tests\Feature;

use App\Mail\NewOrderAdminMail;
use App\Mail\OrderPlacedMail;
use App\Mail\OrderStatusMail;
use App\Models\Category;
use App\Models\Combo;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Models\User;
use App\Services\AttributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Admin tự tạo đơn hộ khách (bopcamping-yv2w). Luật chủ shop chốt 10/11/2026:
 * ngày quá khứ được phép · hết hàng chặn cứng · giá hệ thống + giảm tay có lý do ·
 * tạo thẳng confirmed, mail khách nếu có email, không báo "đơn mới" cho admin.
 */
class AdminOrderCreateTest extends TestCase
{
    use RefreshDatabase;

    private ServiceLocation $vinh;

    private Product $tent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vinh = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1]);
        $this->tent = $this->product('Lều 4 người', 100000, [$this->vinh->id => 3]);
    }

    private function product(string $name, int $perDay, array $stockByLoc, int $earlyPct = 0): Product
    {
        $cat = Category::firstOrCreate(['slug' => 'leu'], ['name' => 'Lều']);
        $p = Product::create([
            'category_id' => $cat->id, 'name' => $name, 'slug' => 'sp-'.uniqid(),
            'price_per_day' => $perDay, 'quantity' => array_sum($stockByLoc), 'deposit' => 200000,
            'early_return_discount_pct' => $earlyPct,
        ]);
        $p->serviceLocations()->sync(collect($stockByLoc)->mapWithKeys(fn ($q, $id) => [$id => ['quantity' => $q]])->all());

        return $p;
    }

    private function admin(array $attrs = []): User
    {
        return User::factory()->create(['is_admin' => true] + $attrs);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => 'Trần Khách',
            'phone' => '0912345678',
            'email' => 'khach@example.com',
            'delivery_method' => 'self_pickup',
            'service_location_id' => $this->vinh->id,
            'start' => '2030-07-01',
            'end' => '2030-07-03',
            'items' => [['product_id' => $this->tent->id, 'quantity' => 2]],
        ], $override);
    }

    /** @test */
    public function admin_sees_create_page_with_catalog(): void
    {
        $this->actingAs($this->admin())->get(route('admin.orders.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Orders/Create')
                ->has('products', 1)
                ->where('products.0.name', 'Lều 4 người')
                ->where('products.0.location_ids', [$this->vinh->id])
                ->has('service_locations', 1)
                ->has('delivery_methods', 2));
    }

    /** @test */
    public function non_admin_cannot_create_orders(): void
    {
        $this->post(route('admin.orders.store'), $this->payload())->assertRedirect();
        $this->actingAs(User::factory()->create())->post(route('admin.orders.store'), $this->payload());

        $this->assertSame(0, Order::count());
    }

    /** @test */
    public function admin_creates_confirmed_order_priced_by_system_and_mails_customer_only(): void
    {
        Mail::fake();
        $admin = $this->admin(['email' => 'admin@example.com']);

        $res = $this->actingAs($admin)->post(route('admin.orders.store'), $this->payload());

        $order = Order::sole();
        $res->assertRedirect(route('admin.orders.show', $order));
        $this->assertSame('confirmed', $order->status);
        $this->assertSame($admin->id, $order->created_by);
        $this->assertSame($this->vinh->id, $order->service_location_id);
        $this->assertFalse((bool) $order->location_auto_assigned);
        // 100k × 2 cái × 3 ngày; cọc 200k × 2
        $this->assertSame(600000, (int) $order->total_price);
        $this->assertSame(400000, (int) $order->deposit_total);
        $this->assertSame(3, $order->items()->sole()->days);

        Mail::assertQueued(OrderPlacedMail::class, fn (OrderPlacedMail $m) => $m->hasTo('khach@example.com'));
        Mail::assertNotQueued(NewOrderAdminMail::class);
        // Tạo thẳng confirmed → không bắn thêm mail "đã xác nhận" từ OrderObserver.
        Mail::assertNotQueued(OrderStatusMail::class);
    }

    /** @test */
    public function confirmed_order_mail_does_not_say_awaiting_confirmation(): void
    {
        $this->actingAs($this->admin())->post(route('admin.orders.store'), $this->payload());

        $html = (new OrderPlacedMail(Order::sole()))->render();
        $this->assertStringContainsString('Đã xác nhận', $html);
        $this->assertStringNotContainsString('Chờ xác nhận', $html);
    }

    /** @test */
    public function no_email_means_no_mail(): void
    {
        Mail::fake();
        $this->actingAs($this->admin())->post(route('admin.orders.store'), $this->payload(['email' => null]));

        $this->assertSame(1, Order::count());
        Mail::assertNothingOutgoing();
    }

    /** @test */
    public function admin_may_record_past_dates(): void
    {
        $this->actingAs($this->admin())->post(route('admin.orders.store'), $this->payload([
            'start' => now()->subDays(5)->toDateString(),
            'end' => now()->subDays(3)->toDateString(),
        ]))->assertSessionHasNoErrors();

        $this->assertSame(now()->subDays(5)->toDateString(), Order::sole()->start_date->toDateString());
    }

    /** @test */
    public function out_of_stock_is_blocked_and_nothing_is_saved(): void
    {
        $this->actingAs($this->admin())->post(route('admin.orders.store'), $this->payload([
            'items' => [['product_id' => $this->tent->id, 'quantity' => 4]],
        ]))->assertSessionHasErrors('items');

        $this->assertSame(0, Order::count());
    }

    /** @test */
    public function overlapping_booking_counts_against_stock(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.orders.store'), $this->payload())->assertSessionHasNoErrors();

        // Còn 1 cái trong khoảng chồng lịch → đặt thêm 2 phải bị chặn.
        $this->actingAs($admin)->post(route('admin.orders.store'), $this->payload([
            'phone' => '0987654321', 'start' => '2030-07-03', 'end' => '2030-07-05',
        ]))->assertSessionHasErrors('items');

        $this->assertSame(1, Order::count());
    }

    /** @test */
    public function manual_discount_is_recorded_with_reason(): void
    {
        $this->actingAs($this->admin())->post(route('admin.orders.store'), $this->payload([
            'discount' => 50000, 'discount_note' => 'Khách quen',
        ]))->assertSessionHasNoErrors();

        $order = Order::sole();
        $this->assertSame(50000, (int) $order->discount_total);
        // assertEquals: MySQL JSON không giữ thứ tự key.
        $this->assertEquals([['source' => 'admin', 'amount' => 50000, 'percent' => false, 'note' => 'Khách quen']], $order->discount_breakdown);
    }

    /** @test */
    public function discount_requires_reason(): void
    {
        $this->actingAs($this->admin())->post(route('admin.orders.store'), $this->payload(['discount' => 50000]))
            ->assertSessionHasErrors('discount_note');

        $this->assertSame(0, Order::count());
    }

    /** @test */
    public function discount_above_rent_is_rejected_and_rolled_back(): void
    {
        $this->actingAs($this->admin())->post(route('admin.orders.store'), $this->payload([
            'discount' => 700000, 'discount_note' => 'Nhầm',
        ]))->assertSessionHasErrors('items');

        $this->assertSame(0, Order::count());
        $this->assertDatabaseCount('order_items', 0);
    }

    /** @test */
    public function links_customer_account_by_phone_but_never_an_admin_account(): void
    {
        $customer = User::factory()->create(['phone' => '0912345678']);
        $admin = $this->admin(['phone' => '0987654321']);

        $this->actingAs($admin)->post(route('admin.orders.store'), $this->payload());
        $this->actingAs($admin)->post(route('admin.orders.store'), $this->payload([
            'phone' => '0987654321', 'start' => '2030-08-01', 'end' => '2030-08-01',
        ]));

        $this->assertSame($customer->id, Order::where('customer_phone', '0912345678')->sole()->user_id);
        $this->assertNull(Order::where('customer_phone', '0987654321')->sole()->user_id);
    }

    /** @test */
    public function single_day_session_applies_half_day_discount(): void
    {
        $chair = $this->product('Ghế', 100000, [$this->vinh->id => 5], earlyPct: 30);

        $this->actingAs($this->admin())->post(route('admin.orders.store'), $this->payload([
            'start' => '2030-07-01', 'end' => '2030-07-01', 'session' => 'morning',
            'items' => [['product_id' => $chair->id, 'quantity' => 1]],
        ]))->assertSessionHasNoErrors();

        $order = Order::sole();
        $this->assertSame('morning', $order->session);
        $this->assertTrue((bool) $order->is_half_day);
        $this->assertSame(70000, (int) $order->total_price);
    }

    /** @test */
    public function creates_combo_order(): void
    {
        $bag = $this->product('Túi ngủ', 30000, [$this->vinh->id => 5]);
        $combo = Combo::create(['name' => 'Combo 2 người', 'slug' => 'combo-2', 'combo_price' => 120000, 'deposit' => 300000, 'is_active' => true]);
        $combo->items()->create(['product_id' => $this->tent->id, 'quantity' => 1]);
        $combo->items()->create(['product_id' => $bag->id, 'quantity' => 2]);
        $combo->serviceLocations()->sync([$this->vinh->id]);

        $this->actingAs($this->admin())->post(route('admin.orders.store'), $this->payload([
            'items' => [], 'combos' => [['combo_id' => $combo->id, 'quantity' => 1]],
        ]))->assertSessionHasNoErrors();

        $order = Order::sole();
        $this->assertSame(2, $order->items()->where('combo_id', $combo->id)->count());
        $this->assertSame(300000, (int) $order->deposit_total);
    }

    /** @test */
    public function empty_order_is_rejected(): void
    {
        $this->actingAs($this->admin())->post(route('admin.orders.store'), $this->payload(['items' => []]))
            ->assertSessionHasErrors('items');
    }

    /** @test */
    public function customer_lookup_prefills_from_account_and_last_order(): void
    {
        $admin = $this->admin();
        User::factory()->create(['phone' => '0912345678', 'name' => 'Tên Tài Khoản', 'email' => 'tk@example.com']);
        $this->actingAs($admin)->post(route('admin.orders.store'), $this->payload(['address' => '12 Lê Lợi']));

        $this->actingAs($admin)->getJson(route('admin.orders.create.customer', ['phone' => '0912345678']))
            ->assertOk()
            ->assertJson(['found' => true, 'has_account' => true, 'name' => 'Tên Tài Khoản', 'email' => 'tk@example.com', 'address' => '12 Lê Lợi']);

        $this->actingAs($admin)->getJson(route('admin.orders.create.customer', ['phone' => '0900000001']))
            ->assertOk()->assertJson(['found' => false]);
    }

    /** @test */
    public function availability_endpoint_reports_remaining_stock(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.orders.store'), $this->payload());

        $this->actingAs($admin)->getJson(route('admin.orders.create.availability', [
            'start' => '2030-07-02', 'end' => '2030-07-02', 'location_id' => $this->vinh->id,
        ]))->assertOk()->assertJsonPath("products.{$this->tent->id}", 1);
    }

    /** @test */
    public function detail_page_shows_who_created_the_order(): void
    {
        $admin = $this->admin(['name' => 'Chủ Shop']);
        $this->actingAs($admin)->post(route('admin.orders.store'), $this->payload());

        $this->actingAs($admin)->get(route('admin.orders.show', Order::sole()))
            ->assertInertia(fn (Assert $page) => $page
                ->where('order.attribution.created_by', 'Chủ Shop')
                ->where('order.attribution.label', AttributionService::ADMIN_LABEL));
    }
}

<?php

namespace Tests\Feature;

use App\Mail\OrderDatesChangedMail;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * bopcamping-5hjm — admin đổi lịch đơn: guard trạng thái, kiểm tồn kho khoảng mới
 * (không tự chặn mình), tính lại tiền tuyến tính, re-arm nhắc lịch, mail báo khách.
 */
class AdminOrderRescheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ServiceLocation $vinh;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->vinh = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1]);

        $cat = Category::firstOrCreate(['slug' => 'leu'], ['name' => 'Lều']);
        $this->product = Product::create([
            'category_id' => $cat->id, 'name' => 'Lều 2P', 'slug' => 'leu-2p',
            'price_per_day' => 50000, 'quantity' => 2, 'deposit' => 100000,
        ]);
        $this->product->serviceLocations()->sync([$this->vinh->id => ['quantity' => 2]]);
    }

    /** Đơn 2 ngày (01→02/07/2030) × 2 lều tại Vinh; subtotal 200k, cọc 100k, giảm 30k. */
    private function makeOrder(array $attrs = []): Order
    {
        $order = Order::factory()->create(array_merge([
            'status' => 'confirmed',
            'customer_email' => 'khach@example.com',
            'service_location_id' => $this->vinh->id,
            'start_date' => '2030-07-01',
            'end_date' => '2030-07-02',
            'total_price' => 200000,
            'deposit_total' => 100000,
            'discount_total' => 30000,
        ], $attrs));

        $order->items()->create([
            'product_id' => $this->product->id, 'quantity' => 2,
            'price_per_day' => 50000, 'days' => 2, 'subtotal' => 200000,
        ]);

        return $order;
    }

    private function patchDates(Order $order, string $start, string $end)
    {
        return $this->actingAs($this->admin)
            ->patch(route('admin.orders.dates', $order), ['start_date' => $start, 'end_date' => $end]);
    }

    /** @test */
    public function changes_dates_recalculates_price_and_rearms_reminder(): void
    {
        Mail::fake();
        $order = $this->makeOrder(['pickup_reminder_sent_at' => now()]);

        $this->patchDates($order, '2030-07-10', '2030-07-12')
            ->assertRedirect()->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('2030-07-10', $order->start_date->format('Y-m-d'));
        $this->assertSame('2030-07-12', $order->end_date->format('Y-m-d'));
        // 2 ngày → 3 ngày: subtotal 200k → 300k, total theo; cọc + giảm giá giữ nguyên
        $this->assertSame(3, $order->items()->first()->days);
        $this->assertSame(300000, $order->items()->first()->subtotal);
        $this->assertSame(300000, $order->total_price);
        $this->assertSame(100000, $order->deposit_total);
        $this->assertSame(30000, $order->discount_total);
        // Ngày nhận đổi → nhắc lịch gửi lại cho ngày mới
        $this->assertNull($order->pickup_reminder_sent_at);
    }

    /** @test */
    public function keeps_reminder_stamp_when_only_end_date_changes(): void
    {
        Mail::fake();
        $order = $this->makeOrder(['pickup_reminder_sent_at' => now()]);

        $this->patchDates($order, '2030-07-01', '2030-07-05')->assertSessionHas('success');

        $this->assertNotNull($order->fresh()->pickup_reminder_sent_at);
    }

    /** @test */
    public function scales_percent_discount_but_keeps_fixed_discount_when_rescheduling(): void
    {
        Mail::fake();
        // Đơn 2 ngày, subtotal 200k. Giảm: voucher % (10% = 20k) + voucher tiền cố định 50k.
        $order = $this->makeOrder(['discount_total' => 70000]);
        $order->update(['discount_breakdown' => [
            ['source' => 'voucher', 'code' => 'PCT10', 'amount' => 20000, 'percent' => true],
            ['source' => 'voucher', 'code' => 'FIX50', 'amount' => 50000, 'percent' => false],
        ]]);

        // 2 ngày → 3 ngày: subtotal 200k → 300k.
        $this->patchDates($order, '2030-07-10', '2030-07-12')->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(300000, $order->total_price);
        // % giảm theo ngày mới (10% của 300k = 30k); tiền cố định giữ nguyên 50k.
        $this->assertSame(80000, $order->discount_total);
        $this->assertSame(30000, $order->discount_breakdown[0]['amount']);
        $this->assertSame(50000, $order->discount_breakdown[1]['amount']);
        // Bất biến: sum(breakdown.amount) === discount_total
        $this->assertSame(80000, array_sum(array_column($order->discount_breakdown, 'amount')));
    }

    /** @test */
    public function keeps_discount_frozen_for_legacy_orders_without_breakdown(): void
    {
        Mail::fake();
        // Đơn cũ (trước bopcamping-3ag): có discount_total nhưng breakdown null → giữ nguyên.
        $order = $this->makeOrder(['discount_total' => 30000, 'discount_breakdown' => null]);

        $this->patchDates($order, '2030-07-10', '2030-07-12')->assertSessionHas('success');

        $this->assertSame(30000, $order->fresh()->discount_total);
    }

    /** @test */
    public function rejects_orders_already_renting_or_finished(): void
    {
        foreach (['renting', 'returned', 'cancelled'] as $status) {
            $order = $this->makeOrder(['status' => $status]);
            $this->patchDates($order, '2030-07-10', '2030-07-12')->assertSessionHasErrors('dates');
            $this->assertSame('2030-07-01', $order->fresh()->start_date->format('Y-m-d'));
        }
    }

    /** @test */
    public function rejects_when_other_order_makes_new_range_unavailable(): void
    {
        $order = $this->makeOrder();
        // Đơn khác chiếm cả 2 lều ở 10–12/08 tại Vinh
        $this->makeOrder(['start_date' => '2030-08-10', 'end_date' => '2030-08-12']);

        $this->patchDates($order, '2030-08-11', '2030-08-13')->assertSessionHasErrors('dates');
        $this->assertSame('2030-07-01', $order->fresh()->start_date->format('Y-m-d'));
    }

    /** @test */
    public function allows_shifting_within_own_range_without_self_blocking(): void
    {
        // Đơn chiếm TOÀN BỘ tồn (2/2) — dịch 1 ngày chồng khoảng cũ vẫn phải OK.
        $order = $this->makeOrder();

        $this->patchDates($order, '2030-07-02', '2030-07-03')->assertSessionHas('success');
        $this->assertSame('2030-07-02', $order->fresh()->start_date->format('Y-m-d'));
    }

    /** @test */
    public function rejects_start_date_in_the_past(): void
    {
        $order = $this->makeOrder();

        $this->patchDates($order, now()->subDay()->format('Y-m-d'), '2030-07-12')
            ->assertSessionHasErrors('start_date');
    }

    /** @test */
    public function queues_mail_for_customer_with_email_but_not_placeholder_email(): void
    {
        Mail::fake();

        $withEmail = $this->makeOrder();
        $this->patchDates($withEmail, '2030-07-10', '2030-07-12')->assertSessionHas('success');
        Mail::assertQueued(OrderDatesChangedMail::class, 1);

        $placeholder = $this->makeOrder(['customer_email' => '0912345678@bopcamping.local', 'start_date' => '2030-09-01', 'end_date' => '2030-09-02']);
        $this->patchDates($placeholder, '2030-09-10', '2030-09-11')->assertSessionHas('success');
        Mail::assertQueued(OrderDatesChangedMail::class, 1); // vẫn 1 — không gửi cho email tạm
    }

    /** @test */
    public function non_admin_cannot_change_dates(): void
    {
        $order = $this->makeOrder();

        $this->patch(route('admin.orders.dates', $order), ['start_date' => '2030-07-10', 'end_date' => '2030-07-12'])
            ->assertRedirect(route('admin.login'));
    }
}

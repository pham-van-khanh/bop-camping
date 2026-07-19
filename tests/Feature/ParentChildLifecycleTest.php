<?php

namespace Tests\Feature;

use App\Mail\OrderPickupReminderMail;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * bopcamping-wtuv (T7) — vòng đời cấp CON: nhắc lịch per-con (loại cha), guard cha
 * khỏi thao tác đơn lẻ (status/payment/refund/đổi lịch), đổi lịch con → cha tính lại,
 * đổi cơ sở đơn gộp → cascade cả cụm.
 */
class ParentChildLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $p;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true]);
        $cat = Category::create(['name' => 'Lều', 'slug' => 'leu']);
        $this->p = Product::create(['category_id' => $cat->id, 'name' => 'A', 'slug' => 'a', 'price_per_day' => 100000, 'quantity' => 5, 'deposit' => 100000]);
    }

    /** Cha + 2 con qua checkout thật (2 khoảng: ngày mai và +9→+10 ngày). */
    private function makeFamily(): Order
    {
        $this->post(route('order.store'), [
            'name' => 'Khách Gộp', 'phone' => '0911777001',
            'email' => 'gop@example.com',
            'items' => [
                ['product_id' => $this->p->id, 'quantity' => 1, 'start' => now()->addDay()->toDateString(), 'end' => now()->addDays(2)->toDateString()],
                ['product_id' => $this->p->id, 'quantity' => 1, 'start' => now()->addDays(9)->toDateString(), 'end' => now()->addDays(10)->toDateString()],
            ],
        ])->assertSessionHas('order_code');

        return Order::where('is_parent', true)->latest('id')->firstOrFail();
    }

    /** @test */
    public function pickup_reminder_targets_children_not_parent(): void
    {
        Mail::fake();
        $parent = $this->makeFamily();
        // Cả cụm confirmed; con 1 có ngày nhận = ngày mai.
        $parent->children()->update(['status' => 'confirmed']);
        $parent->update(['status' => 'confirmed']); // cha lỡ confirmed cũng KHÔNG được nhắc

        $this->artisan('orders:send-pickup-reminders')->assertSuccessful();

        // Chỉ 1 mail (con ngày mai) — không mail cho cha, không mail cho con đợt sau.
        Mail::assertQueued(OrderPickupReminderMail::class, 1);
        $c1 = $parent->children()->orderBy('start_date')->first();
        $this->assertNotNull($c1->fresh()->pickup_reminder_sent_at);
        $this->assertNull($parent->fresh()->pickup_reminder_sent_at);
    }

    /** @test */
    public function parent_rejects_per_order_actions_but_allows_cancel_all(): void
    {
        $parent = $this->makeFamily();
        $this->actingAs($this->admin);

        // Không cho đổi trạng thái đơn lẻ trên cha…
        $this->patch(route('admin.orders.update', $parent), ['status' => 'confirmed'])
            ->assertSessionHasErrors('status');
        // …không payment/refund/đổi lịch trên cha.
        $this->patch(route('admin.orders.payment', $parent), ['payment_status' => 'deposit'])
            ->assertSessionHasErrors('payment_status');
        $this->patch(route('admin.orders.refund', $parent), ['deposit_refund_status' => 'refunded'])
            ->assertSessionHasErrors('deposit_refund_status');
        $this->patch(route('admin.orders.dates', $parent), [
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
        ])->assertSessionHasErrors('dates');

        // Nhưng HUỶ CẢ CỤM thì được → mọi con cancelled.
        $this->patch(route('admin.orders.update', $parent), ['status' => 'cancelled'])
            ->assertSessionHas('success');
        $this->assertSame(0, $parent->children()->where('status', '!=', 'cancelled')->count());
    }

    /** @test */
    public function rescheduling_a_child_updates_parent_totals_and_envelope(): void
    {
        Mail::fake();
        $parent = $this->makeFamily();
        // total cha ban đầu: con1 2 ngày 200k + con2 2 ngày 200k = 400k.
        $this->assertSame(400000, (int) $parent->total_price);
        $c2 = $parent->children()->orderBy('start_date')->get()[1];

        // Đổi lịch con 2: sang +20→+22 (3 ngày = 300k).
        $this->actingAs($this->admin)->patch(route('admin.orders.dates', $c2), [
            'start_date' => now()->addDays(20)->toDateString(),
            'end_date' => now()->addDays(22)->toDateString(),
        ])->assertSessionHas('success');

        $parent->refresh();
        // Tổng cha = 200k + 300k; envelope end bám theo con 2 mới.
        $this->assertSame(500000, (int) $parent->total_price);
        $this->assertSame(now()->addDays(22)->toDateString(), $parent->end_date->format('Y-m-d'));
    }

    /** @test */
    public function changing_location_on_parent_cascades_to_children(): void
    {
        $vinh = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1]);
        $hn = ServiceLocation::create(['name' => 'Hà Nội', 'area' => 'HN', 'status' => 'open', 'sort_order' => 2]);
        $this->p->serviceLocations()->sync([$vinh->id => ['quantity' => 5], $hn->id => ['quantity' => 5]]);

        $parent = $this->makeFamily();
        $parent->update(['service_location_id' => $vinh->id]);
        $parent->children()->update(['service_location_id' => $vinh->id]);

        $this->actingAs($this->admin)->patch(route('admin.orders.location', $parent), ['service_location_id' => $hn->id])
            ->assertSessionHas('success');

        $this->assertSame($hn->id, $parent->fresh()->service_location_id);
        $this->assertSame(2, $parent->children()->where('service_location_id', $hn->id)->count());
    }
}

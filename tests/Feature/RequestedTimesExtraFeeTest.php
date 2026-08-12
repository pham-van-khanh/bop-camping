<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromotionSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-h4to (Phase 2 turnaround) — giao/trả ngoài khung giờ: khách ghi giờ nhận/trả
 * mong muốn ở checkout; admin nhập PHỤ PHÍ tay (cộng vào amount_due). KHÔNG đụng tồn kho.
 */
class RequestedTimesExtraFeeTest extends TestCase
{
    use RefreshDatabase;

    private Product $chair;

    protected function setUp(): void
    {
        parent::setUp();
        PromotionSetting::current()->update(['email_bonus_enabled' => false, 'max_discount_percent_per_order' => 50]);
        $cat = Category::create(['name' => 'Ghế', 'slug' => 'ghe']);
        $this->chair = Product::create([
            'category_id' => $cat->id, 'name' => 'Ghế', 'slug' => 'ghe-test',
            'price_per_day' => 100000, 'quantity' => 5, 'deposit' => 50000,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function order(int $extraFee = 0, bool $parent = false): Order
    {
        return Order::create([
            'code' => 'BOP-'.strtoupper(uniqid()), 'customer_name' => 'X', 'customer_phone' => '0900000000',
            'start_date' => '2030-07-01', 'end_date' => '2030-07-02', 'status' => 'pending', 'payment_method' => 'cod',
            'total_price' => 200000, 'deposit_total' => 50000, 'extra_fee' => $extraFee, 'is_parent' => $parent,
        ]);
    }

    /** @test */
    public function checkout_stores_session_and_derived_times(): void
    {
        $user = User::factory()->create(['phone' => '0911222001']);
        $this->actingAs($user)->post(route('order.store'), [
            'name' => $user->name, 'phone' => $user->phone,
            // Buổi khách chọn khi thuê 1 ngày — gửi ở cấp DÒNG; server suy giờ (spec 2026-07-26).
            'items' => [['product_id' => $this->chair->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-01', 'session' => 'afternoon']],
        ])->assertSessionHas('order_code');

        $order = Order::latest('id')->first();
        $this->assertSame('afternoon', $order->session);
        $this->assertSame('13:00', $order->requested_pickup_time); // đầu buổi chiều (setting 8/12/13/20)
        $this->assertSame('20:00', $order->requested_return_time);
        $this->assertSame(0, (int) $order->extra_fee); // checkout không đặt phụ phí
    }

    /** @test */
    public function checkout_rejects_invalid_session(): void
    {
        $user = User::factory()->create(['phone' => '0911222002']);
        $this->actingAs($user)->post(route('order.store'), [
            'name' => $user->name, 'phone' => $user->phone,
            'items' => [['product_id' => $this->chair->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-01', 'session' => 'evening']],
        ])->assertSessionHasErrors('items.0.session');
    }

    /** @test */
    public function amount_due_includes_extra_fee(): void
    {
        $order = $this->order(extraFee: 30000);
        // 200k thuê + 50k cọc + 30k phụ phí − 0 giảm = 280k
        $this->assertSame(280000, $order->amount_due);
    }

    /** @test */
    public function admin_sets_extra_fee_and_note(): void
    {
        $order = $this->order();
        $this->actingAs($this->admin())->patch(route('admin.orders.fee', $order), [
            'fees' => [['name' => 'Giao sớm 6h', 'value' => 40000]],
        ])->assertRedirect()->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(40000, (int) $order->extra_fee);
        $this->assertSame('Giao sớm 6h', $order->extra_fee_note);
        $this->assertSame(290000, $order->amount_due); // 200k + 50k + 40k
    }

    /**
     * Nhiều khoản phụ phí trên một đơn (bopcamping-f1yj) — đúng thứ trước đây không làm
     * được: đơn vừa giao tận nơi vừa trả muộn phải cộng gộp thành một số.
     *
     * @test
     */
    public function admin_can_save_several_extra_fees_and_the_total_follows(): void
    {
        $order = $this->order();
        $this->actingAs($this->admin())->patch(route('admin.orders.fee', $order), [
            'fees' => [
                ['name' => 'Phí giao tận nơi', 'value' => 50000],
                ['name' => 'Trả muộn 22h', 'value' => 30000],
            ],
        ])->assertRedirect()->assertSessionHas('success');

        $order->refresh();
        $this->assertCount(2, $order->extraFeeLines());
        // Cột tổng phải bám danh sách, nếu lệch thì rental_due/amount_due sai theo.
        $this->assertSame(80000, (int) $order->extra_fee);
        $this->assertSame(330000, $order->amount_due); // 200k + 50k cọc + 80k phụ phí
        $this->assertSame('Phí giao tận nơi', $order->extraFeeLines()[0]['name']);
        $this->assertSame('Trả muộn 22h', $order->extraFeeLines()[1]['name']);
    }

    /**
     * Dòng 0đ / dòng trống bị loại — admin hay bấm "+" rồi bỏ đó.
     *
     * @test
     */
    public function blank_and_zero_fee_rows_are_dropped(): void
    {
        $order = $this->order();
        $this->actingAs($this->admin())->patch(route('admin.orders.fee', $order), [
            'fees' => [
                ['name' => 'Phí giao', 'value' => 20000],
                ['name' => 'Chưa dùng', 'value' => 0],
            ],
        ])->assertRedirect();

        $order->refresh();
        $this->assertCount(1, $order->extraFeeLines());
        $this->assertSame(20000, (int) $order->extra_fee);
    }

    /**
     * Gửi danh sách rỗng = gỡ sạch phụ phí.
     *
     * @test
     */
    public function empty_list_clears_every_fee(): void
    {
        $order = $this->order(extraFee: 40000);
        $this->actingAs($this->admin())->patch(route('admin.orders.fee', $order), ['fees' => []])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame([], $order->extraFeeLines());
        $this->assertSame(0, (int) $order->extra_fee);
    }

    /**
     * Đơn CŨ chỉ có cặp cột (extra_fee, extra_fee_note) vẫn phải đọc ra được — mail và
     * admin không được if/else theo đời dữ liệu.
     *
     * @test
     */
    public function legacy_single_fee_rows_still_read_as_a_list(): void
    {
        $order = $this->order(extraFee: 25000);
        $order->forceFill(['extra_fee_note' => 'Giao sớm', 'extra_fees' => null])->save();

        $lines = $order->fresh()->extraFeeLines();
        $this->assertSame([['name' => 'Giao sớm', 'value' => 25000]], $lines);
    }

    /** @test */
    public function admin_extra_fee_rejects_negative(): void
    {
        $order = $this->order();
        $this->actingAs($this->admin())->patch(route('admin.orders.fee', $order), [
            'fees' => [['name' => 'Âm', 'value' => -5]],
        ])->assertSessionHasErrors('fees.0.value');
    }

    /** @test */
    public function fee_row_without_a_name_is_rejected(): void
    {
        $order = $this->order();
        $this->actingAs($this->admin())->patch(route('admin.orders.fee', $order), [
            'fees' => [['name' => '', 'value' => 10000]],
        ])->assertSessionHasErrors('fees.0.name');
    }

    /** @test */
    public function extra_fee_blocked_on_parent_order(): void
    {
        $parent = $this->order(parent: true);
        $this->actingAs($this->admin())->patch(route('admin.orders.fee', $parent), [
            'fees' => [['name' => 'X', 'value' => 10000]],
        ])->assertSessionHasErrors('fees');
        $this->assertSame(0, (int) $parent->fresh()->extra_fee);
    }

    /** @test */
    public function non_admin_cannot_set_extra_fee(): void
    {
        $order = $this->order();
        $guest = User::factory()->create(['is_admin' => false]);
        $this->actingAs($guest)->patch(route('admin.orders.fee', $order), ['extra_fee' => 10000])
            ->assertRedirect(route('admin.login'));
        $this->assertSame(0, (int) $order->fresh()->extra_fee);
    }
}

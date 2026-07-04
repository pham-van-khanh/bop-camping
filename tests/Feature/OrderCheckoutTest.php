<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-z7w — luồng đặt thuê COD + kiểm trùng lịch & tồn kho.
 * Dùng ngày 2030 để luôn thoả after_or_equal:today bất kể đồng hồ máy.
 */
class OrderCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function product(int $qty = 3, int $price = 100000, int $deposit = 200000): Product
    {
        $cat = Category::create(['name' => 'Lều', 'slug' => 'leu']);

        return Product::create([
            'category_id' => $cat->id,
            'name' => 'Lều',
            'slug' => 'leu-'.uniqid(),
            'price_per_day' => $price,
            'quantity' => $qty,
            'deposit' => $deposit,
        ]);
    }

    private function bookedOrder(Product $p, string $start, string $end, int $qty): Order
    {
        $order = Order::create([
            'code' => 'BOP-'.strtoupper(uniqid()),
            'customer_name' => 'X',
            'customer_phone' => '0900000000',
            'start_date' => $start,
            'end_date' => $end,
            'status' => 'confirmed',
            'payment_method' => 'cod',
        ]);
        $order->items()->create([
            'product_id' => $p->id,
            'quantity' => $qty,
            'price_per_day' => $p->price_per_day,
            'days' => 1,
            'subtotal' => $qty * $p->price_per_day,
        ]);

        return $order;
    }

    /** @test */
    public function creates_cod_order_with_items_and_totals(): void
    {
        $p = $this->product();

        $this->post(route('order.store'), [
            'name' => 'Khách A',
            'phone' => '0912345678',
            'items' => [['product_id' => $p->id, 'quantity' => 2, 'start' => '2030-07-01', 'end' => '2030-07-03']],
        ])->assertSessionHas('order_code');

        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertSame('pending', $order->status);
        $this->assertSame('cod', $order->payment_method);
        // 2 bộ × 3 ngày × 100000 = 600000 ; cọc 2 × 200000 = 400000
        $this->assertSame(600000, $order->total_price);
        $this->assertSame(400000, $order->deposit_total);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $p->id,
            'quantity' => 2,
            'days' => 3,
        ]);
    }

    /** @test */
    public function rejects_order_exceeding_available_stock_on_overlap(): void
    {
        $p = $this->product(qty: 3);
        $this->bookedOrder($p, '2030-07-02', '2030-07-05', qty: 2); // còn 1 trong khoảng chồng lịch

        $this->post(route('order.store'), [
            'name' => 'Khách',
            'phone' => '0912345678',
            'items' => [['product_id' => $p->id, 'quantity' => 2, 'start' => '2030-07-01', 'end' => '2030-07-03']],
        ])->assertSessionHasErrors('items');

        $this->assertSame(1, Order::count()); // chỉ còn đơn cũ, không tạo đơn mới
    }

    /** @test */
    public function allows_order_when_dates_do_not_overlap(): void
    {
        $p = $this->product(qty: 1);
        $this->bookedOrder($p, '2030-06-20', '2030-06-25', qty: 1); // trả trước, không chồng

        $this->post(route('order.store'), [
            'name' => 'Khách',
            'phone' => '0912345678',
            'items' => [['product_id' => $p->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-03']],
        ])->assertSessionHas('order_code');

        $this->assertSame(2, Order::count());
    }

    /** bopcamping-kpf — email tuỳ chọn ở checkout: khách vãng lai nhận mail xác nhận. */

    /** @test */
    public function guest_email_is_saved_on_order(): void
    {
        $p = $this->product();

        $this->post(route('order.store'), [
            'name' => 'Khách Email',
            'phone' => '0912345678',
            'email' => 'khach@gmail.com',
            'items' => [['product_id' => $p->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-02']],
        ])->assertSessionHas('order_code');

        $this->assertSame('khach@gmail.com', Order::first()->customer_email);
    }

    /** @test */
    public function invalid_email_is_rejected(): void
    {
        $p = $this->product();

        $this->post(route('order.store'), [
            'name' => 'Khách',
            'phone' => '0912345678',
            'email' => 'not-an-email',
            'items' => [['product_id' => $p->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-02']],
        ])->assertSessionHasErrors('email');

        $this->assertSame(0, Order::count());
    }

    /** @test */
    public function order_without_email_keeps_customer_email_null_for_guest(): void
    {
        $p = $this->product();

        $this->post(route('order.store'), [
            'name' => 'Khách',
            'phone' => '0912345678',
            'items' => [['product_id' => $p->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-02']],
        ])->assertSessionHas('order_code');

        $this->assertNull(Order::first()->customer_email);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * bopcamping-s9d (Combo P1) — PRD 5.1: tồn kho combo là single source of truth,
 * comboAvailable() = min( intdiv(available(product_i), quantity_i) ), gọi lại
 * availableQuantity() hiện có — KHÔNG có công thức overlap thứ hai.
 */
class ComboAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private AvailabilityService $service;

    private Product $tent;   // kho 3

    private Product $mattress; // kho 4

    private Combo $combo;    // 1 lều + 2 đệm

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AvailabilityService;

        $category = Category::create(['name' => 'Test', 'slug' => 'test']);
        $this->tent = Product::create([
            'category_id' => $category->id,
            'name' => 'Lều Test',
            'slug' => 'leu-test',
            'price_per_day' => 100000,
            'quantity' => 3,
        ]);
        $this->mattress = Product::create([
            'category_id' => $category->id,
            'name' => 'Đệm Test',
            'slug' => 'dem-test',
            'price_per_day' => 40000,
            'quantity' => 4,
        ]);

        $this->combo = Combo::create([
            'name' => 'Combo Cặp Đôi',
            'slug' => 'combo-cap-doi',
            'combo_price' => 150000,
            'deposit' => 300000,
            'suitable_for' => 2,
        ]);
        $this->combo->items()->create(['product_id' => $this->tent->id, 'quantity' => 1]);
        $this->combo->items()->create(['product_id' => $this->mattress->id, 'quantity' => 2]);
    }

    /** @test */
    public function no_orders_returns_min_over_items(): void
    {
        // lều: 3/1 = 3 combo · đệm: 4/2 = 2 combo → min = 2
        $this->assertSame(2, $this->comboAvailable('2026-07-10', '2026-07-12'));
    }

    /** @test */
    public function overlapping_order_on_one_item_reduces_combo(): void
    {
        // Thuê lẻ 3 đệm chồng lịch → đệm còn 1 → 1/2 = 0 combo
        $this->makeOrder($this->mattress, '2026-07-11', '2026-07-15', quantity: 3);

        $this->assertSame(0, $this->comboAvailable('2026-07-10', '2026-07-12'));
    }

    /** @test */
    public function bottleneck_item_uses_intdiv(): void
    {
        // Đệm còn 3 → intdiv(3, 2) = 1 combo (không phải 1.5)
        $this->makeOrder($this->mattress, '2026-07-10', '2026-07-12', quantity: 1);

        $this->assertSame(1, $this->comboAvailable('2026-07-10', '2026-07-12'));
    }

    /** @test */
    public function non_overlapping_order_does_not_reduce_combo(): void
    {
        // Đơn trả 09/07, combo thuê từ 10/07 — ngày kề nhau không chồng
        $this->makeOrder($this->mattress, '2026-07-05', '2026-07-09', quantity: 4);

        $this->assertSame(2, $this->comboAvailable('2026-07-10', '2026-07-12'));
    }

    /** @test */
    public function cancelled_order_does_not_reduce_combo(): void
    {
        $this->makeOrder($this->mattress, '2026-07-10', '2026-07-12', quantity: 4, status: 'cancelled');

        $this->assertSame(2, $this->comboAvailable('2026-07-10', '2026-07-12'));
    }

    /** @test */
    public function combo_without_items_is_never_available(): void
    {
        $empty = Combo::create([
            'name' => 'Combo Rỗng',
            'slug' => 'combo-rong',
            'combo_price' => 100000,
        ]);

        $this->assertSame(
            0,
            $this->service->comboAvailable($empty, Carbon::parse('2026-07-10'), Carbon::parse('2026-07-12')),
        );
    }

    /** @test */
    public function combo_available_never_goes_negative(): void
    {
        // Overbooked (dữ liệu cũ đặt vượt kho) → 0, không âm
        $this->makeOrder($this->mattress, '2026-07-10', '2026-07-12', quantity: 9);

        $this->assertSame(0, $this->comboAvailable('2026-07-10', '2026-07-12'));
    }

    /** @test */
    public function is_combo_available_checks_needed_quantity(): void
    {
        $start = Carbon::parse('2026-07-10');
        $end = Carbon::parse('2026-07-12');

        // Còn 2 combo (xem test đầu)
        $this->assertTrue($this->service->isComboAvailable($this->combo, $start, $end, needed: 2));
        $this->assertFalse($this->service->isComboAvailable($this->combo, $start, $end, needed: 3));
    }

    /** @test */
    public function combo_uses_same_per_product_availability_as_single_rental(): void
    {
        // AC-4 (chiều P1): đơn thuê lẻ và comboAvailable nhìn cùng một nguồn tồn kho
        $start = Carbon::parse('2026-07-10');
        $end = Carbon::parse('2026-07-12');

        $this->makeOrder($this->tent, '2026-07-10', '2026-07-12', quantity: 2);

        $this->assertSame(1, $this->service->availableQuantity($this->tent, $start, $end));
        $this->assertSame(1, $this->service->comboAvailable($this->combo, $start, $end));
    }

    // -------------------------------------------------------------------------

    private function comboAvailable(string $start, string $end): int
    {
        return $this->service->comboAvailable(
            $this->combo,
            Carbon::parse($start),
            Carbon::parse($end),
        );
    }

    private function makeOrder(
        Product $product,
        string $start,
        string $end,
        int $quantity = 1,
        string $status = 'confirmed',
    ): Order {
        $order = Order::create([
            'code' => 'BOP-'.uniqid(),
            'customer_name' => 'Khách Test',
            'customer_phone' => '0900000000',
            'start_date' => $start,
            'end_date' => $end,
            'status' => $status,
            'payment_method' => 'cod',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'price_per_day' => $product->price_per_day,
            'days' => Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1,
            'subtotal' => $quantity * $product->price_per_day,
        ]);

        return $order;
    }
}

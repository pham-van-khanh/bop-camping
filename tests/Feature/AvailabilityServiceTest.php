<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private AvailabilityService $service;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AvailabilityService;

        $category = Category::create(['name' => 'Test', 'slug' => 'test']);
        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Lều Test',
            'slug' => 'leu-test',
            'price_per_day' => 100000,
            'quantity' => 3,
        ]);
    }

    /** @test */
    public function no_orders_returns_full_quantity(): void
    {
        $available = $this->service->availableQuantity(
            $this->product,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-05'),
        );

        $this->assertEquals(3, $available);
    }

    /** @test */
    public function overlapping_order_reduces_quantity(): void
    {
        $this->makeOrder('2026-07-02', '2026-07-06', quantity: 2);

        $available = $this->service->availableQuantity(
            $this->product,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-05'),
        );

        $this->assertEquals(1, $available);
    }

    /** @test */
    public function non_overlapping_order_does_not_reduce_quantity(): void
    {
        // Đơn kết thúc trước ngày mình chọn
        $this->makeOrder('2026-06-20', '2026-06-30', quantity: 2);

        $available = $this->service->availableQuantity(
            $this->product,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-05'),
        );

        $this->assertEquals(3, $available);
    }

    /** @test */
    public function cancelled_order_does_not_reduce_quantity(): void
    {
        $this->makeOrder('2026-07-01', '2026-07-05', quantity: 3, status: 'cancelled');

        $available = $this->service->availableQuantity(
            $this->product,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-05'),
        );

        $this->assertEquals(3, $available);
    }

    /** @test */
    public function available_quantity_never_goes_negative(): void
    {
        // Đặt vượt số lượng (edge case dữ liệu cũ)
        $this->makeOrder('2026-07-01', '2026-07-05', quantity: 5);

        $available = $this->service->availableQuantity(
            $this->product,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-05'),
        );

        $this->assertEquals(0, $available);
    }

    /** @test */
    public function is_available_returns_false_when_not_enough(): void
    {
        $this->makeOrder('2026-07-01', '2026-07-05', quantity: 3);

        $this->assertFalse(
            $this->service->isAvailable(
                $this->product,
                Carbon::parse('2026-07-01'),
                Carbon::parse('2026-07-05'),
            )
        );
    }

    /** @test */
    public function check_cart_returns_only_insufficient_items(): void
    {
        $this->makeOrder('2026-07-01', '2026-07-05', quantity: 2);

        $result = $this->service->checkCart(
            [$this->product->id => 2],   // cần 2, còn 1
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-05'),
        );

        $this->assertArrayHasKey($this->product->id, $result);
        $this->assertEquals(1, $result[$this->product->id]);
    }

    /** @test */
    public function adjacent_dates_do_not_overlap(): void
    {
        // Đơn trả ngày 30/6, mình thuê từ 1/7 — không chồng
        $this->makeOrder('2026-06-25', '2026-06-30', quantity: 3);

        $available = $this->service->availableQuantity(
            $this->product,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-05'),
        );

        $this->assertEquals(3, $available);
    }

    // -------------------------------------------------------------------------

    private function makeOrder(
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
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'price_per_day' => $this->product->price_per_day,
            'days' => Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1,
            'subtotal' => $quantity * $this->product->price_per_day,
        ]);

        return $order;
    }
}

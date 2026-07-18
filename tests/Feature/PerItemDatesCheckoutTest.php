<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * bopcamping-u1nb — đơn nhiều khoảng ngày: mỗi món lưu ngày RIÊNG, tồn kho tính theo
 * ngày món (không khoá dư trên envelope min-start/max-end).
 */
class PerItemDatesCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Product $a;

    private Product $b;

    protected function setUp(): void
    {
        parent::setUp();
        $cat = Category::create(['name' => 'Lều', 'slug' => 'leu']);
        $this->a = Product::create(['category_id' => $cat->id, 'name' => 'Món A', 'slug' => 'mon-a', 'price_per_day' => 100000, 'quantity' => 1, 'deposit' => 0]);
        $this->b = Product::create(['category_id' => $cat->id, 'name' => 'Món B', 'slug' => 'mon-b', 'price_per_day' => 100000, 'quantity' => 1, 'deposit' => 0]);
    }

    /** @test */
    public function multi_range_order_stores_per_item_dates_and_frees_inventory_correctly(): void
    {
        // 1 đơn, 2 khoảng: A 01→02, B 03→04 (envelope 01→04).
        $this->post(route('order.store'), [
            'name' => 'Khách', 'phone' => '0912345678',
            'items' => [
                ['product_id' => $this->a->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-02'],
                ['product_id' => $this->b->id, 'quantity' => 1, 'start' => '2030-07-03', 'end' => '2030-07-04'],
            ],
        ])->assertSessionHas('order_code');

        $order = Order::latest('id')->with('items')->first();
        // Đơn giữ envelope, món giữ ngày riêng.
        $this->assertSame('2030-07-01', $order->start_date->format('Y-m-d'));
        $this->assertSame('2030-07-04', $order->end_date->format('Y-m-d'));
        $itemA = $order->items->firstWhere('product_id', $this->a->id);
        $this->assertSame('2030-07-01', $itemA->start_date->format('Y-m-d'));
        $this->assertSame('2030-07-02', $itemA->end_date->format('Y-m-d'));

        $svc = new AvailabilityService;
        // A chỉ thuê 01→02 → 03→04 phải CÒN (bug cũ: bị khoá theo envelope 01→04).
        $this->assertSame(1, $svc->availableQuantity($this->a, Carbon::parse('2030-07-03'), Carbon::parse('2030-07-04')));
        // A đúng khoảng 01→02 → hết.
        $this->assertSame(0, $svc->availableQuantity($this->a, Carbon::parse('2030-07-01'), Carbon::parse('2030-07-02')));
    }

    /** @test */
    public function second_customer_can_book_the_freed_dates(): void
    {
        $this->post(route('order.store'), [
            'name' => 'Khách 1', 'phone' => '0912345678',
            'items' => [
                ['product_id' => $this->a->id, 'quantity' => 1, 'start' => '2030-08-01', 'end' => '2030-08-02'],
                ['product_id' => $this->b->id, 'quantity' => 1, 'start' => '2030-08-10', 'end' => '2030-08-11'],
            ],
        ])->assertSessionHas('order_code');

        // Khách 2 thuê A vào 10→11/08 (A thực ra chỉ bận 01→02) → đặt được.
        $this->post(route('order.store'), [
            'name' => 'Khách 2', 'phone' => '0987654321',
            'items' => [['product_id' => $this->a->id, 'quantity' => 1, 'start' => '2030-08-10', 'end' => '2030-08-11']],
        ])->assertSessionHas('order_code')->assertSessionHasNoErrors();

        $this->assertSame(2, Order::count());
    }
}

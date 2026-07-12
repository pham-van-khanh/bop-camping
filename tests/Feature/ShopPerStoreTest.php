<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Per-store stock — T4: trang sản phẩm + endpoint availability theo cửa hàng. */
class ShopPerStoreTest extends TestCase
{
    use RefreshDatabase;

    private ServiceLocation $vinh;

    private ServiceLocation $hanoi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vinh = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1]);
        $this->hanoi = ServiceLocation::create(['name' => 'Hà Nội', 'area' => 'Nội thành', 'status' => 'open', 'sort_order' => 2]);
    }

    private function product(): Product
    {
        $cat = Category::firstOrCreate(['slug' => 'leu'], ['name' => 'Lều']);
        $p = Product::create([
            'category_id' => $cat->id, 'name' => 'Lều 2 người', 'slug' => 'leu-2-nguoi',
            'price_per_day' => 50000, 'quantity' => 8,
        ]);
        $p->serviceLocations()->sync([$this->vinh->id => ['quantity' => 5], $this->hanoi->id => ['quantity' => 3]]);

        return $p;
    }

    /** @test */
    public function show_returns_stock_by_location(): void
    {
        $this->product();

        $this->get('/thiet-bi/leu-2-nguoi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProductDetail')
                ->count('stock_by_location', 2)
                ->where('stock_by_location.0.name', 'Vinh')
                ->where('stock_by_location.0.quantity', 5)
                ->where('stock_by_location.1.quantity', 3)
            );
    }

    /** @test */
    public function availability_endpoint_filters_by_location(): void
    {
        $this->product();

        $this->getJson('/thiet-bi/leu-2-nguoi/kha-dung?start=2026-08-01&end=2026-08-03&location_id='.$this->vinh->id)
            ->assertOk()->assertJson(['available' => 5]);

        $this->getJson('/thiet-bi/leu-2-nguoi/kha-dung?start=2026-08-01&end=2026-08-03&location_id='.$this->hanoi->id)
            ->assertOk()->assertJson(['available' => 3]);
    }

    /** @test */
    public function show_returns_unavailable_dates_per_store(): void
    {
        $p = $this->product(); // Vinh=5, Hà Nội=3

        // Đặt hết Hà Nội (3 bộ) cho 1 ngày cụ thể → ngày đó chỉ hết ở Hà Nội.
        $order = Order::create([
            'service_location_id' => $this->hanoi->id,
            'customer_name' => 'X', 'customer_phone' => '0900000000',
            'start_date' => now()->addDays(5)->toDateString(), 'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'confirmed', 'payment_method' => 'cod',
        ]);
        $order->items()->create(['product_id' => $p->id, 'quantity' => 3, 'price_per_day' => 50000, 'days' => 1, 'subtotal' => 1]);

        $day = now()->addDays(5)->toDateString();
        $this->get('/thiet-bi/leu-2-nguoi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // Ngày đó hết ở Hà Nội nhưng KHÔNG hết ở Vinh
                ->where('unavailable_by_location.'.$this->hanoi->id, fn ($dates) => collect($dates)->contains($day))
                ->where('unavailable_by_location.'.$this->vinh->id, fn ($dates) => ! collect($dates)->contains($day))
            );
    }

    /** @test */
    public function availability_endpoint_without_location_returns_map(): void
    {
        $this->product();

        $this->getJson('/thiet-bi/leu-2-nguoi/kha-dung?start=2026-08-01&end=2026-08-03')
            ->assertOk()
            ->assertJson(['by_location' => [$this->vinh->id => 5, $this->hanoi->id => 3]]);
    }
}

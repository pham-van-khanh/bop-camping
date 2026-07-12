<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Services\AvailabilityService;
use App\Services\StoreResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Per-store stock — T1 pivot, T2 AvailabilityService + StoreResolver. */
class PerStoreStockTest extends TestCase
{
    use RefreshDatabase;

    private ServiceLocation $vinh;

    private ServiceLocation $hanoi;

    /** Tạo product có tồn theo store: [locationId => qty]. */
    private function product(string $slug, array $stockByLoc): Product
    {
        $cat = Category::firstOrCreate(['slug' => 'leu'], ['name' => 'Lều']);
        $p = Product::create([
            'category_id' => $cat->id, 'name' => 'SP '.$slug, 'slug' => $slug,
            'price_per_day' => 50000, 'quantity' => array_sum($stockByLoc),
        ]);
        $p->serviceLocations()->sync(collect($stockByLoc)->mapWithKeys(fn ($q, $id) => [$id => ['quantity' => $q]])->all());

        return $p->load('serviceLocations');
    }

    /** Đơn active thuê 1 sản phẩm tại 1 store, khoảng cố định. */
    private function bookingAt(Product $p, ServiceLocation $loc, int $qty): void
    {
        $order = Order::create([
            'service_location_id' => $loc->id,
            'customer_name' => 'X', 'customer_phone' => '0900000000',
            'start_date' => '2026-08-01', 'end_date' => '2026-08-03',
            'status' => 'confirmed', 'payment_method' => 'cod',
        ]);
        $order->items()->create(['product_id' => $p->id, 'quantity' => $qty, 'price_per_day' => 50000, 'days' => 3, 'subtotal' => 1]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->vinh = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1]);
        $this->hanoi = ServiceLocation::create(['name' => 'Hà Nội', 'area' => 'Nội thành', 'status' => 'open', 'sort_order' => 2]);
    }

    private function avail(): AvailabilityService
    {
        return app(AvailabilityService::class);
    }

    private function window(): array
    {
        return [Carbon::parse('2026-08-01'), Carbon::parse('2026-08-03')];
    }

    /** @test */
    public function pivot_stores_quantity_per_location(): void
    {
        $p = $this->product('leu-2-nguoi', [$this->vinh->id => 5, $this->hanoi->id => 3]);

        $fresh = Product::with('serviceLocations')->find($p->id);
        $this->assertSame(5, $fresh->stockAt($this->vinh->id));
        $this->assertSame(3, $fresh->stockAt($this->hanoi->id));
        $this->assertSame(0, $fresh->stockAt(999)); // store không phục vụ → 0
    }

    /** @test */
    public function available_is_per_store_and_does_not_cross(): void
    {
        $p = $this->product('leu', [$this->vinh->id => 5, $this->hanoi->id => 0]);
        [$s, $e] = $this->window();

        $this->assertSame(5, $this->avail()->availableQuantity($p, $s, $e, $this->vinh));
        $this->assertSame(0, $this->avail()->availableQuantity($p, $s, $e, $this->hanoi));
        // availableByLocations trả cả 2 store, KHÔNG cộng gộp
        $this->assertSame([$this->vinh->id => 5, $this->hanoi->id => 0], $this->avail()->availableByLocations($p, $s, $e));
    }

    /** @test */
    public function booking_at_a_does_not_reduce_b(): void
    {
        $p = $this->product('leu', [$this->vinh->id => 5, $this->hanoi->id => 4]);
        [$s, $e] = $this->window();
        $this->bookingAt($p, $this->vinh, 3);

        $this->assertSame(2, $this->avail()->availableQuantity($p, $s, $e, $this->vinh)); // 5-3
        $this->assertSame(4, $this->avail()->availableQuantity($p, $s, $e, $this->hanoi)); // không đổi
    }

    /** @test */
    public function resolver_autopicks_store_with_stock(): void
    {
        $p = $this->product('leu', [$this->vinh->id => 0, $this->hanoi->id => 4]);
        $needed = ["{$p->id}|2026-08-01|2026-08-03" => 2];
        $byId = collect([$p->id => $p]);

        $r = app(StoreResolver::class)->resolveForCart($needed, $byId, null);
        $this->assertSame($this->hanoi->id, $r['location']->id);
        $this->assertTrue($r['auto']);
    }

    /** @test */
    public function resolver_respects_chosen_store(): void
    {
        $p = $this->product('leu', [$this->vinh->id => 5, $this->hanoi->id => 4]);
        $needed = ["{$p->id}|2026-08-01|2026-08-03" => 2];
        $byId = collect([$p->id => $p]);

        $r = app(StoreResolver::class)->resolveForCart($needed, $byId, $this->vinh->id);
        $this->assertSame($this->vinh->id, $r['location']->id);
        $this->assertFalse($r['auto']);
    }

    /** @test */
    public function resolver_throws_when_no_single_store_fills_cart(): void
    {
        $p = $this->product('leu', [$this->vinh->id => 1, $this->hanoi->id => 1]);
        $needed = ["{$p->id}|2026-08-01|2026-08-03" => 2]; // cần 2, mỗi store chỉ 1 → không store nào đủ
        $byId = collect([$p->id => $p]);

        $this->expectException(\RuntimeException::class);
        app(StoreResolver::class)->resolveForCart($needed, $byId, null);
    }

    /** @test */
    public function combo_available_is_per_store(): void
    {
        $p = $this->product('leu', [$this->vinh->id => 4, $this->hanoi->id => 0]);
        $combo = Combo::create(['name' => 'Combo', 'slug' => 'combo-x', 'combo_price' => 90000, 'is_active' => true]);
        $combo->items()->create(['product_id' => $p->id, 'quantity' => 2]);
        [$s, $e] = $this->window();

        $this->assertSame(2, $this->avail()->comboAvailable($combo->load('items.product'), $s, $e, $this->vinh)); // 4/2
        $this->assertSame(0, $this->avail()->comboAvailable($combo, $s, $e, $this->hanoi));
    }
}

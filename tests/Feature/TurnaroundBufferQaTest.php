<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * bopcamping-s1ij (QA gap-fill) — đệm quay vòng ở CÁC LỐI TÍCH HỢP, không chỉ service:
 * endpoint tồn kho khách, chặn checkout, đổi lịch (excludeOrderId), per-store map.
 */
class TurnaroundBufferQaTest extends TestCase
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

    /** @param array<int,array{loc:ServiceLocation,qty:int,buffer:int}> $stocks */
    private function tent(array $stocks): Product
    {
        $cat = Category::firstOrCreate(['slug' => 'leu'], ['name' => 'Lều']);
        $p = Product::create([
            'category_id' => $cat->id, 'name' => 'Lều '.Str::random(4), 'slug' => 'leu-'.Str::random(6),
            'price_per_day' => 100000, 'quantity' => array_sum(array_column($stocks, 'qty')), 'status' => 'active',
        ]);
        foreach ($stocks as $s) {
            $p->serviceLocations()->attach($s['loc']->id, ['quantity' => $s['qty'], 'buffer_days' => $s['buffer']]);
        }

        return $p;
    }

    private function activeOrder(Product $p, ServiceLocation $loc, string $start, string $end, int $qty = 1): Order
    {
        $o = Order::create([
            'code' => 'BOP-'.strtoupper(Str::random(6)), 'customer_name' => 'X', 'customer_phone' => '0900000000',
            'start_date' => $start, 'end_date' => $end, 'status' => 'pending', 'payment_method' => 'cod',
            'service_location_id' => $loc->id,
        ]);
        $o->items()->create([
            'product_id' => $p->id, 'quantity' => $qty, 'price_per_day' => 100000, 'days' => 1,
            'start_date' => $start, 'end_date' => $end, 'subtotal' => 100000,
        ]);

        return $o;
    }

    /** @test */
    public function customer_availability_endpoint_reflects_buffer(): void
    {
        $p = $this->tent([['loc' => $this->vinh, 'qty' => 1, 'buffer' => 2]]);
        $this->activeOrder($p, $this->vinh, '2030-07-10', '2030-07-12');

        // Ngày phơi (13) → hết; ngày khô (15) → còn 1. Đúng ở endpoint khách dùng thật.
        $this->getJson("/thiet-bi/{$p->slug}/kha-dung?start=2030-07-13&end=2030-07-13&location_id={$this->vinh->id}")
            ->assertOk()->assertExactJson(['available' => 0]);
        $this->getJson("/thiet-bi/{$p->slug}/kha-dung?start=2030-07-15&end=2030-07-15&location_id={$this->vinh->id}")
            ->assertOk()->assertExactJson(['available' => 1]);
    }

    /** @test */
    public function checkout_blocked_when_landing_in_buffer_window(): void
    {
        $p = $this->tent([['loc' => $this->vinh, 'qty' => 1, 'buffer' => 2]]);
        $this->activeOrder($p, $this->vinh, '2030-07-10', '2030-07-12');

        // Đặt ngày 13 (đang phơi) — StoreResolver phải chặn, không tạo đơn mới.
        $this->post(route('order.store'), [
            'name' => 'Khách', 'phone' => '0912345678',
            'items' => [['product_id' => $p->id, 'quantity' => 1, 'start' => '2030-07-13', 'end' => '2030-07-13', 'location_id' => $this->vinh->id]],
        ])->assertSessionHasErrors('items');

        $this->assertSame(1, Order::count()); // chỉ còn đơn A ban đầu
    }

    /** @test */
    public function reschedule_excludes_self_but_still_respects_buffer(): void
    {
        $svc = app(AvailabilityService::class);
        $p = $this->tent([['loc' => $this->vinh, 'qty' => 1, 'buffer' => 2]]);
        $a = $this->activeOrder($p, $this->vinh, '2030-07-10', '2030-07-12');

        $day13 = [Carbon::parse('2030-07-13'), Carbon::parse('2030-07-13')];

        // Không loại đơn nào: ngày 13 bị buffer của A chặn → 0.
        $this->assertSame(0, $svc->availableQuantity($p, $day13[0], $day13[1], $this->vinh));
        // Đổi lịch chính đơn A: loại A khỏi "đã đặt" → 1 (A không tự chặn mình dù có buffer).
        $this->assertSame(1, $svc->availableQuantity($p, $day13[0], $day13[1], $this->vinh, $a->id));
    }

    /** @test */
    public function available_by_locations_isolates_buffer_per_store(): void
    {
        $svc = app(AvailabilityService::class);
        // Vinh: 1 lều phơi 2 ngày; Hà Nội: 1 lều phơi 0 ngày.
        $p = $this->tent([
            ['loc' => $this->vinh, 'qty' => 1, 'buffer' => 2],
            ['loc' => $this->hanoi, 'qty' => 1, 'buffer' => 0],
        ]);
        $this->activeOrder($p, $this->vinh, '2030-07-10', '2030-07-12');

        // Ngày 13: Vinh đang phơi → 0; Hà Nội không liên quan → 1.
        $map = $svc->availableByLocations($p, Carbon::parse('2030-07-13'), Carbon::parse('2030-07-13'));
        $this->assertSame(0, $map[$this->vinh->id]);
        $this->assertSame(1, $map[$this->hanoi->id]);
    }
}

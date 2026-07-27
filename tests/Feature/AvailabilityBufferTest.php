<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Models\User;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * bopcamping-s1ij — đệm quay vòng giặt/phơi THEO KHO (adr_turnaround_buffer).
 * Sau ngày trả, món bị coi chưa sẵn sàng thêm buffer_days ngày. Tồn kho × buffer tự
 * kết hợp: cái thứ 2 còn trong kho vẫn cho thuê dù cái thứ nhất đang phơi.
 */
class AvailabilityBufferTest extends TestCase
{
    use RefreshDatabase;

    private AvailabilityService $svc;

    private ServiceLocation $vinh;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(AvailabilityService::class);
        $this->vinh = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1]);
    }

    private function tent(int $stock, int $buffer): Product
    {
        $cat = Category::firstOrCreate(['slug' => 'leu'], ['name' => 'Lều']);
        $p = Product::create([
            'category_id' => $cat->id,
            'name' => 'Lều test '.Str::random(4),
            'slug' => 'leu-'.Str::random(6),
            'price_per_day' => 50000,
            'quantity' => $stock,
            'status' => 'active',
        ]);
        $p->serviceLocations()->attach($this->vinh->id, ['quantity' => $stock, 'buffer_days' => $buffer]);

        return $p;
    }

    /** Đơn active chiếm $qty món trong [start,end] tại Vinh. */
    private function order(Product $p, string $start, string $end, int $qty = 1): void
    {
        $o = Order::create([
            'code' => 'BOP-'.strtoupper(Str::random(6)),
            'customer_name' => 'X',
            'customer_phone' => '0900000000',
            'start_date' => $start,
            'end_date' => $end,
            'status' => 'confirmed', // chỉ đơn đã xác nhận mới khoá tồn (feedback 2026-07-27)
            'payment_method' => 'cod',
            'service_location_id' => $this->vinh->id,
        ]);
        $o->items()->create([
            'product_id' => $p->id,
            'quantity' => $qty,
            'price_per_day' => 50000,
            'days' => 3,
            'subtotal' => 150000,
        ]);
    }

    private function avail(Product $p, string $start, string $end): int
    {
        return $this->svc->availableQuantity($p, Carbon::parse($start), Carbon::parse($end), $this->vinh);
    }

    /** @test */
    public function buffer_zero_behaves_like_before(): void
    {
        $p = $this->tent(stock: 1, buffer: 0);
        $this->order($p, '2030-07-10', '2030-07-12');

        $this->assertSame(0, $this->avail($p, '2030-07-12', '2030-07-12')); // ngày trả vẫn bận
        $this->assertSame(1, $this->avail($p, '2030-07-13', '2030-07-13')); // hôm sau đã trống ngay
    }

    /** @test */
    public function buffer_blocks_drying_days_then_frees(): void
    {
        $p = $this->tent(stock: 1, buffer: 2);
        $this->order($p, '2030-07-10', '2030-07-12');

        $this->assertSame(0, $this->avail($p, '2030-07-13', '2030-07-13')); // đang phơi
        $this->assertSame(0, $this->avail($p, '2030-07-14', '2030-07-14')); // đang phơi
        $this->assertSame(1, $this->avail($p, '2030-07-15', '2030-07-15')); // khô, cho thuê lại
    }

    /** @test */
    public function buffer_boundary_is_inclusive(): void
    {
        $p = $this->tent(stock: 1, buffer: 2);
        $this->order($p, '2030-07-10', '2030-07-12');

        // Bắt đầu đúng end+buffer (14) → vẫn chặn; end+buffer+1 (15) → cho phép.
        $this->assertSame(0, $this->avail($p, '2030-07-14', '2030-07-16'));
        $this->assertSame(1, $this->avail($p, '2030-07-15', '2030-07-16'));
    }

    /** @test */
    public function second_unit_rentable_while_first_dries(): void
    {
        // 2 lều, buffer 2, 1 đơn giữ 1 lều [10..12] → ngày 13 còn 1 (lều thứ 2 rảnh).
        $p = $this->tent(stock: 2, buffer: 2);
        $this->order($p, '2030-07-10', '2030-07-12', qty: 1);

        $this->assertSame(1, $this->avail($p, '2030-07-13', '2030-07-13'));

        // Nếu chỉ có 1 lều thì ngày 13 bị chặn.
        $single = $this->tent(stock: 1, buffer: 2);
        $this->order($single, '2030-07-10', '2030-07-12', qty: 1);
        $this->assertSame(0, $this->avail($single, '2030-07-13', '2030-07-13'));
    }

    /** @test */
    public function unavailable_dates_include_buffer_window(): void
    {
        $p = $this->tent(stock: 1, buffer: 2);
        $this->order($p, '2030-07-10', '2030-07-12');

        $dates = $this->svc->unavailableDates($p, Carbon::parse('2030-07-08'), Carbon::parse('2030-07-16'), $this->vinh);

        // Bận: 10,11,12 (thuê) + 13,14 (phơi). Trống: 09, 15, 16.
        $this->assertContains('2030-07-13', $dates);
        $this->assertContains('2030-07-14', $dates);
        $this->assertNotContains('2030-07-15', $dates);
        $this->assertNotContains('2030-07-09', $dates);
    }

    /** @test */
    public function combo_uses_child_product_buffer(): void
    {
        $p = $this->tent(stock: 1, buffer: 2);
        $combo = Combo::create(['name' => 'Combo B', 'slug' => 'combo-b-'.Str::random(4), 'combo_price' => 90000]);
        $combo->items()->create(['product_id' => $p->id, 'quantity' => 1]);
        $this->order($p, '2030-07-10', '2030-07-12');

        // Ngày 13 con đang phơi → combo hết; ngày 15 → còn.
        $this->assertSame(0, $this->svc->comboAvailable($combo, Carbon::parse('2030-07-13'), Carbon::parse('2030-07-13'), $this->vinh));
        $this->assertSame(1, $this->svc->comboAvailable($combo, Carbon::parse('2030-07-15'), Carbon::parse('2030-07-15'), $this->vinh));
    }

    /** @test */
    public function admin_saves_buffer_days_per_store(): void
    {
        $cat = Category::firstOrCreate(['slug' => 'leu'], ['name' => 'Lều']);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'name' => 'Lều có buffer',
            'category_id' => $cat->id,
            'price_per_day' => 50000,
            'status' => 'active',
            'service_location_ids' => [$this->vinh->id],
            'stocks' => [$this->vinh->id => 3],
            'buffers' => [$this->vinh->id => 2],
        ])->assertRedirect();

        $p = Product::where('name', 'Lều có buffer')->firstOrFail();
        $this->assertSame(2, $p->bufferAt($this->vinh->id));
        $this->assertSame(3, $p->stockAt($this->vinh->id));
    }

    /** @test */
    public function admin_buffer_rejects_over_limit(): void
    {
        $cat = Category::firstOrCreate(['slug' => 'leu'], ['name' => 'Lều']);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'name' => 'Lều buffer lỗi',
            'category_id' => $cat->id,
            'price_per_day' => 50000,
            'status' => 'active',
            'service_location_ids' => [$this->vinh->id],
            'stocks' => [$this->vinh->id => 1],
            'buffers' => [$this->vinh->id => 99], // > trần 30
        ])->assertSessionHasErrors('buffers.'.$this->vinh->id);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Per-store stock — T3: admin nhập tồn kho theo cửa hàng. */
class AdminProductStockTest extends TestCase
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

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => 'Lều 2 người',
            'category_id' => Category::firstOrCreate(['slug' => 'leu'], ['name' => 'Lều'])->id,
            'price_per_day' => 50000,
            'status' => 'active',
            'service_location_ids' => [$this->vinh->id, $this->hanoi->id],
            'stocks' => [$this->vinh->id => 5, $this->hanoi->id => 3],
        ], $override);
    }

    /** @test */
    public function store_saves_stock_per_location_and_sums_total(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.products.store'), $this->payload())
            ->assertRedirect()->assertSessionHas('success');

        $p = Product::with('serviceLocations')->firstWhere('name', 'Lều 2 người');
        $this->assertSame(5, $p->stockAt($this->vinh->id));
        $this->assertSame(3, $p->stockAt($this->hanoi->id));
        $this->assertSame(8, $p->quantity); // tổng = 5+3
    }

    /** @test */
    public function update_can_change_per_store_stock_and_drop_untucked_location(): void
    {
        $this->actingAs($this->admin())->post(route('admin.products.store'), $this->payload());
        $p = Product::firstWhere('name', 'Lều 2 người');

        // Bỏ Hà Nội, đổi Vinh = 10
        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $p), $this->payload([
                'service_location_ids' => [$this->vinh->id],
                'stocks' => [$this->vinh->id => 10],
            ]))
            ->assertRedirect();

        $p->refresh()->load('serviceLocations');
        $this->assertSame(10, $p->stockAt($this->vinh->id));
        $this->assertSame(0, $p->stockAt($this->hanoi->id)); // đã bỏ tick → không còn pivot
        $this->assertSame(10, $p->quantity);
    }

    /** @test */
    public function negative_stock_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.products.store'), $this->payload([
                'stocks' => [$this->vinh->id => -2, $this->hanoi->id => 3],
            ]))
            ->assertSessionHasErrors('stocks.'.$this->vinh->id);
    }
}

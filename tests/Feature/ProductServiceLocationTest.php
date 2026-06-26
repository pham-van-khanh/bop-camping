<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-1cq — sản phẩm hiển thị theo vị trí phục vụ (Vinh/Hà Nội).
 */
class ProductServiceLocationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function category(): Category
    {
        return Category::firstOrCreate(['slug' => 'leu'], ['name' => 'Lều']);
    }

    private function product(string $name, string $slug): Product
    {
        return Product::create([
            'category_id' => $this->category()->id,
            'name' => $name,
            'slug' => $slug,
            'price_per_day' => 50000,
            'quantity' => 5,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function store_requires_at_least_one_location(): void
    {
        $cat = $this->category();

        $this->actingAs($this->admin())->post(route('admin.products.store'), [
            'name' => 'Lều mới',
            'category_id' => $cat->id,
            'price_per_day' => 50000,
            'quantity' => 3,
            'service_location_ids' => [],
        ])->assertSessionHasErrors('service_location_ids');

        $this->assertSame(0, Product::count());
    }

    /** @test */
    public function store_syncs_selected_locations(): void
    {
        $cat = $this->category();
        $vinh = ServiceLocation::create(['name' => 'Vinh', 'status' => 'open', 'sort_order' => 1]);
        $hanoi = ServiceLocation::create(['name' => 'Hà Nội', 'status' => 'open', 'sort_order' => 2]);

        $this->actingAs($this->admin())->post(route('admin.products.store'), [
            'name' => 'Bếp gas',
            'category_id' => $cat->id,
            'price_per_day' => 50000,
            'quantity' => 3,
            'service_location_ids' => [$vinh->id, $hanoi->id],
        ])->assertRedirect()->assertSessionHas('success');

        $p = Product::first();
        $this->assertEqualsCanonicalizing(
            [$vinh->id, $hanoi->id],
            $p->serviceLocations->pluck('id')->all(),
        );
    }

    /** @test */
    public function update_replaces_locations(): void
    {
        $vinh = ServiceLocation::create(['name' => 'Vinh', 'status' => 'open', 'sort_order' => 1]);
        $hanoi = ServiceLocation::create(['name' => 'Hà Nội', 'status' => 'open', 'sort_order' => 2]);
        $p = $this->product('Ghế gấp', 'ghe-gap');
        $p->serviceLocations()->sync([$vinh->id, $hanoi->id]);

        $this->actingAs($this->admin())->put(route('admin.products.update', $p), [
            'name' => 'Ghế gấp',
            'category_id' => $p->category_id,
            'price_per_day' => 40000,
            'quantity' => 5,
            'service_location_ids' => [$hanoi->id],
        ])->assertRedirect();

        $this->assertSame([$hanoi->id], $p->fresh()->serviceLocations->pluck('id')->all());
    }

    /** @test */
    public function listing_filters_by_location_slug(): void
    {
        $vinh = ServiceLocation::create(['name' => 'Vinh', 'status' => 'open', 'sort_order' => 1]);
        $hanoi = ServiceLocation::create(['name' => 'Hà Nội', 'status' => 'open', 'sort_order' => 2]);

        $vinhOnly = $this->product('Đồ Vinh', 'do-vinh');
        $vinhOnly->serviceLocations()->sync([$vinh->id]);
        $hanoiOnly = $this->product('Đồ Hà Nội', 'do-ha-noi');
        $hanoiOnly->serviceLocations()->sync([$hanoi->id]);

        $this->get('/thiet-bi?vi-tri=vinh')->assertInertia(fn (Assert $page) => $page
            ->component('Products')
            ->has('products', 1)
            ->where('products.0.name', 'Đồ Vinh')
            ->where('filters.vi_tri', 'vinh')
            ->has('service_locations', 2));
    }

    /** @test */
    public function card_shape_marks_all_locations_and_lists_specific(): void
    {
        $vinh = ServiceLocation::create(['name' => 'Vinh', 'status' => 'open', 'sort_order' => 1]);
        $hanoi = ServiceLocation::create(['name' => 'Hà Nội', 'status' => 'open', 'sort_order' => 2]);

        $all = $this->product('Phục vụ cả hai', 'ca-hai');
        $all->serviceLocations()->sync([$vinh->id, $hanoi->id]);
        $one = $this->product('Chỉ Vinh', 'chi-vinh');
        $one->serviceLocations()->sync([$vinh->id]);

        // Sản phẩm phủ hết 2 vị trí open -> all_locations = true.
        $this->get('/thiet-bi?sort=low')->assertInertia(function (Assert $page) {
            $products = collect($page->toArray()['props']['products']);
            $caHai = $products->firstWhere('name', 'Phục vụ cả hai');
            $chiVinh = $products->firstWhere('name', 'Chỉ Vinh');

            $this->assertTrue($caHai['all_locations']);
            $this->assertFalse($chiVinh['all_locations']);
            $this->assertSame(['Vinh'], collect($chiVinh['locations'])->pluck('name')->all());
        });
    }

    /** @test */
    public function order_rejected_when_items_have_no_common_location(): void
    {
        $vinh = ServiceLocation::create(['name' => 'Vinh', 'status' => 'open', 'sort_order' => 1]);
        $hanoi = ServiceLocation::create(['name' => 'Hà Nội', 'status' => 'open', 'sort_order' => 2]);

        $vinhOnly = $this->product('Đồ Vinh', 'do-vinh');
        $vinhOnly->serviceLocations()->sync([$vinh->id]);
        $hanoiOnly = $this->product('Đồ Hà Nội', 'do-ha-noi');
        $hanoiOnly->serviceLocations()->sync([$hanoi->id]);

        $this->post(route('order.store'), [
            'name' => 'Khách',
            'phone' => '0912345678',
            'address' => 'Số 1 Đường ABC',
            'items' => [
                ['product_id' => $vinhOnly->id, 'quantity' => 1, 'start' => now()->toDateString(), 'end' => now()->addDay()->toDateString()],
                ['product_id' => $hanoiOnly->id, 'quantity' => 1, 'start' => now()->toDateString(), 'end' => now()->addDay()->toDateString()],
            ],
        ])->assertSessionHasErrors('items');

        $this->assertSame(0, Order::count());
    }

    /** @test */
    public function order_allowed_when_items_share_a_location(): void
    {
        $vinh = ServiceLocation::create(['name' => 'Vinh', 'status' => 'open', 'sort_order' => 1]);
        $hanoi = ServiceLocation::create(['name' => 'Hà Nội', 'status' => 'open', 'sort_order' => 2]);

        $both = $this->product('Toàn hệ thống', 'toan-he-thong');
        $both->serviceLocations()->sync([$vinh->id, $hanoi->id]);
        $vinhOnly = $this->product('Đồ Vinh', 'do-vinh');
        $vinhOnly->serviceLocations()->sync([$vinh->id]);

        // 'Toàn hệ thống' (Vinh+HN) + 'Đồ Vinh' (Vinh) -> giao = Vinh -> hợp lệ.
        $this->post(route('order.store'), [
            'name' => 'Khách',
            'phone' => '0912345678',
            'address' => 'Số 1 Đường ABC',
            'items' => [
                ['product_id' => $both->id, 'quantity' => 1, 'start' => now()->toDateString(), 'end' => now()->addDay()->toDateString()],
                ['product_id' => $vinhOnly->id, 'quantity' => 1, 'start' => now()->toDateString(), 'end' => now()->addDay()->toDateString()],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Order::count());
    }

    /** @test */
    public function coming_locations_excluded_from_card_badges(): void
    {
        $vinh = ServiceLocation::create(['name' => 'Vinh', 'status' => 'open', 'sort_order' => 1]);
        $danang = ServiceLocation::create(['name' => 'Đà Nẵng', 'status' => 'coming', 'sort_order' => 3]);

        $p = $this->product('Đồ test', 'do-test');
        $p->serviceLocations()->sync([$vinh->id, $danang->id]);

        $this->get('/thiet-bi')->assertInertia(function (Assert $page) {
            $product = collect($page->toArray()['props']['products'])->firstWhere('name', 'Đồ test');
            // Chỉ vị trí open hiện badge; 'coming' bị loại.
            $this->assertSame(['Vinh'], collect($product['locations'])->pluck('name')->all());
            // Có 1 vị trí open (Vinh) trong hệ thống, sản phẩm phủ hết -> all_locations true.
            $this->assertTrue($product['all_locations']);
        });
    }
}

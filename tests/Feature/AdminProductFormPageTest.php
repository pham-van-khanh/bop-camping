<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-o4kw — popup thêm/sửa sản phẩm tách thành màn hình riêng (Admin/ProductForm).
 * Kiểm tra route create/edit render đúng, edit nạp đủ dữ liệu, store redirect sang màn sửa,
 * và khách không vào được.
 */
class AdminProductFormPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function makeProduct(): Product
    {
        $category = Category::create(['name' => 'Lều', 'slug' => 'leu']);
        $loc = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Lều 2 người',
            'slug' => 'leu-2-nguoi',
            'price_per_day' => 50000,
            'quantity' => 3,
        ]);
        $product->serviceLocations()->attach($loc->id, ['quantity' => 3]);

        return $product;
    }

    /** @test */
    public function admin_sees_create_page(): void
    {
        Category::create(['name' => 'Bếp', 'slug' => 'bep']);

        $this->actingAs($this->admin())
            ->get(route('admin.products.create'))
            ->assertInertia(fn (Assert $p) => $p
                ->component('Admin/ProductForm')
                ->missing('product')
                ->has('categories')
                ->has('service_locations')
                ->has('accessory_options'));
    }

    /** @test */
    public function admin_sees_edit_page_with_product_data(): void
    {
        $product = $this->makeProduct();

        $this->actingAs($this->admin())
            ->get(route('admin.products.edit', $product))
            ->assertInertia(fn (Assert $p) => $p
                ->component('Admin/ProductForm')
                ->where('product.id', $product->id)
                ->where('product.name', 'Lều 2 người')
                ->has('product.service_location_ids', 1)
                ->has('product.stocks')
                ->has('categories'));
    }

    /** @test */
    public function store_redirects_to_edit_page(): void
    {
        $category = Category::create(['name' => 'Ghế', 'slug' => 'ghe']);
        $loc = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1]);

        $response = $this->actingAs($this->admin())->post(route('admin.products.store'), [
            'name' => 'Ghế xếp',
            'category_id' => $category->id,
            'price_per_day' => 20000,
            'status' => 'active',
            'service_location_ids' => [$loc->id],
            'stocks' => [$loc->id => 5],
        ]);

        $product = Product::where('name', 'Ghế xếp')->firstOrFail();
        $response->assertRedirect(route('admin.products.edit', $product));
        $response->assertSessionHas('success');
    }

    /** @test */
    public function non_admin_cannot_see_create_or_edit_page(): void
    {
        $product = $this->makeProduct();
        $guest = User::factory()->create(['is_admin' => false]);

        $this->actingAs($guest)->get(route('admin.products.create'))->assertRedirect(route('admin.login'));
        $this->actingAs($guest)->get(route('admin.products.edit', $product))->assertRedirect(route('admin.login'));
    }
}

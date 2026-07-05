<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-40r — /admin/products: tìm theo tên + lọc theo danh mục.
 * Dữ liệu dùng token phân biệt (không dấu, không chồng chuỗi) để collation-safe
 * trên cả sqlite (LIKE byte) lẫn MySQL utf8mb4_unicode_ci.
 */
class AdminProductSearchTest extends TestCase
{
    use RefreshDatabase;

    private Category $tents;

    private Category $stoves;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tents = Category::create(['name' => 'Leu', 'slug' => 'leu']);
        $this->stoves = Category::create(['name' => 'Bep', 'slug' => 'bep']);

        $this->product('Tent Alpha', $this->tents);
        $this->product('Tent Bravo', $this->tents);
        $this->product('Stove Charlie', $this->stoves);
    }

    private function product(string $name, Category $cat): Product
    {
        return Product::create([
            'category_id' => $cat->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'price_per_day' => 50000,
            'quantity' => 5,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /** @test */
    public function no_filter_lists_all_products(): void
    {
        $this->actingAs($this->admin())->get(route('admin.products'))->assertInertia(fn (Assert $p) => $p
            ->component('Admin/Products')
            ->has('products.data', 3)
            ->where('filters.search', '')
            ->where('filters.category', null));
    }

    /** @test */
    public function search_filters_by_name(): void
    {
        $this->actingAs($this->admin())->get(route('admin.products', ['search' => 'Tent']))->assertInertia(fn (Assert $p) => $p
            ->has('products.data', 2)
            ->where('filters.search', 'Tent'));
    }

    /** @test */
    public function search_matches_single_product(): void
    {
        $this->actingAs($this->admin())->get(route('admin.products', ['search' => 'Charlie']))->assertInertia(fn (Assert $p) => $p
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Stove Charlie'));
    }

    /** @test */
    public function category_filter_limits_results(): void
    {
        $this->actingAs($this->admin())->get(route('admin.products', ['category' => $this->stoves->id]))->assertInertia(fn (Assert $p) => $p
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Stove Charlie')
            ->where('filters.category', $this->stoves->id));
    }

    /** @test */
    public function search_and_category_combine(): void
    {
        // "Tent" khớp 2 sản phẩm nhưng chỉ 1 thuộc danh mục Leu + tên chứa "Alpha".
        $this->actingAs($this->admin())
            ->get(route('admin.products', ['search' => 'Alpha', 'category' => $this->tents->id]))
            ->assertInertia(fn (Assert $p) => $p
                ->has('products.data', 1)
                ->where('products.data.0.name', 'Tent Alpha'));

        // Kết hợp lệch danh mục → rỗng.
        $this->actingAs($this->admin())
            ->get(route('admin.products', ['search' => 'Alpha', 'category' => $this->stoves->id]))
            ->assertInertia(fn (Assert $p) => $p->has('products.data', 0));
    }
}

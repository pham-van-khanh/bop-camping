<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Epic 1 T4: trang chi tiết trả specs / setup_content / related_products. */
class ProductDetailV2Test extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(string $name, string $slug, string $status = 'active'): Product
    {
        $category = Category::firstOrCreate(['slug' => 'leu'], ['name' => 'Lều']);

        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'price_per_day' => 50000,
            'quantity' => 5,
            'status' => $status,
        ]);
    }

    /** @test */
    public function show_returns_specs_setup_content_and_related_products(): void
    {
        $p = $this->makeProduct('Lều 2 người', 'leu-2-nguoi');
        $p->update([
            'specs' => [['key' => 'Sức chứa', 'value' => '2 người']],
            'setup_content' => '<h2>Setup</h2><p>Bước 1</p>',
        ]);

        $active = $this->makeProduct('Đèn dây', 'den-day');
        $hidden = $this->makeProduct('Bàn xếp', 'ban-xep', 'hidden');
        $p->related()->sync([
            $active->id => ['sort_order' => 0],
            $hidden->id => ['sort_order' => 1],
        ]);

        $this->get('/thiet-bi/leu-2-nguoi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProductDetail')
                ->where('product.specs.0.key', 'Sức chứa')
                ->where('product.setup_content', '<h2>Setup</h2><p>Bước 1</p>')
                // Sản phẩm hidden bị loại khỏi "You may also like"
                ->count('related_products', 1)
                ->where('related_products.0.slug', 'den-day')
            );
    }

    /** @test */
    public function show_defaults_are_safe_when_columns_empty(): void
    {
        $this->makeProduct('Lều 2 người', 'leu-2-nguoi');

        $this->get('/thiet-bi/leu-2-nguoi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProductDetail')
                ->where('product.specs', [])
                ->where('product.setup_content', null)
                ->count('related_products', 0)
            );
    }
}

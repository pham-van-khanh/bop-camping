<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Epic 1 T2: cột specs/setup_content + quan hệ related (You may also like). */
class ProductSpecsRelatedTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(string $name, string $slug): Product
    {
        $category = Category::firstOrCreate(['slug' => 'leu'], ['name' => 'Lều']);

        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'price_per_day' => 50000,
            'quantity' => 5,
        ]);
    }

    /** @test */
    public function specs_json_roundtrips_as_array(): void
    {
        $p = $this->makeProduct('Lều 2 người', 'leu-2-nguoi');
        $p->update(['specs' => [
            ['key' => 'Sức chứa', 'value' => '2 người'],
            ['key' => 'Trọng lượng', 'value' => '2.1 kg'],
        ]]);

        $fresh = Product::find($p->id);
        $this->assertSame('Sức chứa', $fresh->specs[0]['key']);
        $this->assertSame('2.1 kg', $fresh->specs[1]['value']);
        $this->assertCount(2, $fresh->specs);
    }

    /** @test */
    public function related_returns_products_in_sort_order(): void
    {
        $p = $this->makeProduct('Lều 2 người', 'leu-2-nguoi');
        $a = $this->makeProduct('Đèn dây', 'den-day');
        $b = $this->makeProduct('Bàn xếp', 'ban-xep');

        // b xếp trước a
        $p->related()->sync([$b->id => ['sort_order' => 0], $a->id => ['sort_order' => 1]]);

        $this->assertSame(['ban-xep', 'den-day'], $p->related->pluck('slug')->all());
    }
}

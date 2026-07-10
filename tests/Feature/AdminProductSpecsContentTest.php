<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Epic 1 T3: admin lưu specs key–value, related picker, màn soạn nội dung setup. */
class AdminProductSpecsContentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function makeProduct(string $name = 'Lều 2 người', string $slug = 'leu-2-nguoi'): Product
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

    /** Payload update tối thiểu hợp lệ (đủ các field bắt buộc hiện có). */
    private function basePayload(Product $p): array
    {
        $loc = ServiceLocation::firstOrCreate(
            ['name' => 'Vinh'],
            ['area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1],
        );

        return [
            'name' => $p->name,
            'category_id' => $p->category_id,
            'price_per_day' => 50000,
            'quantity' => 5,
            'status' => 'active',
            'service_location_ids' => [$loc->id],
        ];
    }

    /** @test */
    public function update_saves_specs_and_related_in_order(): void
    {
        $p = $this->makeProduct();
        $a = $this->makeProduct('Đèn dây', 'den-day');
        $b = $this->makeProduct('Bàn xếp', 'ban-xep');

        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $p), $this->basePayload($p) + [
                'specs' => [
                    ['key' => ' Sức chứa ', 'value' => '2 người'],
                    ['key' => 'Trọng lượng', 'value' => '2.1 kg'],
                ],
                'related_ids' => [$b->id, $a->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $p->refresh();
        $this->assertSame([
            ['key' => 'Sức chứa', 'value' => '2 người'],
            ['key' => 'Trọng lượng', 'value' => '2.1 kg'],
        ], $p->specs);
        $this->assertSame(['ban-xep', 'den-day'], $p->related->pluck('slug')->all());
    }

    /** @test */
    public function specs_row_missing_value_is_rejected(): void
    {
        $p = $this->makeProduct();

        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $p), $this->basePayload($p) + [
                'specs' => [['key' => 'Sức chứa', 'value' => '']],
            ])
            ->assertSessionHasErrors('specs.0.value');
    }

    /** @test */
    public function related_cannot_include_self_and_empty_specs_saved_as_null(): void
    {
        $p = $this->makeProduct();

        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $p), $this->basePayload($p) + [
                'specs' => [],
                'related_ids' => [$p->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $p->refresh();
        $this->assertNull($p->specs);
        $this->assertCount(0, $p->related);
    }

    /** @test */
    public function content_screen_renders_and_update_sanitizes_html(): void
    {
        $p = $this->makeProduct();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.products.content.edit', $p))
            ->assertOk();

        $this->actingAs($admin)
            ->put(route('admin.products.content.update', $p), [
                'setup_content' => '<h2>Setup</h2><script>alert(1)</script><p>Bước 1</p>',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $p->refresh();
        $this->assertStringContainsString('<h2>Setup</h2>', $p->setup_content);
        $this->assertStringNotContainsString('<script', $p->setup_content);
    }

    /** @test */
    public function content_update_with_empty_html_clears_column(): void
    {
        $p = $this->makeProduct();
        $p->update(['setup_content' => '<p>cũ</p>']);

        $this->actingAs($this->admin())
            ->put(route('admin.products.content.update', $p), ['setup_content' => '<p><br></p>'])
            ->assertRedirect();

        $this->assertNull($p->refresh()->setup_content);
    }

    /** @test */
    public function guest_cannot_open_content_screen(): void
    {
        $p = $this->makeProduct();

        $this->get('/admin/products/'.$p->id.'/noi-dung')->assertRedirect('/admin/login');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * bopcamping-k5u — Admin CRUD danh mục + upload ảnh khi SỬA
 * (phần sản phẩm đã có ở AdminProductTest).
 */
class AdminCategoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'phone' => '0900000001']);
    }

    /** @test */
    public function admin_can_create_category(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.categories.store'), ['name' => 'Lều trại'])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('categories', ['name' => 'Lều trại', 'slug' => 'leu-trai']);
    }

    /** @test */
    public function admin_can_update_category_image_via_spoofed_post(): void
    {
        Storage::fake('media');
        $cat = Category::create(['name' => 'Cũ', 'slug' => 'cu']);

        $this->actingAs($this->admin())->post(route('admin.categories.update', $cat), [
            '_method' => 'put',
            'name' => 'Mới',
            'image' => UploadedFile::fake()->create('c.jpg', 50, 'image/jpeg'),
        ])->assertRedirect()->assertSessionHas('success');

        $cat->refresh();
        $this->assertSame('Mới', $cat->name);
        $this->assertNotNull($cat->image);
        Storage::disk('media')->assertExists($cat->image);
    }

    /** @test */
    public function category_image_rejects_svg(): void
    {
        Storage::fake('media');

        $this->actingAs($this->admin())->post(route('admin.categories.store'), [
            'name' => 'X',
            'image' => UploadedFile::fake()->create('evil.svg', 10, 'image/svg+xml'),
        ])->assertSessionHasErrors('image');
    }

    /** @test */
    public function cannot_delete_category_with_products(): void
    {
        $cat = Category::create(['name' => 'Lều', 'slug' => 'leu']);
        Product::create([
            'category_id' => $cat->id,
            'name' => 'P',
            'slug' => 'p',
            'price_per_day' => 1,
            'quantity' => 1,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.categories.destroy', $cat))
            ->assertSessionHasErrors('message');

        $this->assertDatabaseHas('categories', ['id' => $cat->id]);
    }

    /** @test */
    public function can_delete_empty_category(): void
    {
        $cat = Category::create(['name' => 'Trống', 'slug' => 'trong']);

        $this->actingAs($this->admin())
            ->delete(route('admin.categories.destroy', $cat))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseMissing('categories', ['id' => $cat->id]);
    }

    /** @test */
    public function non_admin_cannot_create_category(): void
    {
        $customer = User::factory()->create(['is_admin' => false, 'phone' => '0911111111']);

        $this->actingAs($customer)
            ->post(route('admin.categories.store'), ['name' => 'X'])
            ->assertRedirect(route('admin.login'));

        $this->assertDatabaseMissing('categories', ['name' => 'X']);
    }
}

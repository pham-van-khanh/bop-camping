<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProductTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function makeProduct(?string $thumbnail = null): Product
    {
        $category = Category::create(['name' => 'Lều', 'slug' => 'leu']);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Lều 2 người',
            'slug' => 'leu-2-nguoi',
            'price_per_day' => 50000,
            'quantity' => 5,
            'thumbnail' => $thumbnail,
        ]);
    }

    /**
     * C1 regression: sửa sản phẩm kèm ảnh mới phải lưu được ảnh và xoá ảnh cũ.
     * Frontend gửi POST + _method=put (vì PHP không nạp $_FILES cho PUT thật),
     * controller phải nhận file và cập nhật thumbnail.
     *
     * @test
     */
    public function update_with_new_thumbnail_persists_file_and_drops_old(): void
    {
        Storage::fake('public');
        $product = $this->makeProduct(thumbnail: 'products/old.jpg');
        Storage::disk('public')->put('products/old.jpg', 'old');

        $response = $this->actingAs($this->admin())->post(
            route('admin.products.update', $product),
            [
                '_method' => 'put',
                'name' => $product->name,
                'category_id' => $product->category_id,
                'price_per_day' => 50000,
                'quantity' => 5,
                'status' => 'active',
                'thumbnail' => UploadedFile::fake()->create('new.jpg', 100, 'image/jpeg'),
            ],
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $product->refresh();
        $this->assertNotNull($product->thumbnail);
        $this->assertNotSame('products/old.jpg', $product->thumbnail);
        Storage::disk('public')->assertExists($product->thumbnail);
        Storage::disk('public')->assertMissing('products/old.jpg');
    }

    /**
     * M1 regression: chặn upload SVG (stored XSS — CWE-434), chỉ cho jpg/png/webp.
     *
     * @test
     */
    public function thumbnail_rejects_svg(): void
    {
        Storage::fake('public');
        $product = $this->makeProduct();

        $response = $this->actingAs($this->admin())->post(
            route('admin.products.update', $product),
            [
                '_method' => 'put',
                'name' => $product->name,
                'category_id' => $product->category_id,
                'price_per_day' => 50000,
                'quantity' => 5,
                'thumbnail' => UploadedFile::fake()->create('evil.svg', 10, 'image/svg+xml'),
            ],
        );

        $response->assertSessionHasErrors('thumbnail');
    }

    /**
     * Khách (không phải admin) không được vào CRUD sản phẩm.
     *
     * @test
     */
    public function non_admin_cannot_update_product(): void
    {
        $product = $this->makeProduct();
        $guest = User::factory()->create(['is_admin' => false]);

        $this->actingAs($guest)
            ->put(route('admin.products.update', $product), [
                'name' => 'Hacked',
                'category_id' => $product->category_id,
                'price_per_day' => 1,
                'quantity' => 1,
            ])
            ->assertRedirect(route('admin.login'));

        $this->assertSame('Lều 2 người', $product->fresh()->name);
    }
}

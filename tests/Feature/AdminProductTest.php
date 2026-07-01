<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ServiceLocation;
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
        $loc = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1]);

        $response = $this->actingAs($this->admin())->post(
            route('admin.products.update', $product),
            [
                '_method' => 'put',
                'name' => $product->name,
                'category_id' => $product->category_id,
                'price_per_day' => 50000,
                'quantity' => 5,
                'status' => 'active',
                'service_location_ids' => [$loc->id],
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

    /**
     * bopcamping-6a3 — admin có thể upload ảnh + video cho sản phẩm, và xoá được video.
     *
     * @test
     */
    public function admin_can_upload_and_delete_image_and_video(): void
    {
        Storage::fake('public');
        $product = $this->makeProduct();

        $this->actingAs($this->admin())->post(route('admin.products.images.store', $product), [
            'images' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->create('clip.mp4', 800, 'video/mp4'),
            ],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(2, $product->images()->count());
        $this->assertSame(['image', 'video'], $product->images()->orderBy('id')->pluck('type')->all());

        $videoImage = $product->images()->where('type', 'video')->first();
        $this->actingAs($this->admin())
            ->delete(route('admin.products.images.destroy', [$product, $videoImage]))
            ->assertRedirect();

        $this->assertDatabaseMissing('product_images', ['id' => $videoImage->id]);
        Storage::disk('public')->assertMissing($videoImage->path);
    }

    /**
     * bopcamping-6a3 — chặn upload mimetype không hợp lệ (chỉ nhận ảnh/video đã khai báo).
     *
     * @test
     */
    public function image_upload_rejects_invalid_mimetype(): void
    {
        Storage::fake('public');
        $product = $this->makeProduct();

        $this->actingAs($this->admin())->post(route('admin.products.images.store', $product), [
            'images' => [
                UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
            ],
        ])->assertSessionHasErrors('images.0');

        $this->assertSame(0, $product->images()->count());
    }

    /**
     * 76f — IDOR: không xoá được ảnh qua URL sản phẩm khác (CWE-639).
     *
     * @test
     */
    public function cannot_delete_image_through_wrong_product(): void
    {
        Storage::fake('public');
        $productA = $this->makeProduct();
        $category = Category::create(['name' => 'Bếp', 'slug' => 'bep']);
        $productB = Product::create([
            'category_id' => $category->id,
            'name' => 'Bếp gas',
            'slug' => 'bep-gas',
            'price_per_day' => 30000,
            'quantity' => 2,
        ]);
        $image = $productA->images()->create(['path' => 'products/a.jpg', 'sort_order' => 1]);

        // Xoá ảnh của A nhưng qua URL của B → 404, ảnh còn nguyên.
        $this->actingAs($this->admin())
            ->delete(route('admin.products.images.destroy', [$productB->id, $image->id]))
            ->assertNotFound();
        $this->assertDatabaseHas('product_images', ['id' => $image->id]);

        // Đúng sản phẩm → xoá được.
        $this->actingAs($this->admin())
            ->delete(route('admin.products.images.destroy', [$productA->id, $image->id]))
            ->assertRedirect();
        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
    }
}

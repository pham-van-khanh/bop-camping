<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * bopcamping-byeb — sắp xếp (kéo-thả) + tái sử dụng ảnh (chia sẻ file, refcount)
 * cho gallery product & combo.
 */
class AdminMediaReorderReuseTest extends TestCase
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

    private function makeCombo(): Combo
    {
        return Combo::create(['name' => 'Combo A', 'slug' => 'combo-a', 'combo_price' => 90000]);
    }

    /** @test */
    public function reorder_updates_sort_order(): void
    {
        $product = $this->makeProduct();
        $a = $product->images()->create(['path' => 'p/a.jpg', 'sort_order' => 0, 'type' => 'image']);
        $b = $product->images()->create(['path' => 'p/b.jpg', 'sort_order' => 1, 'type' => 'image']);
        $c = $product->images()->create(['path' => 'p/c.jpg', 'sort_order' => 2, 'type' => 'image']);

        $this->actingAs($this->admin())
            ->post(route('admin.products.images.reorder', $product), ['image_ids' => [$c->id, $a->id, $b->id]])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(0, $c->fresh()->sort_order);
        $this->assertSame(1, $a->fresh()->sort_order);
        $this->assertSame(2, $b->fresh()->sort_order);
    }

    /** @test */
    public function reorder_ignores_image_ids_from_other_products(): void
    {
        $product = $this->makeProduct();
        $mine = $product->images()->create(['path' => 'p/a.jpg', 'sort_order' => 0, 'type' => 'image']);

        $other = $this->makeProduct('Ghế', 'ghe');
        $foreign = $other->images()->create(['path' => 'p/x.jpg', 'sort_order' => 7, 'type' => 'image']);

        $this->actingAs($this->admin())
            ->post(route('admin.products.images.reorder', $product), ['image_ids' => [$foreign->id, $mine->id]])
            ->assertRedirect();

        // Ảnh của product khác KHÔNG bị đụng (chống IDOR).
        $this->assertSame(7, $foreign->fresh()->sort_order);
        // Ảnh của mình được đặt theo vị trí trong mảng (index 1).
        $this->assertSame(1, $mine->fresh()->sort_order);
    }

    /** @test */
    public function attach_reuses_existing_image_sharing_the_same_file(): void
    {
        Storage::fake('media');
        $source = $this->makeProduct('Nguồn', 'nguon');
        $img = $source->images()->create(['path' => 'admin/products/shared.jpg', 'sort_order' => 0, 'type' => 'image']);

        $target = $this->makeProduct('Đích', 'dich');

        $this->actingAs($this->admin())
            ->post(route('admin.products.images.attach', $target), [
                'sources' => [['type' => 'product', 'id' => $img->id]],
            ])->assertRedirect()->assertSessionHas('success');

        // Row mới ở target trỏ cùng path (chia sẻ, không copy file).
        $this->assertSame(1, $target->images()->count());
        $this->assertSame('admin/products/shared.jpg', $target->images()->first()->path);
    }

    /** @test */
    public function attach_works_cross_type_combo_reuses_product_image(): void
    {
        $source = $this->makeProduct('Nguồn', 'nguon');
        $img = $source->images()->create(['path' => 'admin/products/x.jpg', 'sort_order' => 0, 'type' => 'image']);

        $combo = $this->makeCombo();

        $this->actingAs($this->admin())
            ->post(route('admin.combos.images.attach', $combo), [
                'sources' => [['type' => 'product', 'id' => $img->id]],
            ])->assertRedirect();

        $this->assertSame('admin/products/x.jpg', $combo->images()->first()->path);
    }

    /** @test */
    public function attach_skips_images_already_in_gallery(): void
    {
        $product = $this->makeProduct();
        $existing = $product->images()->create(['path' => 'p/dup.jpg', 'sort_order' => 0, 'type' => 'image']);

        $this->actingAs($this->admin())
            ->post(route('admin.products.images.attach', $product), [
                'sources' => [['type' => 'product', 'id' => $existing->id]],
            ])->assertRedirect();

        // Không tạo row trùng path.
        $this->assertSame(1, $product->images()->count());
    }

    /** @test */
    public function deleting_shared_image_keeps_file_until_last_reference_gone(): void
    {
        Storage::fake('media');
        $path = 'admin/products/shared.jpg';
        Storage::disk('media')->put($path, 'x');

        $a = $this->makeProduct('A', 'a');
        $b = $this->makeProduct('B', 'b');
        $imgA = $a->images()->create(['path' => $path, 'sort_order' => 0, 'type' => 'image']);
        $imgB = $b->images()->create(['path' => $path, 'sort_order' => 0, 'type' => 'image']);

        // Xoá ở A: còn B tham chiếu → file phải CÒN.
        $this->actingAs($this->admin())
            ->delete(route('admin.products.images.destroy', [$a, $imgA]))->assertRedirect();
        Storage::disk('media')->assertExists($path);
        $this->assertDatabaseMissing('product_images', ['id' => $imgA->id]);

        // Xoá ở B: hết tham chiếu → file bị xoá thật.
        $this->actingAs($this->admin())
            ->delete(route('admin.products.images.destroy', [$b, $imgB]))->assertRedirect();
        Storage::disk('media')->assertMissing($path);
    }

    /** @test */
    public function destroying_product_keeps_file_still_used_by_a_combo(): void
    {
        Storage::fake('media');
        $path = 'admin/products/shared.jpg';
        Storage::disk('media')->put($path, 'x');

        $product = $this->makeProduct();
        $product->images()->create(['path' => $path, 'sort_order' => 0, 'type' => 'image']);

        $combo = $this->makeCombo();
        $combo->images()->create(['path' => $path, 'sort_order' => 0, 'type' => 'image']);

        $this->actingAs($this->admin())
            ->delete(route('admin.products.destroy', $product))->assertRedirect();

        // Combo vẫn dùng file → không được xoá file vật lý.
        Storage::disk('media')->assertExists($path);
        $this->assertSame(1, $combo->images()->count());
    }

    /** @test */
    public function gif_upload_is_accepted(): void
    {
        Storage::fake('media');
        $product = $this->makeProduct();

        $this->actingAs($this->admin())
            ->post(route('admin.products.images.store', $product), [
                'images' => [UploadedFile::fake()->create('anim.gif', 100, 'image/gif')],
            ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(1, $product->images()->count());
        $this->assertSame('image', $product->images()->first()->type);
    }
}

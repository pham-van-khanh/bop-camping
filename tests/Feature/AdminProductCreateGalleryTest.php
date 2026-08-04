<?php

namespace Tests\Feature;

use App\Jobs\GenerateMediaVariants;
use App\Models\Category;
use App\Models\Combo;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-7czf — form THÊM sản phẩm phải thêm được nhiều ảnh phụ và chọn ảnh
 * có sẵn ngay lúc tạo.
 *
 * Trước đây gallery bị chặn sau `isEdit && product` nên trang thêm mới chỉ có
 * đúng một input "Ảnh đại diện" (multiple=false) — admin không chọn được nhiều
 * ảnh, cũng không có nút "Chọn ảnh có sẵn".
 */
class AdminProductCreateGalleryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function location(): ServiceLocation
    {
        return ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1]);
    }

    private function category(): Category
    {
        return Category::create(['name' => 'Ghế', 'slug' => 'ghe']);
    }

    /** @return array<string, mixed> */
    private function basePayload(Category $c, ServiceLocation $l): array
    {
        return [
            'name' => 'Ghế xếp',
            'category_id' => $c->id,
            'price_per_day' => 20000,
            'status' => 'active',
            'service_location_ids' => [$l->id],
            'stocks' => [$l->id => 5],
        ];
    }

    public function test_trang_them_moi_cap_kho_anh_cho_picker(): void
    {
        $this->category();

        // mediaLibrary là prop optional (lazy) — chỉ có mặt khi FE hỏi riêng nó,
        // đúng như DraftMediaGallery làm khi admin mở picker.
        $this->actingAs($this->admin())
            ->get(route('admin.products.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ProductForm')
                ->missing('mediaLibrary'));

        // Khi FE hỏi riêng mediaLibrary (partial reload lúc mở picker) thì phải có.
        // Thiếu X-Inertia-Version sẽ bị 409 (asset version mismatch), không phải lỗi route.
        $this->actingAs($this->admin())
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => Inertia::getVersion(),
                'X-Inertia-Partial-Component' => 'Admin/ProductForm',
                'X-Inertia-Partial-Data' => 'mediaLibrary',
            ])
            ->get(route('admin.products.create'))
            ->assertOk()
            // Response partial là JSON rút gọn (không đủ khoá cho assertInertia).
            ->assertJsonStructure(['props' => ['mediaLibrary']]);
    }

    public function test_tao_san_pham_luu_nhieu_anh_phu_cung_luc(): void
    {
        Storage::fake('media');
        Queue::fake();
        $c = $this->category();
        $l = $this->location();

        $this->actingAs($this->admin())->post(route('admin.products.store'), $this->basePayload($c, $l) + [
            'gallery' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.jpg'),
                UploadedFile::fake()->image('c.jpg'),
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $product = Product::where('name', 'Ghế xếp')->firstOrFail();
        $this->assertSame(3, $product->images()->count());

        // sort_order liên tiếp từ 1 — thứ tự đúng như admin chọn.
        $this->assertSame([1, 2, 3], $product->images()->orderBy('sort_order')->pluck('sort_order')->all());
        foreach ($product->images as $img) {
            Storage::disk('media')->assertExists($img->path);
        }

        // Ảnh mới phải được đưa vào hàng đợi sinh biến thể (bopcamping-ix4n).
        Queue::assertPushed(GenerateMediaVariants::class, fn (GenerateMediaVariants $job) => count($job->sourcePaths) === 3);
    }

    public function test_tao_san_pham_gan_duoc_anh_co_san_dung_chung_file(): void
    {
        Storage::fake('media');
        $c = $this->category();
        $l = $this->location();

        // Ảnh sẵn có thuộc một sản phẩm khác + một combo.
        $other = Product::create(['category_id' => $c->id, 'name' => 'Bàn', 'slug' => 'ban', 'price_per_day' => 1, 'quantity' => 1]);
        $otherImage = $other->images()->create(['path' => 'admin/products/dung-chung.jpg', 'sort_order' => 1, 'type' => 'image']);

        $combo = Combo::create(['name' => 'Combo A', 'slug' => 'combo-a', 'combo_price' => 1000, 'is_active' => true]);
        $comboImage = $combo->images()->create(['path' => 'admin/combos/tu-combo.jpg', 'sort_order' => 1, 'type' => 'image']);

        $this->actingAs($this->admin())->post(route('admin.products.store'), $this->basePayload($c, $l) + [
            'gallery_sources' => [
                ['type' => 'product', 'id' => $otherImage->id],
                ['type' => 'combo', 'id' => $comboImage->id],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $product = Product::where('name', 'Ghế xếp')->firstOrFail();

        // CHIA SẺ file, không copy — path trùng với ảnh nguồn. Không khẳng định thứ tự:
        // MediaRef::resolveSources trả product trước combo, không theo thứ tự admin tick.
        $this->assertEqualsCanonicalizing(
            ['admin/combos/tu-combo.jpg', 'admin/products/dung-chung.jpg'],
            $product->images()->pluck('path')->all()
        );
        // Ảnh nguồn không bị ảnh hưởng.
        $this->assertSame(1, $other->images()->count());
    }

    public function test_tron_ca_upload_va_anh_co_san(): void
    {
        Storage::fake('media');
        $c = $this->category();
        $l = $this->location();
        $other = Product::create(['category_id' => $c->id, 'name' => 'Bàn', 'slug' => 'ban', 'price_per_day' => 1, 'quantity' => 1]);
        $otherImage = $other->images()->create(['path' => 'admin/products/dung-chung.jpg', 'sort_order' => 1, 'type' => 'image']);

        $this->actingAs($this->admin())->post(route('admin.products.store'), $this->basePayload($c, $l) + [
            'gallery' => [UploadedFile::fake()->image('a.jpg')],
            'gallery_sources' => [['type' => 'product', 'id' => $otherImage->id]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $product = Product::where('name', 'Ghế xếp')->firstOrFail();
        $this->assertSame(2, $product->images()->count());
        // File upload xếp trước, ảnh dùng chung nối sau — sort_order không trùng nhau.
        $this->assertSame([1, 2], $product->images()->orderBy('sort_order')->pluck('sort_order')->all());
    }

    public function test_tao_san_pham_khong_kem_anh_van_chay_binh_thuong(): void
    {
        Storage::fake('media');
        $c = $this->category();
        $l = $this->location();

        $this->actingAs($this->admin())->post(route('admin.products.store'), $this->basePayload($c, $l))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(0, Product::where('name', 'Ghế xếp')->firstOrFail()->images()->count());
    }

    public function test_tu_choi_file_khong_phai_anh_hoac_video(): void
    {
        Storage::fake('media');
        $c = $this->category();
        $l = $this->location();

        $this->actingAs($this->admin())->post(route('admin.products.store'), $this->basePayload($c, $l) + [
            'gallery' => [UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')],
        ])->assertSessionHasErrors('gallery.0');

        $this->assertDatabaseMissing('products', ['name' => 'Ghế xếp']);
    }

    public function test_tu_choi_qua_12_anh_mot_lan(): void
    {
        Storage::fake('media');
        $c = $this->category();
        $l = $this->location();

        $this->actingAs($this->admin())->post(route('admin.products.store'), $this->basePayload($c, $l) + [
            'gallery' => array_map(fn (int $i) => UploadedFile::fake()->image("a{$i}.jpg"), range(1, 13)),
        ])->assertSessionHasErrors('gallery');

        $this->assertDatabaseMissing('products', ['name' => 'Ghế xếp']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\MediaVariantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Tests\TestCase;

/**
 * srcset phải đi được tới props Inertia thật (bopcamping-ix4n) — service chạy đúng
 * mà không nối vào controller thì khách vẫn tải ảnh full như cũ.
 */
class ProductImageSrcsetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
        MediaVariantService::flushMemo();
    }

    private function putImage(string $path, int $width, int $height): void
    {
        $img = (new ImageManager(new Driver))->createImage($width, $height);
        Storage::disk('media')->put($path, (string) $img->encode(new JpegEncoder(quality: 90)));
    }

    private function makeProduct(): Product
    {
        $category = Category::firstOrCreate(['slug' => 'leu'], ['name' => 'Lều']);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Bàn gấp gọn',
            'slug' => 'ban-gap-gon',
            'price_per_day' => 65000,
            'quantity' => 4,
            'status' => 'active',
            'thumbnail' => 'admin/products/thumb.jpg',
        ]);
    }

    public function test_trang_chi_tiet_tra_ve_srcset_cho_anh_gallery_va_thumbnail(): void
    {
        $product = $this->makeProduct();
        $this->putImage('admin/products/thumb.jpg', 2000, 1500);
        $this->putImage('admin/products/gallery.jpg', 2000, 1500);
        $product->images()->create(['path' => 'admin/products/gallery.jpg', 'sort_order' => 1, 'type' => 'image']);

        $svc = MediaVariantService::make();
        $svc->generate('admin/products/thumb.jpg');
        $svc->generate('admin/products/gallery.jpg');
        MediaVariantService::flushMemo();

        $props = $this->get('/thiet-bi/ban-gap-gon')->assertOk()->inertiaProps();

        $this->assertStringContainsString('thumb-400.webp 400w', $props['product']['thumbnail_srcset']);
        $this->assertStringContainsString('thumb-1600.webp 1600w', $props['product']['thumbnail_srcset']);

        $image = $props['product']['images'][0];
        $this->assertStringContainsString('gallery-1600.webp', $image['url']);
        $this->assertStringContainsString('gallery-400.webp 400w', $image['srcset']);
    }

    public function test_anh_chua_backfill_van_render_binh_thuong_voi_srcset_null(): void
    {
        $product = $this->makeProduct();
        $this->putImage('admin/products/thumb.jpg', 900, 600);
        $this->putImage('admin/products/gallery.jpg', 900, 600);
        $product->images()->create(['path' => 'admin/products/gallery.jpg', 'sort_order' => 1, 'type' => 'image']);

        $props = $this->get('/thiet-bi/ban-gap-gon')->assertOk()->inertiaProps();

        $this->assertNull($props['product']['thumbnail_srcset']);
        $this->assertStringContainsString('thumb.jpg', $props['product']['thumbnail']);
        $this->assertNull($props['product']['images'][0]['srcset']);
        $this->assertStringContainsString('gallery.jpg', $props['product']['images'][0]['url']);
    }

    public function test_video_khong_co_srcset_va_serve_nguyen_file(): void
    {
        $product = $this->makeProduct();
        $this->putImage('admin/products/thumb.jpg', 900, 600);
        Storage::disk('media')->put('admin/products/clip.mp4', 'fake-video');
        $product->images()->create(['path' => 'admin/products/clip.mp4', 'sort_order' => 1, 'type' => 'video']);

        $props = $this->get('/thiet-bi/ban-gap-gon')->assertOk()->inertiaProps();

        $this->assertNull($props['product']['images'][0]['srcset']);
        $this->assertStringContainsString('clip.mp4', $props['product']['images'][0]['url']);
    }

    public function test_danh_sach_san_pham_khong_bi_n_plus_1_khi_lay_bien_the(): void
    {
        $category = Category::firstOrCreate(['slug' => 'leu'], ['name' => 'Lều']);

        // 5 sản phẩm, mỗi cái 1 thumbnail + 2 ảnh gallery = 15 file ảnh.
        foreach (range(1, 5) as $i) {
            $this->putImage("admin/products/t{$i}.jpg", 1200, 900);
            $p = Product::create([
                'category_id' => $category->id,
                'name' => "SP {$i}",
                'slug' => "sp-{$i}",
                'price_per_day' => 50000,
                'quantity' => 3,
                'status' => 'active',
                'thumbnail' => "admin/products/t{$i}.jpg",
            ]);
            foreach (range(1, 2) as $j) {
                $this->putImage("admin/products/g{$i}-{$j}.jpg", 1200, 900);
                $p->images()->create(['path' => "admin/products/g{$i}-{$j}.jpg", 'sort_order' => $j, 'type' => 'image']);
            }
        }

        $svc = MediaVariantService::make();
        foreach (MediaVariantSourcePaths::all() as $path) {
            $svc->generate($path);
        }
        MediaVariantService::flushMemo();

        DB::enableQueryLog();
        $this->get('/thiet-bi')->assertOk();
        $variantQueries = collect(DB::getQueryLog())
            ->filter(fn (array $q) => str_contains($q['query'], 'media_variants'))
            ->count();

        // warm() gom hết 15 file vào 1 query. Không có warm thì đây sẽ là 15.
        $this->assertSame(1, $variantQueries, 'Biến thể phải nạp trong đúng 1 query');
    }
}

/** Gom path ảnh test cho gọn (chỉ dùng trong file test này). */
final class MediaVariantSourcePaths
{
    /** @return list<string> */
    public static function all(): array
    {
        $paths = [];
        foreach (range(1, 5) as $i) {
            $paths[] = "admin/products/t{$i}.jpg";
            foreach (range(1, 2) as $j) {
                $paths[] = "admin/products/g{$i}-{$j}.jpg";
            }
        }

        return $paths;
    }
}

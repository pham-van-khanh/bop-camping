<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
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
 * bopcamping-hjde — ảnh combo ở trang chủ trước đây serve thẳng file admin upload
 * NGUYÊN BẢN (Storage::disk('media')->url(...)), bỏ qua hệ resize WebP mà ProductCard
 * đã dùng đúng qua MediaVariantService. PageSpeed mobile phát hiện qua tổng tải trang
 * ~5.8MB — test này giữ để không lặp lại.
 */
class ComboImageSrcsetTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
        MediaVariantService::flushMemo();

        $this->category = Category::firstOrCreate(['slug' => 'leu'], ['name' => 'Lều']);
    }

    private function putImage(string $path, int $width, int $height): void
    {
        $img = (new ImageManager(new Driver))->createImage($width, $height);
        Storage::disk('media')->put($path, (string) $img->encode(new JpegEncoder(quality: 90)));
    }

    private function makeCombo(string $slug, string $coverPath): Combo
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => "Lều {$slug}",
            'slug' => "leu-{$slug}",
            'price_per_day' => 100000,
            'quantity' => 3,
        ]);

        $combo = Combo::create([
            'name' => "Combo {$slug}",
            'slug' => $slug,
            'combo_price' => 150000,
            'deposit' => 300000,
            'is_active' => true,
        ]);
        $combo->items()->create(['product_id' => $product->id, 'quantity' => 1]);
        $combo->images()->create(['path' => $coverPath, 'sort_order' => 1, 'type' => 'image']);

        return $combo;
    }

    public function test_trang_chu_tra_ve_srcset_cho_anh_cover_combo(): void
    {
        $this->putImage('admin/combos/cover.jpg', 2000, 1500);
        $this->makeCombo('combo-cap-doi', 'admin/combos/cover.jpg');

        MediaVariantService::make()->generate('admin/combos/cover.jpg');
        MediaVariantService::flushMemo();

        $props = $this->get('/')->assertOk()->inertiaProps();
        $combo = collect($props['featured_combos'])->firstWhere('slug', 'combo-cap-doi');

        $this->assertStringContainsString('cover-1600.webp', $combo['image']);
        $this->assertStringContainsString('cover-400.webp 400w', $combo['image_srcset']);
    }

    public function test_anh_combo_chua_backfill_van_render_binh_thuong_voi_srcset_null(): void
    {
        $this->putImage('admin/combos/cover.jpg', 900, 600);
        $this->makeCombo('combo-cap-doi', 'admin/combos/cover.jpg');

        $props = $this->get('/')->assertOk()->inertiaProps();
        $combo = collect($props['featured_combos'])->firstWhere('slug', 'combo-cap-doi');

        $this->assertNull($combo['image_srcset']);
        $this->assertStringContainsString('cover.jpg', $combo['image']);
    }

    public function test_video_cover_khong_co_srcset_va_serve_nguyen_file(): void
    {
        Storage::disk('media')->put('admin/combos/clip.mp4', 'fake-video');
        $combo = $this->makeCombo('combo-cap-doi', 'admin/combos/cover.jpg');
        $combo->images()->delete();
        $combo->images()->create(['path' => 'admin/combos/clip.mp4', 'sort_order' => 1, 'type' => 'video']);

        $props = $this->get('/')->assertOk()->inertiaProps();
        $combo = collect($props['featured_combos'])->firstWhere('slug', 'combo-cap-doi');

        $this->assertNull($combo['image_srcset']);
        $this->assertStringContainsString('clip.mp4', $combo['image']);
    }

    public function test_nhieu_combo_khong_bi_n_plus_1_khi_lay_bien_the_anh_cover(): void
    {
        foreach (range(1, 4) as $i) {
            $this->putImage("admin/combos/c{$i}.jpg", 1200, 900);
            $this->makeCombo("combo-{$i}", "admin/combos/c{$i}.jpg");
            MediaVariantService::make()->generate("admin/combos/c{$i}.jpg");
        }
        MediaVariantService::flushMemo();

        DB::enableQueryLog();
        $this->get('/')->assertOk();
        $variantQueries = collect(DB::getQueryLog())
            ->filter(fn (array $q) => str_contains($q['query'], 'media_variants'))
            ->count();

        // warm() gom hết cover ảnh vào 1 query. Không có warm thì đây sẽ là 4 (1/combo).
        $this->assertSame(1, $variantQueries, 'Biến thể ảnh cover combo phải nạp trong đúng 1 query');
    }
}

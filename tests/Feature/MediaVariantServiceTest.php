<?php

namespace Tests\Feature;

use App\Models\MediaVariant;
use App\Services\MediaVariantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Tests\TestCase;

class MediaVariantServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
        // Memo là static → phải dọn giữa các test, không thì rò trạng thái.
        MediaVariantService::flushMemo();
    }

    /** Đặt một ảnh JPEG thật (kích thước cho trước) lên disk media giả. */
    private function putImage(string $path, int $width, int $height): void
    {
        $img = (new ImageManager(new Driver))->createImage($width, $height);
        Storage::disk('media')->put($path, (string) $img->encode(new JpegEncoder(quality: 90)));
    }

    public function test_sinh_du_bac_nho_hon_anh_goc(): void
    {
        $this->putImage('admin/products/big.jpg', 2400, 1600);

        $created = MediaVariantService::make()->generate('admin/products/big.jpg');

        // 2400px → bậc 400, 800, 1600 (đều nhỏ hơn gốc); không có bậc 2400.
        $this->assertSame(3, $created);
        $this->assertSame(
            [400, 800, 1600],
            MediaVariant::where('source_path', 'admin/products/big.jpg')->orderBy('width')->pluck('width')->all()
        );
    }

    public function test_khong_bao_gio_phong_to_anh_nho(): void
    {
        // Đúng ca ảnh đang mờ trên site: 578x678.
        $this->putImage('admin/products/small.png', 578, 678);

        MediaVariantService::make()->generate('admin/products/small.png');

        $widths = MediaVariant::where('source_path', 'admin/products/small.png')
            ->orderBy('width')->pluck('width')->all();

        // Chỉ bậc 400 + một bậc bằng đúng chiều rộng gốc. KHÔNG có 800/1600.
        $this->assertSame([400, 578], $widths);
    }

    public function test_giu_ti_le_khi_resize(): void
    {
        $this->putImage('admin/products/portrait.jpg', 790, 1146);

        MediaVariantService::make()->generate('admin/products/portrait.jpg');

        $v = MediaVariant::where('source_path', 'admin/products/portrait.jpg')->where('width', 400)->firstOrFail();
        // 400 / 790 * 1146 = 580.2 → làm tròn 580.
        $this->assertSame(580, $v->height);
    }

    public function test_luu_file_webp_ra_thu_muc_variants(): void
    {
        $this->putImage('admin/products/abc.jpg', 1200, 900);

        MediaVariantService::make()->generate('admin/products/abc.jpg');

        Storage::disk('media')->assertExists('admin/products/variants/abc-400.webp');
        Storage::disk('media')->assertExists('admin/products/variants/abc-800.webp');
        // File gốc KHÔNG bị sửa hay xoá.
        Storage::disk('media')->assertExists('admin/products/abc.jpg');
    }

    public function test_idempotent_goi_lai_khong_sinh_them(): void
    {
        $this->putImage('admin/products/abc.jpg', 1200, 900);
        $svc = MediaVariantService::make();

        // 1200px → [400, 800, 1200]: bậc 1200 là bản WebP full-size, để `src`
        // không bao giờ phải trỏ vào file gốc (JPEG/PNG thường nặng hơn nhiều).
        $this->assertSame(3, $svc->generate('admin/products/abc.jpg'));
        $this->assertSame(0, $svc->generate('admin/products/abc.jpg'));
        $this->assertSame(3, MediaVariant::where('source_path', 'admin/products/abc.jpg')->count());
    }

    public function test_anh_loi_khong_lam_vo_luong_upload(): void
    {
        Storage::disk('media')->put('admin/products/broken.jpg', 'không phải ảnh');

        $this->assertSame(0, MediaVariantService::make()->generate('admin/products/broken.jpg'));
        $this->assertSame(0, MediaVariant::count());
    }

    public function test_file_khong_ton_tai_tra_ve_0(): void
    {
        $this->assertSame(0, MediaVariantService::make()->generate('admin/products/khong-co.jpg'));
    }

    public function test_payload_dung_bien_the_lon_nhat_lam_src(): void
    {
        $this->putImage('admin/products/abc.jpg', 2400, 1600);
        MediaVariantService::make()->generate('admin/products/abc.jpg');

        $payload = MediaVariantService::payload('admin/products/abc.jpg');

        // src = biến thể lớn nhất, KHÔNG phải file gốc (browser cũ khỏi tải file to).
        $this->assertStringContainsString('abc-1600.webp', $payload['url']);
        $this->assertStringNotContainsString('abc.jpg', $payload['url']);
        $this->assertSame(1600, $payload['width']);

        // srcset xếp tăng dần, có descriptor `w`.
        $this->assertStringContainsString('abc-400.webp 400w', $payload['srcset']);
        $this->assertStringContainsString('abc-1600.webp 1600w', $payload['srcset']);
        $this->assertLessThan(
            strpos($payload['srcset'], '800w'),
            strpos($payload['srcset'], '400w'),
        );
    }

    public function test_payload_fallback_ve_file_goc_khi_chua_backfill(): void
    {
        $this->putImage('admin/products/chua-xu-ly.jpg', 900, 600);

        $payload = MediaVariantService::payload('admin/products/chua-xu-ly.jpg');

        $this->assertStringContainsString('chua-xu-ly.jpg', $payload['url']);
        $this->assertNull($payload['srcset']);
        $this->assertNull($payload['width']);
    }

    public function test_forget_xoa_ca_file_va_row(): void
    {
        $this->putImage('admin/products/abc.jpg', 1200, 900);
        $svc = MediaVariantService::make();
        $svc->generate('admin/products/abc.jpg');

        $svc->forget('admin/products/abc.jpg');

        Storage::disk('media')->assertMissing('admin/products/variants/abc-400.webp');
        Storage::disk('media')->assertMissing('admin/products/variants/abc-800.webp');
        $this->assertSame(0, MediaVariant::where('source_path', 'admin/products/abc.jpg')->count());
        // Gốc vẫn còn — việc xoá gốc do MediaRef quyết định (ảnh có thể đang chia sẻ).
        Storage::disk('media')->assertExists('admin/products/abc.jpg');
    }

    public function test_warm_nap_nhieu_path_trong_1_query_roi_payload_khong_query_them(): void
    {
        $this->putImage('admin/products/a.jpg', 1200, 900);
        $this->putImage('admin/products/b.jpg', 1200, 900);
        $svc = MediaVariantService::make();
        $svc->generate('admin/products/a.jpg');
        $svc->generate('admin/products/b.jpg');
        MediaVariantService::flushMemo();

        DB::enableQueryLog();
        MediaVariantService::warm(['admin/products/a.jpg', 'admin/products/b.jpg', null]);
        $this->assertCount(1, DB::getQueryLog(), 'warm() phải gom về 1 query');

        // Đã warm rồi thì payload() không được query thêm lần nào nữa.
        MediaVariantService::payload('admin/products/a.jpg');
        MediaVariantService::payload('admin/products/b.jpg');
        $this->assertCount(1, DB::getQueryLog());
    }

    public function test_warm_ghi_ca_path_khong_co_bien_the_de_khoi_query_lai(): void
    {
        $this->putImage('admin/products/chua-xu-ly.jpg', 900, 600);
        MediaVariantService::flushMemo();

        MediaVariantService::warm(['admin/products/chua-xu-ly.jpg']);
        DB::enableQueryLog();
        $payload = MediaVariantService::payload('admin/products/chua-xu-ly.jpg');

        $this->assertCount(0, DB::getQueryLog(), 'path không có biến thể cũng phải nằm trong memo');
        $this->assertNull($payload['srcset']);
    }

    public function test_generate_lam_moi_memo_da_ghi_la_khong_co_bien_the(): void
    {
        $this->putImage('admin/products/a.jpg', 1200, 900);

        // Đọc trước khi resize → memo ghi "không có biến thể".
        $this->assertNull(MediaVariantService::payload('admin/products/a.jpg')['srcset']);

        MediaVariantService::make()->generate('admin/products/a.jpg');

        // Sau khi resize, payload phải thấy biến thể mới (memo đã được dọn).
        $this->assertNotNull(MediaVariantService::payload('admin/products/a.jpg')['srcset']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Order;
use App\Models\Product;
use App\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * bopcamping-4jao — trang ký của khách: cửa 4 số cuối SĐT, chống ký nhầm bản, chống rò CCCD.
 */
class ContractSigningTest extends TestCase
{
    use RefreshDatabase;

    /** PNG 1x1 trong suốt — đủ đại diện cho chữ ký trong test. */
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private function makeContract(string $code = 'BOP-HD003', string $phone = '0912345678'): Contract
    {
        Storage::fake('media');
        // Chặn job sinh PDF: test này kiểm luồng KÝ, không kiểm PDF (đã có ContractPdfTest).
        // Render dompdf ngốn đỉnh ~75MB mỗi lần, để chạy thật ở đây là cả suite tràn bộ nhớ.
        Queue::fake();

        $order = Order::create([
            'code' => $code,
            'customer_name' => 'Nguyễn Văn A',
            'customer_phone' => $phone,
            'start_date' => '2030-07-01',
            'end_date' => '2030-07-03',
            'total_price' => 361000,
            'deposit_total' => 1500000,
            'status' => 'confirmed',
            'payment_method' => 'cod',
        ]);

        $product = Product::factory()->create(['replacement_value' => 4500000]);
        $order->items()->create([
            'product_id' => $product->id, 'quantity' => 1, 'price_per_day' => 190000,
            'days' => 2, 'start_date' => '2030-07-01', 'end_date' => '2030-07-03', 'subtotal' => 380000,
        ]);

        return app(ContractService::class)->createFor($order->fresh(), []);
    }

    private function unlock(Contract $c): void
    {
        $this->post("/hop-dong/{$c->token}/mo", ['last4' => '5678']);
    }

    private function hashOf(Contract $c, string $stage): string
    {
        return hash('sha256', app(ContractService::class)->render($c->fresh(), $stage));
    }

    /** @test */
    public function token_sai_tra_404(): void
    {
        $this->get('/hop-dong/'.str_repeat('z', 64))->assertNotFound();
    }

    /** @test */
    public function chua_qua_cua_4_so_cuoi_thi_khong_thay_noi_dung(): void
    {
        $c = $this->makeContract();

        $this->get("/hop-dong/{$c->token}")
            ->assertInertia(fn ($p) => $p->component('Contract')->where('unlocked', false));
    }

    /** @test */
    public function sai_4_so_cuoi_thi_bi_tu_choi(): void
    {
        $c = $this->makeContract();

        $this->post("/hop-dong/{$c->token}/mo", ['last4' => '0000'])
            ->assertSessionHasErrors('last4');

        $this->get("/hop-dong/{$c->token}")
            ->assertInertia(fn ($p) => $p->where('unlocked', false));
    }

    /** @test */
    public function dung_4_so_cuoi_thi_mo_duoc_va_ghi_first_viewed_at(): void
    {
        $c = $this->makeContract();

        $this->post("/hop-dong/{$c->token}/mo", ['last4' => '5678'])->assertSessionHasNoErrors();

        $this->assertNotNull($c->fresh()->first_viewed_at);
        $this->get("/hop-dong/{$c->token}")
            ->assertInertia(fn ($p) => $p->where('unlocked', true)->where('stage', 'main'));
    }

    /** @test */
    public function so_cccd_va_duong_dan_anh_khong_bao_gio_lot_ra_prop_trang_khach(): void
    {
        $c = $this->makeContract();
        $c->update([
            'signer_id_number' => '040202015437',
            'id_front_path' => 'identity/front.jpg',
        ]);
        $this->unlock($c);

        $props = $this->get("/hop-dong/{$c->token}")->viewData('page')['props'];
        $json = json_encode($props, JSON_UNESCAPED_UNICODE);

        // Số CCCD CÓ trong nội dung hợp đồng khách đang đọc (đó là chủ ý — hợp đồng phải có),
        // nhưng đường dẫn ảnh thì tuyệt đối không được lộ ra.
        $this->assertStringNotContainsString('identity/front.jpg', $json);
    }

    /** @test */
    public function ky_thanh_cong_thi_luu_chu_ky_va_dau_vet(): void
    {
        $c = $this->makeContract();
        $this->unlock($c);
        $hash = $this->hashOf($c, 'main');

        $this->post("/hop-dong/{$c->token}/ky/main", [
            'signature' => self::PNG,
            'content_hash' => $hash,
        ])->assertSessionHasNoErrors();

        $sig = $c->fresh()->signatureFor('main');
        $this->assertNotNull($sig);
        $this->assertSame($hash, $sig->content_hash);
        $this->assertNotNull($sig->signed_ip);
        $this->assertNotSame('', $sig->content_html);
        Storage::disk('media')->assertExists($sig->signature_path);
    }

    /** @test */
    public function chua_qua_cua_4_so_cuoi_thi_khong_ky_duoc(): void
    {
        $c = $this->makeContract();

        $this->post("/hop-dong/{$c->token}/ky/main", [
            'signature' => self::PNG,
            'content_hash' => $this->hashOf($c, 'main'),
        ])->assertForbidden();

        $this->assertNull($c->fresh()->signatureFor('main'));
    }

    /** @test */
    public function hash_lech_thi_tu_choi_va_khong_ghi_gi(): void
    {
        $c = $this->makeContract();
        $this->unlock($c);

        $this->post("/hop-dong/{$c->token}/ky/main", [
            'signature' => self::PNG,
            'content_hash' => hash('sha256', 'một bản hợp đồng khác'),
        ])->assertSessionHasErrors('content_hash');

        $this->assertNull($c->fresh()->signatureFor('main'));
    }

    /** @test */
    public function chu_ky_khong_phai_png_bi_tu_choi(): void
    {
        $c = $this->makeContract();
        $this->unlock($c);

        // Chặn nhồi file lạ qua data URL — không tin dữ liệu client gửi lên.
        $this->post("/hop-dong/{$c->token}/ky/main", [
            'signature' => 'data:text/html;base64,'.base64_encode('<script>alert(1)</script>'),
            'content_hash' => $this->hashOf($c, 'main'),
        ])->assertSessionHasErrors();

        $this->assertNull($c->fresh()->signatureFor('main'));
    }

    /** @test */
    public function ky_lai_cung_giai_doan_bi_chan(): void
    {
        $c = $this->makeContract();
        $this->unlock($c);
        $hash = $this->hashOf($c, 'main');
        $this->post("/hop-dong/{$c->token}/ky/main", ['signature' => self::PNG, 'content_hash' => $hash]);

        $this->post("/hop-dong/{$c->token}/ky/main", ['signature' => self::PNG, 'content_hash' => $hash])
            ->assertSessionHasErrors();

        $this->assertSame(1, $c->fresh()->signatures()->where('stage', 'main')->count());
    }

    /** @test */
    public function khong_duoc_ky_handover_khi_chua_ky_main(): void
    {
        $c = $this->makeContract();
        $this->unlock($c);

        $this->post("/hop-dong/{$c->token}/ky/handover", [
            'signature' => self::PNG,
            'content_hash' => $this->hashOf($c, 'handover'),
        ])->assertSessionHasErrors();

        $this->assertNull($c->fresh()->signatureFor('handover'));
    }

    /** @test */
    public function cua_4_so_cuoi_cua_hop_dong_nay_khong_mo_duoc_hop_dong_khac(): void
    {
        $a = $this->makeContract();
        $b = $this->makeContract('BOP-HD004', '0987651111');

        $this->unlock($a);

        // Session mở khoá phải gắn theo TỪNG hợp đồng, không phải một cờ dùng chung.
        $this->get("/hop-dong/{$b->token}")
            ->assertInertia(fn ($p) => $p->where('unlocked', false));
    }
}

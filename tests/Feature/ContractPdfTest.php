<?php

namespace Tests\Feature;

use App\Jobs\GenerateContractPdf;
use App\Mail\ContractSignedMail;
use App\Models\Contract;
use App\Models\Order;
use App\Models\Product;
use App\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * bopcamping-4jao — sinh PDF sau mỗi lần ký, biên bản chứng thực, và mail bản đã ký.
 *
 * Mail gửi ngay lúc ký là TRỤ CHỨNG CỨ CHÍNH (adr mục 3.2): bản PDF nằm trong hộp thư khách,
 * trên server Google/Microsoft, có DKIM — shop không sửa được. Mất nó là mất phần lớn giá trị
 * chứng cứ của cả tính năng, nên phải có test canh.
 */
class ContractPdfTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private function makeContract(?string $email = 'khach@example.com'): Contract
    {
        Storage::fake('media');

        $order = Order::create([
            'code' => 'BOP-PDF01',
            'customer_name' => 'Nguyễn Văn A',
            'customer_phone' => '0912345678',
            'customer_email' => $email,
            'start_date' => '2030-07-01',
            'end_date' => '2030-07-03',
            'total_price' => 361000,
            'deposit_total' => 1500000,
            'status' => 'confirmed',
            'payment_method' => 'cod',
        ]);

        $product = Product::factory()->create(['name' => 'Lều Village 6.0', 'replacement_value' => 4500000]);
        $order->items()->create([
            'product_id' => $product->id, 'quantity' => 1, 'price_per_day' => 190000,
            'days' => 2, 'start_date' => '2030-07-01', 'end_date' => '2030-07-03', 'subtotal' => 380000,
        ]);

        return app(ContractService::class)->createFor($order->fresh(), []);
    }

    private function signMain(Contract $c): void
    {
        $this->post("/hop-dong/{$c->token}/mo", ['last4' => '5678']);
        $this->post("/hop-dong/{$c->token}/ky/main", [
            'signature' => self::PNG,
            'content_hash' => hash('sha256', app(ContractService::class)->render($c->fresh(), 'main')),
        ]);
    }

    /** @test */
    public function ky_xong_thi_sinh_pdf_va_luu_duong_dan(): void
    {
        Mail::fake();
        $c = $this->makeContract();

        $this->signMain($c);

        $c->refresh();
        $this->assertNotNull($c->pdf_path);
        Storage::disk('media')->assertExists($c->pdf_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('media')->get($c->pdf_path));
    }

    /** @test */
    public function sinh_pdf_chay_nen_chu_khong_nam_trong_request_ky(): void
    {
        Queue::fake();
        $c = $this->makeContract();

        $this->signMain($c);

        // Render một hợp đồng đầy đủ ngốn đỉnh ~75MB (đo thật), sát trần memory_limit 128M
        // của PHP-FPM. Làm inline là có ngày khách ăn lỗi 500 đúng lúc bấm ký — khoảnh khắc
        // tệ nhất để hệ thống trục trặc.
        Queue::assertPushed(GenerateContractPdf::class);
        $this->assertNull($c->fresh()->pdf_path, 'Request ký không được tự sinh PDF.');
    }

    /** @test */
    public function ky_xong_thi_gui_mail_kem_pdf_cho_khach(): void
    {
        Mail::fake();
        $c = $this->makeContract();

        $this->signMain($c);

        Mail::assertQueued(
            ContractSignedMail::class,
            fn (ContractSignedMail $m) => $m->hasTo('khach@example.com')
        );
    }

    /** @test */
    public function don_khong_co_email_that_thi_khong_gui_mail_nhung_van_sinh_pdf(): void
    {
        Mail::fake();
        // Khách đặt qua điện thoại, hệ thống sinh email giả @bopcamping.local.
        $c = $this->makeContract('khach@bopcamping.local');

        $this->signMain($c);

        Mail::assertNothingQueued();
        $this->assertNotNull($c->fresh()->pdf_path);
    }

    /** @test */
    public function pdf_chua_du_ba_phan_va_danh_dau_phan_chua_ky(): void
    {
        Mail::fake();
        // Test này chỉ soi HTML, không cần binary PDF — chặn job để khỏi tốn một lần render
        // dompdf (đỉnh ~75MB mỗi lần, cả suite cộng dồn là tràn).
        Queue::fake();
        $c = $this->makeContract();
        $this->signMain($c);

        $html = app(ContractService::class)->pdfHtml($c->fresh());

        $this->assertStringContainsString('HỢP ĐỒNG THUÊ THIẾT BỊ CAMPING', $html);
        $this->assertStringContainsString('PHỤ LỤC A', $html);
        $this->assertStringContainsString('PHỤ LỤC B', $html);
        // Ký một giai đoạn thì hai phần kia phải nói rõ là chưa ký, không được để trống trơn
        // khiến người đọc tưởng đã ký đủ.
        $this->assertSame(2, substr_count($html, '(Chưa ký)'));
    }

    /** @test */
    public function bien_ban_chung_thuc_co_du_dau_vet(): void
    {
        Mail::fake();
        Queue::fake(); // chỉ soi HTML, không cần binary PDF
        $c = $this->makeContract();
        $this->signMain($c);
        $c->refresh();

        $html = app(ContractService::class)->pdfHtml($c);

        $this->assertStringContainsString('BIÊN BẢN CHỨNG THỰC', $html);
        $this->assertStringContainsString($c->signatureFor('main')->content_hash, $html);
        $this->assertStringContainsString('BOP-PDF01', $html);
        // Chỉ dẫn tra sao kê — bằng chứng ngân hàng nằm NGOÀI hệ thống, biên bản chỉ đường.
        $this->assertStringContainsString('sao kê', $html);
    }

    /** @test */
    public function chua_qua_cua_4_so_cuoi_thi_khong_tai_duoc_pdf(): void
    {
        Mail::fake();
        $c = $this->makeContract();
        $this->signMain($c);

        // PDF chứa tên, địa chỉ, số CCCD — không được để ai có link là tải được.
        $this->flushSession();
        $this->get("/hop-dong/{$c->token}/pdf")->assertForbidden();
    }

    /** @test */
    public function chua_ky_lan_nao_thi_chua_co_pdf_de_tai(): void
    {
        $c = $this->makeContract();
        $this->post("/hop-dong/{$c->token}/mo", ['last4' => '5678']);

        $this->get("/hop-dong/{$c->token}/pdf")->assertNotFound();
    }

    /** @test */
    public function tai_duoc_pdf_sau_khi_ky(): void
    {
        Mail::fake();
        $c = $this->makeContract();
        $this->signMain($c);

        $this->get("/hop-dong/{$c->token}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}

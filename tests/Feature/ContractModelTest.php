<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Order;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * bopcamping-4jao — model hợp đồng: thứ tự ba giai đoạn ký và việc mã hoá số CCCD.
 */
class ContractModelTest extends TestCase
{
    use RefreshDatabase;

    private function makeContract(): Contract
    {
        $order = Order::create([
            'code' => 'BOP-HD001',
            'customer_name' => 'Khách',
            'customer_phone' => '0912345678',
            'start_date' => '2030-07-01',
            'end_date' => '2030-07-03',
            'total_price' => 361000,
            'deposit_total' => 1500000,
            'status' => 'confirmed',
            'payment_method' => 'cod',
        ]);

        return Contract::create([
            'order_id' => $order->id,
            'code' => '1408/HĐTTB',
            'token' => str_repeat('a', 64),
        ]);
    }

    /** @test */
    public function next_stage_di_tu_main_den_return_roi_het(): void
    {
        $c = $this->makeContract();
        $this->assertSame('main', $c->nextStage());

        $this->sign($c, 'main');
        $this->assertSame('handover', $c->fresh()->nextStage());

        $this->sign($c, 'handover');
        $this->assertSame('return', $c->fresh()->nextStage());

        $this->sign($c, 'return');
        $this->assertNull($c->fresh()->nextStage());
    }

    /** @test */
    public function so_cccd_duoc_ma_hoa_trong_db_nhung_doc_ra_van_dung(): void
    {
        $c = $this->makeContract();
        $c->update(['signer_id_number' => '040202015437']);

        $this->assertSame('040202015437', $c->fresh()->signer_id_number);
        $this->assertNotSame(
            '040202015437',
            DB::table('contracts')->where('id', $c->id)->value('signer_id_number'),
            'Số CCCD phải được mã hoá ở tầng ứng dụng, không nằm thô trong DB.'
        );
    }

    /** @test */
    public function phone_last4_bo_qua_ky_tu_khong_phai_so(): void
    {
        $c = $this->makeContract();
        $c->order->update(['customer_phone' => '091 234 5678']);

        $this->assertSame('5678', $c->fresh()->phoneLast4());
    }

    /** @test */
    public function khong_the_ky_hai_lan_cung_mot_giai_doan(): void
    {
        $c = $this->makeContract();
        $this->sign($c, 'main');

        // Chặn ở tầng DB (unique contract_id + stage), không chỉ ở controller — hai request
        // gửi cùng lúc vẫn lọt qua kiểm tra ở tầng PHP.
        $this->expectException(QueryException::class);
        $this->sign($c, 'main');
    }

    private function sign(Contract $c, string $stage): void
    {
        $c->signatures()->create([
            'stage' => $stage,
            'content_html' => '<p>x</p>',
            'content_hash' => hash('sha256', '<p>x</p>'),
            'signature_path' => "contracts/{$stage}.png",
            'signed_at' => now(),
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Order;
use App\Models\Product;
use App\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * bopcamping-4jao — dựng và render hợp đồng. ContractService là NGUỒN DUY NHẤT làm việc này.
 */
class ContractServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(bool $isParent = false, int $replacementValue = 4500000): Order
    {
        $order = Order::create([
            'code' => 'BOP-HD002',
            'customer_name' => 'Nguyễn Văn A',
            'customer_phone' => '0912345678',
            'customer_address' => 'Số 1, phường Bồ Đề, Hà Nội',
            'is_parent' => $isParent,
            'start_date' => '2030-07-01',
            'end_date' => '2030-07-03',
            'total_price' => 361000,
            'deposit_total' => 1500000,
            'status' => 'confirmed',
            'payment_method' => 'cod',
        ]);

        $product = Product::factory()->create([
            'name' => 'Lều Village 6.0',
            'replacement_value' => $replacementValue,
            'parts_list' => '1 túi đựng, 8 dây căng lều, 16 cọc ghim đất',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'price_per_day' => 190000,
            'days' => 2,
            'start_date' => '2030-07-01',
            'end_date' => '2030-07-03',
            'subtotal' => 380000,
        ]);

        return $order->fresh();
    }

    /** @test */
    public function tao_hop_dong_chup_lai_ten_phu_kien_va_gia_den_bu(): void
    {
        $contract = app(ContractService::class)->createFor($this->makeOrder(), []);

        $item = $contract->items->first();
        $this->assertSame('Lều Village 6.0', $item->name);
        $this->assertSame('1 túi đựng, 8 dây căng lều, 16 cọc ghim đất', $item->parts_list);
        $this->assertSame(4500000, $item->replacement_value);
        $this->assertSame(64, strlen($contract->token));
    }

    /** @test */
    public function doi_gia_san_pham_sau_khi_lap_khong_lam_doi_hop_dong(): void
    {
        $service = app(ContractService::class);
        $contract = $service->createFor($this->makeOrder(), []);

        Product::query()->update(['replacement_value' => 9999999, 'name' => 'Tên mới']);

        // Hợp đồng đã lập phải ĐÓNG BĂNG — sản phẩm đổi giá/đổi tên về sau không được
        // làm thay đổi giấy tờ đã đưa cho khách.
        $item = $contract->fresh()->items->first();
        $this->assertSame(4500000, $item->replacement_value);
        $this->assertSame('Lều Village 6.0', $item->name);
    }

    /** @test */
    public function goi_lai_khong_tao_hop_dong_trung(): void
    {
        $service = app(ContractService::class);
        $order = $this->makeOrder();

        $a = $service->createFor($order, []);
        $b = $service->createFor($order->fresh(), []);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, Contract::count());
        $this->assertSame(1, $a->fresh()->items()->count(), 'Gọi lại không được nhân đôi danh mục đồ.');
    }

    /** @test */
    public function don_cha_khong_duoc_tao_hop_dong(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(ContractService::class)->createFor($this->makeOrder(isParent: true), []);
    }

    /** @test */
    public function render_thay_het_bien_va_giu_dau_tieng_viet(): void
    {
        $service = app(ContractService::class);
        $contract = $service->createFor($this->makeOrder(), ['id_number' => '040202015437']);

        $html = $service->render($contract->fresh(), 'main');

        $this->assertStringContainsString('Nguyễn Văn A', $html);
        $this->assertStringContainsString('Lều Village 6.0', $html);
        $this->assertStringContainsString('040202015437', $html);
        $this->assertStringNotContainsString('{{', $html, 'Còn biến chưa thay trong hợp đồng.');
    }

    /** @test */
    public function render_du_ca_ba_giai_doan(): void
    {
        $service = app(ContractService::class);
        $contract = $service->createFor($this->makeOrder(), []);

        foreach (Contract::STAGES as $stage) {
            $html = $service->render($contract->fresh(), $stage);
            $this->assertStringNotContainsString('{{', $html, "Giai đoạn {$stage} còn biến chưa thay.");
            $this->assertNotSame('', trim($html));
        }
    }

    /** @test */
    public function gia_den_bu_bang_0_in_gach_ngang_chu_khong_in_0_dong(): void
    {
        $service = app(ContractService::class);
        $contract = $service->createFor($this->makeOrder(replacementValue: 0), []);

        $html = $service->render($contract->fresh(), 'main');

        // Soi đúng Ô giá trị đền bù của bảng Điều 1, không soi cả trang: tiền cọc
        // "1.500.000 đ" cũng chứa chuỗi "0 đ" nên assert trên cả trang là bẫy tự đặt.
        //
        // "0 đ" ở ô này dễ bị đọc thành "đền 0 đồng" — tức là tự tay bỏ mất căn cứ bồi thường.
        $this->assertMatchesRegularExpression(
            '#<td>Lều Village 6\.0</td><td>1</td><td>—</td>#',
            $html,
            'Giá trị đền bù chưa khai phải in "—", không được in "0 đ".'
        );
    }

    /** @test */
    public function giai_doan_khong_hop_le_bi_tu_choi(): void
    {
        $service = app(ContractService::class);
        $contract = $service->createFor($this->makeOrder(), []);

        $this->expectException(InvalidArgumentException::class);
        $service->render($contract, 'giai-doan-la');
    }

    /** @test */
    public function ten_khach_co_ky_tu_html_khong_pha_duoc_hop_dong(): void
    {
        $service = app(ContractService::class);
        $order = $this->makeOrder();
        $order->update(['customer_name' => '<script>alert(1)</script>']);

        $html = $service->render($service->createFor($order->fresh(), []), 'main');

        // Dữ liệu khách nhập KHÔNG được chèn thẻ vào hợp đồng (CWE-79).
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}

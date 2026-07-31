<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * bopcamping-9299 — mã địa chỉ sau sát nhập trên đơn.
 *
 * ⚠️ CỐ Ý không kiểm ward_code/province_code có tồn tại thật: dữ liệu tỉnh/xã không nằm
 * trong DB (FE gọi provinces.open-api.vn trực tiếp), muốn validate thì phải gọi API bên
 * thứ ba NGAY LÚC tạo đơn — đưa dependency vào đường tiền. customer_address là nguồn
 * chân lý cho giao nhận; các cột mã chỉ để thống kê sau.
 *
 * Điều BẮT BUỘC test: khách KHÔNG gửi mã (dùng ô text như cũ) vẫn đặt được đơn — nếu
 * không thì tính năng này làm vỡ luồng đặt hàng hiện tại.
 */
class OrderAddressCodesTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private string $start;

    private string $end;

    protected function setUp(): void
    {
        parent::setUp();

        $location = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghe An', 'status' => 'open', 'sort_order' => 1]);
        $category = Category::create(['name' => 'Do camping', 'slug' => 'do-camping-oac']);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Leu QA',
            'slug' => 'leu-qa-'.uniqid(),
            'price_per_day' => 100000,
            'quantity' => 5,
            'status' => 'active',
        ]);
        $this->product->serviceLocations()->attach($location->id, ['quantity' => 5, 'buffer_days' => 0]);

        $this->start = Carbon::today()->addDays(3)->toDateString();
        $this->end = Carbon::today()->addDays(5)->toDateString();
    }

    public function test_luu_du_ma_dia_chi_va_chuoi(): void
    {
        $address = 'Số 5 Trần Phú, Phường Ba Đình, Thành phố Hà Nội (trước sát nhập: Phường Điện Biên, Quận Ba Đình)';

        $this->post('/dat-hang', $this->payload([
            'address' => $address,
            'street' => 'Số 5 Trần Phú',
            'province_code' => 1,
            'ward_code' => 4,
            'legacy_ward_code' => 19,
        ]))->assertSessionHasNoErrors();

        $order = Order::where('is_parent', false)->firstOrFail();

        $this->assertSame($address, $order->customer_address, 'chuỗi FE ghép là nguồn chân lý, phải lưu nguyên văn');
        $this->assertSame('Số 5 Trần Phú', $order->street);
        $this->assertSame(1, $order->province_code);
        $this->assertSame(4, $order->ward_code);
        $this->assertSame(19, $order->legacy_ward_code);
    }

    /** Khách đi đường "địa chỉ mới" (không qua địa chỉ cũ) → legacy_ward_code null. */
    public function test_khong_di_duong_dia_chi_cu_thi_legacy_null(): void
    {
        $this->post('/dat-hang', $this->payload([
            'address' => 'Số 1 Lê Lợi, Phường Trường Thi, Tỉnh Nghệ An',
            'street' => 'Số 1 Lê Lợi',
            'province_code' => 40,
            'ward_code' => 16600,
        ]))->assertSessionHasNoErrors();

        $order = Order::where('is_parent', false)->firstOrFail();

        $this->assertSame(40, $order->province_code);
        $this->assertNull($order->legacy_ward_code);
    }

    /**
     * CA QUAN TRỌNG NHẤT — khách không gửi mã nào (API địa chỉ lỗi → form về ô text tự do)
     * thì vẫn phải đặt được đơn. Đây là fallback của tính năng.
     */
    public function test_khong_gui_ma_nao_van_dat_duoc_don(): void
    {
        $this->post('/dat-hang', $this->payload(['address' => 'Số 9 đường Nào Đó, TP Vinh']))
            ->assertSessionHasNoErrors();

        $order = Order::where('is_parent', false)->firstOrFail();

        $this->assertSame('Số 9 đường Nào Đó, TP Vinh', $order->customer_address);
        $this->assertNull($order->province_code);
        $this->assertNull($order->ward_code);
        $this->assertNull($order->legacy_ward_code);
        $this->assertNull($order->street);
    }

    /** @dataProvider maBanProvider */
    public function test_ma_khong_hop_le_bi_tu_choi(string $field, mixed $value): void
    {
        $this->post('/dat-hang', $this->payload(['address' => 'Số 1 Test', $field => $value]))
            ->assertSessionHasErrors($field);

        $this->assertSame(0, Order::count());
    }

    /** @return array<string, array{0: string, 1: mixed}> */
    public static function maBanProvider(): array
    {
        return [
            'ward_code là chữ' => ['ward_code', 'abc'],
            'province_code là 0' => ['province_code', 0],
            'province_code âm' => ['province_code', -5],
            'legacy_ward_code là mảng' => ['legacy_ward_code', [1, 2]],
        ];
    }

    public function test_street_qua_dai_bi_tu_choi(): void
    {
        $this->post('/dat-hang', $this->payload([
            'address' => 'Số 1 Test',
            'street' => str_repeat('a', 256),
        ]))->assertSessionHasErrors('street');
    }

    /** @param  array<string, mixed>  $address */
    private function payload(array $address): array
    {
        return array_merge([
            'name' => 'Khach QA',
            'phone' => '0900000000',
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 1,
                'start' => $this->start,
                'end' => $this->end,
            ]],
        ], $address);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * bopcamping-w7gi — buổi (nửa ngày) cho dòng COMBO.
 *
 * File này khởi đầu là một PROBE ghi lại hành vi "combo KHÔNG hỗ trợ buổi", viết trước khi
 * làm UI để khỏi thêm ô chọn mà lựa chọn của khách bị bỏ qua âm thầm. Chủ shop đã chốt
 * phương án cột riêng cho combo, nên nay nó thành test kỳ vọng THẬT.
 *
 * Ưu đãi nửa ngày lấy từ `combos.early_return_discount_pct` — CỘT RIÊNG, không suy từ các
 * món, để chủ shop giảm hoặc không cho từng combo.
 */
class ComboSessionProbeTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_giu_buoi_cua_dong_combo(): void
    {
        $loc = ServiceLocation::create(['name' => 'Vinh', 'area' => 'NA', 'status' => 'open', 'sort_order' => 1]);
        $cat = Category::create(['name' => 'Do camping', 'slug' => 'do-camping-probe']);

        $p = Product::create([
            'category_id' => $cat->id,
            'name' => 'Leu probe',
            'slug' => Str::slug('leu-probe').'-'.uniqid(),
            'price_per_day' => 100000,
            'quantity' => 5,
            'status' => 'active',
        ]);
        $p->serviceLocations()->attach($loc->id, ['quantity' => 5, 'buffer_days' => 0]);

        $combo = Combo::create([
            'name' => 'Combo probe',
            'slug' => 'combo-probe',
            'combo_price' => 150000,
            'deposit' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $combo->items()->create(['product_id' => $p->id, 'quantity' => 1]);
        $combo->serviceLocations()->sync([$loc->id]);

        $day = Carbon::today()->addDays(3)->toDateString();

        $this->post('/dat-hang', [
            'name' => 'Khach probe',
            'phone' => '0900000111',
            'address' => 'So 1 Test',
            'combos' => [[
                'combo_id' => $combo->id,
                'quantity' => 1,
                'start' => $day,
                'end' => $day,          // CÙNG NGÀY — điều kiện để buổi có nghĩa
                'session' => 'morning', // khách chọn buổi sáng
            ]],
        ])->assertSessionHasNoErrors();

        $order = Order::where('is_parent', false)->firstOrFail();

        $this->assertSame('morning', $order->session);
        $this->assertTrue((bool) $order->is_half_day);
    }

    /**
     * CA TIỀN BẠC: combo có cột giảm 10% -> nửa ngày phải rẻ hơn 10%.
     * 150.000đ × 1 ngày = 150.000đ, giảm 10% -> 135.000đ.
     */
    public function test_nua_ngay_ap_dung_uu_dai_cua_chinh_combo(): void
    {
        [$combo, $day] = $this->dungCombo(10);

        $this->post('/dat-hang', $this->payload($combo, $day, 'morning'))->assertSessionHasNoErrors();

        $order = Order::where('is_parent', false)->firstOrFail();

        $this->assertSame(135000, (int) $order->total_price);
        $this->assertSame(10.0, (float) $order->items->first()->duration_discount_percent);
    }

    /** Combo để 0% -> nửa ngày KHÔNG giảm. Đây là điều chủ shop muốn linh động. */
    public function test_combo_de_0_phan_tram_thi_nua_ngay_khong_giam(): void
    {
        [$combo, $day] = $this->dungCombo(0);

        $this->post('/dat-hang', $this->payload($combo, $day, 'morning'))->assertSessionHasNoErrors();

        $order = Order::where('is_parent', false)->firstOrFail();

        $this->assertSame(150000, (int) $order->total_price, 'để 0% thì giá y như cả ngày');
        $this->assertTrue((bool) $order->is_half_day, 'vẫn ghi nhận là nửa ngày để admin biết trả sớm');
    }

    /**
     * CA TIỀN BẠC dễ sót nhất: thuê ĐÚNG 1 ngày, combo CÓ % giảm, nhưng khách chọn CẢ NGÀY
     * -> tuyệt đối không được giảm. Ưu đãi là để đổi lấy việc trả sớm, không phải quà tặng
     * cho mọi đơn một ngày.
     */
    public function test_mot_ngay_ma_chon_ca_ngay_thi_khong_giam(): void
    {
        [$combo, $day] = $this->dungCombo(10);

        $this->post('/dat-hang', $this->payload($combo, $day, 'full'))->assertSessionHasNoErrors();

        $order = Order::where('is_parent', false)->firstOrFail();

        $this->assertSame('full', $order->session);
        $this->assertFalse((bool) $order->is_half_day, 'cả ngày thì không phải nửa ngày');
        $this->assertSame(150000, (int) $order->total_price, 'chọn cả ngày thì trả đủ tiền');
    }

    /** Không gửi buổi gì cả (khách không chọn) -> cũng không được giảm. */
    public function test_khong_chon_buoi_thi_khong_giam(): void
    {
        [$combo, $day] = $this->dungCombo(10);

        $this->post('/dat-hang', $this->payload($combo, $day, null))->assertSessionHasNoErrors();

        $order = Order::where('is_parent', false)->firstOrFail();

        $this->assertFalse((bool) $order->is_half_day);
        $this->assertSame(150000, (int) $order->total_price);
    }

    /** Thuê NHIỀU ngày thì buổi vô nghĩa — không được áp ưu đãi nửa ngày. */
    public function test_thue_nhieu_ngay_thi_khong_ap_uu_dai_nua_ngay(): void
    {
        [$combo, $day] = $this->dungCombo(10);
        $end = Carbon::parse($day)->addDays(2)->toDateString();

        $this->post('/dat-hang', array_replace_recursive(
            $this->payload($combo, $day, 'morning'),
            ['combos' => [['end' => $end]]],
        ))->assertSessionHasNoErrors();

        $order = Order::where('is_parent', false)->firstOrFail();

        $this->assertNull($order->session, 'nhiều ngày thì buổi phải bị bỏ');
        $this->assertFalse((bool) $order->is_half_day);
    }

    /** @return array{0: Combo, 1: string} */
    private function dungCombo(int $earlyPct): array
    {
        $loc = ServiceLocation::create(['name' => 'Vinh', 'area' => 'NA', 'status' => 'open', 'sort_order' => 1]);
        $cat = Category::create(['name' => 'Do camping', 'slug' => 'do-camping-'.uniqid()]);

        $p = Product::create([
            'category_id' => $cat->id,
            'name' => 'Leu combo half',
            'slug' => 'leu-combo-half-'.uniqid(),
            'price_per_day' => 100000,
            'quantity' => 5,
            'status' => 'active',
        ]);
        $p->serviceLocations()->attach($loc->id, ['quantity' => 5, 'buffer_days' => 0]);

        $combo = Combo::create([
            'name' => 'Combo half',
            'slug' => 'combo-half-'.uniqid(),
            'combo_price' => 150000,
            'deposit' => 0,
            'early_return_discount_pct' => $earlyPct,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $combo->items()->create(['product_id' => $p->id, 'quantity' => 1]);
        $combo->serviceLocations()->sync([$loc->id]);

        return [$combo, Carbon::today()->addDays(3)->toDateString()];
    }

    /** @return array<string, mixed> */
    private function payload(Combo $combo, string $day, ?string $session): array
    {
        return [
            'name' => 'Khach half',
            'phone' => '0900000333',
            'address' => 'So 1 Test',
            'combos' => [[
                'combo_id' => $combo->id,
                'quantity' => 1,
                'start' => $day,
                'end' => $day,
                'session' => $session,
            ]],
        ];
    }

    /** Đối chiếu: dòng SẢN PHẨM LẺ thì server giữ buổi bình thường. */
    public function test_doi_chieu_dong_san_pham_le_thi_buoi_duoc_giu(): void
    {
        $loc = ServiceLocation::create(['name' => 'Vinh', 'area' => 'NA', 'status' => 'open', 'sort_order' => 1]);
        $cat = Category::create(['name' => 'Do camping', 'slug' => 'do-camping-probe2']);

        $p = Product::create([
            'category_id' => $cat->id,
            'name' => 'Leu probe 2',
            'slug' => Str::slug('leu-probe-2').'-'.uniqid(),
            'price_per_day' => 100000,
            'quantity' => 5,
            'status' => 'active',
            'early_return_discount_pct' => 10,
        ]);
        $p->serviceLocations()->attach($loc->id, ['quantity' => 5, 'buffer_days' => 0]);

        $day = Carbon::today()->addDays(3)->toDateString();

        $this->post('/dat-hang', [
            'name' => 'Khach probe 2',
            'phone' => '0900000222',
            'address' => 'So 1 Test',
            'items' => [[
                'product_id' => $p->id,
                'quantity' => 1,
                'start' => $day,
                'end' => $day,
                'session' => 'morning',
            ]],
        ])->assertSessionHasNoErrors();

        $order = Order::where('is_parent', false)->firstOrFail();

        $this->assertSame('morning', $order->session);
        $this->assertTrue((bool) $order->is_half_day);
    }
}

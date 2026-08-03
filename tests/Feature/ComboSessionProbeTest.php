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
 * PROBE — server có nhận "buổi" (session) cho dòng COMBO không?
 *
 * Trước khi thêm ô chọn buổi vào trang combo, phải biết server có giữ lựa chọn đó không.
 * Nếu không mà vẫn thêm UI thì khách chọn "buổi sáng", bấm đặt, và lựa chọn bị bỏ qua ÂM
 * THẦM — tệ hơn là không có ô chọn.
 *
 * Test này ghi lại HÀNH VI THẬT hôm nay, kể cả khi hành vi đó là "không hỗ trợ". Khi nào
 * làm tính năng buổi cho combo (bopcamping-w7gi) thì sửa test này thành kỳ vọng mới.
 */
class ComboSessionProbeTest extends TestCase
{
    use RefreshDatabase;

    public function test_buoi_cua_dong_combo_hien_bi_bo_qua(): void
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

        // HÀNH VI HIỆN TẠI: buổi bị bỏ qua. Đơn thành "cả ngày", không giảm nửa ngày.
        $this->assertNull($order->session, 'dòng này đỏ nghĩa là combo ĐÃ hỗ trợ buổi (bopcamping-w7gi) — cập nhật test + trang combo');
        $this->assertFalse((bool) $order->is_half_day);
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

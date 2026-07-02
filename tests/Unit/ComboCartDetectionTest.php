<?php

/**
 * ComboCartDetectionTest — AC-5, AC-6, mục 5.4 trong prd_combo.md
 *
 * 📌 File này dành cho P4 (bopcamping-28v) — test case giữ nguyên theo template
 *    của chủ shop, implement ComboDetectionService theo TDD ở phase đó.
 *    setUp tự SKIP khi service chưa tồn tại để suite P2/P3 vẫn xanh;
 *    khi làm P4: tạo service + factories (hoặc adapt helper) rồi bỏ guard.
 *
 * ⚠️ GIẢ ĐỊNH: App\Services\ComboDetectionService với method
 *    detect(Collection $cartItems, $start, $end): ?ComboSuggestion
 *    Trong đó ComboSuggestion có: type (exact|superset|upsell),
 *    combo, savings, missingItems (cho upsell).
 *    Cart item dạng: ['product_id' => int, 'quantity' => int]
 */

namespace Tests\Unit;

use App\Models\Combo;
use App\Models\Order;
use App\Models\Product;
use App\Services\ComboDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ComboCartDetectionTest extends TestCase
{
    use RefreshDatabase;

    private object $detector;

    private Product $tent;

    private Product $table;

    private Product $chair;

    private Combo $combo;

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(ComboDetectionService::class)) {
            $this->markTestSkipped('P4 (bopcamping-28v) — ComboDetectionService chưa được implement.');
        }
        $this->detector = app(ComboDetectionService::class);

        $this->tent = Product::factory()->create(['price_per_day' => 200_000, 'quantity' => 5]);
        $this->table = Product::factory()->create(['price_per_day' => 100_000, 'quantity' => 5]);
        $this->chair = Product::factory()->create(['price_per_day' => 25_000, 'quantity' => 20]);

        // Combo: 1 lều + 1 bàn + 4 ghế = 400k lẻ → combo 340k (tiết kiệm 60k)
        $this->combo = Combo::factory()->create(['combo_price' => 340_000, 'is_active' => true]);
        $this->combo->items()->createMany([
            ['product_id' => $this->tent->id, 'quantity' => 1],
            ['product_id' => $this->table->id, 'quantity' => 1],
            ['product_id' => $this->chair->id, 'quantity' => 4],
        ]);
    }

    private function cart(array $items): Collection
    {
        return collect($items)->map(fn ($i) => [
            'product_id' => $i[0]->id,
            'quantity' => $i[1],
        ]);
    }

    public function test_gio_khop_du_combo_duoc_goi_y_exact(): void
    {
        $suggestion = $this->detector->detect(
            $this->cart([[$this->tent, 1], [$this->table, 1], [$this->chair, 4]]),
            '2026-07-12', '2026-07-14'
        );

        $this->assertNotNull($suggestion);
        $this->assertSame('exact', $suggestion->type);
        $this->assertTrue($suggestion->combo->is($this->combo));
        $this->assertSame(60_000, $suggestion->savings); // 400k − 340k
    }

    public function test_gio_superset_van_duoc_goi_y(): void
    {
        $extra = Product::factory()->create(['quantity' => 5]);

        $suggestion = $this->detector->detect(
            $this->cart([[$this->tent, 1], [$this->table, 1], [$this->chair, 4], [$extra, 1]]),
            '2026-07-12', '2026-07-14'
        );

        $this->assertNotNull($suggestion);
        $this->assertSame('superset', $suggestion->type);
    }

    public function test_gio_thieu_1_mon_duoc_goi_y_upsell(): void
    {
        // Thiếu bàn
        $suggestion = $this->detector->detect(
            $this->cart([[$this->tent, 1], [$this->chair, 4]]),
            '2026-07-12', '2026-07-14'
        );

        $this->assertNotNull($suggestion);
        $this->assertSame('upsell', $suggestion->type);
        $this->assertCount(1, $suggestion->missingItems);
        $this->assertSame($this->table->id, $suggestion->missingItems->first()->product_id);
    }

    public function test_thieu_quantity_1_mon_cung_la_upsell(): void
    {
        // Có ghế nhưng chỉ 2/4
        $suggestion = $this->detector->detect(
            $this->cart([[$this->tent, 1], [$this->table, 1], [$this->chair, 2]]),
            '2026-07-12', '2026-07-14'
        );

        $this->assertNotNull($suggestion);
        $this->assertSame('upsell', $suggestion->type);
    }

    public function test_thieu_tu_2_mon_tro_len_khong_goi_y(): void
    {
        // Chỉ có lều — thiếu cả bàn lẫn ghế → theo PRD chỉ upsell khi thiếu đúng 1 loại
        $suggestion = $this->detector->detect(
            $this->cart([[$this->tent, 1]]),
            '2026-07-12', '2026-07-14'
        );

        $this->assertNull($suggestion);
    }

    public function test_combo_het_hang_trong_khoang_ngay_thi_khong_goi_y(): void
    {
        // Chiếm hết tent trong 12–14/07 bằng đơn khác
        $order = Order::factory()->create([
            'start_date' => '2026-07-12',
            'end_date' => '2026-07-14',
        ]);
        $order->items()->create(['product_id' => $this->tent->id, 'quantity' => 5]);

        // Giỏ khớp đủ combo nhưng combo không còn available → KHÔNG gợi ý (AC-6)
        $suggestion = $this->detector->detect(
            $this->cart([[$this->tent, 1], [$this->table, 1], [$this->chair, 4]]),
            '2026-07-12', '2026-07-14'
        );

        $this->assertNull($suggestion, 'Gợi ý combo hết hàng = trải nghiệm ngược');
    }

    public function test_combo_inactive_khong_duoc_goi_y(): void
    {
        $this->combo->update(['is_active' => false]);

        $suggestion = $this->detector->detect(
            $this->cart([[$this->tent, 1], [$this->table, 1], [$this->chair, 4]]),
            '2026-07-12', '2026-07-14'
        );

        $this->assertNull($suggestion);
    }

    public function test_nhieu_combo_cung_khop_chon_combo_tiet_kiem_nhat(): void
    {
        // Combo thứ 2 chỉ gồm lều + bàn, tiết kiệm ít hơn (300k lẻ → 280k, tiết kiệm 20k)
        $small = Combo::factory()->create(['combo_price' => 280_000, 'is_active' => true]);
        $small->items()->createMany([
            ['product_id' => $this->tent->id, 'quantity' => 1],
            ['product_id' => $this->table->id, 'quantity' => 1],
        ]);

        // Giỏ khớp cả 2 combo → phải chọn combo lớn (tiết kiệm 60k > 20k)
        $suggestion = $this->detector->detect(
            $this->cart([[$this->tent, 1], [$this->table, 1], [$this->chair, 4]]),
            '2026-07-12', '2026-07-14'
        );

        $this->assertTrue($suggestion->combo->is($this->combo));
    }

    public function test_convert_khong_re_hon_thi_khong_goi_y(): void
    {
        // Combo "dỏm": giá bằng đúng tổng lẻ → savings = 0 → không gợi ý
        $this->combo->update(['combo_price' => 400_000]);

        $suggestion = $this->detector->detect(
            $this->cart([[$this->tent, 1], [$this->table, 1], [$this->chair, 4]]),
            '2026-07-12', '2026-07-14'
        );

        $this->assertNull($suggestion);
    }
}

<?php

/**
 * ComboPriceAllocationTest — AC-3, mục 5.3 trong prd_combo.md
 *
 * Template do chủ shop cung cấp, đã adapt theo code thực tế (không đổi test case):
 *   - "ComboPriceAllocator" → App\Services\ComboPricingService::allocate(Combo)
 *   - allocate() trả list các dòng {product_id, quantity, price_per_day,
 *     allocated_price, allocated_deposit} → 'price'/'deposit' đọc từ cột tương ứng.
 *   - Factories → Model::create().
 *
 * Công thức PRD: allocated_price_i = combo_price × (price_i × qty_i) / sum_individual
 * Làm tròn về 100₫, món CUỐI nhận phần dư để tổng khớp đúng combo_price.
 */

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Product;
use App\Services\ComboPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComboPriceAllocationTest extends TestCase
{
    use RefreshDatabase;

    private ComboPricingService $allocator;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->allocator = app(ComboPricingService::class);
        $this->category = Category::create(['name' => 'Test', 'slug' => 'test']);
    }

    private function makeCombo(array $items, int $comboPrice, int $deposit = 0): Combo
    {
        $combo = Combo::create([
            'name' => 'Combo '.uniqid(),
            'slug' => 'combo-'.uniqid(),
            'combo_price' => $comboPrice,
            'deposit' => $deposit,
            'is_active' => true,
        ]);
        foreach ($items as [$price, $qty]) {
            $product = Product::create([
                'category_id' => $this->category->id,
                'name' => 'SP '.uniqid(),
                'slug' => 'sp-'.uniqid(),
                'price_per_day' => $price,
                'quantity' => 10,
            ]);
            $combo->items()->create(['product_id' => $product->id, 'quantity' => $qty]);
        }

        return $combo->fresh('items.product');
    }

    // ─────────────────────────────────────────────────────────────
    // Bất biến quan trọng nhất: tổng phân bổ = combo_price CHÍNH XÁC
    // ─────────────────────────────────────────────────────────────

    public function test_tong_phan_bo_bang_dung_gia_combo(): void
    {
        // Giá lẻ: 200k + 100k + 50k = 350k → combo 300k
        $combo = $this->makeCombo([[200_000, 1], [100_000, 1], [50_000, 1]], 300_000);

        $allocation = $this->allocator->allocate($combo);

        $this->assertSame(
            300_000,
            array_sum(array_column($allocation, 'allocated_price')),
            'Tổng allocated_price phải khớp combo_price đến từng đồng'
        );
    }

    public function test_tong_phan_bo_khop_ca_khi_chia_khong_chan(): void
    {
        // Cố tình tạo số lẻ: 3 món giá bằng nhau, combo_price không chia hết cho 3
        // 100k mỗi món, combo 250k → mỗi món ~83.333₫ → làm tròn sẽ lệch nếu code sai
        $combo = $this->makeCombo([[100_000, 1], [100_000, 1], [100_000, 1]], 250_000);

        $allocation = $this->allocator->allocate($combo);

        $this->assertSame(250_000, array_sum(array_column($allocation, 'allocated_price')));

        // Từng món phải tròn 100₫ (trừ món cuối được phép nhận dư)
        $prices = array_column($allocation, 'allocated_price');
        foreach (array_slice($prices, 0, -1) as $p) {
            $this->assertSame(0, $p % 100, "Giá phân bổ $p phải tròn 100₫");
        }
    }

    public function test_phan_bo_theo_ty_le_gia_le(): void
    {
        // 200k vs 100k, tỷ lệ 2:1 → combo 240k phải chia 160k / 80k
        $combo = $this->makeCombo([[200_000, 1], [100_000, 1]], 240_000);

        $allocation = $this->allocator->allocate($combo);
        $prices = array_values(array_column($allocation, 'allocated_price'));
        sort($prices);

        $this->assertSame([80_000, 160_000], $prices);
    }

    public function test_quantity_duoc_tinh_vao_ty_le(): void
    {
        // Bàn 100k×1 + ghế 25k×4 = 200k tổng lẻ, tỷ lệ 1:1 → combo 180k chia 90k/90k
        $combo = $this->makeCombo([[100_000, 1], [25_000, 4]], 180_000);

        $allocation = $this->allocator->allocate($combo);
        $prices = array_values(array_column($allocation, 'allocated_price'));

        $this->assertSame([90_000, 90_000], $prices);
    }

    public function test_coc_phan_bo_va_tong_khop(): void
    {
        $combo = $this->makeCombo([[200_000, 1], [100_000, 1], [100_000, 1]], 350_000, 500_000);

        $allocation = $this->allocator->allocate($combo);

        $this->assertSame(
            500_000,
            array_sum(array_column($allocation, 'allocated_deposit')),
            'Tổng allocated_deposit phải khớp combo.deposit'
        );
    }

    // ─────────────────────────────────────────────────────────────
    // Property-based nhẹ: chạy nhiều tổ hợp ngẫu nhiên, bất biến phải giữ
    // ─────────────────────────────────────────────────────────────

    public function test_bat_bien_tong_khop_voi_nhieu_to_hop_ngau_nhien(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $itemCount = random_int(2, 6);
            $items = [];
            $sum = 0;
            for ($j = 0; $j < $itemCount; $j++) {
                $price = random_int(1, 50) * 10_000;
                $qty = random_int(1, 4);
                $items[] = [$price, $qty];
                $sum += $price * $qty;
            }
            $comboPrice = (int) floor($sum * 0.8 / 100) * 100; // giảm ~20%, tròn 100₫

            $combo = $this->makeCombo($items, $comboPrice);
            $allocation = $this->allocator->allocate($combo);

            $this->assertSame(
                $comboPrice,
                array_sum(array_column($allocation, 'allocated_price')),
                "Lệch tổng với tổ hợp #$i: ".json_encode($items)." combo=$comboPrice"
            );
            $this->assertCount($itemCount, $allocation);
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Product;
use App\Services\ComboPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-6he (Combo P2) — PRD 5.3: phân bổ combo_price/deposit vào từng món
 * theo tỷ lệ giá lẻ, làm tròn về 100₫, món cuối nhận phần dư — tổng khớp từng đồng.
 */
class ComboPricingTest extends TestCase
{
    use RefreshDatabase;

    private ComboPricingService $pricing;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricing = new ComboPricingService;
        $this->category = Category::create(['name' => 'Test', 'slug' => 'test']);
    }

    private function product(string $name, int $price, ?int $deposit = null): Product
    {
        return Product::create([
            'category_id' => $this->category->id,
            'name' => $name,
            'slug' => 'p-'.uniqid(),
            'price_per_day' => $price,
            'quantity' => 10,
            'deposit' => $deposit,
        ]);
    }

    private function combo(int $comboPrice, ?int $deposit, array $items): Combo
    {
        $combo = Combo::create([
            'name' => 'Combo Test',
            'slug' => 'combo-'.uniqid(),
            'combo_price' => $comboPrice,
            'deposit' => $deposit,
        ]);
        foreach ($items as [$product, $qty]) {
            $combo->items()->create(['product_id' => $product->id, 'quantity' => $qty]);
        }

        return $combo->fresh();
    }

    /** @test */
    public function allocation_sums_exactly_to_combo_price_and_deposit(): void
    {
        // sum lẻ = 100k×1 + 30k×3 = 190k; tỷ lệ 100/190 → số lẻ, buộc phải làm tròn
        $tent = $this->product('Lều', 100000);
        $bag = $this->product('Túi ngủ', 30000);
        $combo = $this->combo(150000, 400000, [[$tent, 1], [$bag, 3]]);

        $lines = $this->pricing->allocate($combo);

        $this->assertCount(2, $lines);
        // Món đầu: 150000 × 100000/190000 = 78,947.36… → floor 100 = 78,900
        $this->assertSame(78900, $lines[0]['allocated_price']);
        // Món cuối nhận dư: 150000 − 78900 = 71,100
        $this->assertSame(71100, $lines[1]['allocated_price']);
        $this->assertSame(150000, array_sum(array_column($lines, 'allocated_price')));
        // Cọc phân bổ cùng tỷ lệ, tổng khớp đúng
        $this->assertSame(400000, array_sum(array_column($lines, 'allocated_deposit')));
    }

    /** @test */
    public function non_last_lines_are_rounded_down_to_100(): void
    {
        $a = $this->product('A', 33333);
        $b = $this->product('B', 33333);
        $c = $this->product('C', 33334);
        $combo = $this->combo(80000, null, [[$a, 1], [$b, 1], [$c, 1]]);

        $lines = $this->pricing->allocate($combo);

        // Hai dòng đầu chia hết cho 100 (floor), dòng cuối nhận dư
        $this->assertSame(0, $lines[0]['allocated_price'] % 100);
        $this->assertSame(0, $lines[1]['allocated_price'] % 100);
        $this->assertSame(80000, array_sum(array_column($lines, 'allocated_price')));
        // Không có cọc → allocated_deposit toàn 0
        $this->assertSame([0, 0, 0], array_column($lines, 'allocated_deposit'));
    }

    /** @test */
    public function single_item_combo_takes_full_price(): void
    {
        $tent = $this->product('Lều', 100000);
        $combo = $this->combo(90000, 200000, [[$tent, 2]]);

        $lines = $this->pricing->allocate($combo);

        $this->assertCount(1, $lines);
        $this->assertSame(90000, $lines[0]['allocated_price']);
        $this->assertSame(200000, $lines[0]['allocated_deposit']);
        $this->assertSame(2, $lines[0]['quantity']);
    }

    /** @test */
    public function weight_uses_price_times_quantity(): void
    {
        // 2 món giá bằng nhau nhưng qty khác → món qty lớn nhận phần lớn
        $a = $this->product('A', 50000);
        $b = $this->product('B', 50000);
        $combo = $this->combo(120000, null, [[$a, 3], [$b, 1]]);

        $lines = $this->pricing->allocate($combo);

        // A: 120000 × 150000/200000 = 90,000 (tròn sẵn) · B nhận 30,000
        $this->assertSame(90000, $lines[0]['allocated_price']);
        $this->assertSame(30000, $lines[1]['allocated_price']);
    }

    /** @test */
    public function zero_priced_items_do_not_break_allocation(): void
    {
        // Edge: mọi giá lẻ = 0 (dữ liệu cũ/lỗi) → dồn hết combo_price vào dòng cuối, không chia cho 0
        $a = $this->product('A', 0);
        $b = $this->product('B', 0);
        $combo = $this->combo(50000, 100000, [[$a, 1], [$b, 1]]);

        $lines = $this->pricing->allocate($combo);

        $this->assertSame(50000, array_sum(array_column($lines, 'allocated_price')));
        $this->assertSame(100000, array_sum(array_column($lines, 'allocated_deposit')));
    }
}

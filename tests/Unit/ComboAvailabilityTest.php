<?php

/**
 * ComboAvailabilityTest — AC-2, AC-4, AC-10 trong prd_combo.md
 *
 * Template do chủ shop cung cấp, đã adapt theo code thực tế (không đổi test case):
 *   - Service:  App\Services\AvailabilityService (inject qua app())
 *   - Methods:  availableQuantity(Product, Carbon, Carbon): int
 *               comboAvailable(Combo, Carbon, Carbon): int
 *               "unavailableItems" → comboInsufficientItems(Combo, Carbon, Carbon)
 *                 trả array{product, available, required}[]
 *   - Factories → Model::create() (repo không dùng factory cho Product/Combo/Order)
 */

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Order;
use App\Models\Product;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ComboAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private AvailabilityService $service;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AvailabilityService::class);
        $this->category = Category::create(['name' => 'Test', 'slug' => 'test']);
    }

    private function makeProduct(int $quantity, int $price = 100000): Product
    {
        return Product::create([
            'category_id' => $this->category->id,
            'name' => 'SP '.uniqid(),
            'slug' => 'sp-'.uniqid(),
            'price_per_day' => $price,
            'quantity' => $quantity,
        ]);
    }

    /** Helper: tạo combo từ mảng [product, quantity] */
    private function makeCombo(array $items, array $attrs = []): Combo
    {
        $combo = Combo::create($attrs + [
            'name' => 'Combo '.uniqid(),
            'slug' => 'combo-'.uniqid(),
            'combo_price' => 100000,
            'is_active' => true,
        ]);
        foreach ($items as [$product, $qty]) {
            $combo->items()->create([
                'product_id' => $product->id,
                'quantity' => $qty,
            ]);
        }

        return $combo->fresh('items.product');
    }

    /** Helper: tạo đơn đã đặt cho 1 product trong khoảng ngày (status chiếm tồn kho) */
    private function bookProduct(Product $product, int $qty, string $start, string $end): Order
    {
        $order = Order::create([
            'code' => 'BOP-'.strtoupper(uniqid()),
            'customer_name' => 'X',
            'customer_phone' => '0900000000',
            'start_date' => $start,
            'end_date' => $end,
            'status' => 'confirmed',
            'payment_method' => 'cod',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => $qty,
            'price_per_day' => $product->price_per_day,
            'days' => 1,
            'subtotal' => $qty * $product->price_per_day,
        ]);

        return $order;
    }

    private function comboAvail(Combo $combo, string $start, string $end): int
    {
        return $this->service->comboAvailable($combo, Carbon::parse($start), Carbon::parse($end));
    }

    // ─────────────────────────────────────────────────────────────
    // Công thức min() cơ bản
    // ─────────────────────────────────────────────────────────────

    public function test_combo_availability_la_min_cua_cac_san_pham_con(): void
    {
        $tent = $this->makeProduct(5);
        $table = $this->makeProduct(2); // ← món ít nhất
        $chair = $this->makeProduct(10);

        $combo = $this->makeCombo([[$tent, 1], [$table, 1], [$chair, 1]]);

        // Không có đơn nào → min(5, 2, 10) = 2
        $this->assertSame(2, $this->comboAvail($combo, '2026-07-12', '2026-07-14'));
    }

    public function test_combo_item_quantity_lon_hon_1_dung_phep_chia_nguyen(): void
    {
        $tent = $this->makeProduct(5);
        $chair = $this->makeProduct(7); // combo cần 4 ghế

        $combo = $this->makeCombo([[$tent, 1], [$chair, 4]]);

        // intdiv(7, 4) = 1 → chỉ còn 1 combo dù tồn 7 ghế
        $this->assertSame(1, $this->comboAvail($combo, '2026-07-12', '2026-07-14'));
    }

    public function test_mot_mon_con_het_hang_thi_combo_het(): void
    {
        $tent = $this->makeProduct(5);
        $mat = $this->makeProduct(1);

        $combo = $this->makeCombo([[$tent, 1], [$mat, 1]]);

        $this->bookProduct($mat, 1, '2026-07-12', '2026-07-14');

        $this->assertSame(0, $this->comboAvail($combo, '2026-07-12', '2026-07-14'));
    }

    // ─────────────────────────────────────────────────────────────
    // Biên chồng lịch: start_A <= end_B AND start_B <= end_A
    // (đơn hiện có: 12/07 → 14/07, món mat quantity = 1)
    // ─────────────────────────────────────────────────────────────

    /** @dataProvider overlapProvider */
    public function test_bien_chong_lich(string $start, string $end, int $expected): void
    {
        $mat = $this->makeProduct(1);
        $combo = $this->makeCombo([[$mat, 1]]);

        $this->bookProduct($mat, 1, '2026-07-12', '2026-07-14');

        $this->assertSame(
            $expected,
            $this->comboAvail($combo, $start, $end),
            "Khoảng [$start, $end] so với đơn [2026-07-12, 2026-07-14]"
        );
    }

    public static function overlapProvider(): array
    {
        return [
            'chồng hoàn toàn' => ['2026-07-12', '2026-07-14', 0],
            'chồng một phần đầu' => ['2026-07-10', '2026-07-12', 0], // chạm ngày đầu
            'chồng một phần cuối' => ['2026-07-14', '2026-07-16', 0], // chạm ngày cuối
            'bao trùm đơn cũ' => ['2026-07-10', '2026-07-20', 0],
            'nằm trong đơn cũ' => ['2026-07-13', '2026-07-13', 0],
            'ngay sau, không chạm' => ['2026-07-15', '2026-07-17', 1],
            'ngay trước, không chạm' => ['2026-07-09', '2026-07-11', 1],
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Đơn combo chiếm tồn kho product con (AC-4) — hai chiều
    // ─────────────────────────────────────────────────────────────

    public function test_don_combo_lam_giam_ton_kho_thue_le(): void
    {
        $tent = $this->makeProduct(2);
        $mat = $this->makeProduct(2);

        $combo = $this->makeCombo([[$tent, 1], [$mat, 1]]);

        // Giả lập đơn combo: order_items bung theo product con, có combo_id
        $order = Order::create([
            'code' => 'BOP-'.strtoupper(uniqid()),
            'customer_name' => 'X',
            'customer_phone' => '0900000000',
            'start_date' => '2026-07-12',
            'end_date' => '2026-07-14',
            'status' => 'confirmed',
            'payment_method' => 'cod',
        ]);
        $groupUuid = (string) Str::uuid();
        foreach ([$tent, $mat] as $p) {
            $order->items()->create([
                'product_id' => $p->id,
                'quantity' => 1,
                'combo_id' => $combo->id,
                'combo_group_uuid' => $groupUuid,
                'price_per_day' => $p->price_per_day,
                'days' => 3,
                'subtotal' => $p->price_per_day * 3,
            ]);
        }

        // Thuê LẺ món tent cùng khoảng ngày → chỉ còn 1
        $this->assertSame(
            1,
            $this->service->availableQuantity($tent, Carbon::parse('2026-07-12'), Carbon::parse('2026-07-14'))
        );
    }

    public function test_don_thue_le_lam_giam_ton_kho_combo(): void
    {
        $tent = $this->makeProduct(2);
        $mat = $this->makeProduct(2);

        $combo = $this->makeCombo([[$tent, 1], [$mat, 1]]);

        // Thuê lẻ hết 2 tấm mat
        $this->bookProduct($mat, 2, '2026-07-20', '2026-07-22');

        $this->assertSame(0, $this->comboAvail($combo, '2026-07-20', '2026-07-22'));

        // Khoảng ngày khác vẫn còn
        $this->assertSame(2, $this->comboAvail($combo, '2026-08-01', '2026-08-03'));
    }

    // ─────────────────────────────────────────────────────────────
    // Case 4 — chỉ đúng món hết (comboInsufficientItems)
    // ─────────────────────────────────────────────────────────────

    public function test_case4_tra_ve_dung_mon_het_hang(): void
    {
        $tent = $this->makeProduct(5);
        $mat = $this->makeProduct(1);

        $combo = $this->makeCombo([[$tent, 1], [$mat, 1]]);
        $this->bookProduct($mat, 1, '2026-07-12', '2026-07-14');

        $unavailable = $this->service->comboInsufficientItems(
            $combo,
            Carbon::parse('2026-07-12'),
            Carbon::parse('2026-07-14'),
        );

        $this->assertCount(1, $unavailable);
        $this->assertTrue($unavailable[0]['product']->is($mat));
    }
}

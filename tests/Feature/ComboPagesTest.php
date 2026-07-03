<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-6he (Combo P2) — trang public /combos, /combos/{slug},
 * endpoint kiểm tra tồn kho realtime (Case 4) và section combo trang chủ.
 */
class ComboPagesTest extends TestCase
{
    use RefreshDatabase;

    private Product $tent;   // 100k/ngày, kho 2

    private Product $mattress; // 40k/ngày, kho 2, cùng danh mục có sản phẩm thay thế

    private Product $substitute; // cùng danh mục với mattress

    private Combo $combo;

    protected function setUp(): void
    {
        parent::setUp();

        $tents = Category::create(['name' => 'Lều', 'slug' => 'leu']);
        $sleep = Category::create(['name' => 'Đệm ngủ', 'slug' => 'dem-ngu']);

        $this->tent = Product::create([
            'category_id' => $tents->id,
            'name' => 'Lều Test',
            'slug' => 'leu-test',
            'price_per_day' => 100000,
            'quantity' => 2,
        ]);
        $this->mattress = Product::create([
            'category_id' => $sleep->id,
            'name' => 'Đệm hơi Test',
            'slug' => 'dem-hoi-test',
            'price_per_day' => 40000,
            'quantity' => 2,
        ]);
        $this->substitute = Product::create([
            'category_id' => $sleep->id,
            'name' => 'Đệm bọt Test',
            'slug' => 'dem-bot-test',
            'price_per_day' => 35000,
            'quantity' => 3,
        ]);

        $this->combo = Combo::create([
            'name' => 'Combo Cặp Đôi',
            'slug' => 'combo-cap-doi',
            'combo_price' => 120000,
            'deposit' => 300000,
            'suitable_for' => 2,
        ]);
        $this->combo->items()->create(['product_id' => $this->tent->id, 'quantity' => 1]);
        $this->combo->items()->create(['product_id' => $this->mattress->id, 'quantity' => 2]);
    }

    /** @test */
    public function combos_index_lists_active_combos_with_savings(): void
    {
        Combo::create(['name' => 'Combo Ẩn', 'slug' => 'combo-an', 'combo_price' => 1000, 'is_active' => false]);

        $this->get('/combos')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Combos')
            ->has('combos', 1)
            ->where('combos.0.slug', 'combo-cap-doi')
            // sum lẻ = 100k + 2×40k = 180k → tiết kiệm 60k = 33%
            ->where('combos.0.sum_individual', 180000)
            ->where('combos.0.savings_amount', 60000)
            ->where('combos.0.combo_price', 120000)
        );
    }

    /** @test */
    public function combos_index_reports_availability_for_chosen_range(): void
    {
        // Chiếm hết đệm trong 07/01–07/05 → combo hết trong khoảng đó
        $this->bookedOrder($this->mattress, '2030-07-01', '2030-07-05', qty: 2);

        $this->get('/combos?start=2030-07-02&end=2030-07-04')->assertOk()->assertInertia(fn ($page) => $page
            ->where('combos.0.available', 0)
            ->where('filters.start', '2030-07-02')
        );

        // Khoảng không chồng → còn hàng (min(2/1, 2/2) = 1)
        $this->get('/combos?start=2030-07-10&end=2030-07-12')->assertOk()->assertInertia(fn ($page) => $page
            ->where('combos.0.available', 1)
        );
    }

    /** @test */
    public function combo_detail_shows_items_and_price_comparison(): void
    {
        $this->get('/combos/combo-cap-doi')->assertOk()->assertInertia(fn ($page) => $page
            ->component('ComboDetail')
            ->where('combo.name', 'Combo Cặp Đôi')
            ->where('combo.sum_individual', 180000)
            ->has('combo.items', 2)
            ->where('combo.items.0.name', 'Lều Test')
            ->where('combo.items.0.product_id', $this->tent->id)
        );
    }

    /** @test */
    public function hidden_combo_detail_returns_404(): void
    {
        $this->combo->update(['is_active' => false]);

        $this->get('/combos/combo-cap-doi')->assertNotFound();
    }

    /**
     * Case 4 — endpoint kiểm realtime: món nào hết, khoảng gần nhất còn đủ (≤30 ngày),
     * sản phẩm thay thế cùng danh mục còn hàng (chỉ tham khảo).
     *
     * @test
     */
    public function availability_endpoint_reports_case4_details(): void
    {
        // Đệm bị chiếm hết 07/01–07/05 → combo hết; từ 07/06 trở đi rảnh
        $this->bookedOrder($this->mattress, '2030-07-01', '2030-07-05', qty: 2);

        $res = $this->getJson('/combos/combo-cap-doi/kha-dung?start=2030-07-03&end=2030-07-04')
            ->assertOk()
            ->json();

        $this->assertSame(0, $res['available']);

        // Món hết phải nêu đúng tên đệm (lều vẫn còn)
        $this->assertCount(1, $res['insufficient']);
        $this->assertSame('Đệm hơi Test', $res['insufficient'][0]['name']);
        $this->assertSame(0, $res['insufficient'][0]['available']);
        $this->assertSame(2, $res['insufficient'][0]['required']);

        // Khoảng gần nhất còn đủ, giữ nguyên độ dài 2 ngày: 06–07/07
        $this->assertSame('2030-07-06', $res['next_window']['start']);
        $this->assertSame('2030-07-07', $res['next_window']['end']);

        // Thay thế cùng danh mục với món hết, còn hàng trong khoảng
        $this->assertNotEmpty($res['substitutes']);
        $this->assertSame('Đệm bọt Test', $res['substitutes'][0]['name']);
    }

    /** @test */
    public function availability_endpoint_returns_available_when_in_stock(): void
    {
        $res = $this->getJson('/combos/combo-cap-doi/kha-dung?start=2030-07-01&end=2030-07-03')
            ->assertOk()
            ->json();

        $this->assertSame(1, $res['available']);
        $this->assertSame([], $res['insufficient']);
        $this->assertNull($res['next_window']);
    }

    /** @test */
    public function availability_endpoint_validates_dates(): void
    {
        $this->getJson('/combos/combo-cap-doi/kha-dung?start=xx&end=2030-07-03')->assertStatus(422);
        $this->getJson('/combos/combo-cap-doi/kha-dung')->assertStatus(422);
    }

    /** @test */
    public function homepage_has_featured_combos_section(): void
    {
        // Thêm vị trí phục vụ để trang chủ render đủ props như thật
        ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1]);

        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->has('featured_combos', 1)
            ->where('featured_combos.0.slug', 'combo-cap-doi')
            ->where('featured_combos.0.savings_amount', 60000)
        );
    }

    /** @test */
    public function combo_without_items_is_not_listed_publicly(): void
    {
        Combo::create(['name' => 'Combo Rỗng', 'slug' => 'combo-rong', 'combo_price' => 1000]);

        $this->get('/combos')->assertInertia(fn ($page) => $page->has('combos', 1));
        $this->get('/')->assertInertia(fn ($page) => $page->has('featured_combos', 1));
    }

    // -------------------------------------------------------------------------

    private function bookedOrder(Product $p, string $start, string $end, int $qty): Order
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
            'product_id' => $p->id,
            'quantity' => $qty,
            'price_per_day' => $p->price_per_day,
            'days' => 1,
            'subtotal' => $qty * $p->price_per_day,
        ]);

        return $order;
    }
}

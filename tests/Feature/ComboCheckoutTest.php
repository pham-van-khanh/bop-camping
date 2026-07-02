<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * bopcamping-6he (Combo P2) — checkout combo end-to-end (AC-2, AC-3, AC-4):
 * combo bung thành order_items cùng combo_group_uuid, snapshot allocated_price/
 * allocated_deposit, chiếm tồn kho per-product như đơn lẻ, validate qua
 * AvailabilityService (không có cơ chế giữ chỗ riêng).
 * Ngày 2030 để luôn thoả after_or_equal:today (theo OrderCheckoutTest).
 */
class ComboCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Product $tent;    // 100k/ngày, cọc 300k, kho 3

    private Product $bag;     // 30k/ngày, cọc 100k, kho 5

    private Combo $combo;     // 1 lều + 3 túi, giá 150k/ngày, cọc 400k

    protected function setUp(): void
    {
        parent::setUp();

        $cat = Category::create(['name' => 'Lều', 'slug' => 'leu']);
        $this->tent = Product::create([
            'category_id' => $cat->id,
            'name' => 'Lều Test',
            'slug' => 'leu-test',
            'price_per_day' => 100000,
            'quantity' => 3,
            'deposit' => 300000,
        ]);
        $this->bag = Product::create([
            'category_id' => $cat->id,
            'name' => 'Túi ngủ Test',
            'slug' => 'tui-ngu-test',
            'price_per_day' => 30000,
            'quantity' => 5,
            'deposit' => 100000,
        ]);

        $this->combo = Combo::create([
            'name' => 'Combo Test',
            'slug' => 'combo-test',
            'combo_price' => 150000,
            'deposit' => 400000,
        ]);
        $this->combo->items()->create(['product_id' => $this->tent->id, 'quantity' => 1]);
        $this->combo->items()->create(['product_id' => $this->bag->id, 'quantity' => 3]);
    }

    private function checkout(array $payload = []): TestResponse
    {
        return $this->post(route('order.store'), array_merge([
            'name' => 'Khách Combo',
            'phone' => '0912345678',
        ], $payload));
    }

    /**
     * AC-3: đặt combo → N dòng order_items cùng combo_group_uuid,
     * tổng allocated_price = combo_price chính xác đến từng đồng.
     *
     * @test
     */
    public function combo_checkout_explodes_into_order_items_with_group_uuid(): void
    {
        $this->checkout([
            'combos' => [['combo_id' => $this->combo->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-03']],
        ])->assertSessionHasNoErrors()->assertSessionHas('order_code');

        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertSame(2, $order->items()->count());

        $items = $order->items()->orderBy('id')->get();
        // Cùng 1 nhóm combo
        $this->assertNotNull($items[0]->combo_group_uuid);
        $this->assertSame($items[0]->combo_group_uuid, $items[1]->combo_group_uuid);
        $this->assertTrue($items->every(fn (OrderItem $i) => $i->combo_id === $this->combo->id));

        // Phân bổ khớp từng đồng: sum lẻ = 100k + 90k = 190k
        // lều: floor100(150000×100/190) = 78900 · túi: 150000−78900 = 71100
        $this->assertSame(150000, (int) $items->sum('allocated_price'));
        $this->assertSame(400000, (int) $items->sum('allocated_deposit'));

        // 3 ngày: tổng thuê = combo_price × 3; cọc = cọc combo (1 lần, không nhân ngày)
        $this->assertSame(450000, (int) $order->total_price);
        $this->assertSame(400000, (int) $order->deposit_total);

        // subtotal từng dòng = allocated_price × days (nhất quán tổng đơn)
        $this->assertTrue($items->every(fn (OrderItem $i) => (int) $i->subtotal === (int) $i->allocated_price * 3));

        // Snapshot giá lẻ để đối chiếu về sau
        $this->assertSame(100000, (int) $items[0]->price_per_day);
        $this->assertSame([1, 3], $items->pluck('quantity')->map(fn ($q) => (int) $q)->all());
    }

    /**
     * 1 đơn chứa 2 combo giống nhau → 2 nhóm uuid khác nhau (PRD mục 4).
     *
     * @test
     */
    public function two_of_same_combo_get_distinct_group_uuids(): void
    {
        // kho túi = 5 không đủ 2 combo (cần 6) → nới kho cho test này
        $this->bag->update(['quantity' => 6]);

        $this->checkout([
            'combos' => [['combo_id' => $this->combo->id, 'quantity' => 2, 'start' => '2030-07-01', 'end' => '2030-07-02']],
        ])->assertSessionHasNoErrors()->assertSessionHas('order_code');

        $order = Order::first();
        $this->assertSame(4, $order->items()->count()); // 2 nhóm × 2 món

        $groups = $order->items->groupBy('combo_group_uuid');
        $this->assertCount(2, $groups);
        // Mỗi nhóm tự khớp tổng
        foreach ($groups as $group) {
            $this->assertSame(150000, (int) $group->sum('allocated_price'));
            $this->assertSame(400000, (int) $group->sum('allocated_deposit'));
        }
        // Đơn: thuê = 2 combo × 150k × 2 ngày; cọc = 2 × 400k
        $this->assertSame(600000, (int) $order->total_price);
        $this->assertSame(800000, (int) $order->deposit_total);
    }

    /**
     * AC-2: combo hết vì 1 món con hết trong khoảng ngày → báo lỗi kèm tên món.
     *
     * @test
     */
    public function combo_rejected_when_one_item_out_of_stock(): void
    {
        // Chiếm 3/5 túi ngủ trong khoảng chồng lịch → còn 2 < 3 cần cho combo
        $this->bookedOrder($this->bag, '2030-07-02', '2030-07-05', qty: 3);

        $this->checkout([
            'combos' => [['combo_id' => $this->combo->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-03']],
        ])->assertSessionHasErrors('items');

        // Lỗi phải nêu đúng món hết (Case 4 phía server)
        $errors = session('errors')->get('items');
        $this->assertStringContainsString('Túi ngủ Test', implode(' ', $errors));
        $this->assertSame(1, Order::count()); // không tạo đơn mới
    }

    /**
     * AC-4 (chiều 1): đơn combo chiếm tồn kho product con → khách thuê lẻ thấy giảm đúng.
     *
     * @test
     */
    public function combo_order_consumes_stock_for_single_rentals(): void
    {
        $this->checkout([
            'combos' => [['combo_id' => $this->combo->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-05']],
        ])->assertSessionHas('order_code');

        // Túi ngủ còn 5−3 = 2 → thuê lẻ 3 túi cùng khoảng phải bị chặn
        $this->checkout([
            'items' => [['product_id' => $this->bag->id, 'quantity' => 3, 'start' => '2030-07-03', 'end' => '2030-07-06']],
        ])->assertSessionHasErrors('items');

        // Thuê lẻ 2 túi thì được
        $this->checkout([
            'items' => [['product_id' => $this->bag->id, 'quantity' => 2, 'start' => '2030-07-03', 'end' => '2030-07-06']],
        ])->assertSessionHas('order_code');
    }

    /**
     * AC-4 (chiều 2): đơn lẻ chiếm kho → combo hết theo.
     *
     * @test
     */
    public function single_rental_blocks_combo(): void
    {
        $this->bookedOrder($this->tent, '2030-07-01', '2030-07-10', qty: 3); // hết sạch lều

        $this->checkout([
            'combos' => [['combo_id' => $this->combo->id, 'quantity' => 1, 'start' => '2030-07-05', 'end' => '2030-07-07']],
        ])->assertSessionHasErrors('items');
    }

    /**
     * Đơn hỗn hợp combo + lẻ: tổng tiền/cọc cộng đúng cả hai phần.
     *
     * @test
     */
    public function mixed_order_combines_combo_and_single_items(): void
    {
        $this->checkout([
            'items' => [['product_id' => $this->tent->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-03']],
            'combos' => [['combo_id' => $this->combo->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-03']],
        ])->assertSessionHasNoErrors()->assertSessionHas('order_code');

        $order = Order::first();
        $this->assertSame(3, $order->items()->count()); // 1 lẻ + 2 từ combo

        // Lẻ: 100k × 3 ngày = 300k · combo: 150k × 3 = 450k
        $this->assertSame(750000, (int) $order->total_price);
        // Cọc lẻ 300k + cọc combo 400k
        $this->assertSame(700000, (int) $order->deposit_total);

        // Dòng lẻ không có combo metadata
        $single = $order->items()->whereNull('combo_id')->first();
        $this->assertNotNull($single);
        $this->assertNull($single->combo_group_uuid);
        $this->assertNull($single->allocated_price);
    }

    /**
     * Cùng 1 sản phẩm vừa trong combo vừa thuê lẻ cùng khoảng → tổng nhu cầu
     * phải được cộng gộp khi kiểm kho (chống overbook do check tách rời).
     *
     * @test
     */
    public function combo_plus_single_of_same_product_checked_together(): void
    {
        // kho lều = 3; combo cần 1 + lẻ cần 3 = 4 > 3 → phải bị chặn
        $this->checkout([
            'items' => [['product_id' => $this->tent->id, 'quantity' => 3, 'start' => '2030-07-01', 'end' => '2030-07-03']],
            'combos' => [['combo_id' => $this->combo->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-03']],
        ])->assertSessionHasErrors('items');

        $this->assertSame(0, Order::count());
    }

    /** @test */
    public function inactive_combo_is_rejected(): void
    {
        $this->combo->update(['is_active' => false]);

        $this->checkout([
            'combos' => [['combo_id' => $this->combo->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-03']],
        ])->assertSessionHasErrors('items');

        $this->assertSame(0, Order::count());
    }

    /** @test */
    public function order_requires_at_least_one_item_or_combo(): void
    {
        $this->checkout(['items' => [], 'combos' => []])->assertSessionHasErrors('items');
        $this->checkout([])->assertSessionHasErrors('items');
    }

    /**
     * Đơn chỉ có combo (không items lẻ) vẫn đặt được — khách vãng lai không đăng nhập.
     *
     * @test
     */
    public function combo_only_order_works_for_guest(): void
    {
        $this->checkout([
            'combos' => [['combo_id' => $this->combo->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-02']],
        ])->assertSessionHas('order_code');

        $order = Order::first();
        $this->assertNull($order->user_id);
        $this->assertSame('2030-07-01', $order->start_date->toDateString());
        $this->assertSame('2030-07-02', $order->end_date->toDateString());
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

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * bopcamping-1z1 — trang sản phẩm lẻ phải hiện tồn kho THEO KHOẢNG NGÀY,
 * không phải quantity tĩnh. Bug do chủ shop phát hiện khi test P2: combo
 * chiếm 4/6 ghế nhưng trang ghế vẫn hiện "còn đủ 6 bộ" và cho thêm 6 vào giỏ.
 * Endpoint mirror /combos/{slug}/kha-dung, cùng đi qua AvailabilityService (AC-10).
 */
class ProductAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private Product $chair; // kho 6

    protected function setUp(): void
    {
        parent::setUp();

        $cat = Category::create(['name' => 'Bàn ghế', 'slug' => 'ban-ghe']);
        $this->chair = Product::create([
            'category_id' => $cat->id,
            'name' => 'Ghế gấp Test',
            'slug' => 'ghe-gap-test',
            'price_per_day' => 40000,
            'quantity' => 6,
        ]);
    }

    /** @test */
    public function returns_full_quantity_when_no_orders(): void
    {
        $this->getJson('/thiet-bi/'.$this->chair->id.'/kha-dung?start=2030-07-10&end=2030-07-12')
            ->assertOk()
            ->assertExactJson(['available' => 6]);
    }

    /**
     * Kịch bản đúng như chủ shop test: combo chiếm 4 ghế trong 10–12/07
     * → trang ghế cùng khoảng phải trả 2, khoảng khác vẫn 6.
     *
     * @test
     */
    public function combo_order_reduces_availability_for_range(): void
    {
        $combo = Combo::create(['name' => 'Combo Test', 'slug' => 'combo-test', 'combo_price' => 100000]);
        $combo->items()->create(['product_id' => $this->chair->id, 'quantity' => 4]);

        $order = Order::create([
            'code' => 'BOP-'.strtoupper(uniqid()),
            'customer_name' => 'X',
            'customer_phone' => '0900000000',
            'start_date' => '2030-07-10',
            'end_date' => '2030-07-12',
            'status' => 'pending', // đơn chờ xác nhận vẫn chiếm kho
            'payment_method' => 'cod',
        ]);
        $order->items()->create([
            'product_id' => $this->chair->id,
            'combo_id' => $combo->id,
            'combo_group_uuid' => (string) Str::uuid(),
            'quantity' => 4,
            'price_per_day' => 40000,
            'days' => 3,
            'subtotal' => 480000,
        ]);

        $this->getJson('/thiet-bi/'.$this->chair->id.'/kha-dung?start=2030-07-10&end=2030-07-12')
            ->assertOk()
            ->assertExactJson(['available' => 2]);

        // Chồng một phần cũng bị trừ
        $this->getJson('/thiet-bi/'.$this->chair->id.'/kha-dung?start=2030-07-12&end=2030-07-14')
            ->assertOk()
            ->assertExactJson(['available' => 2]);

        // Khoảng không chồng → đủ 6
        $this->getJson('/thiet-bi/'.$this->chair->id.'/kha-dung?start=2030-07-13&end=2030-07-15')
            ->assertOk()
            ->assertExactJson(['available' => 6]);
    }

    /** @test */
    public function validates_date_params(): void
    {
        $this->getJson('/thiet-bi/'.$this->chair->id.'/kha-dung')->assertStatus(422);
        $this->getJson('/thiet-bi/'.$this->chair->id.'/kha-dung?start=xx&end=2030-07-12')->assertStatus(422);
        // end trước start
        $this->getJson('/thiet-bi/'.$this->chair->id.'/kha-dung?start=2030-07-12&end=2030-07-10')->assertStatus(422);
    }

    /** @test */
    public function hidden_product_returns_404(): void
    {
        $this->chair->update(['status' => 'hidden']);

        $this->getJson('/thiet-bi/'.$this->chair->id.'/kha-dung?start=2030-07-10&end=2030-07-12')
            ->assertNotFound();
    }
}

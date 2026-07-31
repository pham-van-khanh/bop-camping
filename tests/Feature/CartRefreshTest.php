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
 * bopcamping-7sj — làm tươi giỏ: trả giá/vị trí mới nhất, bỏ sản phẩm ẩn/xoá.
 * bopcamping-80c (ADR-5) — re-check tồn kho theo khoảng ngày từng dòng (pr[]/cr[])
 * để giỏ cảnh báo món/combo hết hàng ngay, không đợi checkout.
 */
class CartRefreshTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $name, string $slug, int $price, string $status = 'active'): Product
    {
        $cat = Category::firstOrCreate(['slug' => 'leu'], ['name' => 'Lều']);

        return Product::create([
            'category_id' => $cat->id,
            'name' => $name,
            'slug' => $slug,
            'price_per_day' => $price,
            'deposit' => 100000,
            'quantity' => 5,
            'status' => $status,
        ]);
    }

    /** @test */
    public function returns_fresh_price_and_locations(): void
    {
        $vinh = ServiceLocation::create(['name' => 'Vinh', 'status' => 'open', 'sort_order' => 1]);
        ServiceLocation::create(['name' => 'Hà Nội', 'status' => 'open', 'sort_order' => 2]);

        $p = $this->product('Lều', 'leu-a', 90000);
        $p->serviceLocations()->sync([$vinh->id]); // chỉ Vinh

        $this->getJson(route('cart.refresh', ['ids' => [$p->id]]))
            ->assertOk()
            ->assertJsonPath("products.{$p->id}.price_per_day", 90000)
            ->assertJsonPath("products.{$p->id}.deposit", 100000)
            ->assertJsonPath("products.{$p->id}.all_locations", false)
            ->assertJsonPath("products.{$p->id}.locations.0.slug", 'vinh');
    }

    /** @test */
    public function all_locations_true_when_serving_every_open_location(): void
    {
        $vinh = ServiceLocation::create(['name' => 'Vinh', 'status' => 'open', 'sort_order' => 1]);
        $hanoi = ServiceLocation::create(['name' => 'Hà Nội', 'status' => 'open', 'sort_order' => 2]);

        $p = $this->product('Lều', 'leu-b', 90000);
        $p->serviceLocations()->sync([$vinh->id, $hanoi->id]);

        $this->getJson(route('cart.refresh', ['ids' => [$p->id]]))
            ->assertOk()
            ->assertJsonPath("products.{$p->id}.all_locations", true);
    }

    /** @test */
    public function hidden_or_missing_products_are_omitted(): void
    {
        $active = $this->product('Còn bán', 'con-ban', 50000);
        $hidden = $this->product('Đã ẩn', 'da-an', 50000, status: 'hidden');

        $res = $this->getJson(route('cart.refresh', ['ids' => [$active->id, $hidden->id, 99999]]))
            ->assertOk();

        $res->assertJsonPath("products.{$active->id}.name", 'Còn bán');
        $this->assertArrayNotHasKey((string) $hidden->id, $res->json('products'));
        $this->assertArrayNotHasKey('99999', $res->json('products'));
    }

    /** @test */
    public function empty_ids_returns_empty(): void
    {
        // 'combos' thêm từ Combo P2 (bopcamping-6he), 'stock' từ bopcamping-80c
        $this->getJson(route('cart.refresh'))
            ->assertOk()
            ->assertExactJson(['products' => [], 'combos' => [], 'stock' => []]);
    }

    /**
     * bopcamping-80c: tồn kho theo khoảng ngày từng dòng — dòng chồng lịch
     * bị trừ đúng, dòng khoảng khác đủ kho, combo hết vì món con → 0.
     *
     * @test
     */
    public function stock_reflects_per_line_availability(): void
    {
        $p = $this->product('Lều', 'leu-stock', 90000); // kho 5

        // bopcamping-zdeh: combo giờ có kho riêng, comboAvailable() không fallback toàn cục
        // khi chưa gán kho. Test này không quan tâm chuyện theo-kho, nên gắn món dùng trong
        // combo vào 1 kho duy nhất với đúng tồn toàn cục (buffer 0) để số liệu giữ nguyên.
        $location = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1]);
        $p->serviceLocations()->attach($location->id, ['quantity' => $p->quantity, 'buffer_days' => 0]);

        $combo = Combo::create(['name' => 'Combo Stock', 'slug' => 'combo-stock', 'combo_price' => 80000]);
        $combo->items()->create(['product_id' => $p->id, 'quantity' => 2]);
        $combo->serviceLocations()->sync($combo->fresh()->assignableLocationIds());

        // Chiếm 4/5 lều trong 10–12/07
        $order = Order::factory()->create(['start_date' => '2030-07-10', 'end_date' => '2030-07-12']);
        $order->items()->create([
            'product_id' => $p->id, 'quantity' => 4,
            'price_per_day' => 90000, 'days' => 3, 'subtotal' => 4 * 3 * 90000,
        ]);

        $this->getJson(route('cart.refresh', [
            'pr' => ["{$p->id}:2030-07-10:2030-07-12", "{$p->id}:2030-07-20:2030-07-22"],
            'cr' => ["{$combo->id}:2030-07-10:2030-07-12", "{$combo->id}:2030-07-20:2030-07-22"],
        ]))
            ->assertOk()
            ->assertJsonPath("stock.p:{$p->id}:2030-07-10:2030-07-12", 1)
            ->assertJsonPath("stock.p:{$p->id}:2030-07-20:2030-07-22", 5)
            // combo cần 2 lều/bộ: còn 1 → 0 bộ; khoảng trống → intdiv(5,2) = 2
            ->assertJsonPath("stock.c:{$combo->id}:2030-07-10:2030-07-12", 0)
            ->assertJsonPath("stock.c:{$combo->id}:2030-07-20:2030-07-22", 2);
    }

    /** @test */
    public function stock_skips_malformed_entries_and_hidden_items(): void
    {
        $hidden = $this->product('Ẩn', 'leu-an', 90000, status: 'hidden');

        $res = $this->getJson(route('cart.refresh', [
            'pr' => ['abc', '1:2030-13-99', "{$hidden->id}:2030-07-10:2030-07-12", '2:2030-07-12:2030-07-10'],
        ]))->assertOk();

        $this->assertSame([], (array) $res->json('stock'));
    }
}

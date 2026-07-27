<?php

/**
 * ComboAdminAndCheckoutTest — AC-1, AC-3, AC-7 trong prd_combo.md
 *
 * Template do chủ shop cung cấp, đã adapt theo code thực tế (không đổi test case):
 *   - Admin routes: admin.combos.store / admin.products.update (payload thật)
 *   - Warning giá combo ≥ tổng lẻ = validation error 'combo_price'
 *     (override bằng confirm_over_price — xem AdminComboTest)
 *   - Product không có is_active mà dùng status active/hidden
 *   - Checkout qua route('order.store') với combos[] {combo_id, quantity, start, end};
 *     hết hàng trả lỗi session 'items' (redirect back), không ném ValidationException
 *   - Factories → Model::create().
 */

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ComboAdminAndCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = Category::create(['name' => 'Test', 'slug' => 'test']);
    }

    private function actingAsAdmin(): static
    {
        return $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    private function makeProduct(array $attrs = []): Product
    {
        return Product::create($attrs + [
            'category_id' => $this->category->id,
            'name' => 'SP '.uniqid(),
            'slug' => 'sp-'.uniqid(),
            'price_per_day' => 100_000,
            'quantity' => 5,
        ]);
    }

    private function makeCombo(array $attrs = []): Combo
    {
        return Combo::create($attrs + [
            'name' => 'Combo '.uniqid(),
            'slug' => 'combo-'.uniqid(),
            'combo_price' => 100_000,
            'is_active' => true,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // AC-1: Admin tạo combo
    // ─────────────────────────────────────────────────────────────

    public function test_admin_tao_combo_thanh_cong(): void
    {
        $tent = $this->makeProduct(['price_per_day' => 200_000]);
        $chair = $this->makeProduct(['price_per_day' => 25_000]);

        $this->actingAsAdmin()
            ->post(route('admin.combos.store'), [
                'name' => 'Combo Gia Đình 4 Người',
                'combo_price' => 250_000,
                'deposit' => 500_000,
                'is_active' => true,
                'items' => [
                    ['product_id' => $tent->id, 'quantity' => 1],
                    ['product_id' => $chair->id, 'quantity' => 4],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('combos', ['name' => 'Combo Gia Đình 4 Người']);
        $this->assertDatabaseHas('combo_items', [
            'product_id' => $chair->id,
            'quantity' => 4,
        ]);
    }

    public function test_khong_the_tao_combo_khong_co_san_pham(): void
    {
        $this->actingAsAdmin()
            ->post(route('admin.combos.store'), [
                'name' => 'Combo rỗng',
                'combo_price' => 100_000,
                'items' => [],
            ])
            ->assertSessionHasErrors('items');
    }

    public function test_gia_combo_cao_hon_tong_le_phai_co_warning(): void
    {
        // PRD 5.2: warning khi combo_price >= sum_individual — implement là
        // validation error trên combo_price, override có chủ đích qua confirm_over_price.
        $product = $this->makeProduct(['price_per_day' => 100_000]);

        $response = $this->actingAsAdmin()
            ->post(route('admin.combos.store'), [
                'name' => 'Combo đắt hơn lẻ',
                'combo_price' => 150_000,
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ]);

        $response->assertSessionHasErrors('combo_price');
    }

    // ─────────────────────────────────────────────────────────────
    // AC-7: Toàn vẹn dữ liệu — deactivate product thuộc combo
    // ─────────────────────────────────────────────────────────────

    public function test_deactivate_product_thuoc_combo_thi_combo_tu_an(): void
    {
        $product = $this->makeProduct(['status' => 'active']);
        $combo = $this->makeCombo();
        $combo->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        $loc = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1]);

        $this->actingAsAdmin()->put(route('admin.products.update', $product), [
            'name' => $product->name,
            'category_id' => $product->category_id,
            'price_per_day' => $product->price_per_day,
            'quantity' => $product->quantity,
            'status' => 'hidden',
            'service_location_ids' => [$loc->id],
        ]);

        $this->assertFalse($combo->fresh()->is_active, 'Combo phải tự ẩn khi món con bị deactivate');
    }

    public function test_combo_an_khong_hien_tren_trang_public(): void
    {
        $product = $this->makeProduct();
        $active = $this->makeCombo(['name' => 'Combo Hiện']);
        $active->items()->create(['product_id' => $product->id, 'quantity' => 1]);
        $hidden = $this->makeCombo(['name' => 'Combo Ẩn', 'is_active' => false]);
        $hidden->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        // Trang Inertia — check props thay vì assertSee HTML
        $this->get(route('combos'))->assertOk()->assertInertia(fn ($page) => $page
            ->has('combos', 1)
            ->where('combos.0.name', 'Combo Hiện')
        );
    }

    // ─────────────────────────────────────────────────────────────
    // AC-3: Checkout combo → order_items bung đúng cấu trúc
    // ─────────────────────────────────────────────────────────────

    public function test_checkout_combo_bung_thanh_order_items_theo_mon_con(): void
    {
        $tent = $this->makeProduct(['price_per_day' => 200_000]);
        $mat = $this->makeProduct(['price_per_day' => 100_000]);

        $combo = $this->makeCombo(['combo_price' => 240_000]);
        $combo->items()->createMany([
            ['product_id' => $tent->id, 'quantity' => 1],
            ['product_id' => $mat->id, 'quantity' => 1],
        ]);

        $this->checkoutComboHelper($combo, '2030-07-12', '2030-07-14')->assertSessionHasNoErrors();
        $order = Order::latest('id')->with('items')->first();

        // 1 combo 2 món → 2 order_items
        $this->assertCount(2, $order->items);

        // Cùng combo_group_uuid, đều gắn combo_id
        $uuids = $order->items->pluck('combo_group_uuid')->unique();
        $this->assertCount(1, $uuids);
        $this->assertNotNull($uuids->first());
        $this->assertTrue($order->items->every(fn ($i) => $i->combo_id === $combo->id));

        // Tổng allocated_price = combo_price chính xác
        $this->assertSame(240_000, (int) $order->items->sum('allocated_price'));
    }

    public function test_don_co_2_combo_giong_nhau_co_2_group_uuid_khac_nhau(): void
    {
        $tent = $this->makeProduct(['price_per_day' => 200_000]);
        $combo = $this->makeCombo(['combo_price' => 180_000]);
        $combo->items()->create(['product_id' => $tent->id, 'quantity' => 1]);

        $this->checkoutComboHelper($combo, '2030-07-12', '2030-07-14', comboQty: 2)->assertSessionHasNoErrors();
        $order = Order::latest('id')->with('items')->first();

        $this->assertCount(
            2,
            $order->items->pluck('combo_group_uuid')->unique(),
            'Mỗi combo trong đơn phải có group_uuid riêng để tách khi hoàn cọc'
        );
    }

    public function test_checkout_combo_het_hang_bi_chan(): void
    {
        $mat = $this->makeProduct(['quantity' => 1]);
        $combo = $this->makeCombo(['combo_price' => 80_000]);
        $combo->items()->create(['product_id' => $mat->id, 'quantity' => 1]);

        // Đơn 1 chiếm hết — xác nhận để khoá tồn (feedback 2026-07-27: pending không khoá)
        $this->checkoutComboHelper($combo, '2030-07-12', '2030-07-14')->assertSessionHasNoErrors();
        Order::latest('id')->first()->update(['status' => 'confirmed']);

        // Đơn 2 chồng khoảng ngày → lỗi validation 'items', không tạo thêm order
        $this->checkoutComboHelper($combo, '2030-07-13', '2030-07-15')
            ->assertSessionHasErrors('items');
        $this->assertSame(1, Order::count());
    }

    /** Checkout thật của dự án: POST route('order.store') với combos[] có start/end per line. */
    private function checkoutComboHelper(Combo $combo, string $start, string $end, int $comboQty = 1): TestResponse
    {
        return $this->post(route('order.store'), [
            'name' => 'Nguyễn Văn Test',
            'phone' => '0900000000',
            'combos' => [[
                'combo_id' => $combo->id,
                'quantity' => $comboQty,
                'start' => $start,
                'end' => $end,
            ]],
        ]);
    }
}

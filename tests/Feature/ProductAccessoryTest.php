<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-clv (Combo P3) — "Thường thuê cùng" (US-03, US-08, Case 2, PRD 5.6):
 * - Trang sản phẩm nhận props `accessories` (gán tay ở admin, theo sort_order,
 *   chỉ món active) + `combo_banner` (combo active tiết kiệm nhất chứa sản phẩm
 *   — ưu tiên hiển thị hơn gợi ý lẻ).
 * - AC-9: endpoint /thiet-bi/{id}/goi-y-kha-dung trả tồn kho từng phụ kiện +
 *   tồn combo banner THEO KHOẢNG NGÀY, đi qua AvailabilityService (AC-10) —
 *   FE chỉ hiện món còn hàng trong khoảng đang chọn.
 * - US-08: admin gán/sắp xếp/xoá accessories qua accessory_ids trong form sản phẩm.
 */
class ProductAccessoryTest extends TestCase
{
    use RefreshDatabase;

    private Product $tent;   // sản phẩm chính, 100k/ngày, kho 3

    private Product $chair;  // phụ kiện, 40k/ngày, kho 6

    private Product $mat;    // phụ kiện, 60k/ngày, kho 2

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
        $this->chair = Product::create([
            'category_id' => $cat->id,
            'name' => 'Ghế gấp Test',
            'slug' => 'ghe-gap-test',
            'price_per_day' => 40000,
            'quantity' => 6,
        ]);
        $this->mat = Product::create([
            'category_id' => $cat->id,
            'name' => 'Đệm hơi Test',
            'slug' => 'dem-hoi-test',
            'price_per_day' => 60000,
            'quantity' => 2,
        ]);

        $this->tent->accessories()->attach([
            $this->chair->id => ['sort_order' => 0],
            $this->mat->id => ['sort_order' => 1],
        ]);
    }

    /** @test */
    public function show_lists_accessories_by_sort_order(): void
    {
        // Đảo thứ tự: đệm lên trước — chứng minh sort theo pivot, không theo id
        $this->tent->accessories()->sync([
            $this->mat->id => ['sort_order' => 0],
            $this->chair->id => ['sort_order' => 1],
        ]);

        $this->get('/thiet-bi/'.$this->tent->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProductDetail')
                ->count('accessories', 2)
                ->where('accessories.0.id', $this->mat->id)
                ->where('accessories.0.name', 'Đệm hơi Test')
                ->where('accessories.0.price_per_day', 60000)
                ->where('accessories.1.id', $this->chair->id));
    }

    /** @test */
    public function hidden_accessory_is_excluded_from_show(): void
    {
        $this->mat->update(['status' => 'hidden']);

        $this->get('/thiet-bi/'.$this->tent->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->count('accessories', 1)
                ->where('accessories.0.id', $this->chair->id));
    }

    /**
     * AC-9 (nguồn dữ liệu cho filter): tồn kho phụ kiện tính theo khoảng ngày —
     * món kín lịch trả 0 (FE ẩn), món còn một phần trả đúng số còn lại.
     *
     * @test
     */
    public function suggestion_availability_reflects_overlapping_orders(): void
    {
        $this->bookedOrder($this->chair, '2030-07-10', '2030-07-12', qty: 6); // ghế kín lịch
        $this->bookedOrder($this->mat, '2030-07-10', '2030-07-12', qty: 1);   // đệm còn 1

        $this->getJson('/thiet-bi/'.$this->tent->slug.'/goi-y-kha-dung?start=2030-07-10&end=2030-07-12')
            ->assertOk()
            ->assertJson([
                'accessories' => [
                    ['id' => $this->chair->id, 'available' => 0],
                    ['id' => $this->mat->id, 'available' => 1],
                ],
                'combo_available' => null, // chưa có combo nào chứa lều
            ]);

        // Chồng một phần (ngày cuối trùng ngày đầu đơn cũ) vẫn bị trừ
        $this->getJson('/thiet-bi/'.$this->tent->slug.'/goi-y-kha-dung?start=2030-07-08&end=2030-07-10')
            ->assertOk()
            ->assertJson(['accessories' => [
                ['id' => $this->chair->id, 'available' => 0],
                ['id' => $this->mat->id, 'available' => 1],
            ]]);

        // Khoảng không chồng → đủ kho
        $this->getJson('/thiet-bi/'.$this->tent->slug.'/goi-y-kha-dung?start=2030-07-13&end=2030-07-15')
            ->assertOk()
            ->assertJson(['accessories' => [
                ['id' => $this->chair->id, 'available' => 6],
                ['id' => $this->mat->id, 'available' => 2],
            ]]);
    }

    /** @test */
    public function suggestion_endpoint_skips_hidden_accessories_and_validates_params(): void
    {
        $this->mat->update(['status' => 'hidden']);

        $this->getJson('/thiet-bi/'.$this->tent->slug.'/goi-y-kha-dung?start=2030-07-10&end=2030-07-12')
            ->assertOk()
            ->assertJsonCount(1, 'accessories')
            ->assertJson(['accessories' => [['id' => $this->chair->id, 'available' => 6]]]);

        $this->getJson('/thiet-bi/'.$this->tent->slug.'/goi-y-kha-dung')->assertStatus(422);
        $this->getJson('/thiet-bi/'.$this->tent->slug.'/goi-y-kha-dung?start=xx&end=2030-07-12')->assertStatus(422);
        // end trước start
        $this->getJson('/thiet-bi/'.$this->tent->slug.'/goi-y-kha-dung?start=2030-07-12&end=2030-07-10')->assertStatus(422);

        // Sản phẩm ẩn → 404 như trang chi tiết
        $this->tent->update(['status' => 'hidden']);
        $this->getJson('/thiet-bi/'.$this->tent->slug.'/goi-y-kha-dung?start=2030-07-10&end=2030-07-12')->assertNotFound();
    }

    /**
     * PRD 5.6 — banner "thuộc combo": chọn combo ACTIVE tiết kiệm nhiều nhất
     * chứa sản phẩm; combo bị ẩn không được tính; không có combo → null.
     *
     * @test
     */
    public function show_picks_best_saving_active_combo_for_banner(): void
    {
        // comboA: lều + 2 ghế → giá lẻ 180k, bán 120k → tiết kiệm 60k
        $comboA = Combo::create(['name' => 'Combo A', 'slug' => 'combo-a', 'combo_price' => 120000]);
        $comboA->items()->create(['product_id' => $this->tent->id, 'quantity' => 1]);
        $comboA->items()->create(['product_id' => $this->chair->id, 'quantity' => 2]);

        // comboB: lều + đệm → giá lẻ 160k, bán 140k → tiết kiệm 20k
        $comboB = Combo::create(['name' => 'Combo B', 'slug' => 'combo-b', 'combo_price' => 140000]);
        $comboB->items()->create(['product_id' => $this->tent->id, 'quantity' => 1]);
        $comboB->items()->create(['product_id' => $this->mat->id, 'quantity' => 1]);

        $this->get('/thiet-bi/'.$this->tent->slug)
            ->assertInertia(fn (Assert $page) => $page
                ->where('combo_banner.id', $comboA->id)
                ->where('combo_banner.slug', 'combo-a')
                ->where('combo_banner.savings_amount', 60000)
                ->where('combo_banner.combo_price', 120000));

        // Combo tiết kiệm nhất bị ẩn → rơi về combo còn lại
        $comboA->update(['is_active' => false]);
        $this->get('/thiet-bi/'.$this->tent->slug)
            ->assertInertia(fn (Assert $page) => $page->where('combo_banner.id', $comboB->id));

        // Không còn combo active nào chứa sản phẩm → không có banner
        $comboB->update(['is_active' => false]);
        $this->get('/thiet-bi/'.$this->tent->slug)
            ->assertInertia(fn (Assert $page) => $page->where('combo_banner', null));

        // Sản phẩm không thuộc combo nào (ghế) → null ngay cả khi combo khác đang active
        $comboA->update(['is_active' => true]);
        $this->get('/thiet-bi/'.$this->mat->slug)
            ->assertInertia(fn (Assert $page) => $page->where('combo_banner', null));
    }

    /**
     * Banner chỉ hiện khi combo còn hàng trong khoảng khách chọn — endpoint trả
     * combo_available theo khoảng ngày để FE ẩn banner khi = 0 (PRD 5.6).
     *
     * @test
     */
    public function suggestion_endpoint_reports_combo_stock_for_range(): void
    {
        $combo = Combo::create(['name' => 'Combo Lều', 'slug' => 'combo-leu', 'combo_price' => 150000]);
        $combo->items()->create(['product_id' => $this->tent->id, 'quantity' => 1]);
        $combo->items()->create(['product_id' => $this->chair->id, 'quantity' => 2]);

        // Xem trang GHẾ (thuộc combo): lều kín lịch 10–12 → combo hết theo
        $this->bookedOrder($this->tent, '2030-07-10', '2030-07-12', qty: 3);

        $this->getJson('/thiet-bi/'.$this->chair->slug.'/goi-y-kha-dung?start=2030-07-10&end=2030-07-12')
            ->assertOk()
            ->assertJson(['combo_available' => 0]);

        // Khoảng trống → min(3 lều, intdiv(6 ghế, 2)) = 3
        $this->getJson('/thiet-bi/'.$this->chair->slug.'/goi-y-kha-dung?start=2030-07-13&end=2030-07-15')
            ->assertOk()
            ->assertJson(['combo_available' => 3]);
    }

    // ------------------------------------------------------------------ admin

    /** @test */
    public function admin_syncs_accessories_with_sort_order(): void
    {
        // Gán lại theo thứ tự mới: đệm trước, ghế sau — sort_order theo vị trí mảng
        $this->adminUpdate($this->tent, ['accessory_ids' => [$this->mat->id, $this->chair->id]])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $ids = $this->tent->fresh()->accessories()->pluck('products.id')->all();
        $this->assertSame([$this->mat->id, $this->chair->id], $ids);
    }

    /** @test */
    public function admin_clears_accessories_with_empty_value(): void
    {
        // FormData không gửi được mảng rỗng → FE gửi chuỗi rỗng, backend hiểu là xoá hết
        $this->adminUpdate($this->tent, ['accessory_ids' => ''])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $this->tent->fresh()->accessories()->count());
    }

    /** @test */
    public function admin_update_without_accessory_ids_keeps_existing(): void
    {
        $this->adminUpdate($this->tent, [])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(2, $this->tent->fresh()->accessories()->count());
    }

    /** @test */
    public function self_reference_is_dropped_and_bogus_id_rejected(): void
    {
        // Tự gợi ý chính mình → bị loại lặng lẽ, các món khác vẫn lưu
        $this->adminUpdate($this->tent, ['accessory_ids' => [$this->tent->id, $this->chair->id]])
            ->assertSessionHasNoErrors();

        $ids = $this->tent->fresh()->accessories()->pluck('products.id')->all();
        $this->assertSame([$this->chair->id], $ids);

        // id không tồn tại → validation error, pivot giữ nguyên
        $this->adminUpdate($this->tent, ['accessory_ids' => [999999]])
            ->assertSessionHasErrors('accessory_ids.0');
        $this->assertSame([$this->chair->id], $this->tent->fresh()->accessories()->pluck('products.id')->all());
    }

    /** @test */
    public function admin_store_accepts_accessories(): void
    {
        $loc = $this->location();

        $this->actingAs($this->admin())->post(route('admin.products.store'), [
            'name' => 'Bếp gas Test',
            'category_id' => $this->tent->category_id,
            'price_per_day' => 50000,
            'quantity' => 4,
            'service_location_ids' => [$loc->id],
            'accessory_ids' => [$this->chair->id],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $stove = Product::where('slug', 'bep-gas-test')->firstOrFail();
        $this->assertSame([$this->chair->id], $stove->accessories()->pluck('products.id')->all());
    }

    /** @test */
    public function admin_index_exposes_accessory_ids(): void
    {
        $this->actingAs($this->admin())->get(route('admin.products'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Products')
                ->has('accessory_options')
                ->where('products.data', fn ($data) => collect($data)
                    ->firstWhere('id', $this->tent->id)['accessory_ids'] === [$this->chair->id, $this->mat->id]));
    }

    // -------------------------------------------------------------------------

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function location(): ServiceLocation
    {
        return ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1]);
    }

    /** PUT admin.products.update với đủ field bắt buộc + $extra. */
    private function adminUpdate(Product $p, array $extra)
    {
        return $this->actingAs($this->admin())->put(route('admin.products.update', $p), array_merge([
            'name' => $p->name,
            'category_id' => $p->category_id,
            'price_per_day' => $p->price_per_day,
            'quantity' => $p->quantity,
            'status' => 'active',
            'service_location_ids' => [$this->location()->id],
        ], $extra));
    }

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

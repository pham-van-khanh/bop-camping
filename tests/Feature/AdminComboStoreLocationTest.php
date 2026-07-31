<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\ComboItem;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * bopcamping-iylu (T2) — admin gán kho cho combo.
 *
 * Luật chặn: mọi kho được gán phải phục vụ TẤT CẢ món của combo, tính theo TƯ CÁCH THÀNH
 * VIÊN pivot. KHÔNG chặn theo tồn > 0 — prod chỉ 3/11 sản phẩm còn tồn, có combo mọi món
 * tồn 0, chặn theo tồn thì admin không lưu nổi combo nào (PRD mục 6, R2).
 */
class AdminComboStoreLocationTest extends TestCase
{
    use RefreshDatabase;

    private ServiceLocation $vinh;

    private ServiceLocation $hanoi;

    private ServiceLocation $coming;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vinh = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghe An', 'status' => 'open', 'sort_order' => 1]);
        $this->hanoi = ServiceLocation::create(['name' => 'Ha Noi', 'area' => 'Ha Noi', 'status' => 'open', 'sort_order' => 2]);
        $this->coming = ServiceLocation::create(['name' => 'Da Nang', 'area' => 'Da Nang', 'status' => 'coming', 'sort_order' => 3]);
        $this->category = Category::create(['name' => 'Do camping', 'slug' => 'do-camping-acsl']);
    }

    public function test_luu_combo_kem_kho_thi_pivot_dung(): void
    {
        $a = $this->product('Leu', [$this->vinh->id => 3, $this->hanoi->id => 2]);
        $b = $this->product('Dem', [$this->vinh->id => 5, $this->hanoi->id => 1]);

        $this->actingAs($this->admin())
            ->post(route('admin.combos.store'), $this->payload($a, $b, [$this->vinh->id, $this->hanoi->id]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $combo = Combo::firstWhere('name', 'Combo QA');
        $ids = $combo->serviceLocations->pluck('id')->sort()->values()->all();

        $this->assertSame([$this->vinh->id, $this->hanoi->id], $ids);
    }

    /** Kho mà có món KHÔNG phục vụ → 422, message nêu tên món. */
    public function test_tu_choi_kho_co_mon_khong_phuc_vu(): void
    {
        $a = $this->product('Leu', [$this->vinh->id => 3, $this->hanoi->id => 2]);
        $b = $this->product('Dem hoi', [$this->vinh->id => 5]);   // KHÔNG có Hà Nội

        $response = $this->actingAs($this->admin())
            ->post(route('admin.combos.store'), $this->payload($a, $b, [$this->hanoi->id]));

        $response->assertSessionHasErrors('service_location_ids');
        $this->assertStringContainsString('Dem hoi', session('errors')->first('service_location_ids'));
        $this->assertStringContainsString('Ha Noi', session('errors')->first('service_location_ids'));
        $this->assertDatabaseMissing('combos', ['name' => 'Combo QA']);
    }

    /**
     * CA QUYẾT ĐỊNH — combo mà MỌI món tồn 0 vẫn phải lưu được.
     * Đúng trạng thái combo `relax` / `bbq-party` trên prod.
     */
    public function test_moi_mon_ton_0_van_luu_duoc(): void
    {
        $a = $this->product('Ban gap', [$this->vinh->id => 0, $this->hanoi->id => 0]);
        $b = $this->product('Ghe thu gian', [$this->vinh->id => 0, $this->hanoi->id => 0]);

        $this->actingAs($this->admin())
            ->post(route('admin.combos.store'), $this->payload($a, $b, [$this->vinh->id, $this->hanoi->id]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(2, Combo::firstWhere('name', 'Combo QA')->serviceLocations()->count());
    }

    public function test_thieu_kho_thi_422(): void
    {
        $a = $this->product('Leu', [$this->vinh->id => 3]);
        $b = $this->product('Dem', [$this->vinh->id => 3]);

        $payload = $this->payload($a, $b, [$this->vinh->id]);
        unset($payload['service_location_ids']);

        $this->actingAs($this->admin())
            ->post(route('admin.combos.store'), $payload)
            ->assertSessionHasErrors('service_location_ids');
    }

    public function test_mang_kho_rong_thi_422(): void
    {
        $a = $this->product('Leu', [$this->vinh->id => 3]);
        $b = $this->product('Dem', [$this->vinh->id => 3]);

        $this->actingAs($this->admin())
            ->post(route('admin.combos.store'), $this->payload($a, $b, []))
            ->assertSessionHasErrors('service_location_ids');
    }

    /** Kho chưa mở không phục vụ món nào (không attach) → bị luật chặn từ chối. */
    public function test_tu_choi_kho_chua_mo(): void
    {
        $a = $this->product('Leu', [$this->vinh->id => 3]);
        $b = $this->product('Dem', [$this->vinh->id => 3]);

        $this->actingAs($this->admin())
            ->post(route('admin.combos.store'), $this->payload($a, $b, [$this->coming->id]))
            ->assertSessionHasErrors('service_location_ids');
    }

    public function test_update_sync_dung_khong_sot_dong_cu(): void
    {
        $a = $this->product('Leu', [$this->vinh->id => 3, $this->hanoi->id => 2]);
        $b = $this->product('Dem', [$this->vinh->id => 5, $this->hanoi->id => 1]);

        $combo = Combo::create([
            'name' => 'Combo cu', 'slug' => 'combo-cu-acsl',
            'combo_price' => 100000, 'is_active' => true, 'sort_order' => 1,
        ]);
        ComboItem::create(['combo_id' => $combo->id, 'product_id' => $a->id, 'quantity' => 1]);
        ComboItem::create(['combo_id' => $combo->id, 'product_id' => $b->id, 'quantity' => 1]);
        $combo->serviceLocations()->sync([$this->vinh->id, $this->hanoi->id]);

        // Thu hẹp về chỉ Vinh.
        $this->actingAs($this->admin())
            ->put(route('admin.combos.update', $combo), $this->payload($a, $b, [$this->vinh->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame([$this->vinh->id], $combo->fresh()->serviceLocations->pluck('id')->all());
    }

    public function test_props_co_location_stock_va_service_locations(): void
    {
        $a = $this->product('Leu', [$this->vinh->id => 3, $this->hanoi->id => 2]);

        $this->actingAs($this->admin())->get(route('admin.combos'))
            ->assertOk()
            ->assertInertia(function ($page) use ($a) {
                $props = $page->toArray()['props'];

                // Kho 'coming' KHÔNG được cho gán.
                $ids = collect($props['service_locations'])->pluck('id')->all();
                $this->assertContains($this->vinh->id, $ids);
                $this->assertNotContains($this->coming->id, $ids);

                // location_stock: { locationId: { productId: qty } }
                $this->assertSame(3, $props['location_stock'][(string) $this->vinh->id][(string) $a->id]);
                $this->assertSame(2, $props['location_stock'][(string) $this->hanoi->id][(string) $a->id]);

                // products mang theo kho mà nó phục vụ để FE tính assignable ngay.
                $product = collect($props['products'])->firstWhere('id', $a->id);
                $this->assertEqualsCanonicalizing([$this->vinh->id, $this->hanoi->id], $product['service_location_ids']);
            });
    }

    public function test_combo_trong_props_mang_service_location_ids(): void
    {
        $a = $this->product('Leu', [$this->vinh->id => 3]);
        $combo = Combo::create([
            'name' => 'Combo props', 'slug' => 'combo-props-acsl',
            'combo_price' => 100000, 'is_active' => true, 'sort_order' => 1,
        ]);
        ComboItem::create(['combo_id' => $combo->id, 'product_id' => $a->id, 'quantity' => 1]);
        $combo->serviceLocations()->sync([$this->vinh->id]);

        $this->actingAs($this->admin())->get(route('admin.combos'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('combos.0.service_location_ids', [$this->vinh->id]));
    }

    // ---------- helpers ----------

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /** @param  array<int, int>  $locationIds */
    private function payload(Product $a, Product $b, array $locationIds): array
    {
        return [
            'name' => 'Combo QA',
            'description' => 'QA',
            // Rẻ hơn tổng giá lẻ để không vướng luật confirm_over_price.
            'combo_price' => 50000,
            'deposit' => 100000,
            'suitable_for' => 2,
            'is_active' => true,
            'sort_order' => 1,
            'items' => [
                ['product_id' => $a->id, 'quantity' => 1],
                ['product_id' => $b->id, 'quantity' => 1],
            ],
            'service_location_ids' => $locationIds,
        ];
    }

    /** @param  array<int, int>  $stocks  [locationId => quantity] */
    private function product(string $name, array $stocks): Product
    {
        $p = Product::create([
            'category_id' => $this->category->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.uniqid(),
            'price_per_day' => 100000,
            'quantity' => max(1, array_sum($stocks)),
            'status' => 'active',
        ]);

        foreach ($stocks as $locationId => $qty) {
            $p->serviceLocations()->attach($locationId, ['quantity' => $qty, 'buffer_days' => 0]);
        }

        return $p;
    }
}

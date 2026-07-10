<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * bopcamping-s9d (Combo P1) — Admin CRUD combo (US-06) + tự ẩn combo khi
 * ẩn/xoá sản phẩm con (US-07). Giả định thiết kế: artifacts/adr_combo_data_model.md.
 */
class AdminComboTest extends TestCase
{
    use RefreshDatabase;

    private Product $tent;   // 100k/ngày

    private Product $mattress; // 40k/ngày

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create(['name' => 'Lều', 'slug' => 'leu']);
        $this->tent = Product::create([
            'category_id' => $category->id,
            'name' => 'Lều 2 người',
            'slug' => 'leu-2-nguoi',
            'price_per_day' => 100000,
            'quantity' => 3,
        ]);
        $this->mattress = Product::create([
            'category_id' => $category->id,
            'name' => 'Đệm hơi',
            'slug' => 'dem-hoi',
            'price_per_day' => 40000,
            'quantity' => 4,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function makeCombo(int $comboPrice = 150000, bool $active = true): Combo
    {
        $combo = Combo::create([
            'name' => 'Combo Cặp Đôi',
            'slug' => 'combo-cap-doi',
            'combo_price' => $comboPrice,
            'deposit' => 300000,
            'is_active' => $active,
        ]);
        $combo->items()->create(['product_id' => $this->tent->id, 'quantity' => 1]);
        $combo->items()->create(['product_id' => $this->mattress->id, 'quantity' => 2]);

        return $combo;
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Combo Gia Đình',
            'description' => 'Trọn bộ cho 4 người',
            'combo_price' => 150000,
            'deposit' => 500000,
            'suitable_for' => 4,
            'is_active' => true,
            'sort_order' => 1,
            'items' => [
                ['product_id' => $this->tent->id, 'quantity' => 1],
                ['product_id' => $this->mattress->id, 'quantity' => 2],
            ],
        ], $overrides);
    }

    /** @test */
    public function admin_can_create_combo_with_items(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.combos.store'), $this->validPayload())
            ->assertRedirect()
            ->assertSessionHas('success');

        $combo = Combo::firstWhere('name', 'Combo Gia Đình');
        $this->assertNotNull($combo);
        $this->assertSame('combo-gia-dinh', $combo->slug);
        $this->assertSame(2, $combo->items()->count());
        $this->assertDatabaseHas('combo_items', [
            'combo_id' => $combo->id,
            'product_id' => $this->mattress->id,
            'quantity' => 2,
        ]);
        // sum lẻ = 100k×1 + 40k×2 = 180k → tiết kiệm 30k
        $this->assertSame(180000, $combo->sumIndividualPrice());
        $this->assertSame(30000, $combo->savingsAmount());
    }

    /** @test */
    public function combo_requires_at_least_one_item(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.combos.store'), $this->validPayload(['items' => []]))
            ->assertSessionHasErrors('items');

        $this->assertSame(0, Combo::count());
    }

    /** @test */
    public function combo_rejects_duplicate_products_in_items(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.combos.store'), $this->validPayload([
                'items' => [
                    ['product_id' => $this->tent->id, 'quantity' => 1],
                    ['product_id' => $this->tent->id, 'quantity' => 2],
                ],
            ]))
            ->assertSessionHasErrors('items.1.product_id');

        $this->assertSame(0, Combo::count());
    }

    /**
     * PRD 5.2 — combo_price >= tổng giá lẻ: warning, chỉ lưu khi override có chủ đích.
     *
     * @test
     */
    public function over_priced_combo_requires_explicit_confirmation(): void
    {
        // sum lẻ = 180k; giá combo 200k → phải xác nhận
        $this->actingAs($this->admin())
            ->post(route('admin.combos.store'), $this->validPayload(['combo_price' => 200000]))
            ->assertSessionHasErrors('combo_price');
        $this->assertSame(0, Combo::count());

        // Gửi kèm confirm_over_price → lưu được (override có chủ đích)
        $this->actingAs($this->admin())
            ->post(route('admin.combos.store'), $this->validPayload([
                'combo_price' => 200000,
                'confirm_over_price' => true,
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame(1, Combo::count());
    }

    /** @test */
    public function admin_can_update_combo_and_sync_items(): void
    {
        $combo = $this->makeCombo();

        $this->actingAs($this->admin())
            ->put(route('admin.combos.update', $combo), $this->validPayload([
                'name' => 'Combo Cặp Đôi Plus',
                'items' => [
                    ['product_id' => $this->tent->id, 'quantity' => 2],
                ],
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $combo->refresh();
        $this->assertSame('Combo Cặp Đôi Plus', $combo->name);
        $this->assertSame(1, $combo->items()->count());
        $this->assertSame(2, (int) $combo->items()->first()->quantity);
    }

    /** @test */
    public function update_without_is_active_preserves_hidden_state(): void
    {
        // Combo đang ẩn; request update KHÔNG gửi is_active → phải giữ ẩn.
        $combo = $this->makeCombo(active: false);

        $payload = $this->validPayload(['name' => 'Combo Cặp Đôi (vẫn ẩn)']);
        unset($payload['is_active']);

        $this->actingAs($this->admin())
            ->put(route('admin.combos.update', $combo), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $combo->refresh();
        $this->assertSame('Combo Cặp Đôi (vẫn ẩn)', $combo->name);
        $this->assertFalse($combo->is_active, 'Combo đang ẩn không được tự bật lại khi update thiếu is_active.');
    }

    /** @test */
    public function admin_can_delete_combo_and_items_cascade(): void
    {
        $combo = $this->makeCombo();

        $this->actingAs($this->admin())
            ->delete(route('admin.combos.destroy', $combo))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('combos', ['id' => $combo->id]);
        $this->assertDatabaseMissing('combo_items', ['combo_id' => $combo->id]);
    }

    /** @test */
    public function non_admin_cannot_manage_combos(): void
    {
        $guest = User::factory()->create(['is_admin' => false]);

        $this->actingAs($guest)
            ->post(route('admin.combos.store'), $this->validPayload())
            ->assertRedirect(route('admin.login'));

        $this->assertSame(0, Combo::count());
    }

    /* ---------------------------------------------------------------------
     * US-07 — ẩn/xoá sản phẩm thuộc combo → combo tự ẩn
     * ------------------------------------------------------------------- */

    /** @test */
    public function hiding_product_auto_hides_active_combos_containing_it(): void
    {
        $combo = $this->makeCombo();
        $loc = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1]);

        $this->actingAs($this->admin())->put(route('admin.products.update', $this->mattress), [
            'name' => $this->mattress->name,
            'category_id' => $this->mattress->category_id,
            'price_per_day' => $this->mattress->price_per_day,
            'quantity' => $this->mattress->quantity,
            'status' => 'hidden',
            'service_location_ids' => [$loc->id],
        ])->assertRedirect();

        $this->assertFalse($combo->fresh()->is_active);
    }

    /** @test */
    public function deleting_product_auto_hides_combo_and_cascades_item(): void
    {
        $combo = $this->makeCombo();

        $this->actingAs($this->admin())
            ->delete(route('admin.products.destroy', $this->mattress))
            ->assertRedirect();

        $this->assertFalse($combo->fresh()->is_active);
        $this->assertDatabaseMissing('combo_items', ['product_id' => $this->mattress->id]);
        // Món còn lại của combo không bị đụng
        $this->assertDatabaseHas('combo_items', ['combo_id' => $combo->id, 'product_id' => $this->tent->id]);
    }

    /** @test */
    public function updating_product_without_hiding_keeps_combo_active(): void
    {
        $combo = $this->makeCombo();
        $loc = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1]);

        $this->actingAs($this->admin())->put(route('admin.products.update', $this->mattress), [
            'name' => 'Đệm hơi đôi',
            'category_id' => $this->mattress->category_id,
            'price_per_day' => 45000,
            'quantity' => 4,
            'status' => 'active',
            'service_location_ids' => [$loc->id],
        ])->assertRedirect();

        $this->assertTrue($combo->fresh()->is_active);
    }

    /* ---------------------------------------------------------------------
     * Ảnh combo (mirror pattern product images, ADR-3)
     * ------------------------------------------------------------------- */

    /** @test */
    public function admin_can_upload_and_delete_combo_images(): void
    {
        Storage::fake('media');
        $combo = $this->makeCombo();

        $this->actingAs($this->admin())->post(route('admin.combos.images.store', $combo), [
            'images' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->create('clip.mp4', 800, 'video/mp4'),
            ],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(2, $combo->images()->count());
        $this->assertSame(['image', 'video'], $combo->images()->orderBy('id')->pluck('type')->all());

        $image = $combo->images()->first();
        $this->actingAs($this->admin())
            ->delete(route('admin.combos.images.destroy', [$combo, $image]))
            ->assertRedirect();

        $this->assertDatabaseMissing('combo_images', ['id' => $image->id]);
        Storage::disk('media')->assertMissing($image->path);
    }

    /**
     * IDOR (CWE-639): không xoá được ảnh qua URL combo khác.
     *
     * @test
     */
    public function cannot_delete_image_through_wrong_combo(): void
    {
        Storage::fake('media');
        $comboA = $this->makeCombo();
        $comboB = Combo::create(['name' => 'Combo B', 'slug' => 'combo-b', 'combo_price' => 90000]);
        $image = $comboA->images()->create(['path' => 'admin/combos/a.jpg', 'sort_order' => 1]);

        $this->actingAs($this->admin())
            ->delete(route('admin.combos.images.destroy', [$comboB->id, $image->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('combo_images', ['id' => $image->id]);
    }
}

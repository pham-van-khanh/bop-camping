<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-n6mr — khung giờ nhận/trả: mặc định toàn shop (8/20) + override theo
 * sản phẩm (null = theo shop). Admin lưu được; resource khách phơi ra để FE fallback.
 */
class ProductHoursTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function storePayload(array $extra = []): array
    {
        $cat = Category::firstOrCreate(['slug' => 'leu'], ['name' => 'Lều']);
        $loc = ServiceLocation::firstOrCreate(['name' => 'Vinh'], ['area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1]);

        return array_merge([
            'name' => 'Lều giờ riêng', 'category_id' => $cat->id, 'price_per_day' => 100000, 'status' => 'active',
            'service_location_ids' => [$loc->id], 'stocks' => [$loc->id => 3],
        ], $extra);
    }

    /** @test */
    public function shop_default_hours_are_8_and_20(): void
    {
        $this->get('/')->assertInertia(fn (Assert $p) => $p
            ->where('site.pickup_hour', 8)
            ->where('site.return_hour', 20));
    }

    /** @test */
    public function admin_updates_shop_default_hours(): void
    {
        $this->actingAs($this->admin())->put(route('admin.settings.update'), [
            'pickup_hour' => 6, 'return_hour' => 22,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $s = SiteSetting::current();
        $this->assertSame(6, (int) $s->pickup_hour);
        $this->assertSame(22, (int) $s->return_hour);
    }

    /** @test */
    public function admin_saves_per_product_hours(): void
    {
        $this->actingAs($this->admin())->post(route('admin.products.store'), $this->storePayload([
            'pickup_hour' => 7, 'return_hour' => 19,
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $p = Product::where('name', 'Lều giờ riêng')->firstOrFail();
        $this->assertSame(7, $p->pickup_hour);
        $this->assertSame(19, $p->return_hour);
    }

    /** @test */
    public function empty_product_hours_stored_as_null(): void
    {
        $this->actingAs($this->admin())->post(route('admin.products.store'), $this->storePayload([
            'pickup_hour' => '', 'return_hour' => '',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $p = Product::where('name', 'Lều giờ riêng')->firstOrFail();
        $this->assertNull($p->pickup_hour);
        $this->assertNull($p->return_hour);
    }

    /** @test */
    public function product_hours_reject_out_of_range(): void
    {
        $this->actingAs($this->admin())->post(route('admin.products.store'), $this->storePayload([
            'pickup_hour' => 26,
        ]))->assertSessionHasErrors('pickup_hour');
    }

    /** @test */
    public function product_detail_exposes_per_product_hours(): void
    {
        $cat = Category::create(['name' => 'Bàn', 'slug' => 'ban']);
        $p = Product::create([
            'category_id' => $cat->id, 'name' => 'Bàn xếp', 'slug' => 'ban-xep',
            'price_per_day' => 30000, 'quantity' => 5, 'pickup_hour' => 9, 'return_hour' => 21,
        ]);

        $this->get("/thiet-bi/{$p->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.pickup_hour', 9)
                ->where('product.return_hour', 21));
    }
}

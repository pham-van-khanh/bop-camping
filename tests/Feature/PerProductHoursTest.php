<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ServiceLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-fica — khung giờ nhận/trả OVERRIDE theo sản phẩm (null = theo shop).
 * Admin lưu được, resource khách phơi ra để FE fallback + tính khung giao nhau.
 */
class PerProductHoursTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function payload(array $extra = []): array
    {
        $cat = Category::firstOrCreate(['slug' => 'leu'], ['name' => 'Lều']);
        $loc = ServiceLocation::firstOrCreate(['name' => 'Vinh'], ['area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1]);

        return array_merge([
            'name' => 'Lều giờ riêng', 'category_id' => $cat->id, 'price_per_day' => 100000, 'status' => 'active',
            'service_location_ids' => [$loc->id], 'stocks' => [$loc->id => 3],
        ], $extra);
    }

    /** @test */
    public function admin_saves_per_product_hours(): void
    {
        $this->actingAs($this->admin())->post(route('admin.products.store'), $this->payload([
            'pickup_hour' => 7, 'return_hour' => 19,
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $p = Product::where('name', 'Lều giờ riêng')->firstOrFail();
        $this->assertSame(7, $p->pickup_hour);
        $this->assertSame(19, $p->return_hour);
    }

    /** @test */
    public function empty_hours_stored_as_null_means_follow_shop(): void
    {
        $this->actingAs($this->admin())->post(route('admin.products.store'), $this->payload([
            'pickup_hour' => '', 'return_hour' => '',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $p = Product::where('name', 'Lều giờ riêng')->firstOrFail();
        $this->assertNull($p->pickup_hour);
        $this->assertNull($p->return_hour);
    }

    /** @test */
    public function hours_reject_out_of_range(): void
    {
        $this->actingAs($this->admin())->post(route('admin.products.store'), $this->payload([
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

        // Trang chi tiết render với product resource — kiểm giờ được phơi ra.
        $this->get("/thiet-bi/{$p->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.pickup_hour', 9)
                ->where('product.return_hour', 21));
    }
}

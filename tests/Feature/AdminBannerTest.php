<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\User;
use Database\Seeders\BannerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-8x1 — CRUD banner (hero/promo) + hiển thị công khai.
 */
class AdminBannerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::firstOrCreate(
            ['phone' => '0976544370'],
            ['name' => 'Admin', 'email' => 'admin@bop.test', 'is_admin' => true],
        );
    }

    /** @test */
    public function guest_cannot_access_banners(): void
    {
        $this->get(route('admin.banners'))->assertRedirect(route('admin.login'));
    }

    /** @test */
    public function admin_can_create_banner_with_image(): void
    {
        Storage::fake('media');

        $this->actingAs($this->admin())->post(route('admin.banners.store'), [
            'placement' => 'promo',
            'image' => UploadedFile::fake()->image('promo.jpg', 1200, 400),
            'title' => 'Ưu đãi cuối tuần',
            'link_url' => '/thiet-bi',
            'is_active' => true,
        ])->assertRedirect()->assertSessionHas('success');

        $b = Banner::first();
        $this->assertSame('promo', $b->placement);
        $this->assertSame('/thiet-bi', $b->link_url);
        $this->assertTrue($b->is_active);
        Storage::disk('media')->assertExists($b->image);
    }

    /** @test */
    public function create_requires_placement_and_image(): void
    {
        $this->actingAs($this->admin())->post(route('admin.banners.store'), ['placement' => 'xxx'])
            ->assertSessionHasErrors(['placement', 'image']);

        $this->assertSame(0, Banner::count());
    }

    /** @test */
    public function admin_can_update_without_replacing_image_then_delete(): void
    {
        $b = Banner::create(['placement' => 'hero', 'image' => '/images/album/bay-overview.jpg', 'title' => 'Cũ', 'is_active' => true]);

        $this->actingAs($this->admin())->put(route('admin.banners.update', $b), [
            'placement' => 'hero', 'title' => 'Mới', 'is_active' => false,
        ])->assertRedirect();

        $this->assertSame('Mới', $b->fresh()->title);
        $this->assertFalse($b->fresh()->is_active);
        $this->assertSame('/images/album/bay-overview.jpg', $b->fresh()->image); // giữ ảnh cũ

        $this->actingAs($this->admin())->delete(route('admin.banners.destroy', $b))->assertRedirect();
        $this->assertSame(0, Banner::count());
    }

    /** @test */
    public function home_passes_active_hero_and_promo_banners(): void
    {
        $this->seed(BannerSeeder::class); // 7 hero banner
        Banner::create(['placement' => 'promo', 'image' => 'banners/p.jpg', 'title' => 'Sale', 'link_url' => '/thiet-bi', 'is_active' => true]);
        Banner::create(['placement' => 'promo', 'image' => 'banners/hidden.jpg', 'title' => 'Ẩn', 'is_active' => false]); // không hiện

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->has('hero_banners', 7)
            ->where('hero_banners.0.src', '/images/album/beach-night-tent.jpg')
            ->has('promo_banners', 1)
            ->where('promo_banners.0.title', 'Sale')
            ->where('promo_banners.0.href', '/thiet-bi'));
    }
}

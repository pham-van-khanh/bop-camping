<?php

namespace Tests\Feature;

use App\Models\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-b4c3 — preload ảnh LCP trang chủ phải trỏ đúng ảnh slide đầu đang hiện.
 *
 * Trước đây href ghi cứng `beach-night-tent.webp` trong khi HeroSlideshow lại render
 * banner hero từ admin (seed là `.jpg`) → trình duyệt tải ảnh preload rồi bỏ đó, ảnh
 * LCP thật lại phải tải sau: hại LCP thay vì giúp.
 */
class HeroPreloadTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function preload_points_at_first_active_hero_banner(): void
    {
        $first = Banner::query()->active()->placement('hero')->ordered()->first()
            ?? Banner::create(['placement' => 'hero', 'image' => '/images/album/bay-overview.jpg', 'is_active' => true]);

        $this->get('/')
            ->assertOk()
            ->assertSee('<link rel="preload" as="image" href="'.$first->imageUrl().'" fetchpriority="high">', false);
    }

    /** @test */
    public function preload_falls_back_to_default_slide_without_hero_banners(): void
    {
        Banner::query()->where('placement', 'hero')->delete();

        $this->get('/')
            ->assertSee('<link rel="preload" as="image" href="/images/album/beach-night-tent.webp" fetchpriority="high">', false);
    }

    /** @test */
    public function other_pages_do_not_preload_the_hero(): void
    {
        $this->get('/thiet-bi')->assertDontSee('rel="preload" as="image"', false);
    }
}

<?php

namespace Tests\Feature;

use App\Models\CampingSpot;
use App\Models\ServiceLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-0q6 — trang chủ truyền dữ liệu vị trí phục vụ + điểm gợi ý + điểm gom theo tỉnh.
 */
class HomeCampingGuideTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function home_passes_service_locations_suggested_and_grouped_spots(): void
    {
        $vinh = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1]);
        ServiceLocation::create(['name' => 'Đà Nẵng', 'area' => 'Hải Châu', 'status' => 'coming', 'sort_order' => 2]);

        CampingSpot::create(['name' => 'Biển Cửa Lò', 'province' => 'Nghệ An', 'terrain_tag' => 'Bãi biển', 'is_suggested' => true, 'nearest_service_location_id' => $vinh->id, 'sort_order' => 1]);
        CampingSpot::create(['name' => 'Núi Quyết', 'province' => 'Nghệ An', 'terrain_tag' => 'Đồi view sông', 'sort_order' => 2]);
        CampingSpot::create(['name' => 'Đồng Cao', 'province' => 'Bắc Giang', 'terrain_tag' => 'Đồi cỏ', 'sort_order' => 3]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->has('service_locations', 2)
            ->where('service_locations.0.name', 'Vinh')
            ->where('service_locations.1.status', 'coming')
            ->has('suggested_spots', 1)
            ->where('suggested_spots.0.name', 'Biển Cửa Lò')
            ->where('suggested_spots.0.nearest_name', 'Vinh')
            // gom theo tỉnh: Nghệ An (2 điểm) + Bắc Giang (1 điểm)
            ->has('camping_provinces', 2)
            ->where('camping_provinces.0.province', 'Nghệ An')
            ->has('camping_provinces.0.spots', 2)
            ->where('camping_provinces.1.province', 'Bắc Giang'));
    }

    /** @test */
    public function grouped_spots_carry_media_and_map_for_detail(): void
    {
        $spot = CampingSpot::create([
            'name' => 'Hồ Tà Đùng', 'province' => 'Đắk Nông', 'terrain_tag' => 'Hồ trên núi',
            'description' => 'Vịnh Hạ Long của Tây Nguyên', 'map_url' => 'https://maps.google.com/?q=ta-dung',
            'best_season_from' => 11, 'best_season_to' => 4, 'sort_order' => 1,
        ]);
        $spot->media()->create(['type' => 'image', 'path' => 'camping-spots/a.jpg', 'sort_order' => 0]);
        $spot->media()->create(['type' => 'video', 'path' => 'camping-spots/b.mp4', 'sort_order' => 1]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('camping_provinces.0.spots.0.map_url', 'https://maps.google.com/?q=ta-dung')
            ->where('camping_provinces.0.spots.0.season_label', 'T11 - T4')
            ->has('camping_provinces.0.spots.0.media', 2)
            ->where('camping_provinces.0.spots.0.media.0.type', 'image')
            ->where('camping_provinces.0.spots.0.media.1.type', 'video'));
    }

    /** @test */
    public function home_works_with_no_camping_data(): void
    {
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->has('service_locations', 0)
            ->has('suggested_spots', 0)
            ->has('camping_provinces', 0));
    }
}

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
    public function home_works_with_no_camping_data(): void
    {
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->has('service_locations', 0)
            ->has('suggested_spots', 0)
            ->has('camping_provinces', 0));
    }
}

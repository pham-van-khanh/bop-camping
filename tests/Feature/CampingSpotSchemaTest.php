<?php

namespace Tests\Feature;

use App\Models\CampingSpot;
use App\Models\CampingSpotMedia;
use App\Models\ServiceLocation;
use Database\Seeders\CampingSpotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-44r / rqv — schema + model điểm cắm trại (gom theo tỉnh/thành, có link map).
 */
class CampingSpotSchemaTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function spot_has_media_and_nearest_service_location(): void
    {
        $loc = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open']);
        $spot = CampingSpot::create([
            'name' => 'Biển Cửa Lò', 'province' => 'Nghệ An', 'district' => 'Cửa Lò',
            'terrain_tag' => 'Bãi biển', 'description' => 'Sát biển', 'map_url' => 'https://maps.google.com/?q=cua-lo',
            'best_season_from' => 4, 'best_season_to' => 8,
            'nearest_service_location_id' => $loc->id, 'is_suggested' => true,
        ]);
        $spot->media()->create(['type' => 'image', 'path' => 'camping-spots/a.jpg', 'sort_order' => 0]);
        $spot->media()->create(['type' => 'video', 'path' => 'camping-spots/b.mp4', 'sort_order' => 1]);

        $this->assertSame(2, $spot->media()->count());
        $this->assertInstanceOf(CampingSpotMedia::class, $spot->media()->first());
        $this->assertSame('Vinh', $spot->nearestServiceLocation->name);
        $this->assertTrue($spot->is_suggested);
        $this->assertSame('https://maps.google.com/?q=cua-lo', $spot->map_url);
        $this->assertSame('Biển Cửa Lò', $loc->campingSpots()->first()->name);
    }

    /** @test */
    public function deleting_spot_cascades_media_but_keeps_service_location(): void
    {
        $loc = ServiceLocation::create(['name' => 'Hà Nội', 'area' => 'Nội thành', 'status' => 'open']);
        $spot = CampingSpot::create([
            'name' => 'Núi Hàm Lợn', 'province' => 'Hà Nội',
            'terrain_tag' => 'Rừng núi', 'nearest_service_location_id' => $loc->id,
        ]);
        $spot->media()->create(['type' => 'image', 'path' => 'camping-spots/x.jpg']);

        $spot->delete();

        $this->assertSame(0, CampingSpotMedia::count());          // cascade
        $this->assertNotNull($loc->fresh());                       // vị trí phục vụ vẫn còn
    }

    /** @test */
    public function season_label_renders(): void
    {
        $spot = new CampingSpot(['best_season_from' => 10, 'best_season_to' => 4]);
        $this->assertSame('T10 - T4', $spot->seasonLabel());

        $allYear = new CampingSpot(['best_season_from' => null, 'best_season_to' => null]);
        $this->assertSame('Cả năm', $allYear->seasonLabel());
    }

    /** @test */
    public function provinces_helper_returns_distinct_sorted(): void
    {
        CampingSpot::create(['name' => 'A', 'province' => 'Hà Nội', 'terrain_tag' => 'Ven hồ']);
        CampingSpot::create(['name' => 'B', 'province' => 'Hà Nội', 'terrain_tag' => 'Rừng núi']);
        CampingSpot::create(['name' => 'C', 'province' => 'Bắc Giang', 'terrain_tag' => 'Đồi cỏ']);

        $this->assertSame(['Bắc Giang', 'Hà Nội'], CampingSpot::provinces());
    }

    /** @test */
    public function seeder_creates_locations_and_suggested_spots(): void
    {
        $this->seed(CampingSpotSeeder::class);

        $this->assertSame(2, ServiceLocation::count());
        $this->assertSame(9, CampingSpot::count());
        // Gom theo tỉnh: Hà Nội có 2 điểm, Nghệ An có 2 điểm.
        $this->assertSame(2, CampingSpot::where('province', 'Hà Nội')->count());
        $this->assertSame(2, CampingSpot::where('province', 'Nghệ An')->count());

        // 2 điểm gợi ý có vị trí phục vụ gần + link bản đồ.
        $suggested = CampingSpot::suggested()->with('nearestServiceLocation')->get();
        $this->assertSame(2, $suggested->count());
        $this->assertTrue($suggested->every(fn ($s) => $s->nearestServiceLocation && $s->map_url));
    }
}

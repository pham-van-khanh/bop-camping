<?php

namespace Tests\Feature;

use App\Models\CampingSpot;
use App\Models\CampingSpotMedia;
use App\Models\ServiceLocation;
use Database\Seeders\CampingSpotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-44r — schema + model điểm cắm trại & vị trí phục vụ.
 */
class CampingSpotSchemaTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function spot_has_media_and_nearest_service_location(): void
    {
        $loc = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open']);
        $spot = CampingSpot::create([
            'name' => 'Biển Cửa Lò', 'region' => 'mien_trung', 'province' => 'Vinh',
            'terrain_tag' => 'Bãi biển', 'description' => 'Sát biển',
            'best_season_from' => 4, 'best_season_to' => 8,
            'nearest_service_location_id' => $loc->id, 'travel_time' => '40 phút',
            'is_suggested' => true,
        ]);
        $spot->media()->create(['type' => 'image', 'path' => 'spots/a.jpg', 'sort_order' => 0]);
        $spot->media()->create(['type' => 'video', 'path' => 'spots/b.mp4', 'sort_order' => 1]);

        $this->assertSame(2, $spot->media()->count());
        $this->assertInstanceOf(CampingSpotMedia::class, $spot->media()->first());
        $this->assertSame('Vinh', $spot->nearestServiceLocation->name);
        $this->assertTrue($spot->is_suggested);
        $this->assertSame('Biển Cửa Lò', $loc->campingSpots()->first()->name);
    }

    /** @test */
    public function deleting_spot_cascades_media_but_keeps_service_location(): void
    {
        $loc = ServiceLocation::create(['name' => 'Hà Nội', 'area' => 'Nội thành', 'status' => 'open']);
        $spot = CampingSpot::create([
            'name' => 'Núi Hàm Lợn', 'region' => 'mien_bac', 'province' => 'Hà Nội',
            'terrain_tag' => 'Rừng núi', 'nearest_service_location_id' => $loc->id,
        ]);
        $spot->media()->create(['type' => 'image', 'path' => 'spots/x.jpg']);

        $spot->delete();

        $this->assertSame(0, CampingSpotMedia::count());          // cascade
        $this->assertNotNull($loc->fresh());                       // vị trí phục vụ vẫn còn
    }

    /** @test */
    public function season_label_and_region_label_render(): void
    {
        $spot = new CampingSpot([
            'region' => 'mien_nam_tay_nguyen', 'best_season_from' => 10, 'best_season_to' => 4,
        ]);
        $this->assertSame('T10 - T4', $spot->seasonLabel());
        $this->assertSame('Miền Nam & Tây Nguyên', $spot->regionLabel());

        $allYear = new CampingSpot(['best_season_from' => null, 'best_season_to' => null]);
        $this->assertSame('Cả năm', $allYear->seasonLabel());
    }

    /** @test */
    public function seeder_creates_locations_and_suggested_spots(): void
    {
        $this->seed(CampingSpotSeeder::class);

        $this->assertSame(2, ServiceLocation::count());
        $this->assertSame(9, CampingSpot::count());
        $this->assertSame(3, CampingSpot::where('region', 'mien_bac')->count());
        $this->assertSame(3, CampingSpot::where('region', 'mien_trung')->count());
        $this->assertSame(3, CampingSpot::where('region', 'mien_nam_tay_nguyen')->count());

        // 2 điểm gợi ý có vị trí phục vụ gần + thời gian di chuyển.
        $suggested = CampingSpot::suggested()->with('nearestServiceLocation')->get();
        $this->assertSame(2, $suggested->count());
        $this->assertTrue($suggested->every(fn ($s) => $s->nearestServiceLocation && $s->travel_time));
    }
}

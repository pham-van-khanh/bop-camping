<?php

namespace Tests\Feature;

use App\Models\CampingSpot;
use App\Models\ServiceLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * bopcamping-as1 — admin CRUD điểm cắm trại + upload ảnh/video.
 */
class AdminCampingSpotTest extends TestCase
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
    public function guest_cannot_access_admin_camping_spots(): void
    {
        $this->get(route('admin.camping-spots'))->assertRedirect(route('admin.login'));
    }

    /** @test */
    public function admin_can_create_spot_with_new_province_and_map_link(): void
    {
        $loc = ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open']);

        $this->actingAs($this->admin())->post(route('admin.camping-spots.store'), [
            'name' => 'Biển Cửa Lò', 'province' => 'Nghệ An', 'district' => 'Cửa Lò',
            'terrain_tag' => 'Bãi biển', 'description' => 'Sát biển', 'map_url' => 'https://maps.google.com/?q=cua-lo',
            'best_season_from' => 4, 'best_season_to' => 8,
            'nearest_service_location_id' => $loc->id, 'is_suggested' => true,
        ])->assertRedirect()->assertSessionHas('success');

        $spot = CampingSpot::first();
        $this->assertSame('Biển Cửa Lò', $spot->name);
        $this->assertSame('Nghệ An', $spot->province);          // tỉnh mới được tạo qua việc nhập
        $this->assertSame('https://maps.google.com/?q=cua-lo', $spot->map_url);
        $this->assertTrue($spot->is_suggested);
        $this->assertSame($loc->id, $spot->nearest_service_location_id);
    }

    /** @test */
    public function create_validates_required_fields_and_map_url(): void
    {
        $this->actingAs($this->admin())->post(route('admin.camping-spots.store'), ['map_url' => 'không-phải-url'])
            ->assertSessionHasErrors(['name', 'province', 'terrain_tag', 'map_url']);

        $this->assertSame(0, CampingSpot::count());
    }

    /** @test */
    public function admin_can_update_and_delete_spot(): void
    {
        $spot = CampingSpot::create(['name' => 'Cũ', 'province' => 'Hà Nội', 'terrain_tag' => 'Đồi cỏ']);

        $this->actingAs($this->admin())->put(route('admin.camping-spots.update', $spot), [
            'name' => 'Mới', 'province' => 'Bắc Giang', 'terrain_tag' => 'Đồi cỏ',
        ])->assertRedirect();
        $this->assertSame('Mới', $spot->fresh()->name);
        $this->assertSame('Bắc Giang', $spot->fresh()->province);

        $this->actingAs($this->admin())->delete(route('admin.camping-spots.destroy', $spot))->assertRedirect();
        $this->assertSame(0, CampingSpot::count());
    }

    /** @test */
    public function admin_can_upload_and_delete_media_image_and_video(): void
    {
        Storage::fake('public');
        $spot = CampingSpot::create(['name' => 'X', 'province' => 'Hà Nội', 'terrain_tag' => 'Rừng núi']);

        $this->actingAs($this->admin())->post(route('admin.camping-spots.media.store', $spot), [
            'media' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->create('clip.mp4', 600, 'video/mp4'),
            ],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(2, $spot->media()->count());
        $this->assertSame(['image', 'video'], $spot->media()->pluck('type')->all());

        $media = $spot->media()->first();
        $this->actingAs($this->admin())->delete(route('admin.camping-spots.media.destroy', [$spot, $media]))->assertRedirect();
        $this->assertSame(1, $spot->media()->count());
    }

    /** @test */
    public function cannot_delete_media_of_another_spot(): void
    {
        Storage::fake('public');
        $a = CampingSpot::create(['name' => 'A', 'province' => 'HN', 'terrain_tag' => 'Đồi cỏ']);
        $b = CampingSpot::create(['name' => 'B', 'province' => 'HN', 'terrain_tag' => 'Đồi cỏ']);
        $mediaA = $a->media()->create(['type' => 'image', 'path' => 'camping-spots/a.jpg']);

        // media của A nhưng URL điểm B → 404 (chống IDOR)
        $this->actingAs($this->admin())->delete(route('admin.camping-spots.media.destroy', [$b, $mediaA]))->assertNotFound();
        $this->assertSame(1, $a->media()->count());
    }
}

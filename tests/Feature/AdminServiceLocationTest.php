<?php

namespace Tests\Feature;

use App\Models\CampingSpot;
use App\Models\ServiceLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-yne — admin CRUD vị trí phục vụ.
 */
class AdminServiceLocationTest extends TestCase
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
    public function guest_cannot_create_location(): void
    {
        $this->post(route('admin.service-locations.store'), ['name' => 'X', 'status' => 'open'])
            ->assertRedirect(route('admin.login'));

        $this->assertSame(0, ServiceLocation::count());
    }

    /** @test */
    public function admin_can_create_location(): void
    {
        $this->actingAs($this->admin())->post(route('admin.service-locations.store'), [
            'name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1,
        ])->assertRedirect()->assertSessionHas('success');

        $loc = ServiceLocation::first();
        $this->assertSame('Vinh', $loc->name);
        $this->assertSame('open', $loc->status);
    }

    /** @test */
    public function create_validates_name_and_status(): void
    {
        $this->actingAs($this->admin())->post(route('admin.service-locations.store'), ['status' => 'xxx'])
            ->assertSessionHasErrors(['name', 'status']);

        $this->assertSame(0, ServiceLocation::count());
    }

    /** @test */
    public function admin_can_update_location(): void
    {
        $loc = ServiceLocation::create(['name' => 'Cũ', 'area' => 'X', 'status' => 'coming']);

        $this->actingAs($this->admin())->put(route('admin.service-locations.update', $loc), [
            'name' => 'Hà Nội', 'area' => 'Nội thành', 'status' => 'open',
        ])->assertRedirect();

        $this->assertSame('Hà Nội', $loc->fresh()->name);
        $this->assertSame('open', $loc->fresh()->status);
    }

    /** @test */
    public function deleting_location_unlinks_spots_but_keeps_them(): void
    {
        $loc = ServiceLocation::create(['name' => 'Vinh', 'status' => 'open']);
        $spot = CampingSpot::create([
            'name' => 'Biển Cửa Lò', 'province' => 'Nghệ An', 'terrain_tag' => 'Bãi biển',
            'nearest_service_location_id' => $loc->id,
        ]);

        $this->actingAs($this->admin())->delete(route('admin.service-locations.destroy', $loc))->assertRedirect();

        $this->assertSame(0, ServiceLocation::count());
        $this->assertNotNull($spot->fresh());                          // điểm vẫn còn
        $this->assertNull($spot->fresh()->nearest_service_location_id); // chỉ gỡ liên kết
    }

    /** @test */
    public function camping_spots_screen_lists_service_locations_with_spot_count(): void
    {
        // Vị trí phục vụ giờ quản lý ngay trong màn Điểm cắm trại.
        $loc = ServiceLocation::create(['name' => 'Hà Nội', 'area' => 'Nội thành', 'status' => 'open']);
        CampingSpot::create(['name' => 'A', 'province' => 'Hà Nội', 'terrain_tag' => 'Ven hồ', 'nearest_service_location_id' => $loc->id]);
        CampingSpot::create(['name' => 'B', 'province' => 'Hà Nội', 'terrain_tag' => 'Rừng núi', 'nearest_service_location_id' => $loc->id]);

        $this->actingAs($this->admin())->get(route('admin.camping-spots'))
            ->assertInertia(fn ($page) => $page
                ->component('Admin/CampingSpots')
                ->has('service_locations', 1)
                ->where('service_locations.0.spots_count', 2)
                ->where('service_locations.0.status', 'open'));
    }
}

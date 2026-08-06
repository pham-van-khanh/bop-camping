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
 * bopcamping-n0db — địa chỉ + link bản đồ của địa điểm phục vụ.
 *
 * Khách chọn "Tự đến xem đồ" thì phải biết đi đâu, nên checkout cần địa chỉ và link
 * bản đồ. Admin nhập ở mục Vị trí.
 */
class ServiceLocationAddressTest extends TestCase
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
    public function admin_can_save_address_and_map_url(): void
    {
        $this->actingAs($this->admin())->post(route('admin.service-locations.store'), [
            'name' => 'Vinh',
            'area' => 'Nghệ An',
            'status' => 'open',
            'address' => '25 Nguyễn Văn Cừ, TP Vinh, Nghệ An',
            'map_url' => 'https://maps.app.goo.gl/abc123',
        ])->assertSessionHasNoErrors();

        $loc = ServiceLocation::firstOrFail();
        $this->assertSame('25 Nguyễn Văn Cừ, TP Vinh, Nghệ An', $loc->address);
        $this->assertSame('https://maps.app.goo.gl/abc123', $loc->map_url);
    }

    /** @test */
    public function address_and_map_url_are_optional(): void
    {
        $this->actingAs($this->admin())->post(route('admin.service-locations.store'), [
            'name' => 'Hà Nội', 'status' => 'open',
        ])->assertSessionHasNoErrors();

        $loc = ServiceLocation::firstOrFail();
        $this->assertNull($loc->address);
        $this->assertNull($loc->map_url);
    }

    /**
     * @test
     *
     * map_url được render thành <a href> trên trang checkout nên chỉ nhận http/https.
     *
     * Ghi chú cho người đọc sau: rule 'url' của Laravel TỰ CHẶN javascript: và data:
     * (đã đo trên chính bản Laravel của dự án), nên phần XSS coi như đã có lớp một.
     * Regex ở đây thêm lớp hai và loại nốt ftp: cùng các scheme vô nghĩa với bản đồ.
     */
    public function rejects_non_http_map_url_schemes(): void
    {
        foreach (['javascript:alert(1)', 'data:text/html;base64,PHNjcmlwdD4=', 'ftp://x.test/a'] as $bad) {
            $this->actingAs($this->admin())->post(route('admin.service-locations.store'), [
                'name' => 'X'.md5($bad), 'status' => 'open', 'map_url' => $bad,
            ])->assertSessionHasErrors('map_url');
        }

        $this->assertSame(0, ServiceLocation::count());
    }

    /** @test */
    public function checkout_receives_pickup_locations_with_address_and_map(): void
    {
        $loc = ServiceLocation::create([
            'name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open',
            'address' => '25 Nguyễn Văn Cừ', 'map_url' => 'https://maps.app.goo.gl/abc123',
        ]);
        // Địa điểm chưa mở thì không được lộ ra trang khách.
        ServiceLocation::create(['name' => 'Đà Nẵng', 'status' => 'coming', 'address' => 'Chưa mở']);

        $cat = Category::create(['name' => 'Lều', 'slug' => 'leu']);
        $p = Product::create([
            'category_id' => $cat->id, 'name' => 'Lều', 'slug' => 'leu-1',
            'price_per_day' => 100000, 'quantity' => 2, 'deposit' => 100000,
        ]);
        $p->serviceLocations()->attach($loc->id, ['quantity' => 2]);

        $this->get(route('cart'))->assertInertia(fn (Assert $page) => $page
            ->has('pickup_locations', 1)
            ->where('pickup_locations.0.slug', 'vinh')
            ->where('pickup_locations.0.name', 'Vinh')
            ->where('pickup_locations.0.address', '25 Nguyễn Văn Cừ')
            ->where('pickup_locations.0.map_url', 'https://maps.app.goo.gl/abc123'));
    }
}

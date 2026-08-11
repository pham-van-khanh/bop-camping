<?php

namespace Tests\Feature;

use App\Models\ServiceLocation;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-y0h — Thông tin liên hệ/social (ADR home_faq_contact).
 * Singleton SiteSetting, admin sửa; footer + dải Zalo đọc qua shared prop 'site';
 * địa chỉ lấy từ ServiceLocation::open (không lưu lại trong settings).
 */
class SiteSettingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /** @test */
    public function current_creates_singleton_row(): void
    {
        $a = SiteSetting::current();
        $b = SiteSetting::current();

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, SiteSetting::count());
    }

    /**
     * zaloUrl: có url override → dùng url; không có url nhưng có phone → OA chính
     * thức; không có cả hai → null.
     *
     * bopcamping-yki5: sđt KHÔNG còn bị ghép thành zalo.me/<sđt>. Khoá lại chỗ này
     * vì đó chính là thứ vừa đổi — mọi nút Zalo phải về cùng một OA.
     *
     * @test
     */
    public function zalo_url_uses_official_oa_instead_of_building_one_from_the_phone(): void
    {
        $s = SiteSetting::current();

        $s->update(['zalo1_phone' => '0976544370', 'zalo1_url' => null]);
        $this->assertSame(SiteSetting::ZALO_OA_URL, $s->zaloUrl(1));
        $this->assertStringNotContainsString('0976544370', (string) $s->zaloUrl(1));

        $s->update(['zalo2_phone' => '0373655008', 'zalo2_url' => 'https://zalo.me/vanity']);
        $this->assertSame('https://zalo.me/vanity', $s->zaloUrl(2));

        $s->update(['zalo1_phone' => null, 'zalo1_url' => null]);
        $this->assertNull($s->zaloUrl(1));
    }

    /**
     * SĐT vẫn phải ra tới trang khách để hiện ở footer/tooltip cho khách GỌI —
     * đổi đường dẫn sang OA không được làm mất số (bopcamping-yki5).
     *
     * @test
     */
    public function phone_is_still_exposed_for_display_even_though_the_link_points_at_the_oa(): void
    {
        SiteSetting::current()->update([
            'zalo1_phone' => '0976544370',
            'zalo1_url' => null,
        ]);

        $this->get('/')->assertInertia(fn ($page) => $page
            ->where('site.zalo_1.phone', '0976544370')
            ->where('site.zalo_1.url', SiteSetting::ZALO_OA_URL));
    }

    /** @test */
    public function shared_site_prop_exposes_contact_and_addresses(): void
    {
        ServiceLocation::create(['name' => 'Vinh', 'area' => 'Nghệ An', 'status' => 'open', 'sort_order' => 1]);
        ServiceLocation::create(['name' => 'Hà Nội', 'area' => 'Nội thành', 'status' => 'open', 'sort_order' => 2]);
        ServiceLocation::create(['name' => 'Đà Nẵng', 'area' => 'Sắp mở', 'status' => 'coming', 'sort_order' => 3]);

        SiteSetting::current()->update([
            'hotline_primary' => '0976544370',
            'hotline_secondary' => '0373655008',
            'zalo1_label' => 'Tư vấn', 'zalo1_phone' => '0976544370',
            'zalo2_label' => 'Hỗ trợ', 'zalo2_phone' => '0373655008',
            'facebook_url' => 'https://facebook.com/bopcamping',
            'tiktok_url' => null,
            'working_hours' => '8:00 – 21:00 hằng ngày',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('site.hotline_primary', '0976544370')
                ->where('site.hotline_secondary', '0373655008')
                ->where('site.zalo_1.label', 'Tư vấn')
                ->where('site.zalo_1.url', SiteSetting::ZALO_OA_URL)
                ->where('site.zalo_2.url', SiteSetting::ZALO_OA_URL)
                ->where('site.facebook_url', 'https://facebook.com/bopcamping')
                ->where('site.tiktok_url', null)
                ->where('site.working_hours', '8:00 – 21:00 hằng ngày')
                // Địa chỉ: chỉ vị trí đang mở, theo thứ tự
                ->count('site.addresses', 2)
                ->where('site.addresses.0.name', 'Vinh')
                ->where('site.addresses.0.area', 'Nghệ An')
                ->where('site.addresses.1.name', 'Hà Nội'));
    }

    /** @test */
    public function admin_can_view_and_update_settings(): void
    {
        $this->actingAs($this->admin())->get(route('admin.settings'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/SiteSettings')->has('settings'));

        $this->actingAs($this->admin())->put(route('admin.settings.update'), [
            'hotline_primary' => '0976544370',
            'hotline_secondary' => '0373655008',
            'zalo1_label' => 'Tư vấn & đặt đồ',
            'zalo1_phone' => '0976544370',
            'zalo2_label' => 'Hỗ trợ thêm',
            'zalo2_phone' => '0373655008',
            'facebook_url' => 'https://facebook.com/bopcamping',
            'tiktok_url' => 'https://tiktok.com/@bopcamping',
            'working_hours' => '8:00 – 21:00 hằng ngày',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $s = SiteSetting::current();
        $this->assertSame('0976544370', $s->hotline_primary);
        $this->assertSame('https://tiktok.com/@bopcamping', $s->tiktok_url);
    }

    /** @test */
    public function admin_saves_pickup_return_hours_and_shared_prop_exposes_them(): void
    {
        $this->actingAs($this->admin())->put(route('admin.settings.update'), [
            'pickup_hour' => 6,
            'return_hour' => 22,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $s = SiteSetting::current();
        $this->assertSame(6, (int) $s->pickup_hour);
        $this->assertSame(22, (int) $s->return_hour);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('site.pickup_hour', 6)
            ->where('site.return_hour', 22));
    }

    /** @test */
    public function pickup_hour_rejects_out_of_range(): void
    {
        $this->actingAs($this->admin())->put(route('admin.settings.update'), [
            'pickup_hour' => 25,
        ])->assertSessionHasErrors('pickup_hour');
    }

    /** @test */
    public function pickup_return_hours_default_to_8_and_20(): void
    {
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('site.pickup_hour', 8)
            ->where('site.return_hour', 20));
    }

    /** @test */
    public function update_rejects_invalid_url(): void
    {
        $this->actingAs($this->admin())->put(route('admin.settings.update'), [
            'facebook_url' => 'khong-phai-url',
        ])->assertSessionHasErrors('facebook_url');
    }

    /** @test */
    public function non_admin_cannot_access_settings(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user)->get(route('admin.settings'))->assertRedirect();
        $this->actingAs($user)->put(route('admin.settings.update'), [])->assertRedirect();
        $this->get(route('admin.settings'))->assertRedirect();
    }
}

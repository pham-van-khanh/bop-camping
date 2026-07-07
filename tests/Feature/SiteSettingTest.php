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
     * zaloUrl: có url override → dùng url; không có url nhưng có phone →
     * fallback zalo.me/<phone>; không có cả hai → null.
     *
     * @test
     */
    public function zalo_url_falls_back_to_phone(): void
    {
        $s = SiteSetting::current();

        $s->update(['zalo1_phone' => '0976544370', 'zalo1_url' => null]);
        $this->assertSame('https://zalo.me/0976544370', $s->zaloUrl(1));

        $s->update(['zalo2_phone' => '0373655008', 'zalo2_url' => 'https://zalo.me/vanity']);
        $this->assertSame('https://zalo.me/vanity', $s->zaloUrl(2));

        $s->update(['zalo1_phone' => null, 'zalo1_url' => null]);
        $this->assertNull($s->zaloUrl(1));
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
            'zalo_main' => 1,
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
                ->where('site.zalo_1.url', 'https://zalo.me/0976544370')
                ->where('site.zalo_2.url', 'https://zalo.me/0373655008')
                // Zalo chính = #1 → trang chủ dùng số này
                ->where('site.zalo_main.label', 'Tư vấn')
                ->where('site.zalo_main.phone', '0976544370')
                ->where('site.zalo_main.url', 'https://zalo.me/0976544370')
                ->where('site.facebook_url', 'https://facebook.com/bopcamping')
                ->where('site.tiktok_url', null)
                ->where('site.working_hours', '8:00 – 21:00 hằng ngày')
                // Địa chỉ: chỉ vị trí đang mở, theo thứ tự
                ->count('site.addresses', 2)
                ->where('site.addresses.0.name', 'Vinh')
                ->where('site.addresses.0.area', 'Nghệ An')
                ->where('site.addresses.1.name', 'Hà Nội'));
    }

    /**
     * Admin chọn Zalo chính = #2 → shared prop zalo_main trả tài khoản #2
     * (footer vẫn có cả hai — kiểm ở component). bopcamping-12w.
     *
     * @test
     */
    public function main_zalo_follows_admin_choice(): void
    {
        $s = SiteSetting::current();
        $s->update([
            'zalo1_label' => 'Tư vấn', 'zalo1_phone' => '0976544370',
            'zalo2_label' => 'Hỗ trợ', 'zalo2_phone' => '0373655008',
            'zalo_main' => 2,
        ]);

        $this->assertSame(2, $s->mainZaloIndex());
        $this->assertSame('https://zalo.me/0373655008', $s->mainZalo()['url']);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('site.zalo_main.phone', '0373655008')
            ->where('site.zalo_main.label', 'Hỗ trợ'));
    }

    /** @test */
    public function main_zalo_index_defaults_to_one_for_bad_value(): void
    {
        $s = SiteSetting::current();
        $s->update(['zalo_main' => 1]);
        $this->assertSame(1, $s->mainZaloIndex());
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
            'zalo_main' => 2,
            'facebook_url' => 'https://facebook.com/bopcamping',
            'tiktok_url' => 'https://tiktok.com/@bopcamping',
            'working_hours' => '8:00 – 21:00 hằng ngày',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $s = SiteSetting::current();
        $this->assertSame('0976544370', $s->hotline_primary);
        $this->assertSame('https://tiktok.com/@bopcamping', $s->tiktok_url);
        $this->assertSame(2, (int) $s->zalo_main);
    }

    /** @test */
    public function update_rejects_invalid_zalo_main(): void
    {
        $this->actingAs($this->admin())->put(route('admin.settings.update'), [
            'zalo_main' => 5,
        ])->assertSessionHasErrors('zalo_main');
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

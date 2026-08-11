<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-uen0 — Nút Zalo nổi (ZaloFloatButton, mount trong SiteLayout).
 *
 * Component chọn nhánh hiển thị dựa trên SỐ TÀI KHOẢN CÓ `url` trong shared prop
 * `site`: 0 → ẩn nút, 1 → bấm mở thẳng Zalo, 2 → mở panel cho khách chọn.
 * Các test dưới đây khoá hợp đồng dữ liệu đó ở phía server cho từng cấu hình —
 * riêng trường hợp "cả hai đều đầy đủ" đã có ở SiteSettingTest nên không lặp lại.
 */
class ZaloContactTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Chỉ điền tài khoản thứ HAI (chủ shop bỏ trống ô 1): prop vẫn phải lộ ra
     * đúng một url dùng được, nếu không nút sẽ ẩn oan.
     *
     * @test
     */
    public function only_second_account_configured_still_yields_one_usable_url(): void
    {
        SiteSetting::current()->update([
            'zalo1_label' => null, 'zalo1_phone' => null, 'zalo1_url' => null,
            'zalo2_label' => 'Hỗ trợ thêm', 'zalo2_phone' => '0373655008', 'zalo2_url' => null,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('site.zalo_1.url', null)
                ->where('site.zalo_2.label', 'Hỗ trợ thêm')
                ->where('site.zalo_2.phone', '0373655008')
                ->where('site.zalo_2.url', SiteSetting::ZALO_OA_URL));
    }

    /**
     * Chỉ điền tài khoản thứ NHẤT → đúng một url; đây là nhánh "bấm mở thẳng".
     *
     * @test
     */
    public function only_first_account_configured_yields_one_usable_url(): void
    {
        SiteSetting::current()->update([
            'zalo1_label' => 'Tư vấn & đặt đồ', 'zalo1_phone' => '0976544370', 'zalo1_url' => null,
            'zalo2_label' => null, 'zalo2_phone' => null, 'zalo2_url' => null,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('site.zalo_1.url', SiteSetting::ZALO_OA_URL)
                ->where('site.zalo_2.url', null));
    }

    /**
     * Không cấu hình Zalo nào → cả hai url null để component ẩn hẳn nút.
     *
     * @test
     */
    public function no_account_configured_yields_no_url_at_all(): void
    {
        SiteSetting::current()->update([
            'zalo1_phone' => null, 'zalo1_url' => null,
            'zalo2_phone' => null, 'zalo2_url' => null,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('site.zalo_1.url', null)
                ->where('site.zalo_2.url', null));
    }

    /**
     * Nút mount trong SiteLayout nên phải có dữ liệu ở MỌI trang khách, không
     * riêng trang chủ — chống hồi quy nếu ai đó chuyển `site` thành prop riêng
     * của controller trang chủ.
     *
     * @test
     */
    public function zalo_prop_is_shared_on_every_customer_page(): void
    {
        SiteSetting::current()->update([
            'zalo1_phone' => '0976544370',
            'zalo2_phone' => '0373655008',
        ]);

        foreach (['/', '/thiet-bi', '/tra-cuu'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('site.zalo_1.url', SiteSetting::ZALO_OA_URL)
                    ->where('site.zalo_2.url', SiteSetting::ZALO_OA_URL));
        }
    }

    /**
     * Url override thắng số điện thoại — chủ shop dùng link zalo.me tuỳ chỉnh
     * (vd link nhóm/OA) thì nút phải mở đúng link đó.
     *
     * @test
     */
    public function custom_url_wins_over_phone_fallback(): void
    {
        SiteSetting::current()->update([
            'zalo1_phone' => '0976544370',
            'zalo1_url' => 'https://zalo.me/g/bopcamping',
        ]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('site.zalo_1.url', 'https://zalo.me/g/bopcamping'));
    }
}

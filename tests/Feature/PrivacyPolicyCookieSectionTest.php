<?php

namespace Tests\Feature;

use App\Models\StaticPage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Chính sách bảo mật — mục "5. Cookie và công cụ phân tích": nội dung mặc định mới và
 * migration nối mục này vào trang đang có trên production.
 */
class PrivacyPolicyCookieSectionTest extends TestCase
{
    use RefreshDatabase;

    private const HEADING = 'Cookie và công cụ phân tích';

    /** Nội dung đang chạy trên production trước đợt này (mục 1–4). */
    private const PRODUCTION_HTML = '<p>BỐP CAMPING tôn trọng và cam kết bảo vệ thông tin cá nhân của khách hàng. Chính sách này giải thích chúng tôi thu thập, sử dụng và bảo vệ dữ liệu của bạn như thế nào.</p><h2>1. Thông tin chúng tôi thu thập</h2><ul><li>Họ tên, số điện thoại và email khi bạn đăng nhập hoặc đặt thuê.</li><li>Địa chỉ giao nhận và thời gian thuê để phục vụ đơn hàng.</li><li>Lịch sử đơn thuê và đánh giá của bạn.</li></ul><h2>2. Mục đích sử dụng</h2><ul><li>Xử lý và giao nhận đơn thuê, liên hệ xác nhận lịch.</li><li>Gửi mã OTP xác thực đăng nhập qua email.</li><li>Chăm sóc khách hàng, xử lý khiếu nại và cải thiện dịch vụ.</li></ul><h2>3. Bảo mật & chia sẻ</h2><p>Thông tin của bạn được lưu trữ an toàn và chỉ dùng nội bộ cho việc vận hành dịch vụ. Chúng tôi <strong>không mua bán, trao đổi</strong> thông tin cá nhân của khách với bên thứ ba, trừ khi có yêu cầu hợp pháp của cơ quan chức năng.</p><h2>4. Quyền của khách hàng</h2><p>Bạn có thể yêu cầu xem, cập nhật hoặc xóa thông tin cá nhân của mình bằng cách liên hệ với chúng tôi qua hotline hoặc Zalo.</p>';

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_12_000001_add_cookie_section_to_privacy_policy.php');
    }

    private function privacyRow(string $content): StaticPage
    {
        return StaticPage::create([
            'slug' => 'chinh-sach-bao-mat',
            'title' => 'Chính sách bảo mật — BỐP CAMPING',
            'content' => $content,
        ]);
    }

    /** Phần mục 5 của nội dung mặc định — cái migration phải nối vào. */
    private function defaultSection(): string
    {
        $default = StaticPage::policyDefaults('chinh-sach-bao-mat')['content'];

        return substr($default, (int) strpos($default, '<h2>5. '));
    }

    /** @test */
    public function migration_appends_the_section_once_after_existing_content(): void
    {
        $page = $this->privacyRow(self::PRODUCTION_HTML);

        $this->migration()->up();

        $content = (string) $page->refresh()->content;
        // Khối nối vào = đúng mục 5 của nội dung mặc định: cài mới và production hiện như nhau.
        $this->assertSame(self::PRODUCTION_HTML.$this->defaultSection(), $content);
        $this->assertSame(1, substr_count($content, self::HEADING));
    }

    /** @test */
    public function running_up_twice_does_not_duplicate_the_section(): void
    {
        $page = $this->privacyRow(self::PRODUCTION_HTML);

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame(1, substr_count((string) $page->refresh()->content, self::HEADING));
    }

    /** @test */
    public function admin_edited_content_before_the_section_is_preserved(): void
    {
        $edited = '<p>Bản chủ shop tự viết lại.</p><h2>Liên hệ</h2><p>Gọi hotline.</p>';
        $page = $this->privacyRow($edited);

        $this->migration()->up();

        $content = (string) $page->refresh()->content;
        $this->assertStringStartsWith($edited, $content);
        $this->assertStringContainsString(self::HEADING, $content);
    }

    /** @test */
    public function down_removes_exactly_the_appended_block(): void
    {
        $page = $this->privacyRow(self::PRODUCTION_HTML);

        $this->migration()->up();
        $this->migration()->down();

        $this->assertSame(self::PRODUCTION_HTML, $page->refresh()->content);
    }

    /** @test */
    public function missing_row_is_left_for_first_or_create(): void
    {
        $this->migration()->up();

        $this->assertSame(0, StaticPage::where('slug', 'chinh-sach-bao-mat')->count());
    }

    /** @test */
    public function other_policy_pages_are_untouched(): void
    {
        $terms = StaticPage::create(['slug' => 'dieu-khoan-su-dung', 'title' => 'Điều khoản', 'content' => '<p>Điều khoản.</p>']);

        $this->migration()->up();

        $this->assertSame('<p>Điều khoản.</p>', $terms->refresh()->content);
    }

    /** @test */
    public function fresh_default_contains_section_five_with_opt_out_links(): void
    {
        $content = StaticPage::policy('chinh-sach-bao-mat')->content;

        $this->assertStringContainsString('<h2>5. '.self::HEADING.'</h2>', $content);
        $this->assertStringContainsString('bop_attr', $content);
        $this->assertStringContainsString('XSRF-TOKEN', $content);
        $this->assertStringContainsString('https://tools.google.com/dlpage/gaoptout', $content);
        $this->assertStringContainsString('https://accountscenter.facebook.com/ad_preferences', $content);
        // Mục 1–4 vẫn còn nguyên, mục 5 nằm cuối.
        $this->assertStringStartsWith(self::PRODUCTION_HTML, $content);
    }

    /** @test */
    public function public_privacy_page_shows_the_cookie_section_on_a_fresh_install(): void
    {
        // Chưa có dòng nào → firstOrCreate dùng mặc định mới.
        $this->get('/chinh-sach-bao-mat')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Policy')
                ->where('page.content', fn (string $html) => str_contains($html, self::HEADING))
            );
    }

    /** @test */
    public function public_privacy_page_shows_the_cookie_section_after_migration(): void
    {
        $this->privacyRow(self::PRODUCTION_HTML);
        $this->migration()->up();

        $this->get('/chinh-sach-bao-mat')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Policy')
                ->where('page.content', fn (string $html) => str_contains($html, self::HEADING))
            );
    }
}

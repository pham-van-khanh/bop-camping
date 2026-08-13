<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-1xja — ép mọi request về đúng một tên miền.
 *
 * Đo trên production 13/08/2026: https://www.bopcamping.com phục vụ TRỌN VẸN cả site
 * và canonical tự trỏ về chính www, tức Google thấy hai bản sao độc lập của toàn bộ
 * website. Đây là loại lỗi không nhìn ra khi đọc code — phải gõ thử tên miền phụ.
 */
class CanonicalHostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://bopcamping.com']);
    }

    /** @test */
    public function www_is_redirected_permanently_to_the_canonical_host(): void
    {
        $this->get('https://www.bopcamping.com/thiet-bi')
            ->assertStatus(301)
            ->assertRedirect('https://bopcamping.com/thiet-bi');
    }

    /** Đường dẫn và query phải giữ nguyên — mất query là mất bộ lọc khách đang xem. */
    /** @test */
    public function the_redirect_keeps_the_path_and_query_string(): void
    {
        $this->get('https://www.bopcamping.com/thiet-bi?cat=leu&sort=low')
            ->assertRedirect('https://bopcamping.com/thiet-bi?cat=leu&sort=low');
    }

    /** @test */
    public function the_canonical_host_itself_is_not_redirected(): void
    {
        $this->get('https://bopcamping.com/thiet-bi')->assertOk();
    }

    /**
     * POST KHÔNG được chuyển hướng: chuyển hướng làm mất body, khách gõ nhầm www lúc
     * đặt đơn thì đơn bay mất. Chỉ GET/HEAD mới chuẩn hoá.
     *
     * @test
     */
    public function a_post_is_never_redirected_because_it_would_lose_the_body(): void
    {
        $res = $this->post('https://www.bopcamping.com/gop-y', [
            'content' => 'Góp ý thử',
        ]);

        $this->assertNotSame(301, $res->getStatusCode());
    }

    /**
     * APP_URL chưa cấu hình tử tế (localhost lúc dev) thì đứng yên — ép sai host còn tệ
     * hơn không ép.
     *
     * @test
     */
    public function nothing_is_redirected_when_app_url_is_not_configured(): void
    {
        config(['app.url' => 'http://localhost']);

        $this->get('https://www.bopcamping.com/thiet-bi')->assertOk();
    }

    /**
     * Sitemap chứa URL tuyệt đối dựng từ host request. Cache dùng chung một khoá thì bot
     * vào bằng host phụ sẽ nạp cache toàn URL host đó và mọi người nhận bản sai 1 giờ.
     *
     * @test
     */
    public function the_sitemap_cache_is_kept_per_host(): void
    {
        // Host phụ bị chuyển hướng nên không tự nạp được cache; gọi thẳng controller qua
        // một host khác để kiểm khoá cache có tách hay không.
        config(['app.url' => 'http://localhost']);   // tắt chuyển hướng để test khoá cache

        $a = $this->get('https://bopcamping.com/sitemap.xml')->getContent();
        $b = $this->get('https://www.bopcamping.com/sitemap.xml')->getContent();

        $this->assertStringContainsString('https://bopcamping.com/thiet-bi', $a);
        $this->assertStringContainsString('https://www.bopcamping.com/thiet-bi', $b);
    }
}

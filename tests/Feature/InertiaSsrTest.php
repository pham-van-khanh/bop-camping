<?php

namespace Tests\Feature;

use App\Ssr\PublicPagesGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Ssr\Gateway;
use Tests\TestCase;

/**
 * bopcamping-zvrx — render phía server cho trang khách.
 *
 * Không chạy process Node thật: giả lập endpoint /render bằng Http::fake và kiểm phần
 * Laravel quyết định — gọi khi nào, gửi gì, dùng kết quả ra sao, và lỗi thì rơi về
 * render phía client (trang vẫn 200, y như trước khi có SSR).
 */
class InertiaSsrTest extends TestCase
{
    use RefreshDatabase;

    private const SSR_BODY = '<div id="app" data-page="{}"><main><h1>Tiêu đề SSR</h1></main></div>';

    private function enableSsr(): void
    {
        config([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.url' => 'http://127.0.0.1:13714',
            // Test không build bundle Node — bỏ bước dò file bootstrap/ssr/ssr.js.
            'inertia.ssr.ensure_bundle_exists' => false,
        ]);
    }

    private function fakeRender(): void
    {
        Http::fake([
            '127.0.0.1:13714/render' => Http::response([
                'head' => ['<title inertia>Tiêu đề từ React</title>', '<meta name="x-ssr" content="1">'],
                'body' => self::SSR_BODY,
            ]),
        ]);
    }

    /** @test */
    public function ssr_is_off_by_default_and_never_calls_node(): void
    {
        Http::fake();

        $this->get('/thiet-bi')
            ->assertOk()
            ->assertDontSee('Tiêu đề SSR', false)
            ->assertSee('<div id="app" data-page=', false);

        Http::assertNothingSent();
    }

    /** @test */
    public function public_page_uses_server_rendered_body_and_keeps_one_title(): void
    {
        $this->enableSsr();
        $this->fakeRender();

        $html = $this->get('/thiet-bi')->assertOk()->getContent();

        $this->assertStringContainsString('<h1>Tiêu đề SSR</h1>', $html);
        // Head khác của React vẫn giữ…
        $this->assertStringContainsString('<meta name="x-ssr" content="1">', $html);
        // …nhưng <title> chỉ còn MỘT, và là title của SeoService (nguồn duy nhất).
        $this->assertSame(1, substr_count($html, '<title'));
        $this->assertStringNotContainsString('Tiêu đề từ React', $html);
        // Body SSR thay cho div rỗng, không in cả hai.
        $this->assertStringNotContainsString('<div id="app" data-page="{&quot;', $html);

        // Gọi Node đúng MỘT lần cho cả head lẫn body.
        Http::assertSentCount(1);
    }

    /** @test */
    public function payload_carries_page_and_ziggy_for_route_helper(): void
    {
        $this->enableSsr();
        $this->fakeRender();

        $this->get('/thiet-bi?cat=leu')->assertOk();

        Http::assertSent(function (Request $r) {
            return $r->url() === 'http://127.0.0.1:13714/render'
                && $r['component'] === 'Products'
                && isset($r['props'])
                && isset($r['ziggy']['routes']['products.show'])
                && str_ends_with($r['ziggy']['location'], '/thiet-bi?cat=leu');
        });
    }

    /** @test */
    public function non_public_pages_are_never_server_rendered(): void
    {
        $this->enableSsr();
        Http::fake();

        // Trang khách nhưng không cần index (tra cứu đơn) → không SSR.
        $this->get('/tra-cuu')->assertOk();
        Http::assertNothingSent();

        // Admin/Shipper: lọc ngay ở gateway, kể cả khi lỡ có ai gọi.
        $gateway = app(Gateway::class);
        $this->assertInstanceOf(PublicPagesGateway::class, $gateway);
        foreach (['Admin/Dashboard', 'Shipper/Schedule', 'Cart', 'Account'] as $component) {
            $this->assertNull($gateway->dispatch(['component' => $component, 'props' => []]));
        }
        Http::assertNothingSent();
    }

    /** @test */
    public function falls_back_to_client_rendering_when_node_is_down(): void
    {
        $this->enableSsr();
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->get('/thiet-bi')
            ->assertOk()
            ->assertSee('<div id="app" data-page=', false);
    }

    /** @test */
    public function falls_back_when_node_fails_to_render_the_component(): void
    {
        $this->enableSsr();

        // Component ném lỗi trong Node → server trả rỗng / 500.
        Http::fake([
            '127.0.0.1:13714/render' => Http::sequence()
                ->push('', 200)
                ->push('boom', 500),
        ]);

        foreach (['rỗng', '500'] as $_case) {
            $this->get('/thiet-bi')
                ->assertOk()
                ->assertSee('<div id="app" data-page=', false);
        }
    }
}

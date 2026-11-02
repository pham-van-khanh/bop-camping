<?php

namespace Tests\Feature;

use App\Jobs\SendTelegramMessage;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\VisitEvent;
use App\Services\VisitTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VisitTrackingTest extends TestCase
{
    use RefreshDatabase;

    private const UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari/604.1';

    private function product(): Product
    {
        return Product::factory()->create(['category_id' => Category::factory()->create()->id]);
    }

    private function enableTelegram(): void
    {
        config(['services.telegram.token' => 'T0KEN', 'services.telegram.chat_id' => '123']);
    }

    /** @test */
    public function page_view_is_recorded_with_visitor_cookie(): void
    {
        $this->withHeader('User-Agent', self::UA)->get('/thiet-bi')->assertOk();

        $e = VisitEvent::sole();
        $this->assertSame(VisitEvent::TYPE_PAGE, $e->type);
        $this->assertSame('/thiet-bi', $e->path);
        $this->assertNotEmpty($e->visitor_id);
    }

    /** @test */
    public function product_page_records_product_view(): void
    {
        $p = $this->product();
        $this->withHeader('User-Agent', self::UA)->get('/thiet-bi/'.$p->slug)->assertOk();

        $e = VisitEvent::sole();
        $this->assertSame(VisitEvent::TYPE_PRODUCT, $e->type);
        $this->assertSame($p->id, $e->product_id);
    }

    /** @test */
    public function bots_admins_and_admin_pages_are_not_counted(): void
    {
        $this->withHeader('User-Agent', 'Googlebot/2.1')->get('/thiet-bi')->assertOk();
        $this->withHeader('User-Agent', self::UA)->actingAs(User::factory()->create(['is_admin' => true]))->get('/thiet-bi')->assertOk();
        $this->withHeader('User-Agent', self::UA)->get('/admin/login');

        $this->assertSame(0, VisitEvent::count());
    }

    /** @test */
    public function contact_click_is_recorded_and_notifies_telegram_once_per_cooldown(): void
    {
        $this->enableTelegram();
        Queue::fake();
        $p = $this->product();
        $payload = ['method' => 'zalo', 'placement' => 'float', 'path' => '/thiet-bi/'.$p->slug];

        // Hai lần bấm của CÙNG một khách (cùng mã visitor) → ghi cả hai nhưng chỉ báo 1 lần.
        $tracker = app(VisitTracker::class);
        foreach ([1, 2] as $_) {
            $request = Request::create('/track/lien-he', 'POST');
            $request->attributes->set('visitor_id', '11111111-1111-4111-8111-111111111111');
            $tracker->recordContact($request, 'zalo', 'float', $payload['path']);
        }

        $this->assertSame(2, VisitEvent::where('type', 'contact_click')->where('product_id', $p->id)->count());
        Queue::assertPushed(SendTelegramMessage::class, 1);
    }

    /** @test */
    public function contact_notification_names_logged_in_customer(): void
    {
        $this->enableTelegram();
        Queue::fake();
        $user = User::factory()->create(['name' => 'Lan Anh', 'phone' => '0911222333']);

        $this->withHeader('User-Agent', self::UA)->actingAs($user)
            ->postJson('/track/lien-he', ['method' => 'phone', 'path' => '/'])->assertNoContent();

        Queue::assertPushed(SendTelegramMessage::class, fn ($j) => str_contains($j->html, 'Lan Anh') && str_contains($j->html, '0911222333'));
    }

    /** @test */
    public function contact_rejects_unknown_method_and_skips_bots(): void
    {
        $this->enableTelegram();
        Queue::fake();

        $this->withHeader('User-Agent', self::UA)->postJson('/track/lien-he', ['method' => 'fax'])->assertStatus(422);
        $this->withHeader('User-Agent', 'bingbot')->postJson('/track/lien-he', ['method' => 'zalo'])->assertNoContent();

        $this->assertSame(0, VisitEvent::count());
        Queue::assertNothingPushed();
    }

    /** @test */
    public function no_telegram_job_when_not_configured(): void
    {
        Queue::fake();
        $this->withHeader('User-Agent', self::UA)->postJson('/track/lien-he', ['method' => 'zalo'])->assertNoContent();

        $this->assertSame(1, VisitEvent::count());
        Queue::assertNothingPushed();
    }

    /** @test */
    public function telegram_job_posts_to_bot_api(): void
    {
        $this->enableTelegram();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        (new SendTelegramMessage('<b>hi</b>'))->handle();

        Http::assertSent(fn ($r) => str_contains($r->url(), 'botT0KEN/sendMessage') && $r['chat_id'] === '123' && $r['text'] === '<b>hi</b>');
    }

    /** @test */
    public function admin_traffic_page_shows_totals(): void
    {
        $this->withHeader('User-Agent', self::UA)->get('/thiet-bi');
        $this->withHeader('User-Agent', self::UA)->postJson('/track/lien-he', ['method' => 'zalo']);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin/truy-cap')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Traffic')
                ->where('totals.page_views', 1)
                ->where('totals.contacts', 1)
                ->where('contact_by_method.0.count', 1));
    }

    /** @test */
    public function admin_traffic_page_requires_admin(): void
    {
        $this->get('/admin/truy-cap')->assertRedirect();
    }
}

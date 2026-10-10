<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\AttributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-wi6h — đo lường marketing: Meta/TikTok Pixel từ admin, không chạy ở trang
 * nội bộ, và nguồn đơn first-touch (UTM/click id/referrer) lưu vào đơn + bảng tổng quan.
 */
class MarketingTrackingTest extends TestCase
{
    use RefreshDatabase;

    private const META = '123456789012345';

    private const TIKTOK = 'C4ABC123DEF456GHI789';

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function product(): Product
    {
        $cat = Category::create(['name' => 'Lều', 'slug' => 'leu']);

        return Product::create([
            'category_id' => $cat->id,
            'name' => 'Lều 2 người',
            'slug' => 'leu-2-nguoi',
            'price_per_day' => 100000,
            'quantity' => 5,
            'deposit' => 200000,
        ]);
    }

    private function placeOrder(Product $p): void
    {
        $this->post(route('order.store'), [
            'name' => 'Khách A',
            'phone' => '0912345678',
            'items' => [['product_id' => $p->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-02']],
        ])->assertSessionHas('order_code');
    }

    /* ---------------- Pixel ---------------- */

    /** @test */
    public function pixels_are_not_injected_until_admin_sets_ids(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringNotContainsString('fbevents.js', $html);
        $this->assertStringNotContainsString('analytics.tiktok.com', $html);
    }

    /** @test */
    public function pixels_are_injected_on_shop_pages_when_ids_set(): void
    {
        SiteSetting::current()->update(['meta_pixel_id' => self::META, 'tiktok_pixel_id' => self::TIKTOK]);

        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('connect.facebook.net/en_US/fbevents.js', $html);
        $this->assertStringContainsString('fbq(\'init\',"'.self::META.'")', $html);
        $this->assertStringContainsString('fbq.disablePushState=true', $html);
        $this->assertStringContainsString('facebook.com/tr?id='.self::META, $html);
        $this->assertStringContainsString('ttq.load("'.self::TIKTOK.'")', $html);
    }

    /** @test */
    public function no_tracking_script_runs_on_admin_or_shipper_pages(): void
    {
        SiteSetting::current()->update([
            'meta_pixel_id' => self::META,
            'tiktok_pixel_id' => self::TIKTOK,
            'ga_measurement_id' => 'G-ABC12345',
        ]);

        // Trang đăng nhập admin/shipper là trang công khai → render được khi chưa đăng nhập.
        foreach ([route('admin.login'), route('admin.dashboard')] as $url) {
            $html = $this->actingAs($this->admin())->get($url)->getContent();
            $this->assertStringNotContainsString('fbevents.js', $html, $url);
            $this->assertStringNotContainsString('analytics.tiktok.com', $html, $url);
            $this->assertStringNotContainsString('gtag/js?id=', $html, $url);
        }

        $html = $this->get(route('shipper.login'))->getContent();
        $this->assertStringNotContainsString('fbevents.js', $html);
        $this->assertStringNotContainsString('gtag/js?id=', $html);
    }

    /** @test */
    public function admin_can_save_pixel_ids(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.settings.update'), ['meta_pixel_id' => self::META, 'tiktok_pixel_id' => self::TIKTOK])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $s = SiteSetting::current();
        $this->assertSame(self::META, $s->meta_pixel_id);
        $this->assertSame(self::TIKTOK, $s->tiktok_pixel_id);
    }

    /**
     * Mã pixel được in thẳng vào <script> — chỉ chấp nhận chữ số (Meta) / chữ+số (TikTok)
     * để không ai chèn được JS qua ô này (CWE-79).
     *
     * @test
     */
    public function invalid_pixel_ids_are_rejected(): void
    {
        $admin = $this->admin();

        foreach (["123');alert(1);//", 'abc123456789', '12345'] as $bad) {
            $this->actingAs($admin)
                ->put(route('admin.settings.update'), ['meta_pixel_id' => $bad])
                ->assertSessionHasErrors('meta_pixel_id');
        }
        foreach (["C4AB');alert(1)//", 'C4-ABC-123-DEF', 'short'] as $bad) {
            $this->actingAs($admin)
                ->put(route('admin.settings.update'), ['tiktok_pixel_id' => $bad])
                ->assertSessionHasErrors('tiktok_pixel_id');
        }

        $this->assertNull(SiteSetting::current()->meta_pixel_id);
        $this->assertNull(SiteSetting::current()->tiktok_pixel_id);
    }

    /* ---------------- Nguồn đơn ---------------- */

    /** @test */
    public function landing_with_utm_sets_first_touch_cookie(): void
    {
        $this->get('/?utm_source=Facebook&utm_medium=CPC&utm_campaign=he-2026&fbclid=abc')
            ->assertCookie(AttributionService::COOKIE);

        // Đã có cookie → lượt sau (nguồn khác) KHÔNG ghi đè.
        $this->withCookie(AttributionService::COOKIE, '{"utm_source":"zalo"}')
            ->get('/?utm_source=google')
            ->assertCookieMissing(AttributionService::COOKIE);
    }

    /** @test */
    public function direct_and_internal_visits_set_no_cookie(): void
    {
        $this->get('/')->assertCookieMissing(AttributionService::COOKIE);

        $this->withHeader('referer', 'http://localhost/thiet-bi')
            ->get('/')
            ->assertCookieMissing(AttributionService::COOKIE);
    }

    /** @test */
    public function admin_pages_never_capture_attribution(): void
    {
        $this->get('/admin/login?utm_source=facebook')->assertCookieMissing(AttributionService::COOKIE);
    }

    /** @test */
    public function order_stores_first_touch_utm_fields(): void
    {
        $p = $this->product();

        $this->withCookie(AttributionService::COOKIE, (string) json_encode([
            'utm_source' => 'Facebook',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'Thuê lều hè 2026',
            'utm_content' => 'video-1',
            'utm_term' => 'thuê lều',
            'referrer_host' => 'm.facebook.com',
            'click_id_type' => 'fbclid',
        ]));
        $this->placeOrder($p);

        $order = Order::firstOrFail();
        $this->assertSame('facebook', $order->utm_source); // viết thường để gom nhóm
        $this->assertSame('cpc', $order->utm_medium);
        $this->assertSame('Thuê lều hè 2026', $order->utm_campaign);
        $this->assertSame('video-1', $order->utm_content);
        $this->assertSame('thuê lều', $order->utm_term);
        $this->assertSame('m.facebook.com', $order->referrer_host);
        $this->assertSame('fbclid', $order->click_id_type);
    }

    /** @test */
    public function utm_values_are_sanitized_and_truncated(): void
    {
        $p = $this->product();

        $this->withCookie(AttributionService::COOKIE, (string) json_encode([
            'utm_source' => '<script>alert(1)</script>fb',
            'utm_campaign' => str_repeat('a', 400),
            'click_id_type' => 'evil',
            'unknown_key' => 'x',
        ]));
        $this->placeOrder($p);

        $order = Order::firstOrFail();
        $this->assertSame('alert1fb', $order->utm_source);
        $this->assertSame(150, mb_strlen($order->utm_campaign));
        $this->assertNull($order->click_id_type);
    }

    /** Body request KHÔNG được ghi đè nguồn — chỉ cookie do server ghi mới tính. @test */
    public function order_ignores_attribution_fields_in_request_body(): void
    {
        $p = $this->product();

        $this->post(route('order.store'), [
            'name' => 'Khách A',
            'phone' => '0912345678',
            'utm_source' => 'spoofed',
            'items' => [['product_id' => $p->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-02']],
        ])->assertSessionHas('order_code');

        $this->assertNull(Order::firstOrFail()->utm_source);
    }

    /** Luồng thật end-to-end: vào trang có UTM → cookie (mã hoá) → đặt đơn. @test */
    public function utm_from_landing_url_reaches_the_order(): void
    {
        $p = $this->product();

        $cookie = collect($this->get('/?utm_source=tiktok&utm_campaign=combo-thang-10')->headers->getCookies())
            ->first(fn ($c) => $c->getName() === AttributionService::COOKIE);
        $this->assertNotNull($cookie);

        // Cookie trên response đã mã hoá → gửi lại nguyên văn như trình duyệt.
        $this->withUnencryptedCookie(AttributionService::COOKIE, $cookie->getValue());
        $this->placeOrder($p);

        $order = Order::firstOrFail();
        $this->assertSame('tiktok', $order->utm_source);
        $this->assertSame('combo-thang-10', $order->utm_campaign);
    }

    /** @test */
    public function order_success_flash_carries_value_without_deposit(): void
    {
        $p = $this->product();

        $this->post(route('order.store'), [
            'name' => 'Khách A',
            'phone' => '0912345678',
            'items' => [['product_id' => $p->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-02']],
        ])->assertSessionHas('order_value', 200000); // 2 ngày × 100k, cọc 200k không tính
    }

    /** @test */
    public function admin_order_detail_shows_source(): void
    {
        $order = Order::create([
            'code' => 'BOP-SRC1',
            'customer_name' => 'Khách',
            'customer_phone' => '0900000001',
            'start_date' => '2030-07-10',
            'end_date' => '2030-07-12',
            'status' => 'pending',
            'utm_source' => 'facebook',
            'utm_campaign' => 'he-2026',
        ]);

        $this->actingAs($this->admin())->get(route('admin.orders.show', $order))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Orders/Show')
                ->where('order.attribution.label', 'facebook')
                ->where('order.attribution.utm_campaign', 'he-2026')
            );
    }

    /** @test */
    public function dashboard_breaks_down_orders_and_revenue_by_source_last_30_days(): void
    {
        $mk = function (array $attrs) {
            return Order::create($attrs + [
                'customer_name' => 'Khách',
                'customer_phone' => '0900000001',
                'start_date' => '2030-07-10',
                'end_date' => '2030-07-12',
                'deposit_total' => 100000,
                'discount_total' => 0,
                'status' => 'pending',
            ]);
        };

        $mk(['total_price' => 300000, 'utm_source' => 'facebook']);
        $mk(['total_price' => 200000, 'discount_total' => 50000, 'utm_source' => 'facebook']);
        $mk(['total_price' => 100000, 'click_id_type' => 'gclid']);
        $mk(['total_price' => 80000, 'referrer_host' => 'google.com']);
        $mk(['total_price' => 50000]);
        // Bị loại: đơn huỷ, đơn CHA, đơn quá 30 ngày.
        $mk(['total_price' => 999000, 'utm_source' => 'facebook', 'status' => 'cancelled']);
        $mk(['total_price' => 999000, 'utm_source' => 'facebook', 'is_parent' => true]);
        $old = $mk(['total_price' => 999000, 'utm_source' => 'facebook']);
        $old->forceFill(['created_at' => now()->subDays(31)])->save();

        $this->actingAs($this->admin())->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->has('source_stats', 4)
                ->where('source_stats.0', ['source' => 'facebook', 'orders' => 2, 'revenue' => 450000])
                ->where('source_stats', fn ($rows) => collect($rows)->pluck('revenue', 'source')->all() === [
                    'facebook' => 450000,
                    'google (ads)' => 100000,
                    'google.com' => 80000,
                    'Trực tiếp / không rõ' => 50000,
                ])
            );
    }

    /** @test */
    public function tracking_ids_written_outside_the_admin_form_are_rechecked_before_printing(): void
    {
        // Ghi thẳng DB (seeder/tinker) — bỏ qua validate của controller.
        SiteSetting::current()->forceFill([
            'ga_measurement_id' => "G-X');alert(1)//",
            'meta_pixel_id' => "1');alert(1)//",
            'tiktok_pixel_id' => "C4ABC123\n",
        ])->save();

        $html = $this->get('/')->getContent();

        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertStringNotContainsString('fbevents.js', $html);
        $this->assertStringNotContainsString('analytics.tiktok.com', $html);
        $this->assertStringNotContainsString('gtag/js', $html);
    }

    /** @test */
    public function unreadable_attribution_cookie_is_replaced(): void
    {
        // Cookie không giải mã/không đọc được (vd sau khi đổi APP_KEY) không được chặn ghi mới.
        $this->withUnencryptedCookie(AttributionService::COOKIE, 'rac-khong-phai-json')
            ->get('/?utm_source=google')
            ->assertCookie(AttributionService::COOKIE);
    }

    /** @test */
    public function purchase_value_of_split_order_is_the_parent_rental_total(): void
    {
        $p = $this->product();

        $this->post(route('order.store'), [
            'name' => 'Khách A',
            'phone' => '0912345678',
            'items' => [
                ['product_id' => $p->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-02'],
                ['product_id' => $p->id, 'quantity' => 1, 'start' => '2030-08-01', 'end' => '2030-08-03'],
            ],
        ])->assertSessionHas('order_code');

        $parent = Order::where('is_parent', true)->firstOrFail();
        $children = Order::where('is_parent', false)->get();
        $this->assertCount(2, $children);
        // (2 + 3 ngày) × 100k — cọc không tính; bằng Σ tiền thuê của các đơn con.
        $this->assertSame(500000, session('order_value'));
        $this->assertSame($children->sum(fn (Order $c) => $c->rental_due), session('order_value'));
        $this->assertSame($parent->code, session('order_code'));
    }

    /** @test */
    public function dashboard_source_value_includes_extra_fee_like_finance_revenue(): void
    {
        Order::create([
            'customer_name' => 'Khách',
            'customer_phone' => '0900000001',
            'start_date' => '2030-07-10',
            'end_date' => '2030-07-12',
            'total_price' => 300000,
            'extra_fee' => 30000,
            'discount_total' => 10000,
            'deposit_total' => 100000,
            'status' => 'pending',
            'utm_source' => 'facebook',
        ]);

        $this->actingAs($this->admin())->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('source_stats.0', ['source' => 'facebook', 'orders' => 1, 'revenue' => 320000])
            );
    }
}

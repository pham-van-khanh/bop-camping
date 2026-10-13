<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\OrderLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * SĐT khách không bao giờ nằm trên URL trang tra cứu.
 *
 * Trang khách tải GA4 / GTM / Meta Pixel / TikTok Pixel, cả bốn gửi nguyên URL (gồm query)
 * ở mỗi lượt xem trang. Trước khi sửa, form tra cứu và link "Theo dõi đơn này" sau checkout
 * mở /tra-cuu?code=…&phone=… → SĐT đi thẳng sang Google/Meta/TikTok, trái với mục 5 chính
 * sách bảo mật ("không nhận số điện thoại").
 */
class OrderLookupPhoneOffUrlTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '0912345678';

    private function makeOrder(): Order
    {
        return Order::create([
            'code' => 'BOP-ABC123',
            'customer_name' => 'Khách',
            'customer_phone' => self::PHONE,
            'start_date' => '2030-07-01',
            'end_date' => '2030-07-03',
            'total_price' => 300000,
            'deposit_total' => 200000,
            'status' => 'confirmed',
            'payment_method' => 'cod',
        ]);
    }

    /** @test */
    public function lookup_url_with_phone_redirects_to_the_same_url_without_it(): void
    {
        $res = $this->get('/tra-cuu?code=BOP-ABC123&phone='.self::PHONE);

        $res->assertRedirect(route('lookup', ['code' => 'BOP-ABC123']));
        $this->assertStringNotContainsString(self::PHONE, (string) $res->headers->get('Location'));
        $res->assertSessionHas(OrderLookupService::PHONE_SESSION_KEY, self::PHONE);
    }

    /** @test */
    public function the_redirected_page_finds_the_order_and_its_url_has_no_phone(): void
    {
        $this->makeOrder();

        $this->followingRedirects()
            ->get('/tra-cuu?code=BOP-ABC123&phone='.self::PHONE)
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('OrderLookup')
                ->url('/tra-cuu?code=BOP-ABC123')
                ->where('order.code', 'BOP-ABC123')
                ->where('query.phone', self::PHONE));
    }

    /** @test */
    public function reloading_the_clean_url_keeps_the_result(): void
    {
        $this->makeOrder();
        $this->get('/tra-cuu?code=BOP-ABC123&phone='.self::PHONE);

        $this->get('/tra-cuu?code=BOP-ABC123')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('order.code', 'BOP-ABC123'));
    }

    /** @test */
    public function code_alone_without_a_remembered_phone_finds_nothing(): void
    {
        $this->makeOrder();

        $this->get('/tra-cuu?code=BOP-ABC123')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('order', null)
                ->where('not_found', false));
    }

    /** @test */
    public function account_lookup_also_moves_the_phone_off_the_url(): void
    {
        $user = User::factory()->create(['phone' => self::PHONE]);
        $order = $this->makeOrder();
        $order->update(['user_id' => $user->id]);

        $res = $this->actingAs($user)->get('/tai-khoan?code=BOP-ABC123&phone='.self::PHONE);
        $res->assertRedirect(route('account', ['code' => 'BOP-ABC123']));

        $this->actingAs($user)->get('/tai-khoan?code=BOP-ABC123')
            ->assertInertia(fn (Assert $p) => $p
                ->url('/tai-khoan?code=BOP-ABC123')
                ->where('lookup.order.code', 'BOP-ABC123'));
    }

    /** @test */
    public function malformed_phone_param_does_not_crash(): void
    {
        $this->followingRedirects()
            ->get('/tra-cuu?code=BOP-ABC123&phone[]=x')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->url('/tra-cuu?code=BOP-ABC123'));
    }
}

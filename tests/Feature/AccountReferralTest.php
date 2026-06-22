<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountReferralTest extends TestCase
{
    use RefreshDatabase;

    // ---- Trang tài khoản -----------------------------------------------------

    /** @test */
    public function guests_cannot_view_account(): void
    {
        $this->get(route('account'))->assertRedirect();
    }

    /** @test */
    public function account_shows_completed_product_count_and_active_orders(): void
    {
        $user = User::create(['name' => 'Khách A', 'phone' => '0900000001']);
        $product = $this->makeProduct();

        // Đơn đã hoàn thành (returned): 3 sản phẩm
        $done = $this->makeOrder($user, '2026-05-01', '2026-05-03', 'returned');
        $this->addItem($done, $product, 3);

        // Đơn đang thuê (renting): 2 sản phẩm
        $active = $this->makeOrder($user, '2026-07-01', '2026-07-05', 'renting');
        $this->addItem($active, $product, 2);

        // Đơn đã huỷ — KHÔNG tính vào "đã hoàn thành"
        $cancelled = $this->makeOrder($user, '2026-04-01', '2026-04-02', 'cancelled');
        $this->addItem($cancelled, $product, 9);

        $this->actingAs($user)
            ->get(route('account'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account')
                ->where('stats.completedProductCount', 3)
                ->where('stats.activeOrderCount', 1)
                ->has('activeOrders', 1)
                ->where('activeOrders.0.code', $active->code)
                ->where('referralCode', $user->referral_code));
    }

    // ---- Mã giới thiệu -------------------------------------------------------

    /** @test */
    public function new_user_gets_a_referral_code(): void
    {
        $user = User::create(['name' => 'Khách B', 'phone' => '0900000002']);

        $this->assertNotNull($user->referral_code);
        $this->assertSame(6, strlen($user->referral_code));
    }

    /** @test */
    public function signup_with_a_referral_code_sets_referred_by(): void
    {
        $referrer = User::create(['name' => 'Người mời', 'phone' => '0900000003']);

        $this->post(route('guest.login'), [
            'name' => 'Khách Mới',
            'phone' => '0911111111',
            'ref' => strtolower($referrer->referral_code), // case-insensitive
        ]);

        $newUser = User::where('phone', '0911111111')->first();
        $this->assertNotNull($newUser);
        $this->assertSame($referrer->id, $newUser->referred_by);
    }

    /** @test */
    public function referral_code_from_link_is_captured_via_session(): void
    {
        $referrer = User::create(['name' => 'Người mời', 'phone' => '0900000004']);

        // Khách vào site qua link giới thiệu ?ref=CODE (middleware lưu vào session)
        $this->get('/?ref='.$referrer->referral_code);

        // Sau đó đăng nhập KHÔNG kèm ref trong form → vẫn bắt được từ session
        $this->post(route('guest.login'), [
            'name' => 'Khách Link',
            'phone' => '0911111113',
        ]);

        $this->assertSame($referrer->id, User::where('phone', '0911111113')->value('referred_by'));
    }

    /** @test */
    public function invalid_referral_code_is_ignored_at_signup(): void
    {
        $this->post(route('guest.login'), [
            'name' => 'Khách Mới',
            'phone' => '0911111112',
            'ref' => 'KHONGCO',
        ]);

        $this->assertNull(User::where('phone', '0911111112')->value('referred_by'));
    }

    // ---- Thưởng giới thiệu ---------------------------------------------------

    /** @test */
    public function returning_first_order_rewards_both_referrer_and_referee(): void
    {
        [$referrer, $referee] = $this->makeReferralPair();

        $order = $this->makeOrder($referee, '2026-06-01', '2026-06-03', 'renting');
        $order->update(['status' => 'returned']);

        $amount = (int) config('referral.reward_amount');

        $this->assertDatabaseHas('vouchers', [
            'user_id' => $referee->id, 'source' => 'referral_referee', 'amount' => $amount,
        ]);
        $this->assertDatabaseHas('vouchers', [
            'user_id' => $referrer->id, 'source' => 'referral_referrer', 'amount' => $amount,
        ]);
        $this->assertSame(2, Voucher::count());
    }

    /** @test */
    public function reward_is_not_granted_twice(): void
    {
        [, $referee] = $this->makeReferralPair();

        $first = $this->makeOrder($referee, '2026-06-01', '2026-06-03', 'renting');
        $first->update(['status' => 'returned']);

        // Đơn returned thứ hai — không cấp thêm
        $second = $this->makeOrder($referee, '2026-06-10', '2026-06-12', 'renting');
        $second->update(['status' => 'returned']);

        $this->assertSame(2, Voucher::count());
    }

    /** @test */
    public function no_reward_when_user_has_no_referrer(): void
    {
        $user = User::create(['name' => 'Khách lẻ', 'phone' => '0900000009']);

        $order = $this->makeOrder($user, '2026-06-01', '2026-06-03', 'renting');
        $order->update(['status' => 'returned']);

        $this->assertSame(0, Voucher::count());
    }

    // ---- Redeem voucher ở checkout ------------------------------------------

    /** @test */
    public function voucher_is_redeemed_at_checkout_and_reduces_total(): void
    {
        $user = User::create(['name' => 'Khách C', 'phone' => '0900000010']);
        $product = $this->makeProduct(); // 100.000đ/ngày

        $voucher = Voucher::create([
            'user_id' => $user->id,
            'code' => 'VC-TEST01',
            'amount' => 50000,
            'source' => 'referral_referee',
        ]);

        $start = Carbon::today()->addDays(3)->toDateString();
        $end = Carbon::today()->addDays(3)->toDateString(); // 1 ngày → 100.000đ

        $this->actingAs($user)->post(route('order.store'), [
            'name' => 'Khách C',
            'phone' => '0900000010',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'start' => $start,
                'end' => $end,
            ]],
            'voucher_code' => 'vc-test01', // case-insensitive
        ])->assertSessionHas('order_pay', 50000); // 100.000 − 50.000

        $order = Order::latest('id')->first();
        $this->assertSame(50000, (int) $order->discount_total);

        $voucher->refresh();
        $this->assertNotNull($voucher->redeemed_at);
        $this->assertSame($order->id, $voucher->order_id);
    }

    // ---- Helpers -------------------------------------------------------------

    /** @return array{0: User, 1: User} [referrer, referee] */
    private function makeReferralPair(): array
    {
        $referrer = User::create(['name' => 'Người mời', 'phone' => '0900000020']);
        $referee = User::create([
            'name' => 'Khách được mời', 'phone' => '0900000021', 'referred_by' => $referrer->id,
        ]);

        return [$referrer, $referee];
    }

    private function makeProduct(): Product
    {
        $category = Category::create(['name' => 'Test', 'slug' => 'test-'.uniqid()]);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Lều Test',
            'slug' => 'leu-'.uniqid(),
            'price_per_day' => 100000,
            'quantity' => 10,
        ]);
    }

    private function makeOrder(User $user, string $start, string $end, string $status): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'customer_name' => $user->name,
            'customer_phone' => $user->phone,
            'start_date' => $start,
            'end_date' => $end,
            'status' => $status,
            'payment_method' => 'cod',
        ]);
    }

    private function addItem(Order $order, Product $product, int $quantity): void
    {
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'price_per_day' => $product->price_per_day,
            'days' => 1,
            'subtotal' => $quantity * $product->price_per_day,
        ]);
    }
}

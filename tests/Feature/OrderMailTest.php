<?php

namespace Tests\Feature;

use App\Mail\NewOrderAdminMail;
use App\Mail\OrderPlacedMail;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * bopcamping-d1l — mail xác nhận đặt đơn thành công.
 */
class OrderMailTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function logged_in_user_with_real_email_receives_confirmation(): void
    {
        Mail::fake();
        $user = User::factory()->create([
            'phone' => '0900000001', 'email' => 'khach@example.com', 'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->post(route('order.store'), $this->payload($user->phone))
            ->assertSessionHas('order_code');

        $order = $user->orders()->first();
        $this->assertSame('khach@example.com', $order->customer_email);
        Mail::assertSent(OrderPlacedMail::class, fn (OrderPlacedMail $m) => $m->hasTo('khach@example.com') && $m->order->is($order));
    }

    /** @test */
    public function guest_order_sends_no_mail_and_has_no_email(): void
    {
        Mail::fake();

        $this->post(route('order.store'), $this->payload('0911112222'))
            ->assertSessionHas('order_code');

        $this->assertNull(Order::first()->customer_email);
        Mail::assertNothingSent();
    }

    /** @test */
    public function placeholder_local_email_is_not_mailed(): void
    {
        Mail::fake();
        // User tạo nhanh bằng SĐT → email tạm <phone>@bopcamping.local, chưa verify.
        $user = User::create(['name' => 'Khách Cũ', 'phone' => '0900000002']);
        $this->assertStringEndsWith('@bopcamping.local', $user->email);

        $this->actingAs($user)->post(route('order.store'), $this->payload($user->phone))
            ->assertSessionHas('order_code');

        $this->assertNull($user->orders()->first()->customer_email);
        Mail::assertNothingSent();
    }

    /** @test */
    public function new_order_emails_admins_who_set_a_real_email(): void
    {
        Mail::fake();
        User::factory()->create(['is_admin' => true, 'phone' => '0900000010', 'email' => 'qtv@shop.vn']);
        // Admin chỉ có email tạm .local → không nhận.
        User::create(['name' => 'Admin Cũ', 'phone' => '0900000011', 'is_admin' => true]);

        $this->post(route('order.store'), $this->payload('0911112222'))->assertSessionHas('order_code');

        Mail::assertSent(NewOrderAdminMail::class, fn (NewOrderAdminMail $m) => $m->hasTo('qtv@shop.vn'));
        Mail::assertNotSent(NewOrderAdminMail::class, fn (NewOrderAdminMail $m) => $m->hasTo('0900000011@bopcamping.local'));
    }

    /** @test */
    public function no_admin_with_real_email_means_no_admin_mail(): void
    {
        Mail::fake();
        User::create(['name' => 'Admin Cũ', 'phone' => '0900000011', 'is_admin' => true]); // email .local

        $this->post(route('order.store'), $this->payload('0911112222'))->assertSessionHas('order_code');

        Mail::assertNotSent(NewOrderAdminMail::class);
    }

    private function payload(string $phone): array
    {
        $cat = Category::create(['name' => 'Lều', 'slug' => 'leu-'.uniqid()]);
        $product = Product::create([
            'category_id' => $cat->id,
            'name' => 'Lều Test',
            'slug' => 'leu-test-'.uniqid(),
            'price_per_day' => 100000,
            'quantity' => 5,
            'deposit' => 200000,
            'status' => 'active',
        ]);
        $day = Carbon::today()->addDays(5)->toDateString();

        return [
            'name' => 'Khách Đặt',
            'phone' => $phone,
            'address' => 'Số 1 ABC',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'start' => $day, 'end' => $day]],
        ];
    }
}

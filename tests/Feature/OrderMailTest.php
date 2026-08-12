<?php

namespace Tests\Feature;

use App\Mail\NewOrderAdminMail;
use App\Mail\OrderPickupReminderMail;
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
        Mail::assertQueued(OrderPlacedMail::class, fn (OrderPlacedMail $m) => $m->hasTo('khach@example.com') && $m->order->is($order));
    }

    /** @test */
    public function guest_order_sends_no_mail_and_has_no_email(): void
    {
        Mail::fake();

        $this->post(route('order.store'), $this->payload('0911112222'))
            ->assertSessionHas('order_code');

        $this->assertNull(Order::first()->customer_email);
        Mail::assertNothingOutgoing();
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
        Mail::assertNothingOutgoing();
    }

    /** @test */
    public function new_order_emails_admins_who_set_a_real_email(): void
    {
        Mail::fake();
        User::factory()->create(['is_admin' => true, 'phone' => '0900000010', 'email' => 'qtv@shop.vn']);
        // Admin chỉ có email tạm .local → không nhận.
        User::create(['name' => 'Admin Cũ', 'phone' => '0900000011', 'is_admin' => true]);

        $this->post(route('order.store'), $this->payload('0911112222'))->assertSessionHas('order_code');

        Mail::assertQueued(NewOrderAdminMail::class, fn (NewOrderAdminMail $m) => $m->hasTo('qtv@shop.vn'));
        Mail::assertNotQueued(NewOrderAdminMail::class, fn (NewOrderAdminMail $m) => $m->hasTo('0900000011@bopcamping.local'));
    }

    /** @test */
    public function no_admin_with_real_email_means_no_admin_mail(): void
    {
        Mail::fake();
        User::create(['name' => 'Admin Cũ', 'phone' => '0900000011', 'is_admin' => true]); // email .local

        $this->post(route('order.store'), $this->payload('0911112222'))->assertSessionHas('order_code');

        Mail::assertNotQueued(NewOrderAdminMail::class);
    }

    /** bopcamping-kpf — email nhập tay ở checkout. */

    /** @test */
    public function guest_typed_email_receives_confirmation(): void
    {
        Mail::fake();

        $this->post(route('order.store'), $this->payload('0911112222', email: 'vanglai@example.com'))
            ->assertSessionHas('order_code');

        $this->assertSame('vanglai@example.com', Order::first()->customer_email);
        Mail::assertQueued(OrderPlacedMail::class, fn (OrderPlacedMail $m) => $m->hasTo('vanglai@example.com'));
    }

    /** @test */
    public function typed_email_overrides_verified_account_email(): void
    {
        Mail::fake();
        $user = User::factory()->create([
            'phone' => '0900000003', 'email' => 'taikhoan@example.com', 'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->post(route('order.store'), $this->payload($user->phone, email: 'nhaptay@example.com'))
            ->assertSessionHas('order_code');

        $this->assertSame('nhaptay@example.com', $user->orders()->first()->customer_email);
        Mail::assertQueued(OrderPlacedMail::class, fn (OrderPlacedMail $m) => $m->hasTo('nhaptay@example.com'));
        Mail::assertNotQueued(OrderPlacedMail::class, fn (OrderPlacedMail $m) => $m->hasTo('taikhoan@example.com'));
    }

    /** @test */
    public function cleared_email_falls_back_to_verified_account_email(): void
    {
        Mail::fake();
        $user = User::factory()->create([
            'phone' => '0900000004', 'email' => 'taikhoan2@example.com', 'email_verified_at' => now(),
        ]);

        // FE prefill sẵn email tài khoản; khách xoá trống → '' → null → dùng lại email tài khoản
        // (đúng copy "Bỏ trống sẽ dùng email tài khoản của bạn").
        $this->actingAs($user)->post(route('order.store'), $this->payload($user->phone, email: ''))
            ->assertSessionHas('order_code');

        $this->assertSame('taikhoan2@example.com', $user->orders()->first()->customer_email);
        Mail::assertQueued(OrderPlacedMail::class, fn (OrderPlacedMail $m) => $m->hasTo('taikhoan2@example.com'));
    }

    private function payload(string $phone, ?string $email = null): array
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
            'email' => $email,
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'start' => $day, 'end' => $day]],
        ];
    }

    /**
     * Mail phải TÁCH tiền thuê và tiền cọc thành 2 dòng, kèm tổng (bopcamping-944h).
     *
     * Cọc được hoàn lại khi trả đồ nguyên vẹn, tiền thuê thì không — gộp một con số
     * khiến khách tưởng mất hết ngần ấy. Đơn là COD nên còn phải nói rõ tổng cầm theo.
     *
     * @test
     */
    public function customer_mail_separates_rental_from_deposit_and_shows_the_total(): void
    {
        $this->post(route('order.store'), $this->payload('0911112222'))->assertSessionHas('order_code');
        $order = Order::first();

        $html = (new OrderPlacedMail($order))->render();

        $this->assertStringContainsString('Tiền thuê', $html);
        $this->assertStringContainsString('Tiền cọc', $html);
        $this->assertStringContainsString('Tổng cầm khi nhận đồ', $html);

        $vnd = fn (int $n) => number_format($n, 0, ',', '.').'đ';
        $this->assertStringContainsString($vnd($order->deposit_total), $html);
        $this->assertStringContainsString($vnd($order->amount_due), $html);
        // Tổng phải đúng bằng thuê + cọc, không phải một trong hai.
        $this->assertSame($order->rental_due + $order->deposit_total, $order->amount_due);
    }

    /**
     * Admin cũng cần tách: một bên là doanh thu, một bên là tiền giữ hộ phải hoàn lại.
     * Trước đây mail admin chỉ in mỗi amount_due gộp.
     *
     * @test
     */
    public function admin_mail_breaks_down_rental_and_deposit(): void
    {
        $this->post(route('order.store'), $this->payload('0911112222'))->assertSessionHas('order_code');
        $order = Order::first();
        // Thêm phí phát sinh để rental_due KHÁC total_price. Không có bước này thì hai số
        // bằng nhau, mà total_price vốn đã in ở bảng món — assertion sẽ đậu kể cả khi
        // khối tổng bị gộp lại như bản cũ (đã đo: kiểm ngược không đỏ).
        $order->update(['extra_fee' => 50000, 'extra_fee_note' => 'Phí giao']);
        $order->refresh();

        $html = (new NewOrderAdminMail($order))->render();
        $vnd = fn (int $n) => number_format($n, 0, ',', '.').'đ';

        $this->assertStringContainsString('Tiền thuê', $html);
        $this->assertStringContainsString('Tiền cọc', $html);
        $this->assertNotSame($order->total_price, $order->rental_due);
        $this->assertStringContainsString($vnd($order->rental_due), $html);
        $this->assertStringContainsString($vnd($order->deposit_total), $html);
        $this->assertStringContainsString($vnd($order->amount_due), $html);
    }

    /**
     * Phí phát sinh (admin nhập sau khi gọi xác nhận) phải vào tiền thuê, nếu không
     * mail nhắc lịch báo thiếu so với số thu thật lúc giao (bopcamping-944h).
     *
     * @test
     */
    public function extra_fee_is_included_in_the_rental_amount_shown(): void
    {
        $this->post(route('order.store'), $this->payload('0911112222'))->assertSessionHas('order_code');
        $order = Order::first();
        $order->update(['extra_fee' => 50000, 'extra_fee_note' => 'Phí giao Vinh']);
        $order->refresh();

        $vnd = fn (int $n) => number_format($n, 0, ',', '.').'đ';

        $reminder = (new OrderPickupReminderMail($order))->render();
        $this->assertStringContainsString($vnd($order->rental_due), $reminder);
        $this->assertStringContainsString($vnd($order->amount_due), $reminder);
        // rental_due đã gồm phí -> KHÔNG được in total_price thô.
        $this->assertNotSame($order->total_price, $order->rental_due);

        // Mail khách nêu rõ khoản phí đó tên gì, không giấu vào một con số.
        $placed = (new OrderPlacedMail($order))->render();
        $this->assertStringContainsString('Phí giao Vinh', $placed);
    }
}

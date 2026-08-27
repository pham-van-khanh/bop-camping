<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-z7w — luồng đặt thuê COD + kiểm trùng lịch & tồn kho.
 * Dùng ngày 2030 để luôn thoả after_or_equal:today bất kể đồng hồ máy.
 */
class OrderCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function product(int $qty = 3, int $price = 100000, int $deposit = 200000): Product
    {
        $cat = Category::create(['name' => 'Lều', 'slug' => 'leu']);

        return Product::create([
            'category_id' => $cat->id,
            'name' => 'Lều',
            'slug' => 'leu-'.uniqid(),
            'price_per_day' => $price,
            'quantity' => $qty,
            'deposit' => $deposit,
        ]);
    }

    private function bookedOrder(Product $p, string $start, string $end, int $qty): Order
    {
        $order = Order::create([
            'code' => 'BOP-'.strtoupper(uniqid()),
            'customer_name' => 'X',
            'customer_phone' => '0900000000',
            'start_date' => $start,
            'end_date' => $end,
            'status' => 'confirmed',
            'payment_method' => 'cod',
        ]);
        $order->items()->create([
            'product_id' => $p->id,
            'quantity' => $qty,
            'price_per_day' => $p->price_per_day,
            'days' => 1,
            'subtotal' => $qty * $p->price_per_day,
        ]);

        return $order;
    }

    /** @test */
    public function creates_cod_order_with_items_and_totals(): void
    {
        $p = $this->product();

        $this->post(route('order.store'), [
            'name' => 'Khách A',
            'phone' => '0912345678',
            'items' => [['product_id' => $p->id, 'quantity' => 2, 'start' => '2030-07-01', 'end' => '2030-07-03']],
        ])->assertSessionHas('order_code');

        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertSame('pending', $order->status);
        $this->assertSame('cod', $order->payment_method);
        // 2 bộ × 3 ngày × 100000 = 600000 ; cọc 2 × 200000 = 400000
        $this->assertSame(600000, $order->total_price);
        $this->assertSame(400000, $order->deposit_total);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $p->id,
            'quantity' => 2,
            'days' => 3,
        ]);
    }

    /**
     * @test
     *
     * QUY TẮC NGHIỆP VỤ: cọc cố định theo từng lần thuê — KHÔNG nhân theo số ngày.
     * Chỉ tiền thuê tăng theo ngày. Giữ mọi thứ khác cố định, chỉ đổi số ngày.
     */
    public function deposit_is_fixed_per_rental_and_never_scales_with_days(): void
    {
        $p = $this->product(qty: 5, price: 100000, deposit: 200000);

        // Khoảng ngày KHÔNG chồng nhau để không vướng kiểm tồn kho; chỉ số ngày thay đổi.
        $cases = [
            ['2030-07-01', '2030-07-01', 1],
            ['2030-08-01', '2030-08-03', 3],
            ['2030-09-01', '2030-09-07', 7],
        ];

        foreach ($cases as [$start, $end, $days]) {
            $this->post(route('order.store'), [
                'name' => 'Khách A',
                'phone' => '0912345678',
                'items' => [['product_id' => $p->id, 'quantity' => 2, 'start' => $start, 'end' => $end]],
            ])->assertSessionHas('order_code');

            $order = Order::latest('id')->first();

            // Cọc = 200000 × 2 cái, KHÔNG × số ngày — bằng nhau ở mọi độ dài thuê.
            $this->assertSame(400000, $order->deposit_total, "cọc sai khi thuê $days ngày");
            // Đối chứng: tiền thuê thì có nhân ngày.
            $this->assertSame(100000 * 2 * $days, $order->total_price, "tiền thuê sai khi thuê $days ngày");
        }
    }

    /**
     * @test
     *
     * Đổi ngày thuê (admin dời lịch) làm tiền thuê đổi theo, nhưng cọc phải GIỮ NGUYÊN.
     */
    public function changing_rental_dates_does_not_change_deposit(): void
    {
        $p = $this->product(qty: 5, price: 100000, deposit: 200000);

        $this->post(route('order.store'), [
            'name' => 'Khách A',
            'phone' => '0912345678',
            'items' => [['product_id' => $p->id, 'quantity' => 2, 'start' => '2030-07-01', 'end' => '2030-07-02']],
        ])->assertSessionHas('order_code');

        $order = Order::firstOrFail();
        $depositBefore = $order->deposit_total;
        $this->assertSame(400000, $depositBefore);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->patch(route('admin.orders.dates', $order), [
            'start_date' => '2030-07-01',
            'end_date' => '2030-07-06', // 2 ngày → 6 ngày
        ])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame($depositBefore, $order->deposit_total, 'cọc không được đổi khi dời lịch');
        $this->assertSame(100000 * 2 * 6, $order->total_price, 'tiền thuê phải tính lại theo ngày mới');
    }

    /** @test */
    public function rejects_order_exceeding_available_stock_on_overlap(): void
    {
        $p = $this->product(qty: 3);
        $this->bookedOrder($p, '2030-07-02', '2030-07-05', qty: 2); // còn 1 trong khoảng chồng lịch

        $this->post(route('order.store'), [
            'name' => 'Khách',
            'phone' => '0912345678',
            'items' => [['product_id' => $p->id, 'quantity' => 2, 'start' => '2030-07-01', 'end' => '2030-07-03']],
        ])->assertSessionHasErrors('items');

        $this->assertSame(1, Order::count()); // chỉ còn đơn cũ, không tạo đơn mới
    }

    /** @test */
    public function allows_order_when_dates_do_not_overlap(): void
    {
        $p = $this->product(qty: 1);
        $this->bookedOrder($p, '2030-06-20', '2030-06-25', qty: 1); // trả trước, không chồng

        $this->post(route('order.store'), [
            'name' => 'Khách',
            'phone' => '0912345678',
            'items' => [['product_id' => $p->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-03']],
        ])->assertSessionHas('order_code');

        $this->assertSame(2, Order::count());
    }

    /** bopcamping-kpf — email tuỳ chọn ở checkout: khách vãng lai nhận mail xác nhận. */

    /** @test */
    public function guest_email_is_saved_on_order(): void
    {
        $p = $this->product();

        $this->post(route('order.store'), [
            'name' => 'Khách Email',
            'phone' => '0912345678',
            'email' => 'khach@gmail.com',
            'items' => [['product_id' => $p->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-02']],
        ])->assertSessionHas('order_code');

        $this->assertSame('khach@gmail.com', Order::first()->customer_email);
    }

    /** @test */
    public function invalid_email_is_rejected(): void
    {
        $p = $this->product();

        $this->post(route('order.store'), [
            'name' => 'Khách',
            'phone' => '0912345678',
            'email' => 'not-an-email',
            'items' => [['product_id' => $p->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-02']],
        ])->assertSessionHasErrors('email');

        $this->assertSame(0, Order::count());
    }

    /** @test */
    public function order_without_email_keeps_customer_email_null_for_guest(): void
    {
        $p = $this->product();

        $this->post(route('order.store'), [
            'name' => 'Khách',
            'phone' => '0912345678',
            'items' => [['product_id' => $p->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-02']],
        ])->assertSessionHas('order_code');

        $this->assertNull(Order::first()->customer_email);
    }

    /**
     * CHECKOUT KHÔNG BAO GIỜ ĐỔI EMAIL ĐĂNG NHẬP — kể cả tài khoản chỉ có SĐT.
     *
     * Bản cũ gắn email vào tài khoản chỉ-có-SĐT (bopcamping-kuhg), gỡ 27/08/2026. Chốt duy nhất
     * của nó là hasPlaceholderEmail(), tức chỉ che cho người ĐÃ có email thật; người CHƯA có —
     * đúng nhóm tính năng sinh ra để phục vụ — thì không được che gì: bác khách chỉ có SĐT đặt
     * đồ hộ đứa cháu, điền email của cháu để nó nhận mail, thế là email đăng nhập của bác thành
     * email đứa cháu và lần sau mã bay vào hộp thư của cháu.
     *
     * Gốc rễ: đổi danh tính đăng nhập không được là tác dụng phụ của việc đặt đơn. Chỗ đúng để
     * gắn email là luồng đăng nhập ("Email (bắt buộc)" + OTP), nơi khách chủ động làm việc đó và
     * có OTP chứng minh họ mở được hộp thư — xem OtpFlowTest::a_phone_only_account_can_attach_an_email_at_login.
     *
     * @test
     */
    public function checkout_never_changes_the_account_email(): void
    {
        $p = $this->product();
        $buyer = User::create(['name' => 'Khách', 'phone' => '0912345678']);
        $placeholder = $buyer->email;
        $this->assertTrue($buyer->hasPlaceholderEmail());

        $this->actingAs($buyer)->post(route('order.store'), [
            'name' => 'Khách',
            'phone' => '0912345678',
            'email' => 'chau-toi@gmail.com',
            'items' => [['product_id' => $p->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-02']],
        ])->assertSessionHas('order_code');

        $buyer->refresh();
        // Tài khoản không suy suyển: vẫn là tài khoản chỉ-có-SĐT, vẫn vào được bằng luồng cũ.
        $this->assertSame($placeholder, $buyer->email);
        $this->assertTrue($buyer->hasPlaceholderEmail());
        // Nhưng đơn vẫn giữ email để gửi mail xác nhận cho đúng người.
        $this->assertSame('chau-toi@gmail.com', Order::first()->customer_email);
    }

    /** Email checkout trùng tài khoản KHÁC cũng không đụng gì tới tài khoản đang đăng nhập. @test */
    public function checkout_email_already_used_by_another_account_is_not_attached(): void
    {
        $p = $this->product();
        User::factory()->create(['phone' => '0900000009', 'email' => 'daco@gmail.com']);
        $buyer = User::create(['name' => 'Khách', 'phone' => '0912345678']);
        $placeholder = $buyer->email;

        $this->actingAs($buyer)->post(route('order.store'), [
            'name' => 'Khách',
            'phone' => '0912345678',
            'email' => 'daco@gmail.com',
            'items' => [['product_id' => $p->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-02']],
        ])->assertSessionHas('order_code');

        $this->assertSame($placeholder, $buyer->fresh()->email);
        $this->assertSame('daco@gmail.com', Order::first()->customer_email);
    }

    /**
     * Tài khoản ĐÃ có email thật thì email gõ ở checkout chỉ dùng cho đơn, KHÔNG đè lên tài
     * khoản — nếu không, đặt hộ người thân bằng email của họ là tự đá mình ra khỏi tài khoản.
     *
     * @test
     */
    public function checkout_email_does_not_overwrite_an_existing_real_account_email(): void
    {
        $p = $this->product();
        $buyer = User::factory()->create(['phone' => '0912345678', 'email' => 'toi@gmail.com']);

        $this->actingAs($buyer)->post(route('order.store'), [
            'name' => 'Khách',
            'phone' => '0912345678',
            'email' => 'nguoithan@gmail.com',
            'items' => [['product_id' => $p->id, 'quantity' => 1, 'start' => '2030-07-01', 'end' => '2030-07-02']],
        ])->assertSessionHas('order_code');

        $this->assertSame('toi@gmail.com', $buyer->fresh()->email);
        $this->assertSame('nguoithan@gmail.com', Order::first()->customer_email);
    }
}

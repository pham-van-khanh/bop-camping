<?php

namespace Tests\Feature;

use App\Mail\OrderPickupReminderMail;
use App\Mail\OrderStatusMail;
use App\Models\Order;
use App\Models\User;
use App\Services\PaymentQrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-55rh — QR chuyển khoản có sẵn số tiền + nội dung cho từng đơn.
 *
 * Tính năng này CHỈ sinh ảnh QR: không webhook, không tự đối soát, không viết một dòng
 * nào vào trạng thái tiền. Admin vẫn tự kiểm sao kê rồi bấm markPaid().
 *
 * bopcamping-pew1 — LUẬT HIỆN QR TÁCH ĐÔI theo người xem:
 *   - Khách: chỉ đơn 'pending'. Quy trình shop là khách chuyển tiền XONG mới xác nhận
 *     đơn, nên đơn đã xác nhận tức là đã trả — còn chìa QR là mời trả lần hai.
 *   - Admin: mọi trạng thái, miễn còn tiền chưa thu. Admin là người GỬI QR đi đòi tiền;
 *     ẩn theo luật khách thì admin chỉ thấy QR đúng lúc khách đã hết cần.
 */
class PaymentQrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.sepay.bank' => 'Vietcombank',
            'services.sepay.account' => 'QRPSEP1ZZZZ55400303',
            'services.sepay.holder' => 'HKD BOP CAMPING',
        ]);
    }

    private function qr(): PaymentQrService
    {
        return app(PaymentQrService::class);
    }

    /**
     * Mặc định 'pending' — đúng lúc khách nhìn thấy QR để chuyển khoản.
     *
     * @param  array<string, mixed>  $extra
     */
    private function order(array $extra = []): Order
    {
        return Order::factory()->create(array_merge([
            'total_price' => 500_000,
            'deposit_total' => 300_000,
            'status' => 'pending',
        ], $extra));
    }

    /** Tách query string của URL QR thành mảng để soi từng tham số. */
    private function params(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

        return $q;
    }

    /** @test */
    public function the_url_carries_the_configured_account_and_the_amount_due(): void
    {
        $order = $this->order(['total_price' => 500_000, 'deposit_total' => 300_000]);

        $url = $this->qr()->urlFor($order);

        $this->assertNotNull($url);
        $this->assertStringStartsWith('https://qr.sepay.vn/img?', $url);

        $p = $this->params($url);
        $this->assertSame('Vietcombank', $p['bank']);
        $this->assertSame('QRPSEP1ZZZZ55400303', $p['acc']);
        $this->assertSame('HKD BOP CAMPING', $p['holder']);
        $this->assertSame('800000', $p['amount']);   // 500k thuê + 300k cọc
    }

    /**
     * Số tiền trên QR phải là amount_due ĐẦY ĐỦ — gồm phụ phí, đã trừ giảm giá. Lấy mỗi
     * total_price là khách chuyển thiếu, mà thiếu thì admin phải đòi thêm lần nữa.
     *
     * @test
     */
    public function the_amount_follows_extra_fees_and_discounts(): void
    {
        $order = $this->order([
            'total_price' => 500_000,
            'extra_fee' => 40_000,
            'discount_total' => 50_000,
            'deposit_total' => 300_000,
        ]);

        $this->assertSame((string) $order->amount_due, $this->params($this->qr()->urlFor($order))['amount']);
        $this->assertSame('790000', $this->params($this->qr()->urlFor($order))['amount']);
    }

    /**
     * QR phải đòi số CÒN THIẾU, không phải tổng đơn (bopcamping-pew1).
     *
     * LỖI ĐÃ ĐO: hai khoản thu độc lập nên khách trả tiền thuê trước rồi còn nợ mỗi cọc
     * là chuyện thường. Bản đầu luôn ghi amount_due, nên đơn 540k đã thu 240k tiền thuê
     * vẫn hiện QR đòi đủ 540k — khách quét là chuyển THỪA đúng 240k.
     *
     * @test
     */
    public function the_qr_asks_only_for_what_is_still_owed(): void
    {
        $order = $this->order(['total_price' => 240_000, 'deposit_total' => 300_000]);
        $this->assertSame('540000', $this->params($this->qr()->urlFor($order))['amount']);

        // Đã thu tiền thuê → chỉ còn cọc.
        $order->markPaid('rental', true);
        $order->refresh();
        $this->assertSame('300000', $this->params($this->qr()->urlFor($order))['amount']);
        $this->assertSame(300_000, $this->qr()->payloadFor($order)['amount']);

        // Đổi lại: chỉ thu cọc → còn mỗi tiền thuê.
        $order->markPaid('rental', false);
        $order->markPaid('deposit', true);
        $order->refresh();
        $this->assertSame('240000', $this->params($this->qr()->urlFor($order))['amount']);
    }

    /**
     * LỖI ĐÃ ĐO trước khi lên production (bopcamping-r3fy) — đây là ca đắt nhất của cả
     * tính năng, nên khoá chặt.
     *
     * markPaid() chỉ ghi CỜ đã-thu chứ không ghi SỐ TIỀN. Admin bấm thu tiền thuê 500k rồi
     * mới nhập phí ship 50k → rental_due thành 550k nhưng hệ thống vẫn coi tiền thuê xong,
     * QR chỉ đòi 300k tiền cọc. Shop thu hụt đúng 50k, không dấu hiệu gì; còn khách thì
     * được báo "Tiền thuê 550.000đ — Shop đã nhận" trong khi shop mới nhận 500k.
     *
     * @test
     */
    public function a_price_change_after_collecting_still_gets_billed(): void
    {
        $order = $this->order(['total_price' => 500_000, 'deposit_total' => 300_000]);

        $order->markPaid('rental', true);
        $order->refresh();
        $this->assertSame(500_000, $order->rentalPaidAmount(), 'phải chụp lại SỐ TIỀN lúc bấm thu');
        $this->assertSame(300_000, $this->qr()->payloadFor($order)['amount']);

        // Admin gọi khách chốt ship rồi nhập phụ phí SAU khi đã đánh dấu thu.
        $order->update(['extra_fee' => 50_000]);
        $order->refresh();

        $this->assertSame(550_000, $order->rental_due);
        $this->assertSame(500_000, $order->rentalPaidAmount(), 'số đã thu KHÔNG được chạy theo giá mới');
        $this->assertSame(350_000, $order->outstanding_due, '300k cọc + 50k chênh tiền thuê');
        $this->assertSame(350_000, $this->qr()->payloadFor($order)['amount']);
    }

    /** Bỏ đánh dấu thu thì xoá luôn số tiền đã ghi — không để lại vết ma. */
    /** @test */
    public function unmarking_a_payment_clears_the_recorded_amount(): void
    {
        $order = $this->order();

        $order->markPaid('rental', true);
        $order->refresh();
        $this->assertNotNull($order->rental_paid_amount);

        $order->markPaid('rental', false);
        $order->refresh();
        $this->assertNull($order->rental_paid_amount);
        $this->assertSame(0, $order->rentalPaidAmount());
        $this->assertSame($order->amount_due, $order->outstanding_due);
    }

    /**
     * Đơn CŨ (trước migration) có mốc thu nhưng cột số tiền còn null — phải coi như đã thu
     * đủ phần đó, đúng bằng nghĩa cũ của cái cờ. Không thì mọi đơn cũ bỗng dưng thành nợ.
     *
     * @test
     */
    public function a_legacy_order_without_a_recorded_amount_counts_as_fully_collected(): void
    {
        $order = $this->order(['total_price' => 500_000, 'deposit_total' => 300_000]);
        $order->forceFill(['rental_paid_at' => now(), 'rental_paid_amount' => null])->save();
        $order->syncPaymentStatus();
        $order->refresh();

        $this->assertSame(500_000, $order->rentalPaidAmount());
        $this->assertSame(300_000, $order->outstanding_due);
    }

    /**
     * Giảm giá vượt tiền thuê làm rental_due âm. Kẹp max(0) ở TỔNG thì phần âm đó ăn lẹm
     * vào tiền cọc và QR đòi thiếu đúng bằng nó — phải kẹp TỪNG KHOẢN.
     *
     * @test
     */
    public function a_negative_rental_never_eats_into_the_deposit(): void
    {
        $order = $this->order([
            'total_price' => 100_000,
            'discount_total' => 150_000,
            'deposit_total' => 300_000,
        ]);

        $this->assertSame(-50_000, $order->rental_due);
        $this->assertSame(300_000, $order->outstanding_due, 'vẫn phải thu đủ tiền cọc');
    }

    /** Khối QR phải mang thông tin người nhận dạng CHỮ — ảnh hỏng thì khách vẫn chuyển được. */
    /** @test */
    public function the_payload_carries_the_account_details_as_text(): void
    {
        $p = $this->qr()->payloadFor($this->order());

        $this->assertSame('Vietcombank', $p['bank']);
        $this->assertSame('QRPSEP1ZZZZ55400303', $p['account']);
        $this->assertSame('HKD BOP CAMPING', $p['holder']);
    }

    /** Danh sách đơn admin KHÔNG dựng QR — chỉ màn chi tiết mới cần. */
    /** @test */
    public function the_admin_list_does_not_carry_a_dead_qr_payload(): void
    {
        $this->order();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.orders'))
            ->assertInertia(fn (Assert $p) => $p->where('orders.0.payment_qr', null));
    }

    /** Admin phải biết QR đang tắt vì CHƯA CẤU HÌNH, khác với tắt vì luật đơn. */
    /** @test */
    public function the_admin_screen_reports_whether_the_account_is_configured(): void
    {
        $order = $this->order();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.orders.show', $order))
            ->assertInertia(fn (Assert $p) => $p->where('payment_qr_configured', true));

        config(['services.sepay.account' => null]);

        $this->actingAs($admin)->get(route('admin.orders.show', $order))
            ->assertInertia(fn (Assert $p) => $p->where('payment_qr_configured', false));
    }

    /**
     * Mail không được bắt khách cầm lại số tiền họ vừa chuyển (bopcamping-r3fy).
     *
     * Quy trình mới: khách chuyển khoản xong shop mới xác nhận. Mail xác nhận đơn và mail
     * nhắc nhận đồ đều gửi cho đơn ĐÃ xác nhận, tức phần lớn người nhận đã trả xong.
     *
     * @test
     */
    public function the_emails_ask_only_for_what_is_still_owed(): void
    {
        $order = $this->order(['status' => 'confirmed', 'total_price' => 490_000, 'deposit_total' => 300_000]);
        $order->markPaid('rental', true);
        $order->markPaid('deposit', true);
        $order->refresh();

        $this->assertSame(0, $order->outstanding_due);

        $mails = [
            'nhắc nhận đồ' => new OrderPickupReminderMail($order),
            'xác nhận đơn' => new OrderStatusMail($order, 'confirmed'),
        ];

        foreach ($mails as $label => $mail) {
            $html = $mail->render();

            $this->assertStringNotContainsString('790.000đ', $html, "mail $label còn đòi lại tiền khách đã trả");
            $this->assertStringContainsString('đã thanh toán đủ', $html);
        }
    }

    /** Phụ phí và giảm giá vẫn phải theo đúng khoản còn thiếu. */
    /** @test */
    public function what_is_still_owed_keeps_following_extra_fees_and_discounts(): void
    {
        $order = $this->order([
            'total_price' => 500_000,
            'extra_fee' => 40_000,
            'discount_total' => 50_000,
            'deposit_total' => 300_000,
        ]);

        $order->markPaid('deposit', true);
        $order->refresh();

        // Còn mỗi tiền thuê: 500k + 40k − 50k = 490k.
        $this->assertSame(490_000, $order->outstanding_due);
        $this->assertSame('490000', $this->params($this->qr()->urlFor($order))['amount']);
    }

    /**
     * VietQR chỉ nhận chữ và số ở tham số des. Giữ dấu gạch thì tuỳ ngân hàng mà bị cắt
     * hoặc thay ký tự, nội dung về sao kê không còn dò được.
     *
     * @test
     */
    public function the_transfer_content_strips_the_hyphen_from_the_order_code(): void
    {
        $order = $this->order(['code' => 'BOP-1485E3']);

        $this->assertSame('BOP1485E3', $this->qr()->transferContentFor($order));
        $this->assertSame('BOP1485E3', $this->params($this->qr()->urlFor($order))['des']);
    }

    /** Đơn con (mã cha-i) cũng phải ra chuỗi sạch, không còn gạch nào. */
    /** @test */
    public function a_child_order_code_also_comes_out_alphanumeric(): void
    {
        $order = $this->order(['code' => 'BOP-1485E3-2']);

        $this->assertSame('BOP1485E32', $this->qr()->transferContentFor($order));
    }

    /** @test */
    public function the_download_flag_is_only_set_when_asked(): void
    {
        $order = $this->order();

        $this->assertArrayNotHasKey('download', $this->params($this->qr()->urlFor($order)));
        $this->assertSame('true', $this->params($this->qr()->urlFor($order, download: true))['download']);
    }

    /** Chưa khai báo tài khoản nhận tiền thì URL vô nghĩa — thà không có QR. */
    /** @test */
    public function there_is_no_qr_until_the_account_is_configured(): void
    {
        $order = $this->order();

        config(['services.sepay.account' => null]);
        $this->assertNull($this->qr()->urlFor($order));

        config(['services.sepay.account' => 'QRPSEP1ZZZZ55400303', 'services.sepay.bank' => null]);
        $this->assertNull($this->qr()->urlFor($order));
    }

    /**
     * LUẬT KHÁCH (bopcamping-pew1): QR sống đúng ở 'pending'.
     *
     * Shop chỉ xác nhận đơn sau khi tiền đã về, nên đơn 'confirmed' nghĩa là khách trả
     * xong rồi — còn chìa QR ra là mời khách chuyển lần thứ hai.
     *
     * @test
     */
    public function the_customer_only_sees_the_qr_while_the_order_is_pending(): void
    {
        $this->assertNotNull($this->qr()->urlFor($this->order(['status' => 'pending'])));

        foreach (['confirmed', 'renting', 'returned', 'cancelled'] as $status) {
            $this->assertNull(
                $this->qr()->urlFor($this->order(['status' => $status])),
                "đơn '$status' không được hiện QR cho khách",
            );
        }
    }

    /**
     * LUẬT ADMIN (bopcamping-pew1): admin vẫn thấy QR sau khi đơn đã xác nhận.
     *
     * Admin là người GỬI QR đi đòi tiền. Ẩn theo luật khách thì admin chỉ thấy QR đúng
     * lúc khách đã hết cần — và đơn nào lỡ xác nhận mà khách chưa trả sẽ không còn
     * đường nào lấy lại QR.
     *
     * @test
     */
    public function the_admin_still_gets_the_qr_after_the_order_is_confirmed(): void
    {
        foreach (['pending', 'confirmed', 'renting'] as $status) {
            $this->assertNotNull(
                $this->qr()->payloadFor($this->order(['status' => $status]), forAdmin: true),
                "admin phải thấy QR ở đơn '$status'",
            );
        }
    }

    /** Đơn đã huỷ thì không đòi tiền nữa — kể cả admin. */
    /** @test */
    public function a_cancelled_order_has_no_qr_for_anyone(): void
    {
        $cancelled = $this->order(['status' => 'cancelled']);

        $this->assertNull($this->qr()->payloadFor($cancelled));
        $this->assertNull($this->qr()->payloadFor($cancelled, forAdmin: true));
    }

    /** @test */
    public function there_is_no_qr_when_there_is_nothing_left_to_collect(): void
    {
        $order = $this->order(['total_price' => 0, 'deposit_total' => 0]);

        $this->assertSame(0, $order->amount_due);
        $this->assertNull($this->qr()->urlFor($order));
        $this->assertNull($this->qr()->payloadFor($order, forAdmin: true));
    }

    /**
     * Thu đủ rồi mà còn chìa QR là mời khách trả lần hai — đúng cho cả admin.
     *
     * @test
     */
    public function a_fully_paid_order_stops_showing_its_qr(): void
    {
        $order = $this->order();
        $this->assertNotNull($this->qr()->urlFor($order));

        $order->markPaid('rental', true);
        $this->assertNotNull($this->qr()->urlFor($order->fresh()), 'mới thu 1 trong 2 khoản thì vẫn còn phải trả');

        $order->markPaid('deposit', true);
        $this->assertNull($this->qr()->urlFor($order->fresh()));
        $this->assertNull($this->qr()->payloadFor($order->fresh(), forAdmin: true));
    }

    /**
     * Đơn CHA là vỏ chứa (tổng = Σ đơn con), tiền thu theo từng đơn con. Sinh QR cho cha
     * là đếm đôi số tiền của chính những đơn con đã có QR riêng.
     *
     * @test
     */
    public function a_parent_order_has_no_qr_of_its_own(): void
    {
        $parent = $this->order(['is_parent' => true]);

        $this->assertNull($this->qr()->payloadFor($parent));
        $this->assertNull($this->qr()->payloadFor($parent, forAdmin: true));
    }

    /** @test */
    public function the_admin_order_screen_receives_the_qr_and_a_download_link(): void
    {
        $order = $this->order(['status' => 'confirmed']);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.orders.show', $order))
            ->assertInertia(fn (Assert $p) => $p
                ->component('Admin/Orders/Show')
                ->where('order.payment_qr.content', $this->qr()->transferContentFor($order))
                ->where('order.payment_qr.amount', $order->amount_due)
                ->has('order.payment_qr.url')
                ->has('order.payment_qr.download_url'));
    }

    /** @test */
    public function the_lookup_page_shows_the_customer_their_qr(): void
    {
        $order = $this->order();

        $this->get(route('lookup', ['code' => $order->code, 'phone' => $order->customer_phone]))
            ->assertInertia(fn (Assert $p) => $p
                ->component('OrderLookup')
                ->where('order.payment_qr.content', $this->qr()->transferContentFor($order))
                ->has('order.payment_qr.url'));
    }

    /**
     * Khách KHÔNG được thấy nút tải ảnh — đó là công cụ của admin để gửi qua Zalo.
     *
     * @test
     */
    public function the_customer_never_gets_the_download_link(): void
    {
        $order = $this->order();

        $this->get(route('lookup', ['code' => $order->code, 'phone' => $order->customer_phone]))
            ->assertInertia(fn (Assert $p) => $p->missing('order.payment_qr.download_url'));
    }

    /** @test */
    public function a_confirmed_order_no_longer_shows_the_qr_on_the_lookup_page(): void
    {
        $order = $this->order(['status' => 'confirmed']);

        $this->get(route('lookup', ['code' => $order->code, 'phone' => $order->customer_phone]))
            ->assertInertia(fn (Assert $p) => $p->where('order.payment_qr', null));
    }

    /*
    |--------------------------------------------------------------------------
    | Tình trạng thu tiền hiện cho khách (bopcamping-pew1)
    |--------------------------------------------------------------------------
    | Trước đây rental_paid/deposit_paid KHÔNG hề ra tới khách, nên khách chuyển
    | khoản xong không có cách nào biết shop đã ghi nhận chưa — chỉ còn nước nhắn hỏi.
    */

    /** @test */
    public function the_lookup_page_tells_the_customer_which_amounts_have_landed(): void
    {
        $order = $this->order(['status' => 'confirmed']);
        $order->markPaid('rental', true);

        $this->get(route('lookup', ['code' => $order->code, 'phone' => $order->customer_phone]))
            ->assertInertia(fn (Assert $p) => $p
                ->where('order.rental_due', $order->rental_due)
                ->where('order.rental_paid', true)
                ->where('order.deposit_paid', false));
    }

    /** @test */
    public function the_account_page_tells_the_customer_which_amounts_have_landed(): void
    {
        $user = User::factory()->create(['phone' => '0911222333']);
        $order = $this->order(['status' => 'confirmed', 'user_id' => $user->id]);
        $order->markPaid('deposit', true);

        $this->actingAs($user)->get(route('account'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('orders.0.rental_paid', false)
                ->where('orders.0.deposit_paid', true)
                ->where('orders.0.rental_due', $order->rental_due));
    }

    /** Đơn chưa thu đồng nào thì cả hai khoản đều là chưa — không được vắng field. */
    /** @test */
    public function an_unpaid_order_reports_both_amounts_as_not_yet_received(): void
    {
        $order = $this->order();

        $this->get(route('lookup', ['code' => $order->code, 'phone' => $order->customer_phone]))
            ->assertInertia(fn (Assert $p) => $p
                ->where('order.rental_paid', false)
                ->where('order.deposit_paid', false));
    }
}

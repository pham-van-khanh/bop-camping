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
        $this->assertSame(350_000, $order->outstanding_due, '300k cọc + 50k phụ phí');

        // QR chỉ đòi 300k: tiền thuê đã trả rồi thì phụ phí KHÔNG đòi qua chuyển khoản nữa,
        // nó trừ vào cọc lúc hoàn (bopcamping-urqo).
        $this->assertSame(300_000, $order->transfer_due);
        $this->assertSame(300_000, $this->qr()->payloadFor($order)['amount']);
        $this->assertSame(50_000, $this->qr()->payloadFor($order)['fee_from_deposit']);
    }

    /*
    |--------------------------------------------------------------------------
    | Phụ phí là KHOẢN THU RIÊNG (bopcamping-urqo)
    |--------------------------------------------------------------------------
    | Trước đây phụ phí gộp vào tiền thuê nên chủ shop không biết khoản nào đã thu.
    */

    /** Đẳng thức nền: tách khoản không được làm đổi tổng. */
    /** @test */
    public function the_base_rental_plus_the_fee_always_equals_the_old_rental_total(): void
    {
        $order = $this->order(['total_price' => 500_000, 'extra_fee' => 40_000, 'discount_total' => 50_000]);

        $this->assertSame($order->rental_due, $order->base_rental_due + $order->fee_due);
        $this->assertSame(450_000, $order->base_rental_due);
        $this->assertSame(40_000, $order->fee_due);
    }

    /** Ba khoản thu độc lập — thu khoản này không đụng khoản kia. */
    /** @test */
    public function the_three_amounts_are_collected_independently(): void
    {
        $order = $this->order(['total_price' => 500_000, 'extra_fee' => 50_000, 'deposit_total' => 300_000]);

        $order->markPaid('fee', true);
        $order->refresh();

        $this->assertTrue($order->feePaid());
        $this->assertFalse($order->rentalPaid());
        $this->assertFalse($order->depositPaid());
        $this->assertSame(800_000, $order->outstanding_due, 'còn tiền thuê 500k + cọc 300k');
    }

    /**
     * ĐƠN CŨ KHÔNG BỊ GHI ĐÈ (bopcamping-urqo). Luật suy từ chính con số đã ghi, không dùng
     * mốc ngày hardcode — mốc ngày luôn mục nát sau vài lần deploy.
     *
     * @test
     */
    public function an_old_order_paid_under_the_old_meaning_is_not_falsely_shown_as_owing_the_fee(): void
    {
        $order = $this->order(['total_price' => 500_000, 'extra_fee' => 50_000, 'deposit_total' => 300_000]);

        // Nghĩa CŨ: "đã thu tiền thuê" = thu cả phụ phí, số ghi lại là rental_due (550k).
        $order->forceFill([
            'rental_paid_at' => now(),
            'rental_paid_amount' => $order->rental_due,
        ])->save();
        $order->refresh();

        $this->assertTrue($order->feePaid(), 'đơn cũ không được báo nợ phụ phí');
        $this->assertSame(300_000, $order->outstanding_due, 'chỉ còn cọc');
    }

    /** Chiều ngược lại: đơn MỚI thu tiền thuê gốc rồi thêm phụ phí thì vẫn còn nợ. */
    /** @test */
    public function a_new_order_that_gains_a_fee_after_collection_still_owes_it(): void
    {
        $order = $this->order(['total_price' => 500_000, 'deposit_total' => 300_000]);
        $order->markPaid('rental', true);
        $order->update(['extra_fee' => 50_000]);
        $order->refresh();

        $this->assertFalse($order->feePaid(), 'phụ phí thêm sau KHÔNG được coi là đã thu');
        $this->assertSame(50_000, $order->feeOutstanding());
    }

    /**
     * Hoàn cọc trừ phụ phí chưa thu, và tự đánh dấu khoản đó đã thu — tiền về tay shop qua
     * đường giữ lại, không có lý do gì để nó treo "chưa thu" mãi.
     *
     * @test
     */
    public function refunding_the_deposit_deducts_the_unpaid_fee(): void
    {
        $order = $this->order(['total_price' => 500_000, 'extra_fee' => 50_000, 'deposit_total' => 300_000]);
        $order->markPaid('rental', true);
        $order->markPaid('deposit', true);
        $order->refresh();

        $this->assertSame(250_000, $order->refund_due, '300k cọc − 50k phụ phí');

        $order->markRefunded(true, null);
        $order->refresh();

        $this->assertSame(250_000, $order->deposit_refund_amount, 'ghi sổ đúng số đã trả khách');
        $this->assertTrue($order->feePaid());
        $this->assertSame(0, $order->outstanding_due);
        $this->assertSame('full', $order->payment_status);
    }

    /** Phụ phí lớn hơn cọc thì hoàn kẹp về 0, phần thiếu tách ra cho admin thu tay. */
    /** @test */
    public function a_fee_bigger_than_the_deposit_never_makes_the_refund_negative(): void
    {
        $order = $this->order(['total_price' => 500_000, 'extra_fee' => 50_000, 'deposit_total' => 30_000]);
        $order->markPaid('rental', true);
        $order->markPaid('deposit', true);
        $order->refresh();

        $this->assertSame(0, $order->refund_due);
        $this->assertSame(20_000, $order->refundShortfall());
    }

    /** Khoản phụ phí phải ra tới cả trang khách lẫn màn admin. */
    /** @test */
    public function the_fee_amount_reaches_the_customer_and_the_admin(): void
    {
        $order = $this->order(['total_price' => 500_000, 'extra_fee' => 50_000]);

        $this->get(route('lookup', ['code' => $order->code, 'phone' => $order->customer_phone]))
            ->assertInertia(fn (Assert $p) => $p
                ->where('order.fee_due', 50_000)
                ->where('order.fee_received', 0)
                ->where('order.rental_due', 500_000));

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.orders.show', $order))
            ->assertInertia(fn (Assert $p) => $p
                ->where('order.fee_due', 50_000)
                ->where('order.fee_paid', false)
                ->has('order.fee_lines'));
    }

    /**
     * LỖI ĐÃ ĐO — markRefunded phải IDEMPOTENT.
     *
     * Bản đầu giữ lại phụ phí ở MỌI lần gọi. Admin chỉ cần bổ sung ghi chú sau khi đã
     * hoàn là feeOutstanding() đã về 0 → số hoàn ghi lại nhảy lên NGUYÊN cọc (150.000 →
     * 200.000). Admin đọc con số đó rồi đưa khách dư đúng phần phụ phí vừa giữ.
     *
     * @test
     */
    public function saving_the_refund_note_again_does_not_inflate_the_recorded_refund(): void
    {
        $order = $this->order(['status' => 'returned', 'total_price' => 500_000, 'extra_fee' => 50_000, 'deposit_total' => 200_000]);
        $order->markPaid('rental', true);
        $order->markPaid('deposit', true);
        $order->refresh();

        $order->markRefunded(true, null, 'Đủ đồ');
        $order->refresh();
        $this->assertSame(150_000, $order->deposit_refund_amount);

        // Admin quay lại bổ sung ghi chú — KHÔNG được đụng vào số tiền.
        $order->markRefunded(true, null, 'Đủ đồ, khách đã ký nhận');
        $order->refresh();
        $this->assertSame(150_000, $order->deposit_refund_amount, 'sửa ghi chú không được thổi số hoàn lên');
        $this->assertSame(50_000, $order->feePaidAmount(), 'không được cộng dồn phụ phí');
    }

    /** Bỏ hoàn rồi hoàn lại phải khép kín — không rò số nào. */
    /** @test */
    public function toggling_the_refund_off_and_on_returns_to_the_same_numbers(): void
    {
        $order = $this->order(['status' => 'returned', 'total_price' => 500_000, 'extra_fee' => 50_000, 'deposit_total' => 200_000]);
        $order->markPaid('rental', true);
        $order->markPaid('deposit', true);
        $order->refresh();

        $order->markRefunded(true, null);
        $order->refresh();
        $this->assertSame(150_000, $order->deposit_refund_amount);

        // Bỏ hoàn: phần phụ phí đã giữ phải trả lại trạng thái CHƯA thu.
        $order->markRefunded(false, null);
        $order->refresh();
        $this->assertNull($order->deposit_refund_amount);
        $this->assertSame(0, $order->feePaidAmount(), 'bỏ hoàn phải hoàn tác phần đã giữ');
        $this->assertSame(50_000, $order->feeOutstanding());

        $order->markRefunded(true, null);
        $order->refresh();
        $this->assertSame(150_000, $order->deposit_refund_amount);
    }

    /**
     * LỖI ĐÃ ĐO — luật đơn cũ phải suy ra SỐ TIỀN, không phải cờ đúng/sai.
     *
     * Bản đầu dùng cờ (rental_paid_amount >= rental_due). Admin nâng phụ phí 50k→80k trên
     * đơn cũ là cờ lật về false, hệ thống đòi lại cả 80k thay vì 30k chênh — khách bị trừ
     * cọc thừa đúng 50k.
     *
     * @test
     */
    public function raising_the_fee_on_a_legacy_order_only_asks_for_the_difference(): void
    {
        $order = $this->order(['total_price' => 500_000, 'extra_fee' => 50_000, 'deposit_total' => 200_000]);
        // Nghĩa CŨ: đã thu tiền thuê = thu cả phụ phí, ghi lại rental_due (550k).
        $order->forceFill(['rental_paid_at' => now(), 'rental_paid_amount' => $order->rental_due])->save();
        $order->refresh();
        $this->assertSame(0, $order->feeOutstanding());

        $order->update(['extra_fee' => 80_000]);
        $order->refresh();

        $this->assertSame(30_000, $order->feeOutstanding(), 'chỉ đòi phần CHÊNH, không đòi lại cả 80k');
    }

    /** Trên đơn cũ, bấm bỏ đánh dấu phụ phí phải có tác dụng — luật suy ra không được đè lên. */
    /** @test */
    public function unmarking_the_fee_on_a_legacy_order_actually_takes_effect(): void
    {
        $order = $this->order(['total_price' => 500_000, 'extra_fee' => 50_000]);
        $order->forceFill(['rental_paid_at' => now(), 'rental_paid_amount' => $order->rental_due])->save();
        $order->refresh();
        $this->assertTrue($order->feePaid());

        $order->markPaid('fee', false);
        $order->refresh();

        $this->assertFalse($order->feePaid(), 'bỏ đánh dấu phải đè được lên phần suy ra của đơn cũ');
        $this->assertSame(50_000, $order->feeOutstanding());
    }

    /** Đơn không có phụ phí thì không treo mốc "Đã nhận phụ phí — chưa làm" vĩnh viễn. */
    /** @test */
    public function an_order_without_a_fee_has_no_fee_step_in_the_action_log(): void
    {
        $withFee = collect($this->order(['extra_fee' => 50_000])->actionLog())->pluck('key');
        $without = collect($this->order(['extra_fee' => 0])->actionLog())->pluck('key');

        $this->assertContains('fee_paid', $withFee->all());
        $this->assertNotContains('fee_paid', $without->all());
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
        // Ghi 0 chứ KHÔNG ghi null: null mang nghĩa "chưa từng ghi nhận", và với khoản
        // phụ phí thì nó rơi về phần suy ra của đơn cũ — bấm bỏ đánh dấu xong vẫn hiện
        // đã thu (bopcamping-urqo).
        $this->assertSame(0, $order->rental_paid_amount);
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

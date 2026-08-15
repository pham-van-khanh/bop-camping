<?php

namespace Tests\Feature;

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

    /** @param  array<string, mixed>  $extra */
    private function order(array $extra = []): Order
    {
        return Order::factory()->create(array_merge([
            'total_price' => 500_000,
            'deposit_total' => 300_000,
            'status' => 'confirmed',
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
     * Đơn còn 'pending' thì GIÁ CHƯA CHẮC (shop còn sửa lịch/phụ phí). QR in ra số tiền
     * sai còn tệ hơn không có QR: khách chuyển xong mới biết thiếu.
     *
     * @test
     */
    public function a_pending_order_has_no_qr_because_the_price_is_not_settled(): void
    {
        $this->assertNull($this->qr()->urlFor($this->order(['status' => 'pending'])));

        // Đã xác nhận thì có.
        $this->assertNotNull($this->qr()->urlFor($this->order(['status' => 'confirmed'])));
        $this->assertNotNull($this->qr()->urlFor($this->order(['status' => 'renting'])));
    }

    /** @test */
    public function there_is_no_qr_when_there_is_nothing_left_to_collect(): void
    {
        $order = $this->order(['total_price' => 0, 'deposit_total' => 0]);

        $this->assertSame(0, $order->amount_due);
        $this->assertNull($this->qr()->urlFor($order));
    }

    /**
     * Thu đủ rồi mà còn chìa QR ra là mời khách trả lần hai.
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

        $this->assertNull($this->qr()->urlFor($parent));
    }

    /** @test */
    public function the_admin_order_screen_receives_the_qr_and_a_download_link(): void
    {
        $order = $this->order();

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
    public function a_pending_order_leaks_no_qr_to_the_lookup_page(): void
    {
        $order = $this->order(['status' => 'pending']);

        $this->get(route('lookup', ['code' => $order->code, 'phone' => $order->customer_phone]))
            ->assertInertia(fn (Assert $p) => $p->where('order.payment_qr', null));
    }
}

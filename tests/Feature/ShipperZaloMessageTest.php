<?php

namespace Tests\Feature;

use App\Mail\ShipperScheduleMail;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\DeliveryScheduleService;
use App\Services\ShipperScheduleNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-dolb — tin nhắn giao việc cho shipper (admin Copy rồi dán vào Zalo).
 * Không có Zalo OA/ZNS nên KHÔNG gửi tự động; nội dung sinh ở server để 1 nguồn chân lý.
 */
class ShipperZaloMessageTest extends TestCase
{
    use RefreshDatabase;

    private const DATE = '2030-11-05';

    private function order(array $attrs = []): Order
    {
        $order = Order::create(array_merge([
            'code' => 'BOP-ZALO1',
            'customer_name' => 'Phạm Văn A',
            'customer_phone' => '0912345678',
            'customer_address' => '12 Ngõ 5, Hà Nội',
            'start_date' => self::DATE, 'end_date' => '2030-11-07',
            'status' => 'confirmed', 'payment_method' => 'cod',
            'total_price' => 300000, 'deposit_total' => 200000,
            'confirmed_pickup_time' => '14:30',
        ], $attrs));

        $product = Product::factory()->create(['name' => 'Lều 2 người']);
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'price_per_day' => 100000,
            'days' => 3,
            'subtotal' => 300000,
        ]);

        return $order->fresh(['items.product']);
    }

    private function message(Order $order, string $leg = 'pickup'): string
    {
        return app(DeliveryScheduleService::class)->zaloMessage($order, $leg);
    }

    /** @test */
    public function message_follows_the_template_agreed_with_the_shop_owner(): void
    {
        $text = $this->message($this->order());

        $this->assertStringContainsString('Mã đơn: BOP-ZALO1', $text);
        $this->assertStringContainsString('Phạm Văn A', $text);
        $this->assertStringContainsString('0912345678', $text);
        $this->assertStringContainsString('12 Ngõ 5, Hà Nội', $text);
        $this->assertStringContainsString("Sản phẩm:\n1 x Lều 2 người", $text);
        $this->assertStringContainsString('Ngày giờ giao: 05/11/2030 · 14:30', $text);
        $this->assertStringContainsString('Nếu có vấn đề gì khác vui lòng liên hệ admin.', $text);
    }

    /** @test */
    public function collection_lines_appear_only_for_amounts_not_yet_collected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $order = $this->order();

        // Chưa thu gì → nhờ thu cả hai khoản.
        $text = $this->message($order);
        $this->assertStringContainsString('Nhờ shipper thu tiền thuê: 300.000đ', $text);
        $this->assertStringContainsString('Nhờ shipper thu tiền cọc: 200.000đ', $text);

        // Admin đã thu tiền thuê → chỉ còn nhờ thu cọc (tránh shipper thu lần hai).
        $order->markPaid('rental', true, $admin->id);
        $text = $this->message($order->fresh(['items.product']));
        $this->assertStringNotContainsString('thu tiền thuê', $text);
        $this->assertStringContainsString('Nhờ shipper thu tiền cọc: 200.000đ', $text);

        // Thu đủ cả hai → nói rõ không cần thu gì.
        $order->markPaid('deposit', true, $admin->id);
        $text = $this->message($order->fresh(['items.product']));
        $this->assertStringNotContainsString('Nhờ shipper thu', $text);
        $this->assertStringContainsString('Khách đã chuyển đủ tiền', $text);
    }

    /** @test */
    public function return_leg_mentions_checking_gear_and_refunding_the_deposit(): void
    {
        $order = $this->order(['confirmed_return_time' => '09:00']);

        $text = $this->message($order, 'return');

        $this->assertStringContainsString('Ngày giờ thu: 07/11/2030 · 09:00', $text);
        $this->assertStringContainsString('Shipper tự kiểm tra đồ và trả cọc cho khách: 200.000đ', $text);
    }

    /** @test */
    public function confirmed_order_without_any_time_uses_shop_wide_default(): void
    {
        // Đơn nhiều ngày ĐÃ XÁC NHẬN → giao 08:00 / thu 21:00 (chủ shop chốt 30/07).
        $order = $this->order(['confirmed_pickup_time' => null, 'schedule_note' => 'Gọi trước 15 phút']);

        $text = $this->message($order);

        $this->assertStringContainsString('Ngày giờ giao: 05/11/2030 · 08:00 (giờ mặc định)', $text);
        $this->assertStringContainsString('Ngày giờ thu: 07/11/2030 · 21:00 (giờ mặc định)', $text);
        $this->assertStringContainsString('Ghi chú: Gọi trước 15 phút', $text);
    }

    /** @test */
    public function pending_order_is_still_reported_as_having_no_time(): void
    {
        $order = $this->order(['status' => 'pending', 'confirmed_pickup_time' => null]);

        $text = $this->message($order);

        $this->assertStringContainsString('Ngày giờ giao: 05/11/2030 (chưa chốt giờ)', $text);
    }

    /** @test */
    public function schedule_page_ships_the_message_and_shipper_phone_for_each_order(): void
    {
        $shipper = User::factory()->create(['name' => 'Shipper A', 'phone' => '0977000111', 'is_shipper' => true]);
        $this->order(['pickup_shipper_id' => $shipper->id]);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get(route('admin.schedule', ['date' => self::DATE]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('pickups.0.shipper_phone', '0977000111'));

        // Lấy thẳng prop ra kiểm nội dung (chuỗi nhiều dòng, so khớp từng phần cho rõ lỗi).
        $message = $response->viewData('page')['props']['pickups'][0]['zalo_message'];
        $this->assertStringContainsString('Mã đơn: BOP-ZALO1', $message);
        $this->assertStringContainsString('Nhờ shipper thu tiền cọc', $message);
    }

    /** @test */
    public function message_shows_both_legs_and_a_link_to_the_shipper_app(): void
    {
        $order = $this->order(['confirmed_return_time' => '09:00']);

        $text = $this->message($order);

        // Lượt đang giao việc để trước, mốc còn lại để sau (feedback 30/07).
        $this->assertStringContainsString('Ngày giờ giao: 05/11/2030 · 14:30', $text);
        $this->assertStringContainsString('Ngày giờ thu: 07/11/2030 · 09:00', $text);
        // Link mở đúng NGÀY của lượt này trong app shipper.
        $this->assertStringContainsString('/shipper/lich-giao?date=2030-11-05&month=2030-11', $text);
    }

    /** @test */
    public function return_leg_link_points_to_the_return_date(): void
    {
        $text = $this->message($this->order(), 'return');

        $this->assertStringContainsString('/shipper/lich-giao?date=2030-11-07&month=2030-11', $text);
    }

    /** @test */
    public function half_day_order_without_confirmed_times_falls_back_to_shop_hours(): void
    {
        // Thuê trong ngày, buổi sáng: giờ mặc định 08:00–12:00 do OrderSplitter suy lúc checkout.
        $order = $this->order([
            'code' => 'BOP-HALF',
            'end_date' => self::DATE,
            'session' => 'morning',
            'is_half_day' => true,
            'confirmed_pickup_time' => null,
            'requested_pickup_time' => '08:00',
            'requested_return_time' => '12:00',
        ]);

        $text = $this->message($order);

        $this->assertStringContainsString('Ngày giờ giao: 05/11/2030 · 08:00 (giờ mặc định)', $text);
        $this->assertStringContainsString('Ngày giờ thu: 05/11/2030 · 12:00 (giờ mặc định)', $text);
    }

    /** @test */
    public function confirmed_time_wins_over_the_default_and_is_not_labelled_default(): void
    {
        $order = $this->order([
            'end_date' => self::DATE,
            'session' => 'morning',
            'requested_pickup_time' => '08:00',
            'confirmed_pickup_time' => '07:30',
        ]);

        $text = $this->message($order);

        $this->assertStringContainsString('Ngày giờ giao: 05/11/2030 · 07:30', $text);
        $this->assertStringNotContainsString('07:30 (giờ mặc định)', $text);
    }

    /** @test */
    public function the_email_schedule_feature_is_gone(): void
    {
        // Chốt 30/07: bỏ hẳn email lịch, chỉ còn Copy + Zalo — không để lại tính năng chết.
        $this->assertFalse(class_exists(ShipperScheduleMail::class));
        $this->assertFalse(class_exists(ShipperScheduleNotifier::class));
        $this->assertArrayNotHasKey('shipper:send-daily-schedule', Artisan::all());
        $this->assertFalse(app('router')->has('admin.schedule.email'));
    }
}

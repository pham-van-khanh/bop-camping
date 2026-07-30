<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\DeliveryScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Lịch giao KHÔNG được rơi ngày cuối tháng.
 *
 * Lỗi gốc: monthDays() dùng whereBetween($col, ['2026-07-01', '2026-07-31']) với chuỗi NGÀY.
 * Cột khai báo date() nhưng giá trị lưu kèm giờ ('2026-07-31 00:00:00' — bopcamping-ioku),
 * nên so chuỗi cho '2026-07-31 00:00:00' > '2026-07-31' và đơn của ngày 31 bị loại.
 * Hậu quả: đúng ngày cuối mỗi tháng, shipper mở lịch KHÔNG thấy việc của hôm đó.
 *
 * Test đóng băng thời gian nên chạy ngày nào cũng cho cùng kết quả (lỗi cũ chỉ lộ vào
 * ngày cuối tháng — CI chạy giữa tháng sẽ xanh oan).
 */
class ScheduleMonthEndTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array<string, array{0: string}> */
    public static function ngayCuoiThangProvider(): array
    {
        return [
            'tháng 31 ngày' => ['2026-07-31'],
            'tháng 30 ngày' => ['2026-09-30'],
            'tháng 2 thường' => ['2026-02-28'],
            'tháng 2 nhuận' => ['2028-02-29'],
            'cuối năm' => ['2026-12-31'],
        ];
    }

    /**
     * @dataProvider ngayCuoiThangProvider
     */
    public function test_don_nhan_dung_ngay_cuoi_thang_van_hien_trong_lich(string $lastDay): void
    {
        Carbon::setTestNow(Carbon::parse($lastDay.' 09:00:00'));

        $order = Order::create([
            'code' => 'BOP-'.strtoupper(uniqid()),
            'customer_name' => 'Khách cuối tháng',
            'customer_phone' => '0900000000',
            'start_date' => $lastDay,
            'end_date' => Carbon::parse($lastDay)->addDays(2)->toDateString(),
            'status' => 'confirmed',
            'payment_method' => 'cod',
            'total_price' => 300000,
            'deposit_total' => 200000,
        ]);

        $days = app(DeliveryScheduleService::class)->monthDays(Carbon::parse($lastDay));

        $dates = array_column($days, 'date');
        $this->assertContains($lastDay, $dates, "ngày {$lastDay} phải có trong lịch giao");

        $row = collect($days)->firstWhere('date', $lastDay);
        $this->assertSame(1, $row['pickups'], 'phải đếm được 1 lượt nhận');

        $this->assertSame($order->id, Order::query()->value('id'));
    }

    /** Ngày ĐẦU tháng cũng phải vào lịch — chốt luôn biên dưới. */
    public function test_don_nhan_dung_ngay_dau_thang_van_hien_trong_lich(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-01 09:00:00'));

        Order::create([
            'code' => 'BOP-'.strtoupper(uniqid()),
            'customer_name' => 'Khách đầu tháng',
            'customer_phone' => '0900000000',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-03',
            'status' => 'confirmed',
            'payment_method' => 'cod',
            'total_price' => 300000,
            'deposit_total' => 200000,
        ]);

        $days = app(DeliveryScheduleService::class)->monthDays(Carbon::parse('2026-07-01'));

        $this->assertContains('2026-07-01', array_column($days, 'date'));
    }

    /** Đơn của tháng KHÁC không được lọt vào — nới biên không được nới quá. */
    public function test_don_thang_khac_khong_lot_vao_lich(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-15 09:00:00'));

        foreach (['2026-06-30', '2026-08-01'] as $outside) {
            Order::create([
                'code' => 'BOP-'.strtoupper(uniqid()),
                'customer_name' => 'Khách ngoài tháng',
                'customer_phone' => '0900000000',
                'start_date' => $outside,
                'end_date' => Carbon::parse($outside)->addDay()->toDateString(),
                'status' => 'confirmed',
                'payment_method' => 'cod',
                'total_price' => 300000,
                'deposit_total' => 200000,
            ]);
        }

        $days = app(DeliveryScheduleService::class)->monthDays(Carbon::parse('2026-07-15'));
        $dates = array_column($days, 'date');

        // 30/06 trả về 01/07 nên NGÀY TRẢ nằm trong tháng 7 — đó là hợp lệ, chỉ kiểm lượt NHẬN.
        $pickupDates = array_column(array_filter($days, fn (array $d) => $d['pickups'] > 0), 'date');

        $this->assertNotContains('2026-06-30', $pickupDates);
        $this->assertNotContains('2026-08-01', $pickupDates);
        $this->assertNotContains('2026-08-01', $dates);
    }
}

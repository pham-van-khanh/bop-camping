<?php

namespace App\Services;

use App\Mail\ShipperScheduleMail;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Gửi lịch giao/thu trong ngày cho shipper qua email (bopcamping-5r5m).
 * Dùng chung cho nút "Gửi lịch qua email" của admin và command chạy 06:00 hằng ngày —
 * một nguồn chân lý cho việc "ai nhận gì", tránh 2 chỗ tính khác nhau.
 *
 * Zalo: KHÔNG gửi tự động (không dùng Zalo OA/ZNS — xem prd_shipper_delivery_ops mục 2).
 * FE chỉ mở link zalo.me để chủ shop tự nhắn.
 */
class ShipperScheduleNotifier
{
    public function __construct(private DeliveryScheduleService $schedule) {}

    /**
     * Gửi lịch 1 ngày cho 1 shipper.
     *
     * @return 'sent'|'no_legs'|'no_email' Lý do để admin biết vì sao không gửi
     */
    public function send(User $shipper, Carbon $date): string
    {
        [$pickups, $returns] = $this->rowsFor($shipper, $date);

        if ($pickups === [] && $returns === []) {
            return 'no_legs';
        }

        $email = $shipper->hasPlaceholderEmail() ? null : $shipper->email;
        if (! $email) {
            return 'no_email';   // tài khoản chỉ có email tạm <sđt>@bopcamping.local
        }

        Mail::to($email)->send(new ShipperScheduleMail($shipper, $date, $pickups, $returns));

        return 'sent';
    }

    /**
     * Gửi cho MỌI shipper có lượt trong ngày.
     *
     * @return array{sent:int,no_email:list<string>} Tên những người không có email thật
     */
    public function sendToAllWithLegs(Carbon $date): array
    {
        $sent = 0;
        $noEmail = [];

        foreach (User::shippers()->get() as $shipper) {
            match ($this->send($shipper, $date)) {
                'sent' => $sent++,
                'no_email' => $noEmail[] = $shipper->name,
                default => null,   // no_legs: không có việc hôm đó, im lặng bỏ qua
            };
        }

        return ['sent' => $sent, 'no_email' => $noEmail];
    }

    /**
     * Các lượt của shipper trong ngày, đã chuẩn hoá cho view mail.
     *
     * @return array{0:list<array<string,mixed>>,1:list<array<string,mixed>>}
     */
    private function rowsFor(User $shipper, Carbon $date): array
    {
        $rows = fn (string $leg) => $this->schedule
            ->legOrders($leg, $date, $shipper->id)
            ->map(fn (Order $o) => $this->schedule->row($o, $leg))
            ->values()
            ->all();

        return [$rows('pickup'), $rows('return')];
    }
}

<?php

namespace App\Console\Commands;

use App\Mail\OrderPickupReminderMail;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Gửi email nhắc khách sắp đến ngày nhận đồ (trước 1 ngày) — bopcamping-sdy8.
 * Chỉ đơn confirmed, có start_date = ngày mai, chưa gửi nhắc, có email hợp lệ.
 * Chạy hằng ngày (xem lịch ở routes/console.php). Idempotent nhờ pickup_reminder_sent_at.
 */
class SendPickupReminders extends Command
{
    protected $signature = 'orders:send-pickup-reminders';

    protected $description = 'Gửi email nhắc nhận đồ cho đơn confirmed có ngày nhận là ngày mai';

    public function handle(): int
    {
        $orders = Order::query()
            ->where('status', 'confirmed')
            // Đơn cha chỉ gom đợt, không có món — nhắc lịch theo TỪNG CON (bopcamping-wtuv T7).
            ->where('is_parent', false)
            ->whereNull('pickup_reminder_sent_at')
            ->whereDate('start_date', Carbon::tomorrow())
            ->with('items.product')
            ->get();

        $sent = 0;
        foreach ($orders as $order) {
            $email = $order->notifiableEmail();
            if (! $email) {
                continue; // đơn không có email hợp lệ — bỏ qua, không đánh dấu
            }

            Mail::to($email)->send(new OrderPickupReminderMail($order));
            $order->forceFill(['pickup_reminder_sent_at' => now()])->saveQuietly();
            $sent++;
        }

        $this->info("Đã gửi {$sent} email nhắc nhận đồ.");

        return self::SUCCESS;
    }
}

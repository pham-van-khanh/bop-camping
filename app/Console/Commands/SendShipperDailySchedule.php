<?php

namespace App\Console\Commands;

use App\Services\ShipperScheduleNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Gửi email lịch giao/thu HÔM NAY cho từng shipper có lượt (bopcamping-5r5m).
 * Chạy 06:00 hằng ngày — xem lịch ở routes/console.php. Cần cron trên server
 * (bead bopcamping-ybsm) và queue worker vì mail là ShouldQueue.
 *
 * Idempotent bằng cache key theo ngày: chạy lại trong cùng ngày KHÔNG gửi trùng
 * (không thêm cột DB cho việc này). Nút "Gửi lịch qua email" của admin không bị chặn.
 */
class SendShipperDailySchedule extends Command
{
    protected $signature = 'shipper:send-daily-schedule {--force : Gửi lại dù hôm nay đã gửi}';

    protected $description = 'Gửi email lịch giao/thu hôm nay cho từng shipper có lượt';

    public function handle(ShipperScheduleNotifier $notifier): int
    {
        $today = Carbon::today();
        $key = 'shipper-schedule-sent:'.$today->toDateString();

        if (! $this->option('force') && Cache::has($key)) {
            $this->info('Hôm nay đã gửi lịch cho shipper — bỏ qua (dùng --force để gửi lại).');

            return self::SUCCESS;
        }

        ['sent' => $sent, 'no_email' => $noEmail] = $notifier->sendToAllWithLegs($today);

        // Giữ dấu 2 ngày cho chắc, kể cả khi cron chạy lệch giờ quanh nửa đêm.
        Cache::put($key, true, now()->addDays(2));

        $this->info("Đã gửi lịch cho {$sent} shipper.");
        if ($noEmail !== []) {
            $this->warn('Chưa có email thật (không gửi được): '.implode(', ', $noEmail));
        }

        return self::SUCCESS;
    }
}

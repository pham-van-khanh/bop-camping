<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nhắc khách sắp đến ngày nhận đồ (trước 1 ngày) — chạy mỗi sáng (bopcamping-sdy8).
// Cần chạy scheduler trên server: `php artisan schedule:work` (dev) hoặc cron gọi
// `schedule:run` mỗi phút (prod). Mail vẫn cần queue worker để gửi.
Schedule::command('orders:send-pickup-reminders')->dailyAt('08:00');

// Gửi lịch giao/thu hôm nay cho từng shipper (bopcamping-5r5m) — sớm hơn giờ mở cửa
// để shipper xem trước khi lên đường. Cũng cần cron + queue worker như trên.
Schedule::command('shipper:send-daily-schedule')->dailyAt('06:00');

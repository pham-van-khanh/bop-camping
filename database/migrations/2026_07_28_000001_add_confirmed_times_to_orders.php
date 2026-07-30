<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Giờ giao/thu ĐÃ CHỐT do admin đặt (bopcamping-n7bh, prd_delivery_schedule).
 * Tách hẳn khỏi `requested_*` (giờ KHÁCH xin, server suy từ buổi khách chọn) để
 * giữ được "khách xin 6:00 → shop chốt 7:30" làm căn cứ cho `extra_fee` ngoài khung giờ.
 * KHÔNG ảnh hưởng tồn kho (AvailabilityService chỉ tính theo ngày — INVARIANT).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Giờ shop chốt dạng "HH:MM" (null = chưa chốt giờ).
            $table->string('confirmed_pickup_time', 5)->nullable()->after('requested_return_time');
            $table->string('confirmed_return_time', 5)->nullable()->after('confirmed_pickup_time');
            // Ghi chú NỘI BỘ cho shipper (gọi trước 15p, nhà cuối hẻm...) — không gửi khách.
            $table->string('schedule_note')->nullable()->after('confirmed_return_time');
            // Lần chốt/đổi giờ gần nhất — audit + hiển thị "chốt lúc …".
            $table->timestamp('schedule_confirmed_at')->nullable()->after('schedule_note');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['confirmed_pickup_time', 'confirmed_return_time', 'schedule_note', 'schedule_confirmed_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Khung giờ giao/trả mặc định (bopcamping-s1ij, adr_turnaround_buffer mục 3.1).
 * CHỈ để hiển thị kỳ vọng cho khách (nhận 8h, trả trước 20h) — KHÔNG tham gia tính
 * tồn kho (INVARIANT: mọi lượt khoá trọn ngày). Cấu hình được để đổi giờ theo mùa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('pickup_hour')->default(8)->after('working_hours');
            $table->unsignedTinyInteger('return_hour')->default(20)->after('pickup_hour');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['pickup_hour', 'return_hour']);
        });
    }
};

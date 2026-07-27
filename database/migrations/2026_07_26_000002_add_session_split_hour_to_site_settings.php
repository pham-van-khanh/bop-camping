<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Giờ chia buổi sáng/chiều (spec 2026-07-26). Mặc định 14 → sáng = pickup_hour..14,
 * chiều = 14..return_hour. CHỈ để hiển thị khung giờ + phân biệt buổi cho giá; không
 * tạo suất turnaround, không đụng tồn kho (kiểm soát vệ sinh vẫn là buffer_days theo ngày).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('session_split_hour')->default(14)->after('return_hour');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn('session_split_hour');
        });
    }
};

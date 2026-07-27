<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Khung giờ buổi 2 cửa sổ có KHOẢNG NGHỈ (feedback 2026-07-27) — thay 1 mốc chia buổi:
 *   Sáng = pickup_hour → morning_end_hour (vd 8→12)
 *   Chiều = afternoon_start_hour → return_hour (vd 13→21)
 * Khoảng giữa (12→13) để shop chuẩn bị/ship. Chỉ hiển thị + tính giờ; KHÔNG đụng tồn kho.
 * Guard hasColumn để chạy đúng cả trên môi trường đã/chưa có session_split_hour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('site_settings', 'morning_end_hour')) {
                $table->unsignedTinyInteger('morning_end_hour')->default(12)->after('return_hour');
            }
            if (! Schema::hasColumn('site_settings', 'afternoon_start_hour')) {
                $table->unsignedTinyInteger('afternoon_start_hour')->default(13)->after('morning_end_hour');
            }
        });

        if (Schema::hasColumn('site_settings', 'session_split_hour')) {
            Schema::table('site_settings', function (Blueprint $table) {
                $table->dropColumn('session_split_hour');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('site_settings', 'session_split_hour')) {
            Schema::table('site_settings', function (Blueprint $table) {
                $table->unsignedTinyInteger('session_split_hour')->default(14)->after('return_hour');
            });
        }
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['morning_end_hour', 'afternoon_start_hour']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đánh dấu đã gửi email nhắc nhận đồ (trước ngày nhận 1 ngày) — chống gửi trùng
 * khi command daily chạy lại. Cùng mẫu với review_invited_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('pickup_reminder_sent_at')->nullable()->after('review_submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('pickup_reminder_sent_at');
        });
    }
};

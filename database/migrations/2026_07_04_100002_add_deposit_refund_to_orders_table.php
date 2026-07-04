<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * bopcamping-7be — khi đơn ĐÃ TRẢ, admin theo dõi hoàn cọc thay vì chuyển tiền:
 * deposit_refund_status = pending (chưa hoàn, mặc định) | refunded (đã hoàn) và
 * deposit_refund_note lưu lý do trừ/không hoàn đủ cọc (rách lều, hư hại…).
 * String (không enum DB) để chạy được trên cả sqlite lẫn MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('deposit_refund_status', 20)->default('pending')->after('payment_status');
            $table->text('deposit_refund_note')->nullable()->after('deposit_refund_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['deposit_refund_status', 'deposit_refund_note']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * bopcamping-452 — lớp thanh toán 2 tầng (orthogonal với status xử lý).
 *
 * payment_option: 'full' (trả cọc + phí thuê trước) | 'deposit' (trả cọc trước, phí thuê COD).
 * deposit_paid_at / rental_paid_at: admin đánh dấu khi nhận được (chuyển khoản thủ công / COD).
 * Đơn cũ: payment_option = null (luồng COD cũ), không ảnh hưởng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_option', 16)->nullable()->after('payment_method');
            $table->timestamp('deposit_paid_at')->nullable()->after('payment_option');
            $table->timestamp('rental_paid_at')->nullable()->after('deposit_paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_option', 'deposit_paid_at', 'rental_paid_at']);
        });
    }
};

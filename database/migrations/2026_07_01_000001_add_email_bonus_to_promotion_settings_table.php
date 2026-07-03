<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ưu đãi khuyến khích khách thêm email thật (email vốn không bắt buộc khi đăng ký/SĐT):
     * giảm % cho đơn hàng ĐẦU TIÊN khi email đã xác thực — cùng pattern referee_discount_*.
     */
    public function up(): void
    {
        Schema::table('promotion_settings', function (Blueprint $table) {
            $table->boolean('email_bonus_enabled')->default(true)->after('reward_clawback_enabled');
            $table->enum('email_bonus_discount_type', ['fixed', 'percent'])->default('percent')->after('email_bonus_enabled');
            $table->decimal('email_bonus_discount_value', 12, 2)->default(5)->after('email_bonus_discount_type');
        });
    }

    public function down(): void
    {
        Schema::table('promotion_settings', function (Blueprint $table) {
            $table->dropColumn(['email_bonus_enabled', 'email_bonus_discount_type', 'email_bonus_discount_value']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Email khách trên đơn — nơi gửi mail xác nhận / cập nhật trạng thái (KE_HOACH 8.1).
     * Snapshot tại lúc đặt (lấy từ tài khoản đã đăng nhập); nullable cho khách vãng lai.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('customer_email')->nullable()->after('customer_phone');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('customer_email');
        });
    }
};

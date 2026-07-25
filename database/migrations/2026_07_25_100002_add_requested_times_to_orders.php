<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Giờ nhận/trả KHÁCH TỰ CHỌN khi thuê 1 ngày (bopcamping-n6mr) — lưu vào đơn để admin
 * thấy đơn muốn nhận/trả giờ nào. "HH:MM"; null với đơn nhiều ngày (dùng khung mặc định).
 * KHÔNG ảnh hưởng tồn kho.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('requested_pickup_time', 5)->nullable()->after('end_date');
            $table->string('requested_return_time', 5)->nullable()->after('requested_pickup_time');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['requested_pickup_time', 'requested_return_time']);
        });
    }
};

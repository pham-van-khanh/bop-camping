<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * bopcamping-7be — marker thủ công cho admin đánh dấu tình trạng chuyển tiền của
 * đơn (COD thuần): unpaid = chưa chuyển (mặc định) · deposit = đã chuyển cọc ·
 * full = chuyển hết. Dùng string (không enum DB) để migration chạy được trên cả
 * sqlite lẫn MySQL; ràng buộc giá trị ở tầng app (validate + cast).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_status', 20)->default('unpaid')->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('payment_status');
        });
    }
};

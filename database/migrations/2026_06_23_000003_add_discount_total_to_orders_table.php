<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Giảm giá từ voucher. Giữ total_price = tổng item; tiền phải trả = total_price + deposit_total − discount_total.
            $table->unsignedInteger('discount_total')->default(0)->after('deposit_total');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('discount_total');
        });
    }
};

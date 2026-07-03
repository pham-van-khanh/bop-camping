<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * bopcamping-3ag — orders chỉ lưu discount_total tổng nên admin không biết
 * nguồn giảm (voucher/referral/email bonus). Lưu vết từng dòng
 * {source, amount thực áp, code?} lúc checkout; đơn cũ null → FE fallback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->json('discount_breakdown')->nullable()->after('discount_total');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('discount_breakdown');
        });
    }
};

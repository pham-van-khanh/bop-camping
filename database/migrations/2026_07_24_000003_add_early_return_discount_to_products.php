<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ưu đãi trả sớm trong ngày (nửa ngày) THEO SẢN PHẨM (bopcamping-jrh8, adr_pricing_models).
 * Đơn cùng ngày trả sớm được giảm % này; món vẫn khoá TRỌN NGÀY (không phải bán nửa phần).
 * 0 = không giảm (mặc định). Đơn nhiều ngày KHÔNG áp ưu đãi này.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedTinyInteger('early_return_discount_pct')->default(0)->after('deposit');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('early_return_discount_pct');
        });
    }
};

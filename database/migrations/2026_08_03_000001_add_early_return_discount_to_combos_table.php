<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * bopcamping-w7gi — ưu đãi trả sớm (nửa ngày) cho COMBO.
 *
 * Sản phẩm lẻ đã có `products.early_return_discount_pct` (adr_pricing_models). Combo chưa
 * có cột tương đương, nên trước đây thuê combo nửa ngày không giảm được gì.
 *
 * Chủ shop chốt phương án cột RIÊNG cho combo (không suy từ các món) để linh động: combo
 * nào muốn giảm thì nhập, không muốn thì để 0.
 *
 * Default 0 = KHÔNG giảm -> mọi combo đang có giữ nguyên giá, không cần backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('combos', function (Blueprint $table) {
            $table->unsignedTinyInteger('early_return_discount_pct')
                ->default(0)
                ->after('deposit');
        });
    }

    public function down(): void
    {
        Schema::table('combos', function (Blueprint $table) {
            $table->dropColumn('early_return_discount_pct');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cờ "nửa ngày" ở CẤP ĐƠN (bopcamping-jrh8, adr_pricing_models mục 3) — đơn cùng ngày
 * trả sớm, đã áp ưu đãi trả sớm. Để admin thấy đơn nào trả trưa (tín hiệu quay vòng)
 * + tính giá. KHÔNG ảnh hưởng tồn kho (INVARIANT: mọi lượt khoá trọn ngày).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_half_day')->default(false)->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('is_half_day');
        });
    }
};

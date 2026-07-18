<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Snapshot % bậc giảm dài ngày đã áp cho dòng (bopcamping-e36e). subtotal đã lưu NET
    // (sau giảm); cột này để hiển thị "giá gốc → giá giảm" và tái tính khi đổi lịch.
    // Đơn cũ = 0 → net == gross, không đổi.
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('duration_discount_percent', 5, 2)->default(0)->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('duration_discount_percent');
        });
    }
};

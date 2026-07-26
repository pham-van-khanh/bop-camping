<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Buổi thuê khách chọn khi thuê ĐÚNG 1 NGÀY (adr_pricing_models + spec 2026-07-26):
 * morning | afternoon | full | null. THUẦN hiển thị + phân biệt giờ sáng/chiều cho GIÁ —
 * KHÔNG tham gia tính tồn kho (giữ INVARIANT: mọi lượt khoá trọn ngày). null = thuê
 * nhiều ngày (dùng khung mặc định, không phải nửa ngày). Giờ + is_half_day suy ra từ
 * session ở server (OrderSplitter), không tin client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('session', 16)->nullable()->after('is_half_day');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('session');
        });
    }
};

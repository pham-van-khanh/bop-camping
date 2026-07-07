<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chọn tài khoản Zalo "chính" hiển thị ở trang chủ (1 hoặc 2). Footer vẫn liệt kê
 * cả hai; trang chủ chỉ hiện 1 số duy nhất (bopcamping-12w).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('zalo_main')->default(1)->after('zalo2_url');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn('zalo_main');
        });
    }
};

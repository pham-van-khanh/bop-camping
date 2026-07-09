<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Epic 1 (trang sản phẩm v2):
 * - specs: thông số key–value admin nhập ([{key, value}]) — card "THÔNG SỐ" dưới ảnh.
 * - setup_content: HTML TipTap (đã sanitize) — khối "chi tiết sản phẩm" text + ảnh xen kẽ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('specs')->nullable()->after('description');
            $table->longText('setup_content')->nullable()->after('specs');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['specs', 'setup_content']);
        });
    }
};

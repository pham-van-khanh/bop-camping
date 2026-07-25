<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Khung giờ nhận/trả RIÊNG theo sản phẩm (bopcamping-n6mr) — trống (null) = dùng khung
 * giờ mặc định của shop (site_settings). Mỗi sản phẩm admin đặt được giờ riêng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedTinyInteger('pickup_hour')->nullable()->after('deposit');
            $table->unsignedTinyInteger('return_hour')->nullable()->after('pickup_hour');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['pickup_hour', 'return_hour']);
        });
    }
};

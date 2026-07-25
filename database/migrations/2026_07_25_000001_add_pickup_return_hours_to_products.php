<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Khung giờ nhận/trả OVERRIDE theo sản phẩm (bopcamping-fica) — trống (null) = dùng
 * khung giờ mặc định của shop (site_settings). CHỈ hiển thị kỳ vọng + prefill checkout,
 * KHÔNG đụng tồn kho (INVARIANT giữ nguyên từ adr_turnaround_buffer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedTinyInteger('pickup_hour')->nullable()->after('early_return_discount_pct');
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

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Khung giờ giao/trả MẶC ĐỊNH TOÀN SHOP (bopcamping-n6mr) — nhận 8h, trả 20h.
 * Mỗi sản phẩm có thể đặt giờ riêng (products.pickup_hour/return_hour) đè lên giá trị này.
 * Chỉ hiển thị kỳ vọng cho khách, KHÔNG ảnh hưởng tồn kho.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('pickup_hour')->default(8)->after('working_hours');
            $table->unsignedTinyInteger('return_hour')->default(20)->after('pickup_hour');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['pickup_hour', 'return_hour']);
        });
    }
};

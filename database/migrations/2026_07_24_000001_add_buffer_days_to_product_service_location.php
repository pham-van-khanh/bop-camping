<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đệm quay vòng giặt/phơi THEO TỪNG KHO (bopcamping-s1ij, adr_turnaround_buffer).
 * Sau ngày trả, sản phẩm bị coi là chưa sẵn sàng thêm buffer_days ngày (giặt/phơi/
 * kiểm tra) trước khi cho thuê lượt kế. 0 = hành vi y hệt hiện tại (tương thích ngược).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_service_location', function (Blueprint $table) {
            $table->unsignedTinyInteger('buffer_days')->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('product_service_location', function (Blueprint $table) {
            $table->dropColumn('buffer_days');
        });
    }
};

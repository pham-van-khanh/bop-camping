<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * bopcamping-n0db — địa chỉ + link bản đồ của địa điểm phục vụ.
 *
 * Khách chọn "Tự đến xem đồ" ở checkout thì phải biết đi đâu. Địa chỉ và link bản đồ
 * thuộc về ĐỊA ĐIỂM chứ không thuộc về đơn — mỗi cơ sở nhập một lần, mọi đơn dùng chung.
 *
 * map_url dài 500: link rút gọn của Google Maps ngắn, nhưng link đầy đủ kèm toạ độ và
 * tham số có thể rất dài — cắt ngắn là hỏng link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_locations', function (Blueprint $table) {
            $table->string('address')->nullable()->after('area');
            $table->string('map_url', 500)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('service_locations', function (Blueprint $table) {
            $table->dropColumn(['address', 'map_url']);
        });
    }
};

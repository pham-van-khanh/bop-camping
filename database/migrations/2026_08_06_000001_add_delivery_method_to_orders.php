<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * bopcamping-z3ug — hình thức GIAO khách chọn ở checkout.
 *
 * 'self_pickup' = khách tự đến lấy (không mất phí, xem và kiểm đồ tại chỗ)
 * 'ship'        = Bốp giao tới địa điểm (có phí, admin báo khi gọi xác nhận rồi
 *                 nhập vào extra_fee — checkout KHÔNG tự tính)
 *
 * Mặc định 'self_pickup': phương án rẻ nhất, và là mặc định đúng cho Nghệ An nơi
 * phải thuê xe ngoài. Đơn cũ cũng nhận giá trị này — không suy diễn ngược thành 'ship'.
 *
 * VARCHAR chứ không ENUM: dự án đã có bài học đổi ENUM rất đau (xem bopcamping-g5hn).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_method', 12)->default('self_pickup')->after('service_location_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('delivery_method');
        });
    }
};

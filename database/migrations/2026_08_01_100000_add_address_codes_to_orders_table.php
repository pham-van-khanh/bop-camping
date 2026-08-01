<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * bopcamping-9299 — mã địa chỉ theo cấu trúc SAU sát nhập 07/2025 (34 tỉnh, 2 cấp).
 *
 * customer_address VẪN là nguồn chân lý cho giao nhận (8 chỗ đang đọc nó: đơn admin,
 * lịch giao, tin Zalo shipper, trang tài khoản — không chỗ nào phải sửa). Các cột dưới
 * chỉ để THỐNG KÊ sau này, nên đều nullable và KHÔNG backfill đơn cũ.
 *
 * Cố ý KHÔNG có khoá ngoại: dữ liệu tỉnh/xã không nằm trong DB này (FE gọi
 * provinces.open-api.vn trực tiếp — xem artifacts/plan_address_picker.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('province_code')->nullable()->after('customer_address');
            $table->unsignedInteger('ward_code')->nullable()->after('province_code');
            // Số nhà / đường — phần khách tự gõ, tách khỏi phần chọn từ select.
            $table->string('street')->nullable()->after('ward_code');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['province_code', 'ward_code', 'street']);
        });
    }
};

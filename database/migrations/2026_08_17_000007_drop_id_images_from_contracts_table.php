<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * bopcamping-4jao — BỎ lưu ảnh CCCD trong hệ thống.
 *
 * Chủ shop chốt: xem CCCD khách gửi qua Zalo rồi NHẬP TAY thông tin vào hợp đồng, và không
 * cần lệnh xoá tự động. Nhưng nếu vẫn giữ ảnh mà không có cơ chế xoá thì hợp đồng hứa một
 * đằng (ban đầu có câu "xoá trong vòng 90 ngày") mà hệ thống làm một nẻo — đúng thứ dễ bị
 * bắt lỗi nhất khi tranh chấp, và là rủi ro dữ liệu cá nhân không đổi lại được gì.
 *
 * Nên bỏ hẳn hai cột: không lưu thì không phải hứa xoá. Câu chữ Điều 8 và ghi chú CCCD trong
 * mẫu hợp đồng đã sửa cho khớp.
 *
 * Cố ý KHÔNG dùng cách sửa lại migration gốc: nhánh này đã được chạy thử nên viết lại lịch sử
 * sẽ làm lệch schema ở nơi đã migrate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['id_front_path', 'id_back_path']);
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('id_front_path')->nullable()->after('signer_id_issued_place');
            $table->string('id_back_path')->nullable()->after('id_front_path');
        });
    }
};

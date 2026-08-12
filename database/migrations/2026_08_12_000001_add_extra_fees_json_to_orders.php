<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phụ phí NHIỀU KHOẢN (bopcamping-f1yj).
 *
 * Trước đây một đơn chỉ ghi được đúng một phụ phí (extra_fee + extra_fee_note), nên đơn
 * vừa giao tận nơi vừa trả muộn phải cộng gộp thành một số rồi ghi chú chung chung —
 * không tách ra đối soát được (bopcamping-was4).
 *
 * `extra_fees` là danh sách [{name, value}] và là NGUỒN CHÂN LÝ.
 * `extra_fee` GIỮ LẠI làm TỔNG, ghi lại mỗi lần lưu. Cố ý không bỏ cột này:
 *   - `rental_due` = total_price + extra_fee − discount_total, dùng ở mail/admin/shipper.
 *   - Thống kê sau này còn SUM() được ở SQL, không phải giải JSON từng dòng.
 * Đổi lại phải kỷ luật: CHỈ được ghi qua OrderController::updateExtraFee để tổng không
 * bao giờ lệch với danh sách.
 *
 * Dùng list [{name,value}] chứ không phải map {name: value}: map sẽ mất thứ tự admin
 * nhập và gộp mất hai khoản trùng tên.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->json('extra_fees')->nullable()->after('extra_fee_note');
        });

        // Backfill đơn cũ: một khoản duy nhất, lấy đúng ghi chú admin đã nhập.
        // Chạy theo lô để không nuốt hết RAM nếu bảng lớn.
        DB::table('orders')
            ->where('extra_fee', '>', 0)
            ->orderBy('id')
            ->chunkById(500, function ($orders) {
                foreach ($orders as $o) {
                    DB::table('orders')->where('id', $o->id)->update([
                        'extra_fees' => json_encode(
                            [['name' => $o->extra_fee_note ?: 'Phụ phí', 'value' => (int) $o->extra_fee]],
                            JSON_UNESCAPED_UNICODE
                        ),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('extra_fees');
        });
    }
};

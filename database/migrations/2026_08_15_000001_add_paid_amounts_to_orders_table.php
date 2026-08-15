<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * bopcamping-r3fy — ghi SỐ TIỀN đã thu, không chỉ ghi CỜ đã thu hay chưa.
 *
 * VẤN ĐỀ ĐÃ ĐO: rental_paid_at/deposit_paid_at chỉ nói "đã thu", không nói thu BAO NHIÊU.
 * Đơn 500k thuê + 300k cọc, admin bấm đã thu tiền thuê rồi mới nhập phí ship 50k →
 * rental_due thành 550k nhưng hệ thống vẫn coi tiền thuê đã xong, QR chỉ đòi 300k tiền
 * cọc. Shop thu hụt 50k mà không có dấu hiệu gì, còn khách thì được báo "Tiền thuê
 * 550.000đ — Shop đã nhận" trong khi shop mới nhận 500k.
 *
 * Có số tiền thật rồi thì mọi thay đổi giá sau lúc thu đều lộ ra thành phần còn thiếu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('rental_paid_amount')->nullable()->after('rental_paid_by');
            $table->unsignedBigInteger('deposit_paid_amount')->nullable()->after('deposit_paid_by');
        });

        // Đơn CŨ đã đánh dấu thu: chốt số tiền theo giá hiện tại. Đó là giả định đúng nhất
        // có thể — trước migration này hệ thống coi "đã thu" nghĩa là thu đủ phần đó. Không
        // backfill thì mọi đơn cũ bỗng dưng bị tính là còn nợ.
        //
        // Kẹp âm phải làm bằng ĐIỀU KIỆN LỌC, không phải sửa sau khi ghi: cột unsigned +
        // MySQL strict mode (config/database.php) thì ghi số âm là NÉM LỖI, migration chết
        // giữa chừng ngay trên dữ liệu thật. SQLite không ép unsigned nên dev không lộ ra.
        $netRental = 'COALESCE(total_price, 0) + COALESCE(extra_fee, 0) - COALESCE(discount_total, 0)';

        DB::table('orders')
            ->whereNotNull('rental_paid_at')
            ->whereRaw("$netRental >= 0")
            ->update(['rental_paid_amount' => DB::raw($netRental)]);

        // Giảm giá vượt tiền thuê (đơn cũ, trước khi có trần) → coi như thu 0.
        DB::table('orders')
            ->whereNotNull('rental_paid_at')
            ->whereRaw("$netRental < 0")
            ->update(['rental_paid_amount' => 0]);

        // deposit_total là tổng cọc, luôn ≥ 0 — không cần kẹp.
        DB::table('orders')
            ->whereNotNull('deposit_paid_at')
            ->update(['deposit_paid_amount' => DB::raw('COALESCE(deposit_total, 0)')]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['rental_paid_amount', 'deposit_paid_amount']);
        });
    }
};

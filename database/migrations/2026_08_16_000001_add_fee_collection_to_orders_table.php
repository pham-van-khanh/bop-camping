<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * bopcamping-urqo — phụ phí thành KHOẢN THU riêng, và ghi số tiền hoàn cọc thật.
 *
 * Trước đây phụ phí bị gộp vào tiền thuê nên chủ shop không biết khoản nào đã thu.
 *
 * CỐ Ý KHÔNG BACKFILL. Đơn cũ giữ nguyên, không ghi đè một dòng nào — luật suy ra từ chính
 * dữ liệu sẵn có (xem Order::feePaid()): số tiền thuê đã ghi mà bao trùm cả phụ phí thì
 * coi như phụ phí đã thu. Cách đó tự đúng cho cả đơn cũ lẫn đơn mới và không cần mốc thời
 * gian hardcode — thứ luôn mục nát sau vài lần deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('fee_paid_at')->nullable()->after('deposit_paid_amount');
            $table->foreignId('fee_paid_by')->nullable()->after('fee_paid_at')->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('fee_paid_amount')->nullable()->after('fee_paid_by');

            // Hoàn cọc hiện chỉ có trạng thái + ghi chú, không có SỐ TIỀN. Cần số thật vì
            // phụ phí chưa thu được trừ thẳng vào khoản hoàn.
            $table->unsignedBigInteger('deposit_refund_amount')->nullable()->after('deposit_refund_note');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fee_paid_by');
            $table->dropColumn(['fee_paid_at', 'fee_paid_amount', 'deposit_refund_amount']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ghi dấu AI ĐÃ LÀM GÌ trên đơn (bopcamping-3wfk — chủ shop 31/07/2026: "action trong admin
 * khó để biết ai đã nhận cọc/tiền thuê/hoàn cọc").
 *
 * Thu tiền thuê / thu cọc đã có `*_paid_at|by` từ bopcamping-q7i0. Migration này bổ sung
 * 3 mốc còn lại: HOÀN CỌC, BẤM ĐÃ GIAO, BẤM ĐÃ THU ĐỒ — cả admin lẫn shipper đều làm được
 * nên không có dấu thì tranh chấp ("shipper bảo giao rồi") không có gì để đối chiếu.
 *
 * CỐ TÌNH KHÔNG backfill đơn cũ: trạng thái đơn đã nói việc đó xảy ra rồi, nhưng ai làm thì
 * thật sự không biết — điền `updated_at` + người bất kỳ sẽ là bịa dữ liệu đối soát.
 * UI hiển thị "không rõ ai" cho các mốc cũ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('deposit_refunded_at')->nullable()->after('deposit_refund_note');
            $table->foreignId('deposit_refunded_by')->nullable()->after('deposit_refunded_at')
                ->constrained('users')->nullOnDelete();

            $table->timestamp('delivered_at')->nullable()->after('deposit_refunded_by');
            $table->foreignId('delivered_by')->nullable()->after('delivered_at')
                ->constrained('users')->nullOnDelete();

            $table->timestamp('collected_at')->nullable()->after('delivered_by');
            $table->foreignId('collected_by')->nullable()->after('collected_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deposit_refunded_by');
            $table->dropConstrainedForeignId('delivered_by');
            $table->dropConstrainedForeignId('collected_by');
            $table->dropColumn(['deposit_refunded_at', 'delivered_at', 'collected_at']);
        });
    }
};

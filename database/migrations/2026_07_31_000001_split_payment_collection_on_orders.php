<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tách việc thu tiền thành 2 KHOẢN ĐỘC LẬP: tiền thuê và tiền cọc (bopcamping-q7i0).
 *
 * `payment_status` 3 mức (unpaid/deposit/full) không biểu diễn được "đã thu tiền thuê
 * nhưng chưa thu cọc" — tình huống thật khi khách chuyển khoản tiền thuê trước, cọc trả
 * khi nhận đồ. Từ nay 4 cột dưới đây là NGUỒN CHÂN LÝ; `payment_status` thành giá trị
 * SUY RA, chỉ được ghi qua Order::syncPaymentStatus() để màn admin/báo cáo cũ không vỡ.
 *
 * `*_paid_by` = ai đánh dấu đã thu (admin hoặc shipper) — cần cho đối soát tiền.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('rental_paid_at')->nullable()->after('payment_status');
            $table->foreignId('rental_paid_by')->nullable()->after('rental_paid_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('deposit_paid_at')->nullable()->after('rental_paid_by');
            $table->foreignId('deposit_paid_by')->nullable()->after('deposit_paid_at')
                ->constrained('users')->nullOnDelete();
        });

        // Backfill từ payment_status cũ. Không biết chính xác thu lúc nào nên lấy updated_at
        // (mốc gần nhất đơn được sửa) — đủ cho đối soát, và KHÔNG để trống gây hiểu là chưa thu.
        DB::table('orders')->where('payment_status', 'full')->update([
            'rental_paid_at' => DB::raw('updated_at'),
            'deposit_paid_at' => DB::raw('updated_at'),
        ]);
        // 'deposit' = đã chuyển cọc (nghĩa cũ) → chỉ cọc đã thu.
        DB::table('orders')->where('payment_status', 'deposit')->update([
            'deposit_paid_at' => DB::raw('updated_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rental_paid_by');
            $table->dropConstrainedForeignId('deposit_paid_by');
            $table->dropColumn(['rental_paid_at', 'deposit_paid_at']);
        });
    }
};

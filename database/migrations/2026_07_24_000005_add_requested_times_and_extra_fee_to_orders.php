<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Giao/trả NGOÀI KHUNG GIỜ (bopcamping-h4to, adr_turnaround_buffer mục 4 / Phase 2).
 * Khách ghi giờ nhận/trả mong muốn ở checkout (nhận sớm 6h, trả muộn 22h...); admin
 * thấy nhu cầu, liên hệ và nhập PHỤ PHÍ tay. KHÔNG ảnh hưởng tồn kho (INVARIANT).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Giờ mong muốn dạng "HH:MM" (null = theo khung giờ mặc định của shop).
            $table->string('requested_pickup_time', 5)->nullable()->after('is_half_day');
            $table->string('requested_return_time', 5)->nullable()->after('requested_pickup_time');
            // Phụ phí admin nhập tay (giao sớm/trả muộn...) — cộng vào tiền phải trả.
            $table->decimal('extra_fee', 14, 0)->default(0)->unsigned()->after('deposit_total');
            $table->string('extra_fee_note')->nullable()->after('extra_fee');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['requested_pickup_time', 'requested_return_time', 'extra_fee', 'extra_fee_note']);
        });
    }
};

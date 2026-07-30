<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gán shipper cho từng LƯỢT của đơn (bopcamping-xdvx, prd_shipper_delivery_ops mục 3).
 * Gán theo lượt (giao / thu) vì hai lượt ở hai ngày khác nhau, có thể hai người khác nhau.
 * nullOnDelete: xoá tài khoản shipper thì đơn về "chưa gán", KHÔNG mất đơn.
 *
 * KHÔNG có cột "đã giao/đã thu" — việc đó là chuyển status confirmed→renting→returned sẵn có.
 * KHÔNG có cột thứ tự đi: chủ shop bỏ chức năng kéo-thả sắp thứ tự (feedback 29/07/2026) —
 * lịch sắp theo giờ đã chốt là đủ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('pickup_shipper_id')->nullable()->after('schedule_confirmed_at')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('return_shipper_id')->nullable()->after('pickup_shipper_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pickup_shipper_id');
            $table->dropConstrainedForeignId('return_shipper_id');
        });
    }
};

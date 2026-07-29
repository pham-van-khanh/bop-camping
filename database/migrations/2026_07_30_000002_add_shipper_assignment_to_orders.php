<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gán shipper + thứ tự đi cho từng LƯỢT của đơn (bopcamping-xdvx, prd_shipper_delivery_ops mục 3).
 * Gán theo lượt (giao / thu) vì hai lượt ở hai ngày khác nhau, có thể hai người khác nhau.
 * nullOnDelete: xoá tài khoản shipper thì đơn về "chưa gán", KHÔNG mất đơn.
 * KHÔNG có cột "đã giao/đã thu" — việc đó là chuyển status confirmed→renting→returned sẵn có.
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
            // Thứ tự admin kéo-thả trong ngày; null = chưa sắp tay (xếp theo giờ đã chốt).
            $table->unsignedSmallInteger('pickup_sort')->nullable()->after('return_shipper_id');
            $table->unsignedSmallInteger('return_sort')->nullable()->after('pickup_sort');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pickup_shipper_id');
            $table->dropConstrainedForeignId('return_shipper_id');
            $table->dropColumn(['pickup_sort', 'return_sort']);
        });
    }
};

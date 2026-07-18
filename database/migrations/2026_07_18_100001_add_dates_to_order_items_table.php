<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Ngày thuê RIÊNG từng món (bopcamping-u1nb). Trước đây chỉ đơn lưu envelope
    // min-start/max-end, order_items chỉ có `days` → đơn nhiều khoảng ngày khoá tồn dư
    // (AvailabilityService đếm theo ngày đơn). Lưu ngày món để tính tồn đúng theo món.
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('days');
            $table->date('end_date')->nullable()->after('start_date');
        });

        // Backfill đơn cũ: ngày món = ngày đơn (envelope). Đơn cũ vốn 1 khoảng nên đúng.
        // Correlated subquery — chạy đúng cả MySQL lẫn SQLite (không dùng UPDATE JOIN).
        DB::statement('
            UPDATE order_items
            SET start_date = (SELECT start_date FROM orders WHERE orders.id = order_items.order_id),
                end_date = (SELECT end_date FROM orders WHERE orders.id = order_items.order_id)
        ');
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date']);
        });
    }
};

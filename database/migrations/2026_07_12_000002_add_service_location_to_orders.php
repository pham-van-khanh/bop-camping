<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Đơn thuê gắn cửa hàng (per-store stock): trừ kho đúng store, không tính xuyên cửa hàng.
 * location_auto_assigned = true khi hệ thống tự gán (khách không chọn) → admin review theo địa chỉ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('service_location_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->boolean('location_auto_assigned')->default(false)->after('service_location_id');
        });

        // Backfill đơn đang hoạt động: gán store phục vụ đầu tiên (sort_order) chung của các món.
        foreach (DB::table('orders')->whereIn('status', ['pending', 'confirmed', 'renting'])->get(['id']) as $o) {
            $locId = DB::table('order_items as oi')
                ->join('product_service_location as psl', 'psl.product_id', '=', 'oi.product_id')
                ->join('service_locations as sl', 'sl.id', '=', 'psl.service_location_id')
                ->where('oi.order_id', $o->id)
                ->where('sl.status', 'open')
                ->orderBy('sl.sort_order')
                ->orderBy('sl.id')
                ->value('psl.service_location_id');

            if ($locId) {
                DB::table('orders')->where('id', $o->id)->update(['service_location_id' => $locId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_location_id');
            $table->dropColumn('location_auto_assigned');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tồn kho theo cửa hàng (per-store stock): mỗi (sản phẩm × vị trí phục vụ) một số tồn.
 * Đây thành nguồn chân lý cho availability. products.quantity đổi vai thành "tổng".
 *
 * Di trú: dồn toàn bộ products.quantity hiện tại vào store phục vụ ĐẦU TIÊN (sort_order),
 * store còn lại = 0 — admin chỉnh lại số thật sau. Sản phẩm chỉ phục vụ 1 store thì tự đúng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_service_location', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(0);
        });

        foreach (DB::table('products')->get(['id', 'quantity']) as $p) {
            $firstLocationId = DB::table('product_service_location as psl')
                ->join('service_locations as sl', 'sl.id', '=', 'psl.service_location_id')
                ->where('psl.product_id', $p->id)
                ->orderBy('sl.sort_order')
                ->orderBy('sl.id')
                ->value('psl.service_location_id');

            if ($firstLocationId) {
                DB::table('product_service_location')
                    ->where('product_id', $p->id)
                    ->where('service_location_id', $firstLocationId)
                    ->update(['quantity' => $p->quantity]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('product_service_location', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
};

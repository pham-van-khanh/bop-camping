<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * bopcamping-1cq — pivot Product <-> ServiceLocation.
 * Mỗi sản phẩm phục vụ ở 1 hoặc nhiều vị trí (Vinh, Hà Nội...).
 * Backfill: gán TẤT CẢ vị trí đang mở cho sản phẩm cũ để không cái nào
 * biến mất khỏi trang khách sau khi triển khai filter theo vị trí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_service_location', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_location_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'service_location_id']);
        });

        // Backfill: sản phẩm hiện có -> tất cả vị trí đang mở
        $openIds = DB::table('service_locations')->where('status', 'open')->pluck('id');
        if ($openIds->isEmpty()) {
            return;
        }

        $rows = [];
        foreach (DB::table('products')->pluck('id') as $productId) {
            foreach ($openIds as $locationId) {
                $rows[] = ['product_id' => $productId, 'service_location_id' => $locationId];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('product_service_location')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_service_location');
    }
};

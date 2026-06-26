<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bỏ "miền" — gom điểm cắm trại theo TỈNH/THÀNH. Bỏ "thời gian di chuyển",
     * thay bằng link bản đồ (map_url).
     */
    public function up(): void
    {
        Schema::table('camping_spots', function (Blueprint $table) {
            $table->dropIndex(['region', 'sort_order']);
            $table->dropColumn(['region', 'travel_time']);
        });

        Schema::table('camping_spots', function (Blueprint $table) {
            $table->string('map_url')->nullable()->after('description');
            $table->index(['province', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('camping_spots', function (Blueprint $table) {
            $table->dropIndex(['province', 'sort_order']);
            $table->dropColumn('map_url');
        });

        Schema::table('camping_spots', function (Blueprint $table) {
            $table->enum('region', ['mien_bac', 'mien_trung', 'mien_nam_tay_nguyen'])->default('mien_bac')->after('name');
            $table->string('travel_time')->nullable();
            $table->index(['region', 'sort_order']);
        });
    }
};

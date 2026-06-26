<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Điểm cắm trại gợi ý (Cẩm nang cắm trại). Admin quản lý; khách xem trong modal trang chủ.
     * - region: nhóm theo miền (Bắc / Trung / Nam & Tây Nguyên).
     * - best_season_from/to: tháng 1..12; cả hai null → "Cả năm".
     * - nearest_service_location_id + travel_time: cho danh sách "ĐIỂM GỢI Ý" gần vị trí phục vụ.
     * - is_suggested: hiện ở panel hero.
     */
    public function up(): void
    {
        Schema::create('camping_spots', function (Blueprint $table) {
            $table->id();
            $table->string('name');                                          // Đồng Cao
            $table->enum('region', ['mien_bac', 'mien_trung', 'mien_nam_tay_nguyen']);
            $table->string('province');                                      // tỉnh/thành: Bắc Giang
            $table->string('district')->nullable();                          // khu vực: Ba Vì, Sóc Sơn
            $table->string('terrain_tag');                                   // Đồi cỏ, Ven hồ, Bãi biển...
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('best_season_from')->nullable();     // 1..12
            $table->unsignedTinyInteger('best_season_to')->nullable();
            $table->foreignId('nearest_service_location_id')->nullable()
                ->constrained('service_locations')->nullOnDelete();
            $table->string('travel_time')->nullable();                       // "40 phút"
            $table->boolean('is_suggested')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['region', 'sort_order']);
            $table->index('is_suggested');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('camping_spots');
    }
};

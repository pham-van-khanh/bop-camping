<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Bậc giảm giá thuê dài ngày (bopcamping-e36e). Admin cấu hình mốc ngày + %.
    // Chọn bậc: min_days lớn nhất mà days >= min_days (bậc cao đè bậc thấp). Xem
    // artifacts/adr_duration_discount.md.
    public function up(): void
    {
        Schema::create('duration_discount_tiers', function (Blueprint $table) {
            $table->id();
            // Ngày tối thiểu để hưởng bậc (inclusive) — unique để không có 2 bậc cùng mốc.
            $table->unsignedInteger('min_days')->unique();
            $table->decimal('discount_percent', 5, 2); // 0–100
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duration_discount_tiers');
    }
};

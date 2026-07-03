<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('combos', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            // Giá thuê combo/ngày. Tổng giá lẻ + % tiết kiệm tính runtime, không lưu (PRD 5.2).
            $table->decimal('combo_price', 12, 0)->unsigned();
            // Cọc combo — admin nhập tay (ADR-1).
            $table->decimal('deposit', 12, 0)->nullable()->unsigned();
            // Số người phù hợp ("gia đình 4 người", "cặp đôi" = 2) — FE render nhãn từ số.
            $table->unsignedTinyInteger('suitable_for')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combos');
    }
};

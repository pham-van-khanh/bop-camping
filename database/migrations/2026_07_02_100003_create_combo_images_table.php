<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bảng riêng theo pattern product_images / camping_spot_media — không polymorphic (ADR-3).
        Schema::create('combo_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('combo_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->enum('type', ['image', 'video'])->default('image');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combo_images');
    }
};

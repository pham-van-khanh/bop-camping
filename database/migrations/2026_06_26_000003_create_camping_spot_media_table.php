<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Ảnh + video của 1 điểm cắm trại (gallery xem ở màn chi tiết). */
    public function up(): void
    {
        Schema::create('camping_spot_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('camping_spot_id')->constrained('camping_spots')->cascadeOnDelete();
            $table->enum('type', ['image', 'video'])->default('image');
            $table->string('path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('camping_spot_media');
    }
};

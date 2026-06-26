<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vị trí shop đang phục vụ (giao nhận đồ thuê). Hiện ở panel hero "ĐANG PHỤC VỤ TẠI".
     * status: open = đang mở, coming = sắp mở.
     */
    public function up(): void
    {
        Schema::create('service_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');                              // Vinh, Hà Nội
            $table->string('area')->nullable();                  // Nghệ An, Nội thành
            $table->enum('status', ['open', 'coming'])->default('open');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_locations');
    }
};

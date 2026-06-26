<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Banner dùng chung theo vị trí (placement):
     * - hero: ảnh slideshow đầu trang chủ.
     * - promo: dải banner khuyến mãi (bấm vào link).
     */
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->enum('placement', ['hero', 'promo'])->default('hero');
            $table->string('image');                     // đường dẫn ảnh (storage hoặc /images/...)
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->string('link_url')->nullable();      // link tuỳ chọn khi bấm
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['placement', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trang tĩnh chỉnh sửa được trong admin (Epic 4): giới thiệu, sau này thêm
 * chính sách thuê, hướng dẫn... — mỗi trang 1 slug, nội dung HTML TipTap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('static_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title', 150);
            $table->string('cover_path')->nullable();
            $table->longText('content')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('static_pages');
    }
};

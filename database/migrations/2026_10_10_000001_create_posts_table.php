<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cẩm nang (bopcamping-ennp): bài viết hướng dẫn cắm trại để kéo traffic tìm kiếm
 * dạng thông tin ("checklist đồ cắm trại", "chọn lều"...) rồi dẫn sang trang thuê.
 *
 * Bảng nối post_product / combo_post: bài có thể gợi ý sản phẩm/combo liên quan —
 * xoá sản phẩm/combo/bài thì dòng nối tự đi theo (cascade), bài không bị ảnh hưởng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            $table->string('excerpt', 500)->nullable();
            $table->longText('content')->nullable();
            $table->string('cover_path')->nullable();
            // draft | published — chuỗi thay vì enum để migration chạy y hệt trên sqlite/MySQL.
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->string('seo_title', 200)->nullable();
            $table->string('seo_description', 300)->nullable();
            $table->string('author_name', 100)->nullable();
            $table->timestamps();

            // Truy vấn công khai luôn lọc status + published_at rồi sắp theo published_at.
            $table->index(['status', 'published_at']);
        });

        Schema::create('post_product', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['post_id', 'product_id']);
        });

        Schema::create('combo_post', function (Blueprint $table) {
            $table->foreignId('combo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->primary(['combo_id', 'post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combo_post');
        Schema::dropIfExists('post_product');
        Schema::dropIfExists('posts');
    }
};

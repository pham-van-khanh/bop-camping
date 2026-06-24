<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Đánh giá (KE_HOACH 8.2). 2 loại:
     * - product: gắn 1 sản phẩm (qua order_item đã thuê) — hiện ở màn chi tiết.
     * - system: trải nghiệm tổng thể shop — hiện ở trang chủ.
     * Có kiểm duyệt: pending → approved/rejected; chỉ approved hiển thị công khai.
     */
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reviewer_name');                 // snapshot tên hiển thị
            $table->unsignedTinyInteger('rating');           // 1..5
            $table->text('content')->nullable();
            $table->enum('category', ['system', 'product'])->default('product');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('admin_note')->nullable();        // lý do từ chối
            $table->string('review_token')->nullable()->unique(); // link mời đánh giá qua email
            $table->timestamp('review_token_used_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'status']);
            $table->index(['category', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};

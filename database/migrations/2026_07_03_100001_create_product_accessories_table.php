<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Case 2 — "thường thuê cùng" (PRD combo mục 4, P3): admin gán tay danh sách
 * phụ kiện hay đi kèm cho từng sản phẩm (US-08), trang sản phẩm gợi ý (US-03).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_accessories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'related_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_accessories');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Cho phép đính kèm cả ảnh lẫn video cho sản phẩm. */
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->enum('type', ['image', 'video'])->default('image')->after('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};

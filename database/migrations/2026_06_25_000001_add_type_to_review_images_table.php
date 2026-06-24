<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Cho phép đính kèm cả ảnh lẫn video trong đánh giá. */
    public function up(): void
    {
        Schema::table('review_images', function (Blueprint $table) {
            $table->enum('type', ['image', 'video'])->default('image')->after('review_id');
        });
    }

    public function down(): void
    {
        Schema::table('review_images', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phụ đề combo — câu mô tả chứa từ khoá khách tìm ("Combo cắm trại gia đình 4 người"),
 * vì tên combo là tên thương hiệu ("Relax", "Camping Family 2") không khớp truy vấn nào.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('combos', function (Blueprint $table) {
            $table->string('subtitle', 120)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('combos', function (Blueprint $table) {
            $table->dropColumn('subtitle');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * bopcamping-h1s — chi phí phát sinh nhập tay (mua thiết bị, sửa chữa, vận chuyển,
 * marketing, khác) để dựng bảng thu-chi trong trang Thống kê admin.
 * amount lưu VND nguyên (integer, khớp cách lưu tiền của Order).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->date('spent_on');
            $table->unsignedBigInteger('amount');
            $table->string('category', 30)->default('other');
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index('spent_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};

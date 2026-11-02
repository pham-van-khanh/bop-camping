<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Thống kê truy cập nội bộ: lượt xem trang, xem sản phẩm, bấm liên hệ (Zalo/gọi/Messenger). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_events', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_id', 36);
            $table->string('type', 20);
            $table->string('path', 255);
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 20)->nullable();
            $table->string('placement', 40)->nullable();
            $table->string('source', 100)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['type', 'created_at']);
            $table->index(['visitor_id', 'created_at']);
            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_events');
    }
};

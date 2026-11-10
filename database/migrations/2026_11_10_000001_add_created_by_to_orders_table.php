<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin tự tạo đơn (bopcamping-yv2w): ghi admin nào đã tạo đơn hộ khách.
 * null = khách tự đặt trên web (mọi đơn cũ). Xoá tài khoản admin → giữ đơn, bỏ dấu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('user_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};

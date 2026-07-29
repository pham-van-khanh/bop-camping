<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vai shipper (bopcamping-xdvx, adr_shipper_role_and_access mục 3).
 * Cờ boolean song song `is_admin` — cố tình KHÔNG refactor sang `role` enum để không
 * phải sửa mọi chỗ đang đọc `is_admin`. Admin KHÔNG tự động là shipper.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_shipper')->default(false)->index()->after('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_shipper']);
            $table->dropColumn('is_shipper');
        });
    }
};

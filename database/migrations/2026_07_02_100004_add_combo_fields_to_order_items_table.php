<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Combo được "bung" thành order_items per-product (PRD mục 4) — các cột này
        // null với đơn thuê lẻ; P2 (checkout combo) bắt đầu ghi dữ liệu.
        Schema::table('order_items', function (Blueprint $table) {
            // nullOnDelete: xoá combo không đụng đơn cũ — giá/cọc đã snapshot (ADR).
            $table->foreignId('combo_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            // Nhóm các item cùng 1 combo trong 1 đơn (1 đơn có thể chứa 2 combo giống nhau).
            $table->uuid('combo_group_uuid')->nullable()->after('combo_id')->index();
            // Snapshot phân bổ từ combo_price / combo.deposit theo tỷ lệ giá lẻ (PRD 5.3).
            $table->decimal('allocated_price', 12, 0)->nullable()->unsigned()->after('subtotal');
            $table->decimal('allocated_deposit', 12, 0)->nullable()->unsigned()->after('allocated_price');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('combo_id');
            $table->dropColumn(['combo_group_uuid', 'allocated_price', 'allocated_deposit']);
        });
    }
};

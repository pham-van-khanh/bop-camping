<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Đơn cha/con (bopcamping-wtuv): giỏ nhiều khoảng ngày → 1 đơn CHA (gom khách+voucher
    // +tổng, is_parent=true, không món) + N đơn CON (parent_id set, mỗi khoảng 1 con).
    // Đơn 1 khoảng và đơn cũ: parent_id=null, is_parent=false → đơn thường như hiện tại.
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // restrictOnDelete: xoá cha khi còn con phải xử lý ở app (huỷ hết con trước) — không nullOnDelete.
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('orders')->restrictOnDelete();
            $table->boolean('is_parent')->default(false)->after('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('is_parent');
        });
    }
};

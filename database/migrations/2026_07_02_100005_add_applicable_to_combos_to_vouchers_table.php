<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PRD mục 7: mặc định voucher KHÔNG áp lên giá trị combo (tránh double discount);
        // admin bật per-voucher khi muốn. P2 dùng cột này khi tính giảm giá.
        Schema::table('vouchers', function (Blueprint $table) {
            $table->boolean('applicable_to_combos')->default(false)->after('applies_to');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn('applicable_to_combos');
        });
    }
};

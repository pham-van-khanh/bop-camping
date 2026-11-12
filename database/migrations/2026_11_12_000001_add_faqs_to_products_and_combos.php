<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FAQ riêng cho từng sản phẩm / combo (bopcamping-0345): mảng [{question, answer}],
 * cùng kiểu lưu với `products.specs`. Null = chưa có câu nào.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'combos'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->json('faqs')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['products', 'combos'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('faqs');
            });
        }
    }
};

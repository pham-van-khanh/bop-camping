<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ba mẫu văn bản của hợp đồng: chính, Phụ lục A, Phụ lục B. Admin sửa ở trang "Mẫu hợp đồng"
 * bằng đúng editor TipTap + EditorHtml::clean() đang phục vụ StaticPage — không dựng cơ chế
 * soạn thảo mới.
 *
 * NULL = chưa soạn, ContractService dùng mẫu mặc định trong resources/views/contracts/defaults.
 * Hợp đồng ĐÃ KÝ giữ bản HTML riêng trong contract_signatures nên sửa mẫu ở đây không bao giờ
 * đụng được vào bản đã ký.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->longText('contract_template_html')->nullable();
            $table->longText('handover_template_html')->nullable();
            $table->longText('return_template_html')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['contract_template_html', 'handover_template_html', 'return_template_html']);
        });
    }
};

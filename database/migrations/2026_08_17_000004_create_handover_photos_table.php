<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ảnh chụp lúc giao / lúc thu đồ — BẰNG CHỨNG THỰC HIỆN hợp đồng (adr_contract_esignature
 * mục 3.2). Toà Việt Nam trên thực tế coi trọng bằng chứng thực hiện hơn là tranh cãi kỹ
 * thuật về chữ ký, nên đây là lớp chứng cứ rẻ mà mạnh.
 *
 * Cột "Ghi chú / ảnh chụp kèm" của Phụ lục A trong hợp đồng giấy chính là chỗ này.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('handover_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            // Ảnh có thể gắn vào một món cụ thể, hoặc chụp chung cả đơn (null).
            $table->foreignId('contract_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('kind', 16); // pickup | return
            $table->string('path');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handover_photos');
    }
};

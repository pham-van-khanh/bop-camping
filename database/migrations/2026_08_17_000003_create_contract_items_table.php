<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ảnh chụp danh mục đồ tại thời điểm lập hợp đồng + tình trạng hai lượt (giao / trả).
 *
 * ĐÓNG BĂNG name/accessories/replacement_value: sản phẩm đổi giá hay đổi tên về sau KHÔNG
 * được làm thay đổi hợp đồng đã lập. product_id nullable vì sản phẩm có thể bị xoá, nhưng
 * hợp đồng thì vẫn phải đọc được nguyên vẹn sau nhiều năm.
 *
 * Ba giá trị của mỗi cột tình trạng lấy đúng từ checkbox của Phụ lục A và B trên hợp đồng
 * giấy: A = Mới / Tốt / Có vết cũ; B = Như lúc giao / Hao mòn thường / Hư hỏng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('combo_name')->nullable();
            $table->string('name');
            // Hợp đồng gọi cột này là "Phụ Kiện", nhưng đặt tên parts_list cho khớp với
            // products.parts_list — và để không ai nhầm với quan hệ Product::accessories().
            $table->text('parts_list')->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('replacement_value')->default(0);

            $table->string('handover_condition', 16)->nullable();
            $table->string('handover_note', 500)->nullable();
            $table->string('return_condition', 16)->nullable();
            $table->string('return_note', 500)->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_items');
    }
};

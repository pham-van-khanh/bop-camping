<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * bopcamping-4jao — hợp đồng thuê điện tử, MỘT đơn một hợp đồng.
 *
 * Đơn CHA (is_parent) không có hợp đồng: nó chỉ gom đợt, không có ngày/đồ riêng — hợp đồng
 * bám vào đơn con. Ràng buộc đó nằm ở ContractService::createFor() chứ không ép được ở
 * tầng schema (unique order_id không phân biệt cha/con).
 *
 * CCCD: số mã hoá ở tầng ứng dụng (cast 'encrypted' trong model), ảnh 2 mặt để riêng và
 * KHÔNG sinh biến thể public. Cả hai bị xoá bởi lệnh contracts:purge-identity 90 ngày sau
 * khi hoàn cọc — Luật Bảo vệ dữ liệu cá nhân 2025.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('token', 64)->unique();

            // Danh tính bên thuê — admin nhập từ ảnh CCCD khách gửi qua Zalo.
            // TEXT chứ không string: bản mã hoá dài hơn số gốc rất nhiều.
            $table->text('signer_id_number')->nullable();
            $table->date('signer_id_issued_on')->nullable();
            $table->string('signer_id_issued_place')->nullable();
            $table->string('id_front_path')->nullable();
            $table->string('id_back_path')->nullable();

            $table->string('pdf_path')->nullable();
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};

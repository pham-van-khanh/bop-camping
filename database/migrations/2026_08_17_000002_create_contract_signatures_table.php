<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Một dòng cho MỖI giai đoạn ký: main (hợp đồng chính) / handover (Phụ lục A — bàn giao) /
 * return (Phụ lục B — nhận lại). Bám đúng ba chỗ ký của hợp đồng giấy 1408/HĐTTB.
 *
 * Cố ý TÁCH BẢNG thay vì nhân ba bộ cột trên contracts: ba giai đoạn có cùng cấu trúc dấu
 * vết, gộp lại thì truy vấn "ai ký gì lúc nào" viết một lần thay vì ba lần.
 *
 * content_html đóng băng LÚC KÝ (không phải lúc tạo hợp đồng) vì admin sửa mẫu được giữa
 * chừng; content_hash để đối chiếu bản khách ĐANG ĐỌC với bản khách BẤM KÝ — lệch thì từ
 * chối, để không ai ký thứ mình chưa đọc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 16);
            $table->longText('content_html');
            $table->char('content_hash', 64);
            $table->string('signature_path');
            $table->timestamp('signed_at');
            $table->string('signed_ip', 45)->nullable();
            $table->string('signed_user_agent', 512)->nullable();
            $table->timestamps();

            // Mỗi giai đoạn ký ĐÚNG MỘT LẦN — chặn ở DB chứ không chỉ ở controller, vì
            // hai request gửi cùng lúc vẫn lọt qua kiểm tra ở tầng PHP.
            $table->unique(['contract_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_signatures');
    }
};

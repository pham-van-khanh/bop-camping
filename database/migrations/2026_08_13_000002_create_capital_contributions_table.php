<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * bopcamping-n4qy — vốn góp của từng thành viên quản trị.
 *
 * SỔ GHI TỪNG LẦN GÓP, không phải một con số mỗi người: góp thêm về sau vẫn giữ được
 * dấu vết ai bỏ bao nhiêu, lúc nào. Tổng vốn và tỉ lệ chia lợi nhuận suy ra từ SUM —
 * thêm người thứ ba chỉ là thêm dòng, mọi tỉ lệ tự tính lại.
 *
 * Trước đây vốn ghi cứng trong FinanceService::PARTNERS. Chuyển sang bảng vì chủ shop
 * cần tự nâng vốn mà không phải sửa code + deploy.
 *
 * amount lưu VND nguyên (integer, khớp cách lưu tiền của Order và Expense).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capital_contributions', function (Blueprint $table) {
            $table->id();
            // Xoá user thì xoá luôn dòng góp vốn — giữ lại sẽ thành vốn "vô chủ",
            // tỉ lệ chia không quy được về ai.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->date('contributed_on');
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('contributed_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capital_contributions');
    }
};

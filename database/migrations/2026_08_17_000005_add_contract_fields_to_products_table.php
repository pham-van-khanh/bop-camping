<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * bopcamping-4jao — GIÁ TRỊ ĐỀN BÙ, thứ hợp đồng giấy đang thiếu.
 *
 * Điều 6.3 hợp đồng 1408/HĐTTB bắt đền "100% giá trị thiết bị theo bảng Điều 1", nhưng bảng
 * Điều 1 chỉ ghi "15-90% giá trị thiết bị" — KHÔNG có con số gốc nào để nhân tỷ lệ vào.
 * products cũng chỉ có `deposit`, vốn là tiền cọc chứ không phải giá trị món đồ. Không có
 * cột này thì khách làm mất lều xong hỏi "100% của cái gì?" là shop hết căn cứ trừ cọc.
 *
 * Mặc định 0 = "chưa khai giá". Hợp đồng in "—" chứ KHÔNG in "0 đ", vì số 0 dễ bị đọc thành
 * "đền 0 đồng" — tức là tự tay bỏ mất căn cứ đòi bồi thường.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('replacement_value')->default(0)->after('deposit');
            // Danh mục chi tiết trong túi đồ ("1 túi đựng, 8 dây căng lều, 16 cọc ghim đất")
            // — in vào Phụ lục A/B để lúc thu đồ còn biết phải đếm những gì.
            //
            // TÊN CỘT KHÔNG ĐƯỢC LÀ 'accessories': Product::accessories() đã là quan hệ
            // BelongsToMany (các sản phẩm cho thuê kèm). Cột trùng tên sẽ CHE quan hệ đó,
            // và $product->accessories trả về chuỗi thay vì Collection — trang admin sản
            // phẩm nổ "pluck() on null". Đã dính đúng bẫy này một lần.
            $table->text('parts_list')->nullable()->after('replacement_value');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['replacement_value', 'parts_list']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * bopcamping-saeb — đánh giá cho combo.
 *
 * `combo_id` nằm CẠNH `product_id` (cả hai nullable) chứ không làm bảng polymorphic:
 * schema dự án đang theo lối FK phẳng + nullOnDelete (order_item_id/product_id/user_id
 * đứng cạnh nhau), đổi sang reviewable_type/reviewable_id sẽ phải viết lại mọi chỗ
 * dùng product_id cho một nhu cầu chỉ có hai loại thực thể.
 *
 * `category` chuyển từ enum sang string(20) để thêm 'combo': enum trong MySQL phải ALTER
 * bằng SQL thô và mỗi lần thêm giá trị lại sửa schema, còn sqlite thì không có enum thật.
 * Cùng lý do đã chốt ở migration payment_status (bopcamping-7be): ràng buộc giá trị ở
 * tầng app (validate + in_array), không ở DDL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->foreignId('combo_id')->nullable()->after('product_id')
                ->constrained('combos')->nullOnDelete();
            // Trang chi tiết combo luôn truy theo cặp (combo_id, status=approved) — cùng
            // hình dáng index [product_id, status] đã có cho sản phẩm.
            $table->index(['combo_id', 'status']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->string('category', 20)->default('product')->change();
        });
    }

    public function down(): void
    {
        // Gỡ combo_id là mất chỗ neo của đánh giá combo, nên phải hạ category về giá trị
        // mà bản cũ hiểu được TRƯỚC. Không làm thì các dòng 'combo' còn nguyên và admin
        // của bản cũ (chỉ biết system/product) hiển thị chúng thành "Sản phẩm" — sai hẳn
        // thứ đang được đánh giá. Hạ về 'system' chứ không 'product': mất combo_id rồi thì
        // đánh giá không còn gắn với món nào, 'system' (nói về shop) là chỗ đúng hơn.
        DB::table('reviews')->where('category', 'combo')->update(['category' => 'system']);

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['combo_id']);
            $table->dropIndex(['combo_id', 'status']);
            $table->dropColumn('combo_id');
        });

        // `category` CỐ Ý giữ string(20), không trả về enum: bản cũ đọc string bình thường
        // (enum chỉ là ràng buộc ghi), còn ép về enum thì cần liệt kê lại đúng danh sách cũ
        // và sẽ vỡ nếu sau này có thêm loại. Đây là thay đổi tiến-một-chiều có chủ ý.
    }
};

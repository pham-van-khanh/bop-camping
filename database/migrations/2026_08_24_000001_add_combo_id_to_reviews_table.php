<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['combo_id']);
            $table->dropIndex(['combo_id', 'status']);
            $table->dropColumn('combo_id');
        });
    }
};

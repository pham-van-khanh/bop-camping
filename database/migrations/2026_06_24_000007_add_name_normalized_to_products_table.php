<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tìm kiếm KHÔNG DẤU: lưu sẵn tên đã bỏ dấu để LIKE khớp "leu" với "Lều".
     * Cột được Product model tự cập nhật mỗi khi name đổi (xem Product::booted()).
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('name_normalized')->nullable()->after('name')->index();
        });

        // Backfill dữ liệu hiện có.
        foreach (DB::table('products')->select('id', 'name')->get() as $row) {
            DB::table('products')->where('id', $row->id)
                ->update(['name_normalized' => Product::normalizeText($row->name)]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['name_normalized']);
            $table->dropColumn('name_normalized');
        });
    }
};

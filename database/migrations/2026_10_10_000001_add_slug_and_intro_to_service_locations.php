<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * bopcamping-y05c — trang khu vực /thue-do-cam-trai/{slug}.
 *
 * Trước đây slug của cơ sở là ACCESSOR tính từ tên (Str::slug('Hà Nội') = 'ha-noi') và chỉ
 * dùng làm khoá lọc ?vi-tri=. Giờ nó thành một phần URL được index, nên phải là CỘT THẬT:
 * đổi tên cơ sở (vd "Vinh" -> "Kho Vinh") không được làm đổi URL đã lên Google.
 *
 * Backfill bằng đúng công thức cũ (Str::slug(name)) nên ?vi-tri=vinh / ?vi-tri=ha-noi đang
 * nằm trong link, bookmark, quảng cáo... vẫn resolve như trước. Viết bằng query builder,
 * không qua model, để migration không phụ thuộc code model về sau.
 *
 * `intro`: đoạn giới thiệu khu vực do admin viết — trang có fallback tự sinh từ dữ liệu
 * thật khi để trống.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_locations', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
            $table->text('intro')->nullable()->after('map_url');
        });

        $taken = [];
        foreach (DB::table('service_locations')->orderBy('id')->get(['id', 'name']) as $row) {
            $base = Str::slug((string) $row->name) ?: 'co-so';
            $slug = $base;
            $i = 1;
            while (in_array($slug, $taken, true)) {
                $slug = $base.'-'.$i++;
            }
            $taken[] = $slug;
            DB::table('service_locations')->where('id', $row->id)->update(['slug' => $slug]);
        }

        Schema::table('service_locations', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('service_locations', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'intro']);
        });
    }
};

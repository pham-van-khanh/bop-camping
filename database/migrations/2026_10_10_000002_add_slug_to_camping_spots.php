<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * bopcamping-y05c — trang riêng cho từng điểm cắm trại /diem-cam-trai/{slug}.
 *
 * Trước đây điểm cắm trại chỉ hiện trong modal "Cẩm nang" ở trang chủ — không có URL nên
 * Google không index được truy vấn "cắm trại ở <địa điểm>" nào.
 *
 * Backfill slug từ tên, trùng thì thêm -1, -2... (cùng quy ước App\Support\Slug).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('camping_spots', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        $taken = [];
        foreach (DB::table('camping_spots')->orderBy('id')->get(['id', 'name']) as $row) {
            $base = Str::slug((string) $row->name) ?: 'diem-cam-trai';
            $slug = $base;
            $i = 1;
            while (in_array($slug, $taken, true)) {
                $slug = $base.'-'.$i++;
            }
            $taken[] = $slug;
            DB::table('camping_spots')->where('id', $row->id)->update(['slug' => $slug]);
        }

        Schema::table('camping_spots', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('camping_spots', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};

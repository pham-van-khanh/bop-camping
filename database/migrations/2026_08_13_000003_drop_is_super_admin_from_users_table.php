<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * bopcamping-xlmy — bỏ hẳn phân quyền super admin.
 *
 * Chủ shop dùng thử rồi đổi ý: MỌI admin đều được sửa số liệu thu chi và vốn góp. Cột
 * này không còn chỗ nào đọc nữa nên xoá luôn thay vì để lại schema chết — cột quyền bỏ
 * quên là thứ dễ bị code sau này tưởng còn hiệu lực.
 *
 * down() dựng lại cột và gán cho admin có id nhỏ nhất, đúng cách migration trước đã làm.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('is_admin');
        });

        $firstAdminId = DB::table('users')->where('is_admin', true)->min('id');
        if ($firstAdminId !== null) {
            DB::table('users')->where('id', $firstAdminId)->update(['is_super_admin' => true]);
        }
    }
};

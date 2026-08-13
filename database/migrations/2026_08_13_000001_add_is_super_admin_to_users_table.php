<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * bopcamping-n4qy — super admin: tài khoản DUY NHẤT được sửa số liệu thu chi
 * (khoản chi, vốn góp). Admin khác vẫn xem được màn Tài chính nhưng không sửa.
 *
 * Chủ shop nói "tài khoản admin id = 1". Nhưng đo trên DB: KHÔNG có user id 1 nào là
 * admin (admin thật là id 2 và 7) — ghi cứng id 1 thì không ai là super admin và màn
 * Tài chính khoá cứng với tất cả. Nên dùng cờ, và gán cho admin có id NHỎ NHẤT (tài
 * khoản admin đầu tiên, đúng ý "id 1"). Đổi người thì update cột này.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('is_admin');
        });

        $firstAdminId = DB::table('users')->where('is_admin', true)->min('id');
        if ($firstAdminId !== null) {
            DB::table('users')->where('id', $firstAdminId)->update(['is_super_admin' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
        });
    }
};

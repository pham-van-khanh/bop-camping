<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Đăng nhập OTP qua email (KE_HOACH 8.1): email trở thành BẮT BUỘC.
     * User cũ (tạo bằng SĐT+tên, chưa có email) → backfill email tạm theo phone;
     * khách sẽ bổ sung email thật + verify ở lần đăng nhập kế tiếp (luồng s2q).
     */
    public function up(): void
    {
        foreach (DB::table('users')->whereNull('email')->get(['id', 'phone']) as $u) {
            $local = $u->phone ?: ('user'.$u->id);
            DB::table('users')->where('id', $u->id)
                ->update(['email' => $local.'@bopcamping.local']);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }
};

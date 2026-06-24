<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lưu OTP đăng nhập gửi qua email (KE_HOACH 8.1).
     * - code: LƯU ĐÃ HASH (không bao giờ lưu OTP thô).
     * - OTP 6 số, hết hạn 10', dùng 1 lần (consumed_at).
     * - attempts: đếm số lần nhập sai để khoá brute-force (dùng ở luồng s2q).
     */
    public function up(): void
    {
        Schema::create('email_otps', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('code'); // hashed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_otps');
    }
};

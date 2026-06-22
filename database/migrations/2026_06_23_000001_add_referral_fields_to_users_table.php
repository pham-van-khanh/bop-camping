<?php

use App\Models\User;
use App\Support\ReferralCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 12)->nullable()->unique()->after('phone');
            $table->foreignId('referred_by')->nullable()->after('referral_code')
                ->constrained('users')->nullOnDelete();
        });

        // Backfill mã giới thiệu cho user đã có (dev/prod). Test dùng :memory: nên bảng rỗng.
        User::whereNull('referral_code')->get()->each(function (User $user) {
            $user->forceFill(['referral_code' => ReferralCode::generate()])->save();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by');
            $table->dropUnique(['referral_code']);
            $table->dropColumn('referral_code');
        });
    }
};

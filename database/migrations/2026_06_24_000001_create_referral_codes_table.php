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
        Schema::create('referral_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('code', 20)->unique();
            $table->timestamps();
        });

        // Backfill: mỗi user hiện có 1 mã. (Test dùng :memory: nên bảng rỗng.)
        User::query()->each(function (User $user) {
            App\Models\ReferralCode::firstOrCreate(
                ['user_id' => $user->id],
                ['code' => ReferralCode::generate()],
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_codes');
    }
};

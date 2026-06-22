<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('referral_enabled')->default(true);
            $table->enum('referee_discount_type', ['fixed', 'percent'])->default('percent');
            $table->decimal('referee_discount_value', 12, 2)->default(10);
            $table->enum('referrer_reward_type', ['fixed', 'percent'])->default('fixed');
            $table->decimal('referrer_reward_value', 12, 2)->default(30000);
            $table->boolean('referrer_reward_per_referral')->default(true);
            $table->decimal('max_discount_percent_per_order', 5, 2)->default(25); // VAN AN TOÀN
            $table->unsignedInteger('max_vouchers_stack_per_order')->default(2);
            $table->unsignedInteger('voucher_validity_days')->default(90);
            $table->decimal('min_order_amount', 12, 2)->default(0);
            $table->enum('discount_applies_to', ['rental_fee', 'total'])->default('rental_fee');
            $table->string('conversion_trigger_status')->default('returned'); // adapt: dự án dùng 'returned'
            $table->unsignedInteger('max_referrals_per_user_per_month')->default(0); // 0 = không giới hạn
            $table->boolean('reward_clawback_enabled')->default(true);
            $table->timestamps();
        });

        // Seed 1 dòng mặc định (singleton).
        DB::table('promotion_settings')->insert([
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_settings');
    }
};

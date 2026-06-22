<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thay bảng vouchers đơn giản (v1) bằng schema đầy đủ theo REFERRAL_VOUCHER_SPEC.
 * Dev: chấp nhận bỏ dữ liệu voucher cũ (chỉ là dữ liệu test).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('vouchers');

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // chủ sở hữu
            $table->string('code', 30)->unique();                            // mã nhập tay ở checkout
            $table->enum('type', ['fixed', 'percent'])->default('fixed');
            $table->decimal('value', 12, 2);                                 // số tiền (fixed) hoặc % (percent)
            $table->enum('source', ['referral_reward', 'first_order', 'manual_admin', 'campaign'])->default('referral_reward');
            $table->foreignId('referral_id')->nullable()->constrained('referrals')->nullOnDelete();
            $table->enum('status', ['active', 'used', 'expired', 'revoked'])->default('active');
            $table->decimal('min_order_amount', 12, 2)->nullable();
            $table->enum('applies_to', ['rental_fee', 'total'])->default('rental_fee');
            $table->unsignedInteger('max_uses')->default(1);
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');

        // Khôi phục schema v1 tối thiểu (đủ để rollback chạy).
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->unsignedInteger('amount');
            $table->string('source')->default('referral_referee');
            $table->foreignId('referred_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('trigger_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }
};

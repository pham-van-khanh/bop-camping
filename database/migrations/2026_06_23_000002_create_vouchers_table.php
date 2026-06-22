<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // chủ sở hữu (người redeem)
            $table->string('code')->unique();                               // VC-XXXXXX
            $table->unsignedInteger('amount');                              // số tiền giảm (VND)
            $table->string('source')->default('referral_referee');          // referral_referrer | referral_referee
            $table->foreignId('referred_user_id')->nullable()->constrained('users')->nullOnDelete(); // referee đã kích hoạt
            $table->foreignId('trigger_order_id')->nullable()->constrained('orders')->nullOnDelete(); // đơn returned kích hoạt
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();         // đơn đã dùng voucher
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Idempotency: mỗi referee chỉ sinh tối đa 1 voucher cho mỗi source.
            $table->unique(['referred_user_id', 'source']);
            $table->index(['user_id', 'redeemed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};

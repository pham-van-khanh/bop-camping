<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete(); // người sở hữu mã
            $table->foreignId('referee_id')->nullable()->constrained('users')->nullOnDelete(); // người dùng mã
            $table->string('code_used', 20);                                          // snapshot mã đã dùng
            $table->enum('status', ['pending', 'converted', 'rejected', 'expired'])->default('pending');
            $table->foreignId('first_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            // 1 referee chỉ được tính referral 1 lần (bất kể trạng thái) — chống dùng nhiều mã.
            $table->unique('referee_id');
            $table->index(['referrer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};

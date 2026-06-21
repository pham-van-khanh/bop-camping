<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->unique(); // BOP-XXXX
            $table->string('customer_name');
            $table->string('customer_phone', 20);
            $table->string('customer_address')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_price', 14, 0)->default(0)->unsigned();
            $table->decimal('deposit_total', 14, 0)->default(0)->unsigned();
            $table->enum('status', ['pending', 'confirmed', 'renting', 'returned', 'cancelled'])->default('pending');
            $table->string('payment_method')->default('cod');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['start_date', 'end_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

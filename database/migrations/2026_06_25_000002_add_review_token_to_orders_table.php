<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Token mời đánh giá sau chuyến đi (KE_HOACH 8.2): khi đơn 'returned',
     * gửi mail kèm link /danh-gia/{token} để khách viết đánh giá không cần đăng nhập.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('review_token')->nullable()->unique()->after('customer_email');
            $table->timestamp('review_invited_at')->nullable();
            $table->timestamp('review_submitted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['review_token', 'review_invited_at', 'review_submitted_at']);
        });
    }
};

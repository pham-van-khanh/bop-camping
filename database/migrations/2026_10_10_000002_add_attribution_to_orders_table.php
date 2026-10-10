<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nguồn đơn (bopcamping-wi6h): first-touch UTM + nơi giới thiệu + loại click id quảng cáo.
 * Ghi lúc khách đặt đơn từ cookie `bop_attr` (xem AttributionService). Đơn cũ = null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 150)->nullable();
            $table->string('utm_content', 150)->nullable();
            $table->string('utm_term', 150)->nullable();
            $table->string('referrer_host', 100)->nullable();
            $table->string('click_id_type', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'referrer_host', 'click_id_type']);
        });
    }
};

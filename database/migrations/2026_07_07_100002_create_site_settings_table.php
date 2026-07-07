<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thông tin liên hệ/mạng xã hội (singleton — 1 dòng, mirror promotion_settings).
 * Footer + dải Zalo đọc qua shared prop 'site'. Địa chỉ KHÔNG ở đây — lấy từ
 * service_locations (single source). ADR home_faq_contact B4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('hotline_primary', 20)->nullable();
            $table->string('hotline_secondary', 20)->nullable();
            $table->string('zalo1_label', 60)->nullable();
            $table->string('zalo1_phone', 20)->nullable();
            $table->string('zalo1_url')->nullable();   // override; trống => zalo.me/{zalo1_phone}
            $table->string('zalo2_label', 60)->nullable();
            $table->string('zalo2_phone', 20)->nullable();
            $table->string('zalo2_url')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('tiktok_url')->nullable();
            $table->string('working_hours', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};

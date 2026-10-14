<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meta Pixel + TikTok Pixel (bopcamping-wi6h) — admin nhập ở Cài đặt shop giống mã GA4.
 * Trống = không chèn script nào.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('meta_pixel_id', 30)->nullable()->after('google_site_verification');
            $table->string('tiktok_pixel_id', 40)->nullable()->after('meta_pixel_id');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['meta_pixel_id', 'tiktok_pixel_id']);
        });
    }
};

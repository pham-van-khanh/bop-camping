<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SEO nhập từ admin (Epic 5): mã đo lường GA4 + mã xác minh Google Search Console.
 * Trống = không chèn script/meta (không hard-code, không bật theo dõi ngoài ý muốn).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('ga_measurement_id', 40)->nullable()->after('working_hours');
            $table->string('google_site_verification', 120)->nullable()->after('ga_measurement_id');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['ga_measurement_id', 'google_site_verification']);
        });
    }
};

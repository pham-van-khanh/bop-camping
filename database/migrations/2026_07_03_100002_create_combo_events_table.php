<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-09 — event log cho cart combo detection (PRD 5.4): combo_suggestion_shown /
 * combo_suggestion_converted. Bảng đơn giản, đủ cho dashboard convert-rate,
 * không cần package analytics.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('combo_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('combo_id')->constrained()->cascadeOnDelete();
            $table->string('event', 20)->index();      // shown | converted
            $table->string('suggestion_type', 20);     // exact | superset | upsell
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combo_events');
    }
};

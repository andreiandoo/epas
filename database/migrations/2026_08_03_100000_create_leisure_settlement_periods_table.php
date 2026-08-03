<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-event, per-period bookkeeping flags for the leisure settlement (decont)
 * accordion in the event "Deconturi" tab. Pure STATE tracking — it does NOT touch
 * money/payouts. "generated_at" = the organizer ticked "S-a generat decont?" for
 * that biweekly period; "settled_at" = ticked "S-a lichidat decont?". Figures are
 * always recomputed live (SalesBreakdownService); only these two flags persist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leisure_settlement_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'period_from'], 'lsp_event_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leisure_settlement_periods');
    }
};

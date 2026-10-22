<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per fiscal-document notice sent to an organizer, so each
     * problem (cerere missing, cerere outdated, impozit + PV due) is
     * announced once and never repeated.
     */
    public function up(): void
    {
        Schema::create('event_document_notices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('marketplace_organizer_id')->nullable();
            // cerere_missing | cerere_stale:{document id} | impozit_pv_due
            $table->string('notice_key', 80);
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['event_id', 'notice_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_document_notices');
    }
};

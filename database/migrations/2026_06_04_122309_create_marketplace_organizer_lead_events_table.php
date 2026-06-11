<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-lead activity timeline. Each row is a single thing that happened:
 * a page view, a status change, a manual note, an email sent, a phone
 * call, etc. Rows can be ANONYMOUS (lead_id null + session_token set)
 * during the pre-submission window — the moment a form is submitted,
 * the LeadsController fills in lead_id for every prior row with the
 * matching session_token, so the timeline shows the complete journey.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_organizer_lead_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained('marketplace_organizer_leads')->cascadeOnDelete();
            $table->foreignId('marketplace_client_id')->constrained()->cascadeOnDelete();
            $table->string('session_token', 80)->nullable()->index();

            // Event type — kept as string, app defines the vocabulary
            // (page_view_landing, page_view_onboarding, form_submitted,
            //  status_changed, note, email_sent, call, demo_scheduled, …)
            $table->string('event_type', 60)->index();
            $table->text('summary')->nullable();
            $table->json('payload')->nullable();

            // Actor — for admin-driven events; null for visitor-side events
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('page_url', 500)->nullable();

            $table->timestamps();

            $table->index(['lead_id', 'created_at']);
            $table->index(['marketplace_client_id', 'event_type', 'created_at'], 'lead_events_mc_type_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_organizer_lead_events');
    }
};

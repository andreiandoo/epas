<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lead pipeline for prospective organizers signing up via
 * /devino-partener + /inregistrare-locatie on bilete.online (and any
 * other leisure marketplace that wires up the same flow).
 *
 * The `session_token` column carries the cookie-set UUID so anonymous
 * funnel events tracked BEFORE submission can be linked to the lead
 * the moment the form is submitted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_organizer_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->cascadeOnDelete();
            $table->string('session_token', 80)->nullable()->index();

            // Contact identity
            $table->string('contact_name', 160);
            $table->string('email', 190)->index();
            $table->string('phone', 40)->nullable();

            // Location info
            $table->string('location_name', 200);
            $table->string('city', 120);
            $table->string('website', 255)->nullable();

            // What they sell
            $table->string('category_slug', 120)->nullable()->index();
            $table->string('category_name', 200)->nullable();
            $table->string('category_other', 200)->nullable();
            $table->string('volume_estimate', 40)->nullable();
            $table->text('notes')->nullable();

            // Pipeline state — kept as a string column (no DB enum) so
            // we can add new states without an ALTER. App enforces values.
            $table->string('status', 40)->default('new')->index();
            $table->string('source', 60)->default('partner_signup');
            $table->string('source_detail', 200)->nullable();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('next_action_at')->nullable();

            // Acquisition tracking (from the form)
            $table->string('prefill_tip', 80)->nullable();
            $table->string('prefill_loc', 200)->nullable();
            $table->text('referrer')->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 150)->nullable();
            $table->string('utm_content', 150)->nullable();
            $table->string('utm_term', 150)->nullable();

            // Funnel timestamps — populated by tracking events
            $table->timestamp('first_landing_at')->nullable();
            $table->timestamp('first_onboarding_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('ghosted_at')->nullable();

            // Visit counts kept denormalized for cheap dashboard sorts
            $table->unsignedInteger('landing_views')->default(0);
            $table->unsignedInteger('onboarding_views')->default(0);

            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['marketplace_client_id', 'status']);
            $table->index(['marketplace_client_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_organizer_leads');
    }
};

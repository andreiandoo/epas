<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved itineraries from the bilete.online trip planner (/plan).
 *
 * The planner works entirely in the browser and keeps a plan in the URL and in localStorage; this
 * table is only for the travellers who want one kept against their account. The plan itself is
 * stored as the planner's own compact payload — attraction slugs, days, locks and per-stop
 * durations — rather than normalised rows, because the catalogue is the source of truth for
 * everything else and a plan must not go stale when a place is renamed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_trip_plans')) {
            return;
        }

        Schema::create('marketplace_trip_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained('marketplace_clients')->cascadeOnDelete();
            $table->foreignId('marketplace_customer_id')->constrained('marketplace_customers')->cascadeOnDelete();

            // The share token is what a link carries, so it is random, not the row id.
            $table->string('token', 32)->unique();
            $table->string('title', 160);
            $table->string('place', 120)->nullable();      // "Cluj-Napoca", "Bucovina"
            $table->unsignedTinyInteger('days')->default(1);
            $table->unsignedSmallInteger('stops')->default(0);
            $table->date('starts_on')->nullable();
            $table->json('payload');                        // the planner's own state

            $table->timestamps();

            $table->index(['marketplace_customer_id', 'updated_at'], 'mtp_customer_idx');
            $table->index(['marketplace_client_id', 'updated_at'], 'mtp_client_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_trip_plans');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Media-partners microservice — the event feed partners read.
 *
 * One row per marketplace event, holding the exact payload served on
 * /api/partner/v1/events and its hash. partners:refresh-event-feed recomputes
 * the payload every few minutes and bumps changed_at only when the hash differs,
 * which catches price, artist and availability changes that never touch
 * events.updated_at. Events are hard-deleted, so the row stays behind with
 * removed_at set and is served on /events/deleted.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('partner_event_feed', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')
                ->constrained('marketplace_clients')
                ->cascadeOnDelete();

            // Not FK-constrained: the row must outlive a deleted event.
            $table->unsignedBigInteger('event_id');

            // published | cancelled | postponed
            $table->string('status', 16);
            $table->timestamp('starts_at')->nullable();
            // End of the event, or 23:59 on its last day; drives time_scope.
            $table->timestamp('listed_until')->nullable();
            // Lowercase ASCII city, for the ?city= filter.
            $table->string('city_key', 191)->nullable();
            $table->unsignedBigInteger('category_id')->nullable();

            $table->json('payload');
            $table->string('payload_hash', 64);
            $table->timestamp('changed_at')->useCurrent();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->unique(['marketplace_client_id', 'event_id']);
            $table->index(['marketplace_client_id', 'changed_at']);
            $table->index(['marketplace_client_id', 'starts_at']);
            $table->index(['marketplace_client_id', 'listed_until']);
            $table->index(['marketplace_client_id', 'removed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_event_feed');
    }
};

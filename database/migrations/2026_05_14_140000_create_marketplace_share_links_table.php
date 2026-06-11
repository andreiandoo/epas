<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moves marketplace organizer share-monitoring links from a flat JSON
 * file (epas/resources/marketplaces/ambilet/data/share-links.json) to
 * the database so they survive the cPanel auto-pull / deploy cycle.
 *
 * Background: the proxy.php on ambilet read/wrote share-links.json
 * directly. The file was gitignored and the webhook deploy script
 * preserved data/ — but the actual deploy mechanism on the box is
 * cPanel's auto-pull, not the webhook, and any deploy that effectively
 * runs git clean -fdx (or replaces public_html) wipes untracked
 * files. Result: organizers kept losing their generated links on
 * every push. DB rows are immune.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('marketplace_share_links')) {
            return;
        }

        Schema::create('marketplace_share_links', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->unsignedBigInteger('marketplace_client_id');
            $table->unsignedBigInteger('marketplace_organizer_id');
            $table->string('name', 150)->default('');
            $table->json('event_ids');
            $table->boolean('is_active')->default(true);
            $table->boolean('has_password')->default(false);
            $table->string('password_hash')->nullable();
            $table->boolean('show_participants')->default(false);
            $table->boolean('show_revenue')->default(false);

            // Cached totals so the public view doesn't re-hit the API on
            // every load. Refreshed on update and on demand.
            $table->json('ticket_data')->nullable();
            $table->json('participants_data')->nullable();
            $table->timestamp('ticket_data_updated_at')->nullable();

            // Access analytics
            $table->unsignedInteger('access_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();

            $table->timestamps();

            $table->index(['marketplace_organizer_id', 'is_active'], 'mp_share_links_org_active_idx');
            $table->index(['marketplace_client_id', 'is_active'], 'mp_share_links_client_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_share_links');
    }
};

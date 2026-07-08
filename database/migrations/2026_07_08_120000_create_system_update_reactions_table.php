<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('system_update_reactions')) {
            return;
        }

        Schema::create('system_update_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_update_id')
                ->constrained('system_updates')
                ->cascadeOnDelete();
            // Session-based identity — no login required. The public page
            // sets a long-lived cookie on first reaction; subsequent
            // toggles pass the same hash so we can (a) prevent duplicate
            // votes from the same browser, (b) show back which reactions
            // the visitor has already given.
            $table->char('session_hash', 64);
            // Fixed set of reaction types (enforced at the model + API layer).
            // Storing as string keeps future additions migration-free.
            $table->string('reaction_type', 32);
            $table->timestamps();

            // One vote per (update, session, type). Toggling deletes the row.
            $table->unique(['system_update_id', 'session_hash', 'reaction_type'], 'sur_unique');
            // Fast aggregation by update.
            $table->index(['system_update_id', 'reaction_type'], 'sur_by_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_update_reactions');
    }
};

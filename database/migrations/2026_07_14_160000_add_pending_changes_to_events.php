<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pending-changes store for live (published) event edits.
 *
 * Organizers WITHOUT the allow_live_edits flag can still edit a live event, but
 * the changes don't touch the live record — they're parked here (event stays
 * published with its current data) until a marketplace admin approves them.
 *
 * Only "safe" scalar/relation fields are ever stored/applied this way; ticket
 * type restructuring is never part of a live edit (it would destroy sold
 * tickets), so it's stripped before storing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'pending_changes')) {
                $table->json('pending_changes')->nullable();
            }
            if (!Schema::hasColumn('events', 'pending_changes_status')) {
                $table->string('pending_changes_status', 20)->nullable();
            }
            if (!Schema::hasColumn('events', 'pending_changes_submitted_at')) {
                $table->timestamp('pending_changes_submitted_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            foreach (['pending_changes', 'pending_changes_status', 'pending_changes_submitted_at'] as $col) {
                if (Schema::hasColumn('events', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

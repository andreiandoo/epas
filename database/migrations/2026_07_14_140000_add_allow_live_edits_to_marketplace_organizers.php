<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-organizer "live edits" flag.
 *
 * By default an organizer cannot edit an event once it's published/live —
 * EventsController::update() returns 403 ("contact support"). When this flag
 * is on, the organizer may edit their live events directly from their account
 * and the changes publish immediately, without going through approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_organizers', 'allow_live_edits')) {
                $table->boolean('allow_live_edits')
                    ->default(false)
                    ->after('test_pos_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_organizers', 'allow_live_edits')) {
                $table->dropColumn('allow_live_edits');
            }
        });
    }
};

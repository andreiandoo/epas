<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add "online event" columns to events.
 *
 * Design notes:
 *  - is_online is a plain boolean flag rather than a value in
 *    manifestation_type/display_template because it composes with all
 *    other event types (a "muzicala" event can be online). Keeping it
 *    orthogonal avoids a combinatorial UI later.
 *  - online_meeting_url stays plaintext (short string, low sensitivity;
 *    the URL alone is inert without the passcode + our /join gate).
 *    online_passcode is encrypted at rest via a model mutator so a
 *    leaked DB dump doesn't expose meeting passcodes.
 *  - online_provider is a string enum enforced at the model + form
 *    layer so future providers (google_meet, teams, custom) add without
 *    a schema migration.
 *  - online_capacity_hint lets the organizer declare their Zoom plan
 *    cap (100 / 300 / 500 / 1000 / null) so we can render a soft
 *    warning when total tickets exceed it. NOT auto-detected in MVP;
 *    Phase 2 (OAuth) will fill this from the Zoom API.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'is_online')) {
                $table->boolean('is_online')->default(false)->after('venue_id');
            }
            if (! Schema::hasColumn('events', 'online_provider')) {
                $table->string('online_provider', 32)->nullable()->after('is_online');
            }
            if (! Schema::hasColumn('events', 'online_meeting_url')) {
                $table->string('online_meeting_url', 500)->nullable()->after('online_provider');
            }
            if (! Schema::hasColumn('events', 'online_passcode')) {
                // Encrypted at rest via Event::setOnlinePasscodeAttribute.
                // Widened to text because encrypted strings are ~2× plaintext.
                $table->text('online_passcode')->nullable()->after('online_meeting_url');
            }
            if (! Schema::hasColumn('events', 'online_instructions')) {
                // Short WYSIWYG (dresscode virtual, tips audio, etc.).
                // Sanitized on save via HTMLPurifier (system_update profile).
                $table->text('online_instructions')->nullable()->after('online_passcode');
            }
            if (! Schema::hasColumn('events', 'online_lobby_opens_minutes_before')) {
                $table->integer('online_lobby_opens_minutes_before')
                    ->default(15)
                    ->after('online_instructions');
            }
            if (! Schema::hasColumn('events', 'online_capacity_hint')) {
                // Organizer-declared Zoom plan cap for soft warning at
                // publish. Null = unknown, don't warn.
                $table->integer('online_capacity_hint')->nullable()->after('online_lobby_opens_minutes_before');
            }
        });

        // Index for the "list online events" filter in reports.
        if (Schema::hasColumn('events', 'is_online')) {
            Schema::table('events', function (Blueprint $table) {
                try {
                    $table->index(['marketplace_client_id', 'is_online'], 'events_marketplace_online_idx');
                } catch (\Throwable $e) {
                    // Index already exists on rerun — swallow.
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            try { $table->dropIndex('events_marketplace_online_idx'); } catch (\Throwable $e) {}
            $cols = [
                'is_online',
                'online_provider',
                'online_meeting_url',
                'online_passcode',
                'online_instructions',
                'online_lobby_opens_minutes_before',
                'online_capacity_hint',
            ];
            foreach ($cols as $c) {
                if (Schema::hasColumn('events', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};

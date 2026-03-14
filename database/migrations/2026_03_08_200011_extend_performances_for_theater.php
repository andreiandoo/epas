<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Each column wrapped in hasColumn check because a previous partial run
        // may have added some columns before failing on the index.
        Schema::table('performances', function (Blueprint $table) {
            if (!Schema::hasColumn('performances', 'season_id')) {
                $table->foreignId('season_id')->nullable()->after('event_id')
                    ->constrained('seasons')->nullOnDelete();
            }

            if (!Schema::hasColumn('performances', 'label')) {
                $table->string('label')->nullable()->after('status')
                    ->comment('Display label, e.g. "Matineu", "Seara", "Premiera"');
            }

            if (!Schema::hasColumn('performances', 'is_premiere')) {
                $table->boolean('is_premiere')->default(false)->after('label');
            }

            if (!Schema::hasColumn('performances', 'door_time')) {
                $table->string('door_time', 5)->nullable()->after('ends_at')
                    ->comment('Door open time HH:MM');
            }

            if (!Schema::hasColumn('performances', 'special_guests')) {
                $table->json('special_guests')->nullable()->after('is_premiere')
                    ->comment('Guest performers for this specific showing: [{name, role}]');
            }

            if (!Schema::hasColumn('performances', 'notes')) {
                $table->json('notes')->nullable()->after('special_guests')
                    ->comment('Translatable internal/public notes');
            }

            if (!Schema::hasColumn('performances', 'ticket_overrides')) {
                $table->json('ticket_overrides')->nullable()->after('notes')
                    ->comment('Per-performance ticket type overrides: [{ticket_type_id, price_cents, quota}]');
            }

            if (!Schema::hasColumn('performances', 'capacity_override')) {
                $table->integer('capacity_override')->nullable()->after('ticket_overrides')
                    ->comment('Override event capacity for this specific performance');
            }
        });

        if (!Schema::hasIndex('performances', 'performances_season_id_index')) {
            Schema::table('performances', function (Blueprint $table) {
                $table->index(['season_id']);
            });
        }

        if (!Schema::hasIndex('performances', 'performances_event_id_starts_at_index')) {
            Schema::table('performances', function (Blueprint $table) {
                $table->index(['event_id', 'starts_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('performances', function (Blueprint $table) {
            if (Schema::hasColumn('performances', 'season_id')) {
                $table->dropForeign(['season_id']);
                $table->dropIndex(['season_id']);
            }
            if (Schema::hasIndex('performances', 'performances_event_id_starts_at_index')) {
                $table->dropIndex(['event_id', 'starts_at']);
            }
            $table->dropColumn(array_filter([
                'season_id', 'label', 'is_premiere', 'door_time',
                'special_guests', 'notes', 'ticket_overrides', 'capacity_override',
            ], fn ($col) => Schema::hasColumn('performances', $col)));
        });
    }
};

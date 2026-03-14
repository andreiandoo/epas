<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('performances', function (Blueprint $table) {
            $table->foreignId('season_id')->nullable()->after('event_id')
                ->constrained('seasons')->nullOnDelete();

            $table->string('label')->nullable()->after('status')
                ->comment('Display label, e.g. "Matineu", "Seara", "Premiera"');

            $table->boolean('is_premiere')->default(false)->after('label');

            $table->string('door_time', 5)->nullable()->after('ends_at')
                ->comment('Door open time HH:MM');

            $table->json('special_guests')->nullable()->after('is_premiere')
                ->comment('Guest performers for this specific showing: [{name, role}]');

            $table->json('notes')->nullable()->after('special_guests')
                ->comment('Translatable internal/public notes');

            $table->json('ticket_overrides')->nullable()->after('notes')
                ->comment('Per-performance ticket type overrides: [{ticket_type_id, price_cents, quota}]');

            $table->integer('capacity_override')->nullable()->after('ticket_overrides')
                ->comment('Override event capacity for this specific performance');

            $table->index(['season_id']);
        });

        // Add composite index only if it doesn't already exist
        if (!Schema::hasIndex('performances', 'performances_event_id_starts_at_index')) {
            Schema::table('performances', function (Blueprint $table) {
                $table->index(['event_id', 'starts_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('performances', function (Blueprint $table) {
            $table->dropForeign(['season_id']);
            $table->dropIndex(['season_id']);
            $table->dropIndex(['event_id', 'starts_at']);
            $table->dropColumn([
                'season_id', 'label', 'is_premiere', 'door_time',
                'special_guests', 'notes', 'ticket_overrides', 'capacity_override',
            ]);
        });
    }
};

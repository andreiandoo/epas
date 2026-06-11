<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old foreign key(s) referencing event_id
        $foreignKeys = collect(Schema::getForeignKeys('organizer_documents'))
            ->filter(fn ($fk) => str_contains($fk['name'], 'event_id'));

        if ($foreignKeys->isNotEmpty()) {
            Schema::table('organizer_documents', function (Blueprint $table) use ($foreignKeys) {
                foreach ($foreignKeys as $fk) {
                    $table->dropForeign($fk['name']);
                }
            });
        }

        // Delete orphaned records where event_id doesn't exist in events table
        DB::statement('DELETE FROM organizer_documents WHERE event_id NOT IN (SELECT id FROM events)');

        // Add new foreign key referencing events table
        Schema::table('organizer_documents', function (Blueprint $table) {
            $table->foreign('event_id')
                ->references('id')
                ->on('events')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organizer_documents', function (Blueprint $table) {
            $table->dropForeign(['event_id']);

            $table->foreign('event_id')
                ->references('id')
                ->on('marketplace_events')
                ->cascadeOnDelete();
        });
    }
};

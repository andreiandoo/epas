<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Parent event reference for child events (multi-day, recurring)
            if (!Schema::hasColumn('events', 'parent_id')) {
                $table->foreignId('parent_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('events')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }

            // Is this a template event (parent for recurring/multi-day)
            if (!Schema::hasColumn('events', 'is_template')) {
                $table->boolean('is_template')->default(false)->after('parent_id');
            }

            // Child occurrence number (1, 2, 3...)
            if (!Schema::hasColumn('events', 'occurrence_number')) {
                $table->unsignedInteger('occurrence_number')->nullable()->after('is_template');
            }
        });

        // Add index for parent_id queries
        Schema::table('events', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $sm->listTableIndexes('events');

            if (!isset($indexes['events_parent_id_index'])) {
                $table->index('parent_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'parent_id')) {
                $table->dropConstrainedForeignId('parent_id');
            }
            if (Schema::hasColumn('events', 'is_template')) {
                $table->dropColumn('is_template');
            }
            if (Schema::hasColumn('events', 'occurrence_number')) {
                $table->dropColumn('occurrence_number');
            }
        });
    }
};

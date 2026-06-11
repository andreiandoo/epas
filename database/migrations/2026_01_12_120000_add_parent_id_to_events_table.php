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
                $table->unsignedBigInteger('parent_id')->nullable()->after('id');
                $table->foreign('parent_id')
                    ->references('id')
                    ->on('events')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
                $table->index('parent_id');
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
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'parent_id')) {
                $table->dropForeign(['parent_id']);
                $table->dropIndex(['parent_id']);
                $table->dropColumn('parent_id');
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

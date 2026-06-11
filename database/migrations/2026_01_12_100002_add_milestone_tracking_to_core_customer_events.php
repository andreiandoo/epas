<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_customer_events', function (Blueprint $table) {
            // Add milestone attribution
            $table->foreignId('attributed_milestone_id')
                ->nullable()
                ->after('event_id')
                ->constrained('event_milestones')
                ->onDelete('set null');

            // Add index for milestone attribution queries
            $table->index(['attributed_milestone_id', 'event_type']);
        });

        // Also add revenue target to events table if not exists
        if (!Schema::hasColumn('events', 'revenue_target')) {
            Schema::table('events', function (Blueprint $table) {
                $table->decimal('revenue_target', 12, 2)->nullable()->after('target_price');
                $table->integer('capacity')->nullable()->after('revenue_target');
            });
        }
    }

    public function down(): void
    {
        Schema::table('core_customer_events', function (Blueprint $table) {
            $table->dropForeign(['attributed_milestone_id']);
            $table->dropColumn('attributed_milestone_id');
        });

        if (Schema::hasColumn('events', 'revenue_target')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropColumn(['revenue_target', 'capacity']);
            });
        }
    }
};

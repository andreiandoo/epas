<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The original migration referenced `users` for the ticket assignee, but
 * the marketplace Filament panel authenticates against `marketplace_admins`.
 * Rename the column and re-point the FK so assignment from the admin panel
 * actually works. Safe to do destructively because no ticket has been
 * assigned yet — the system is still in beta and the table is empty for
 * the assignee column.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropIndex('support_tkt_assigned_idx');
            $table->dropForeign(['assigned_to_user_id']);
            $table->dropColumn('assigned_to_user_id');
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->foreignId('assigned_to_marketplace_admin_id')
                ->nullable()
                ->after('support_problem_type_id')
                ->constrained('marketplace_admins')
                ->nullOnDelete();
            $table->index(['assigned_to_marketplace_admin_id', 'status'], 'support_tkt_assigned_idx');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropIndex('support_tkt_assigned_idx');
            $table->dropForeign(['assigned_to_marketplace_admin_id']);
            $table->dropColumn('assigned_to_marketplace_admin_id');
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->foreignId('assigned_to_user_id')
                ->nullable()
                ->after('support_problem_type_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->index(['assigned_to_user_id', 'status'], 'support_tkt_assigned_idx');
        });
    }
};

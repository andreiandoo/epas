<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // First, make tenant_id nullable using raw SQL (avoids doctrine/dbal requirement)
        DB::statement('ALTER TABLE event_milestones MODIFY tenant_id BIGINT UNSIGNED NULL');

        // Drop the foreign key constraint first if it exists
        try {
            Schema::table('event_milestones', function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
            });
        } catch (\Exception $e) {
            // Foreign key might not exist or have different name
        }

        // Add marketplace_client_id column if it doesn't exist
        if (!Schema::hasColumn('event_milestones', 'marketplace_client_id')) {
            Schema::table('event_milestones', function (Blueprint $table) {
                $table->foreignId('marketplace_client_id')
                    ->nullable()
                    ->after('tenant_id')
                    ->constrained()
                    ->onDelete('cascade');

                $table->index(['marketplace_client_id', 'type']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('event_milestones', function (Blueprint $table) {
            if (Schema::hasColumn('event_milestones', 'marketplace_client_id')) {
                $table->dropIndex(['marketplace_client_id', 'type']);
                $table->dropForeign(['marketplace_client_id']);
                $table->dropColumn('marketplace_client_id');
            }
        });
    }
};

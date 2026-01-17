<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_milestones', function (Blueprint $table) {
            // Make tenant_id nullable (for marketplace events without tenant)
            $table->foreignId('tenant_id')->nullable()->change();

            // Add marketplace_client_id for marketplace context
            $table->foreignId('marketplace_client_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained()
                ->onDelete('cascade');

            // Add index for marketplace client queries
            $table->index(['marketplace_client_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('event_milestones', function (Blueprint $table) {
            $table->dropIndex(['marketplace_client_id', 'type']);
            $table->dropForeign(['marketplace_client_id']);
            $table->dropColumn('marketplace_client_id');

            // Note: Cannot easily revert nullable change
        });
    }
};

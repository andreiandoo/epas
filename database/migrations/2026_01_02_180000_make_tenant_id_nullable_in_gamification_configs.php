<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('gamification_configs', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['tenant_id']);

            // Make tenant_id nullable
            $table->foreignId('tenant_id')->nullable()->change();

            // Re-add the foreign key with nullable support
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->nullOnDelete();
        });

        // Also drop the unique constraint on tenant_id since marketplace configs won't have tenant_id
        Schema::table('gamification_configs', function (Blueprint $table) {
            $table->dropUnique(['tenant_id']);
        });

        // Add a composite unique for marketplace_client_id (if not exists)
        if (!Schema::hasColumn('gamification_configs', 'marketplace_client_id')) {
            Schema::table('gamification_configs', function (Blueprint $table) {
                $table->foreignId('marketplace_client_id')
                    ->nullable()
                    ->after('tenant_id')
                    ->constrained('marketplace_clients')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gamification_configs', function (Blueprint $table) {
            // Remove the nullable foreign key
            $table->dropForeign(['tenant_id']);

            // Make tenant_id NOT NULL again
            $table->foreignId('tenant_id')->nullable(false)->change();

            // Re-add the original foreign key
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            // Re-add unique constraint
            $table->unique('tenant_id');
        });
    }
};

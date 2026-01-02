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
        // Step 1: Drop the foreign key constraint first
        Schema::table('gamification_configs', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
        });

        // Step 2: Drop the unique index (now that foreign key is gone)
        Schema::table('gamification_configs', function (Blueprint $table) {
            $table->dropUnique(['tenant_id']);
        });

        // Step 3: Make tenant_id nullable
        Schema::table('gamification_configs', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->change();
        });

        // Step 4: Re-add the foreign key with nullable support
        Schema::table('gamification_configs', function (Blueprint $table) {
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->nullOnDelete();
        });

        // Step 5: Add marketplace_client_id column if not exists
        if (!Schema::hasColumn('gamification_configs', 'marketplace_client_id')) {
            Schema::table('gamification_configs', function (Blueprint $table) {
                $table->foreignId('marketplace_client_id')
                    ->nullable()
                    ->after('id')
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
        // Remove marketplace_client_id if we added it
        if (Schema::hasColumn('gamification_configs', 'marketplace_client_id')) {
            Schema::table('gamification_configs', function (Blueprint $table) {
                $table->dropForeign(['marketplace_client_id']);
                $table->dropColumn('marketplace_client_id');
            });
        }

        Schema::table('gamification_configs', function (Blueprint $table) {
            // Remove the nullable foreign key
            $table->dropForeign(['tenant_id']);
        });

        Schema::table('gamification_configs', function (Blueprint $table) {
            // Make tenant_id NOT NULL again (requires existing rows to have values)
            $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
        });

        Schema::table('gamification_configs', function (Blueprint $table) {
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

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('marketplace_client_microservices', function (Blueprint $table) {
            // Rename configuration to settings for consistency with tenant_microservices
            if (Schema::hasColumn('marketplace_client_microservices', 'configuration')) {
                $table->renameColumn('configuration', 'settings');
            }
        });

        Schema::table('marketplace_client_microservices', function (Blueprint $table) {
            // Add status column (replaces boolean is_active for more granular control)
            if (!Schema::hasColumn('marketplace_client_microservices', 'status')) {
                $table->string('status', 20)->default('inactive')->after('microservice_id');
            }

            // Add usage_stats for tracking API calls, transactions, etc.
            if (!Schema::hasColumn('marketplace_client_microservices', 'usage_stats')) {
                $table->json('usage_stats')->nullable()->after('settings');
            }

            // Add is_default to mark the default payment method
            if (!Schema::hasColumn('marketplace_client_microservices', 'is_default')) {
                $table->boolean('is_default')->default(false)->after('usage_stats');
            }

            // Add sort_order for display ordering
            if (!Schema::hasColumn('marketplace_client_microservices', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('is_default');
            }
        });

        // Migrate data: convert is_active boolean to status enum
        DB::table('marketplace_client_microservices')
            ->where('is_active', true)
            ->update(['status' => 'active']);

        DB::table('marketplace_client_microservices')
            ->where('is_active', false)
            ->update(['status' => 'inactive']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_client_microservices', function (Blueprint $table) {
            // Rename settings back to configuration
            if (Schema::hasColumn('marketplace_client_microservices', 'settings')) {
                $table->renameColumn('settings', 'configuration');
            }

            // Drop new columns
            $columns = ['status', 'usage_stats', 'is_default', 'sort_order'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('marketplace_client_microservices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

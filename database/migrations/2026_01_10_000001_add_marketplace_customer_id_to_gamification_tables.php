<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add marketplace_client_id and marketplace_customer_id to gamification tables
     * to support marketplace customers (separate from regular tenant customers)
     */
    public function up(): void
    {
        // =============================================
        // STEP 1: Add marketplace_client_id to tables that don't have it
        // =============================================

        // Customer Points - add marketplace_client_id first
        if (Schema::hasTable('customer_points') && !Schema::hasColumn('customer_points', 'marketplace_client_id')) {
            Schema::table('customer_points', function (Blueprint $table) {
                $table->foreignId('marketplace_client_id')
                    ->nullable()
                    ->after('tenant_id')
                    ->constrained('marketplace_clients')
                    ->cascadeOnDelete();
            });
        }

        // Points Transactions - add marketplace_client_id first
        if (Schema::hasTable('points_transactions') && !Schema::hasColumn('points_transactions', 'marketplace_client_id')) {
            Schema::table('points_transactions', function (Blueprint $table) {
                $table->foreignId('marketplace_client_id')
                    ->nullable()
                    ->after('tenant_id')
                    ->constrained('marketplace_clients')
                    ->cascadeOnDelete();
            });
        }

        // =============================================
        // STEP 2: Add marketplace_customer_id to all gamification tables
        // =============================================

        // Customer Points
        if (Schema::hasTable('customer_points') && !Schema::hasColumn('customer_points', 'marketplace_customer_id')) {
            Schema::table('customer_points', function (Blueprint $table) {
                $table->foreignId('marketplace_customer_id')
                    ->nullable()
                    ->after('customer_id')
                    ->constrained('marketplace_customers')
                    ->cascadeOnDelete();

                $table->index(['marketplace_client_id', 'marketplace_customer_id'], 'cp_mp_client_cust_idx');
            });
        }

        // Points Transactions
        if (Schema::hasTable('points_transactions') && !Schema::hasColumn('points_transactions', 'marketplace_customer_id')) {
            Schema::table('points_transactions', function (Blueprint $table) {
                $table->foreignId('marketplace_customer_id')
                    ->nullable()
                    ->after('customer_id')
                    ->constrained('marketplace_customers')
                    ->cascadeOnDelete();

                $table->index(['marketplace_client_id', 'marketplace_customer_id', 'created_at'], 'pt_mp_client_cust_created_idx');
            });
        }

        // Customer Experience
        if (Schema::hasTable('customer_experience') && !Schema::hasColumn('customer_experience', 'marketplace_customer_id')) {
            Schema::table('customer_experience', function (Blueprint $table) {
                $table->foreignId('marketplace_customer_id')
                    ->nullable()
                    ->after('customer_id')
                    ->constrained('marketplace_customers')
                    ->cascadeOnDelete();

                $table->unique(['marketplace_client_id', 'marketplace_customer_id'], 'ce_mp_client_cust_unique');
            });
        }

        // Experience Transactions
        if (Schema::hasTable('experience_transactions') && !Schema::hasColumn('experience_transactions', 'marketplace_customer_id')) {
            Schema::table('experience_transactions', function (Blueprint $table) {
                $table->foreignId('marketplace_customer_id')
                    ->nullable()
                    ->after('customer_id')
                    ->constrained('marketplace_customers')
                    ->cascadeOnDelete();

                $table->index(['marketplace_client_id', 'marketplace_customer_id', 'created_at'], 'et_mp_client_cust_created_idx');
            });
        }

        // Customer Badges
        if (Schema::hasTable('customer_badges') && !Schema::hasColumn('customer_badges', 'marketplace_customer_id')) {
            Schema::table('customer_badges', function (Blueprint $table) {
                $table->foreignId('marketplace_customer_id')
                    ->nullable()
                    ->after('customer_id')
                    ->constrained('marketplace_customers')
                    ->cascadeOnDelete();

                $table->index(['marketplace_client_id', 'marketplace_customer_id'], 'cb_mp_client_cust_idx');
            });
        }

        // Reward Redemptions
        if (Schema::hasTable('reward_redemptions') && !Schema::hasColumn('reward_redemptions', 'marketplace_customer_id')) {
            Schema::table('reward_redemptions', function (Blueprint $table) {
                $table->foreignId('marketplace_customer_id')
                    ->nullable()
                    ->after('customer_id')
                    ->constrained('marketplace_customers')
                    ->cascadeOnDelete();

                $table->index(['marketplace_client_id', 'marketplace_customer_id'], 'rr_mp_client_cust_idx');
            });
        }
    }

    public function down(): void
    {
        // Drop marketplace_customer_id from all tables
        $tablesWithCustomerId = [
            'customer_points' => 'cp_mp_client_cust_idx',
            'points_transactions' => 'pt_mp_client_cust_created_idx',
            'customer_experience' => 'ce_mp_client_cust_unique',
            'experience_transactions' => 'et_mp_client_cust_created_idx',
            'customer_badges' => 'cb_mp_client_cust_idx',
            'reward_redemptions' => 'rr_mp_client_cust_idx',
        ];

        foreach ($tablesWithCustomerId as $tableName => $indexName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'marketplace_customer_id')) {
                Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                    $table->dropIndex($indexName);
                    $table->dropForeign(['marketplace_customer_id']);
                    $table->dropColumn('marketplace_customer_id');
                });
            }
        }

        // Drop marketplace_client_id from tables that didn't have it originally
        $tablesWithClientId = ['customer_points', 'points_transactions'];

        foreach ($tablesWithClientId as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'marketplace_client_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropForeign(['marketplace_client_id']);
                    $table->dropColumn('marketplace_client_id');
                });
            }
        }
    }
};

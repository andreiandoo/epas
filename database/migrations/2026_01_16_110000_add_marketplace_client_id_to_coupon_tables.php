<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Helper to check if foreign key exists
        $foreignKeyExists = function (string $table, string $column): bool {
            $foreignKeys = Schema::getConnection()->getDoctrineSchemaManager()->listTableForeignKeys($table);
            foreach ($foreignKeys as $foreignKey) {
                if (in_array($column, $foreignKey->getLocalColumns())) {
                    return true;
                }
            }
            return false;
        };

        // Helper to check if index exists
        $indexExists = function (string $table, string $indexName): bool {
            $indexes = Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes($table);
            return isset($indexes[$indexName]);
        };

        // ==================== COUPON_CAMPAIGNS ====================

        // Drop tenant_id foreign key if exists
        if ($foreignKeyExists('coupon_campaigns', 'tenant_id')) {
            Schema::table('coupon_campaigns', function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
            });
        }

        Schema::table('coupon_campaigns', function (Blueprint $table) use ($foreignKeyExists) {
            // Make tenant_id nullable (if not already)
            $table->unsignedBigInteger('tenant_id')->nullable()->change();

            // Add marketplace_client_id if it doesn't exist
            if (!Schema::hasColumn('coupon_campaigns', 'marketplace_client_id')) {
                $table->foreignId('marketplace_client_id')->nullable()->after('tenant_id')->constrained('marketplace_clients')->onDelete('cascade');
            }

            // Re-add foreign key for tenant_id if it doesn't exist
            if (!$foreignKeyExists('coupon_campaigns', 'tenant_id')) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            }
        });

        // ==================== COUPON_CODES ====================

        // Drop unique constraint if exists
        if ($indexExists('coupon_codes', 'coupon_codes_tenant_id_code_unique')) {
            Schema::table('coupon_codes', function (Blueprint $table) {
                $table->dropUnique(['tenant_id', 'code']);
            });
        }

        // Drop tenant_id foreign key if exists
        if ($foreignKeyExists('coupon_codes', 'tenant_id')) {
            Schema::table('coupon_codes', function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
            });
        }

        Schema::table('coupon_codes', function (Blueprint $table) use ($foreignKeyExists, $indexExists) {
            // Make tenant_id nullable (if not already)
            $table->unsignedBigInteger('tenant_id')->nullable()->change();

            // Add marketplace_client_id if it doesn't exist
            if (!Schema::hasColumn('coupon_codes', 'marketplace_client_id')) {
                $table->foreignId('marketplace_client_id')->nullable()->after('tenant_id')->constrained('marketplace_clients')->onDelete('cascade');
            }

            // Re-add foreign key for tenant_id if it doesn't exist
            if (!$foreignKeyExists('coupon_codes', 'tenant_id')) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            }

            // Add unique constraint if it doesn't exist
            if (!$indexExists('coupon_codes', 'coupon_codes_marketplace_code_unique')) {
                $table->unique(['marketplace_client_id', 'code'], 'coupon_codes_marketplace_code_unique');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('coupon_codes', 'marketplace_client_id')) {
            Schema::table('coupon_codes', function (Blueprint $table) {
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                $indexes = $sm->listTableIndexes('coupon_codes');
                if (isset($indexes['coupon_codes_marketplace_code_unique'])) {
                    $table->dropUnique('coupon_codes_marketplace_code_unique');
                }
                $table->dropForeign(['marketplace_client_id']);
                $table->dropColumn('marketplace_client_id');
            });
        }

        if (Schema::hasColumn('coupon_campaigns', 'marketplace_client_id')) {
            Schema::table('coupon_campaigns', function (Blueprint $table) {
                $table->dropForeign(['marketplace_client_id']);
                $table->dropColumn('marketplace_client_id');
            });
        }
    }
};

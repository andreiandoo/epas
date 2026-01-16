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
        // ==================== COUPON_CAMPAIGNS ====================

        // Check and drop tenant_id foreign key if exists (using raw SQL for MySQL)
        $this->dropForeignKeyIfExists('coupon_campaigns', 'coupon_campaigns_tenant_id_foreign');

        Schema::table('coupon_campaigns', function (Blueprint $table) {
            // Make tenant_id nullable
            $table->unsignedBigInteger('tenant_id')->nullable()->change();
        });

        // Add marketplace_client_id if it doesn't exist
        if (!Schema::hasColumn('coupon_campaigns', 'marketplace_client_id')) {
            Schema::table('coupon_campaigns', function (Blueprint $table) {
                $table->foreignId('marketplace_client_id')->nullable()->after('tenant_id')->constrained('marketplace_clients')->onDelete('cascade');
            });
        }

        // Re-add tenant_id foreign key
        $this->addForeignKeyIfNotExists('coupon_campaigns', 'tenant_id', 'tenants', 'id', 'cascade');

        // ==================== COUPON_CODES ====================

        // Drop unique constraint if exists
        $this->dropIndexIfExists('coupon_codes', 'coupon_codes_tenant_id_code_unique');

        // Drop tenant_id foreign key if exists
        $this->dropForeignKeyIfExists('coupon_codes', 'coupon_codes_tenant_id_foreign');

        Schema::table('coupon_codes', function (Blueprint $table) {
            // Make tenant_id nullable
            $table->unsignedBigInteger('tenant_id')->nullable()->change();
        });

        // Add marketplace_client_id if it doesn't exist
        if (!Schema::hasColumn('coupon_codes', 'marketplace_client_id')) {
            Schema::table('coupon_codes', function (Blueprint $table) {
                $table->foreignId('marketplace_client_id')->nullable()->after('tenant_id')->constrained('marketplace_clients')->onDelete('cascade');
            });
        }

        // Re-add tenant_id foreign key
        $this->addForeignKeyIfNotExists('coupon_codes', 'tenant_id', 'tenants', 'id', 'cascade');

        // Add marketplace unique constraint if it doesn't exist
        if (!$this->indexExists('coupon_codes', 'coupon_codes_marketplace_code_unique')) {
            Schema::table('coupon_codes', function (Blueprint $table) {
                $table->unique(['marketplace_client_id', 'code'], 'coupon_codes_marketplace_code_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('coupon_codes', 'marketplace_client_id')) {
            $this->dropIndexIfExists('coupon_codes', 'coupon_codes_marketplace_code_unique');
            $this->dropForeignKeyIfExists('coupon_codes', 'coupon_codes_marketplace_client_id_foreign');
            Schema::table('coupon_codes', function (Blueprint $table) {
                $table->dropColumn('marketplace_client_id');
            });
        }

        if (Schema::hasColumn('coupon_campaigns', 'marketplace_client_id')) {
            $this->dropForeignKeyIfExists('coupon_campaigns', 'coupon_campaigns_marketplace_client_id_foreign');
            Schema::table('coupon_campaigns', function (Blueprint $table) {
                $table->dropColumn('marketplace_client_id');
            });
        }
    }

    /**
     * Check if a foreign key exists
     */
    private function foreignKeyExists(string $table, string $keyName): bool
    {
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            AND CONSTRAINT_NAME = ?
        ", [$table, $keyName]);

        return count($foreignKeys) > 0;
    }

    /**
     * Check if an index exists
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("
            SELECT INDEX_NAME
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND INDEX_NAME = ?
        ", [$table, $indexName]);

        return count($indexes) > 0;
    }

    /**
     * Drop foreign key if it exists
     */
    private function dropForeignKeyIfExists(string $table, string $keyName): void
    {
        if ($this->foreignKeyExists($table, $keyName)) {
            Schema::table($table, function (Blueprint $table) use ($keyName) {
                $table->dropForeign($keyName);
            });
        }
    }

    /**
     * Drop index if it exists
     */
    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            Schema::table($table, function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        }
    }

    /**
     * Add foreign key if it doesn't exist
     */
    private function addForeignKeyIfNotExists(string $table, string $column, string $referencedTable, string $referencedColumn, string $onDelete): void
    {
        $keyName = "{$table}_{$column}_foreign";
        if (!$this->foreignKeyExists($table, $keyName)) {
            Schema::table($table, function (Blueprint $table) use ($column, $referencedTable, $referencedColumn, $onDelete) {
                $table->foreign($column)->references($referencedColumn)->on($referencedTable)->onDelete($onDelete);
            });
        }
    }
};

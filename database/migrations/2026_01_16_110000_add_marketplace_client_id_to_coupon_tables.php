<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ==================== COUPON_CAMPAIGNS ====================

        $this->dropForeignKeyIfExists('coupon_campaigns', 'coupon_campaigns_tenant_id_foreign');

        Schema::table('coupon_campaigns', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->change();
        });

        if (!Schema::hasColumn('coupon_campaigns', 'marketplace_client_id')) {
            Schema::table('coupon_campaigns', function (Blueprint $table) {
                $table->foreignId('marketplace_client_id')->nullable()->constrained('marketplace_clients')->onDelete('cascade');
            });
        }

        $this->addForeignKeyIfNotExists('coupon_campaigns', 'tenant_id', 'tenants', 'id', 'cascade');

        // ==================== COUPON_CODES ====================

        $this->dropIndexIfExists('coupon_codes', 'coupon_codes_tenant_id_code_unique');
        $this->dropForeignKeyIfExists('coupon_codes', 'coupon_codes_tenant_id_foreign');

        Schema::table('coupon_codes', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->change();
        });

        if (!Schema::hasColumn('coupon_codes', 'marketplace_client_id')) {
            Schema::table('coupon_codes', function (Blueprint $table) {
                $table->foreignId('marketplace_client_id')->nullable()->constrained('marketplace_clients')->onDelete('cascade');
            });
        }

        $this->addForeignKeyIfNotExists('coupon_codes', 'tenant_id', 'tenants', 'id', 'cascade');

        if (!$this->indexExists('coupon_codes', 'coupon_codes_marketplace_code_unique')) {
            Schema::table('coupon_codes', function (Blueprint $table) {
                $table->unique(['marketplace_client_id', 'code'], 'coupon_codes_marketplace_code_unique');
            });
        }
    }

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

    private function foreignKeyExists(string $table, string $keyName): bool
    {
        $foreignKeys = collect(Schema::getForeignKeys($table));
        return $foreignKeys->contains(fn ($fk) => $fk['name'] === $keyName);
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn ($idx) => $idx['name'] === $indexName);
    }

    private function dropForeignKeyIfExists(string $table, string $keyName): void
    {
        if ($this->foreignKeyExists($table, $keyName)) {
            Schema::table($table, function (Blueprint $table) use ($keyName) {
                $table->dropForeign($keyName);
            });
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            $index = collect(Schema::getIndexes($table))->first(fn ($idx) => $idx['name'] === $indexName);
            Schema::table($table, function (Blueprint $table) use ($indexName, $index) {
                if (!empty($index['unique'])) {
                    $table->dropUnique($indexName);
                } else {
                    $table->dropIndex($indexName);
                }
            });
        }
    }

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

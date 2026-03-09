<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Change ENUM to VARCHAR to support any provider (oblio, keez, etc.)
        DB::statement("ALTER TABLE acc_connectors MODIFY COLUMN provider VARCHAR(50) NOT NULL");

        // Make tenant_id nullable (may have failed in earlier migration due to FK)
        // Drop FK if exists, then modify column
        try {
            DB::statement("ALTER TABLE acc_connectors DROP FOREIGN KEY acc_connectors_tenant_id_foreign");
        } catch (\Exception $e) {
            // FK may not exist
        }
        try {
            DB::statement("ALTER TABLE acc_connectors DROP INDEX acc_connectors_tenant_id_foreign");
        } catch (\Exception $e) {
            // Index may not exist
        }
        DB::statement("ALTER TABLE acc_connectors MODIFY COLUMN tenant_id VARCHAR(255) NULL DEFAULT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE acc_connectors MODIFY COLUMN provider ENUM('smartbill','fgo','exact','xero','quickbooks') NOT NULL");
    }
};

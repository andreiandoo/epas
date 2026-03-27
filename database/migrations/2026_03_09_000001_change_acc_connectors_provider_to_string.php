<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        // Change ENUM to VARCHAR
        if ($isPgsql) {
            DB::statement('ALTER TABLE "acc_connectors" ALTER COLUMN "provider" TYPE VARCHAR(50)');
        } else {
            DB::statement("ALTER TABLE acc_connectors MODIFY COLUMN provider VARCHAR(50) NOT NULL");
        }

        // Drop FK if exists
        try {
            if ($isPgsql) {
                DB::statement("ALTER TABLE acc_connectors DROP CONSTRAINT IF EXISTS acc_connectors_tenant_id_foreign");
            } else {
                DB::statement("ALTER TABLE acc_connectors DROP FOREIGN KEY acc_connectors_tenant_id_foreign");
            }
        } catch (\Exception $e) {}

        // Drop index if exists
        try {
            if ($isPgsql) {
                DB::statement("DROP INDEX IF EXISTS acc_connectors_tenant_id_foreign");
            } else {
                DB::statement("ALTER TABLE acc_connectors DROP INDEX acc_connectors_tenant_id_foreign");
            }
        } catch (\Exception $e) {}

        // Make tenant_id nullable VARCHAR
        if ($isPgsql) {
            DB::statement('ALTER TABLE "acc_connectors" ALTER COLUMN "tenant_id" TYPE VARCHAR(255)');
            DB::statement('ALTER TABLE "acc_connectors" ALTER COLUMN "tenant_id" DROP NOT NULL');
            DB::statement('ALTER TABLE "acc_connectors" ALTER COLUMN "tenant_id" SET DEFAULT NULL');
        } else {
            DB::statement("ALTER TABLE acc_connectors MODIFY COLUMN tenant_id VARCHAR(255) NULL DEFAULT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE acc_connectors ALTER COLUMN provider TYPE VARCHAR(50)");
        } else {
            DB::statement("ALTER TABLE acc_connectors MODIFY COLUMN provider ENUM('smartbill','fgo','exact','xero','quickbooks') NOT NULL");
        }
    }
};

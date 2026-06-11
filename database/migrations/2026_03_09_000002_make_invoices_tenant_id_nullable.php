<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        // Drop FK if exists
        try {
            if ($isPgsql) {
                DB::statement("ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_tenant_id_foreign");
            } else {
                DB::statement("ALTER TABLE invoices DROP FOREIGN KEY invoices_tenant_id_foreign");
            }
        } catch (\Exception $e) {}

        // Drop index if exists
        try {
            if ($isPgsql) {
                DB::statement("DROP INDEX IF EXISTS invoices_tenant_id_foreign");
            } else {
                DB::statement("ALTER TABLE invoices DROP INDEX invoices_tenant_id_foreign");
            }
        } catch (\Exception $e) {}

        // Make tenant_id nullable
        if ($isPgsql) {
            DB::statement('ALTER TABLE "invoices" ALTER COLUMN "tenant_id" DROP NOT NULL');
            DB::statement('ALTER TABLE "invoices" ALTER COLUMN "tenant_id" SET DEFAULT NULL');
        } else {
            DB::statement("ALTER TABLE invoices MODIFY COLUMN tenant_id BIGINT UNSIGNED NULL DEFAULT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE "invoices" ALTER COLUMN "tenant_id" SET NOT NULL');
        } else {
            DB::statement("ALTER TABLE invoices MODIFY COLUMN tenant_id BIGINT UNSIGNED NOT NULL");
        }
    }
};

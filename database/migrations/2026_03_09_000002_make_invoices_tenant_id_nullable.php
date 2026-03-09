<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop FK if exists, then make tenant_id nullable
        // Marketplace invoices use marketplace_client_id instead of tenant_id
        try {
            DB::statement("ALTER TABLE invoices DROP FOREIGN KEY invoices_tenant_id_foreign");
        } catch (\Exception $e) {
            // FK may not exist
        }
        try {
            DB::statement("ALTER TABLE invoices DROP INDEX invoices_tenant_id_foreign");
        } catch (\Exception $e) {
            // Index may not exist
        }
        DB::statement("ALTER TABLE invoices MODIFY COLUMN tenant_id BIGINT UNSIGNED NULL DEFAULT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE invoices MODIFY COLUMN tenant_id BIGINT UNSIGNED NOT NULL");
    }
};

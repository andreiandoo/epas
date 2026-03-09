<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Change ENUM to VARCHAR to support any provider (oblio, keez, etc.)
        DB::statement("ALTER TABLE acc_connectors MODIFY COLUMN provider VARCHAR(50) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE acc_connectors MODIFY COLUMN provider ENUM('smartbill','fgo','exact','xero','quickbooks') NOT NULL");
    }
};

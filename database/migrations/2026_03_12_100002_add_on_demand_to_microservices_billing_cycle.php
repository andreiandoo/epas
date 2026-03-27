<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // PostgreSQL: billing_cycle is a varchar, no ENUM to modify
        } else {
            DB::statement("ALTER TABLE microservices MODIFY COLUMN billing_cycle ENUM('monthly', 'yearly', 'one_time', 'on_demand') DEFAULT 'monthly'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            DB::statement("ALTER TABLE microservices MODIFY COLUMN billing_cycle ENUM('monthly', 'yearly', 'one_time') DEFAULT 'monthly'");
        }
    }
};

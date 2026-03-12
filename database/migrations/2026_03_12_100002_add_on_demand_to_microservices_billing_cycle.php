<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE microservices MODIFY COLUMN billing_cycle ENUM('monthly', 'yearly', 'one_time', 'on_demand') DEFAULT 'monthly'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE microservices MODIFY COLUMN billing_cycle ENUM('monthly', 'yearly', 'one_time') DEFAULT 'monthly'");
    }
};

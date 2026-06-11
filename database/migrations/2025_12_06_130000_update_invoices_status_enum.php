<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // PostgreSQL: status is already a varchar, just update the check constraint if needed
            // On fresh PG install the column is string type, no ENUM to modify
        } else {
            DB::statement("ALTER TABLE invoices MODIFY COLUMN status ENUM('new', 'pending', 'outstanding', 'overdue', 'paid', 'cancelled') NOT NULL DEFAULT 'new'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            DB::statement("ALTER TABLE invoices MODIFY COLUMN status ENUM('outstanding', 'paid', 'cancelled') NOT NULL DEFAULT 'outstanding'");
        }
    }
};

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
        // Alter the ENUM to include all needed statuses
        DB::statement("ALTER TABLE invoices MODIFY COLUMN status ENUM('new', 'pending', 'outstanding', 'overdue', 'paid', 'cancelled') NOT NULL DEFAULT 'new'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original ENUM (only if no records use the new values)
        DB::statement("ALTER TABLE invoices MODIFY COLUMN status ENUM('outstanding', 'paid', 'cancelled') NOT NULL DEFAULT 'outstanding'");
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the existing check constraint
        DB::statement("ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_status_check");

        // Add new check constraint with 'new' status included
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_status_check CHECK (status IN ('new', 'outstanding', 'paid', 'cancelled'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the new constraint
        DB::statement("ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_status_check");

        // Restore the old constraint without 'new'
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_status_check CHECK (status IN ('outstanding', 'paid', 'cancelled'))");
    }
};

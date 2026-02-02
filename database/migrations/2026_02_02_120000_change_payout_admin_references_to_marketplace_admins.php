<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Changes the foreign key constraints on approved_by, processed_by, and rejected_by
     * columns from 'users' table to 'marketplace_admins' table since the Marketplace
     * panel uses marketplace_admins for authentication.
     */
    public function up(): void
    {
        Schema::table('marketplace_payouts', function (Blueprint $table) {
            // Drop existing foreign key constraints to users table
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['processed_by']);
            $table->dropForeign(['rejected_by']);

            // Add new foreign key constraints to marketplace_admins table
            $table->foreign('approved_by')
                ->references('id')
                ->on('marketplace_admins')
                ->nullOnDelete();

            $table->foreign('processed_by')
                ->references('id')
                ->on('marketplace_admins')
                ->nullOnDelete();

            $table->foreign('rejected_by')
                ->references('id')
                ->on('marketplace_admins')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_payouts', function (Blueprint $table) {
            // Drop foreign key constraints to marketplace_admins
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['processed_by']);
            $table->dropForeign(['rejected_by']);

            // Restore foreign key constraints to users table
            $table->foreign('approved_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('processed_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('rejected_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }
};

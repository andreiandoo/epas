<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make ticket_type_id nullable on tickets table.
 *
 * The original tickets table required ticket_type_id (FK to tenant ticket_types).
 * Marketplace tickets use marketplace_ticket_type_id instead and have no
 * corresponding tenant ticket_type_id — so this column must be nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Drop the existing NOT NULL FK constraint
            try {
                $table->dropForeign(['ticket_type_id']);
            } catch (\Exception $e) {
                // FK may already have been dropped or named differently — ignore
            }

            // Make nullable
            $table->unsignedBigInteger('ticket_type_id')->nullable()->change();

            // Re-add FK as nullable-safe (SET NULL on delete)
            $table->foreign('ticket_type_id')
                ->references('id')
                ->on('ticket_types')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            try {
                $table->dropForeign(['ticket_type_id']);
            } catch (\Exception $e) {
                //
            }

            $table->unsignedBigInteger('ticket_type_id')->nullable(false)->change();

            $table->foreign('ticket_type_id')
                ->references('id')
                ->on('ticket_types')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }
};

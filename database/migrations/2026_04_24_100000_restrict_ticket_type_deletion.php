<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Switch the FK behaviour on ticket_type_id from `SET NULL` to `RESTRICT`
 * for tickets and order_items. Before this migration, deleting a
 * ticket_type (directly or via cascade from an event) silently nulled the
 * ticket_type_id on every related row, producing orphan tickets/orders
 * that became invisible to admin UIs filtering by ticketType. With
 * RESTRICT, Postgres refuses the delete if any ticket/order_item still
 * points at the row, forcing the admin to cancel/refund or explicitly
 * reassign before removal.
 *
 * Existing NULL values stay put — RESTRICT only applies to future deletes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            try { $table->dropForeign(['ticket_type_id']); } catch (\Throwable $e) {}
            $table->foreign('ticket_type_id')
                ->references('id')
                ->on('ticket_types')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });

        Schema::table('order_items', function (Blueprint $table) {
            try { $table->dropForeign(['ticket_type_id']); } catch (\Throwable $e) {}
            $table->foreign('ticket_type_id')
                ->references('id')
                ->on('ticket_types')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            try { $table->dropForeign(['ticket_type_id']); } catch (\Throwable $e) {}
            $table->foreign('ticket_type_id')
                ->references('id')
                ->on('ticket_types')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });

        Schema::table('order_items', function (Blueprint $table) {
            try { $table->dropForeign(['ticket_type_id']); } catch (\Throwable $e) {}
            $table->foreign('ticket_type_id')
                ->references('id')
                ->on('ticket_types')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });
    }
};

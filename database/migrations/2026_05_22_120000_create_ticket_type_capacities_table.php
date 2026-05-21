<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E2 — Leisure tenant: per-date (and optionally per-hour-slot) capacity for
 * ticket types. New table dedicated to the tenant-leisure context — does NOT
 * touch the existing marketplace_event_date_capacities used by Ambilet.
 *
 *   ticket_type_id + capacity_date + time_slot_start  → UNIQUE
 *   time_slot_start IS NULL                          → capacity is per-day
 *   time_slot_start = '10:00:00', time_slot_end = '11:00:00' → hourly slot
 *
 * `sold` increases on order completion; `reserved` while items are in cart;
 * `is_closed` lets the operator manually mark a day/slot unavailable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_type_capacities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained()->cascadeOnDelete();
            $table->date('capacity_date');
            $table->time('time_slot_start')->nullable();
            $table->time('time_slot_end')->nullable();
            $table->unsignedInteger('capacity');
            $table->unsignedInteger('sold')->default(0);
            $table->unsignedInteger('reserved')->default(0);
            $table->boolean('is_closed')->default(false);
            $table->integer('price_override_cents')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            // Composite uniqueness — NULL slots are distinct under SQL NULL semantics
            // on Postgres, which is what we want (one per-day row + many hourly slots).
            $table->unique(
                ['ticket_type_id', 'capacity_date', 'time_slot_start'],
                'ttc_unique_slot'
            );
            $table->index(['tenant_id', 'capacity_date'], 'ttc_tenant_date_idx');
            $table->index(['ticket_type_id', 'capacity_date'], 'ttc_ticket_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_type_capacities');
    }
};

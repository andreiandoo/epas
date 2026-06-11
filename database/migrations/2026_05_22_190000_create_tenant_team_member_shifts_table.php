<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduling / pontaj for leisure tenant operators. One row = one
 * scheduled shift (date + interval + role/position). Used to power both
 * the team-member edit page (per-member shift list) and a weekly
 * overview page (all members × days grid).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_team_member_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_team_member_id')->constrained('tenant_team_members')->cascadeOnDelete();
            $table->date('shift_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('position')->nullable();   // ex: check_in, rental_kayak, pos_cashier
            $table->string('location')->nullable();   // ex: Gate A, Pontoon B
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'shift_date'], 'tms_tenant_date_idx');
            $table->index(['tenant_team_member_id', 'shift_date'], 'tms_member_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_team_member_shifts');
    }
};

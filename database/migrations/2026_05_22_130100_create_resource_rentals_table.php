<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E3 — Active and historical rental sessions linking a paid Ticket to a
 * specific PhysicalResource. Operator starts a rental at handout, ends it
 * at return — overtime + surcharge are computed at end-time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_rentals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('physical_resource_id')->constrained('physical_resources')->cascadeOnDelete();
            $table->unsignedBigInteger('started_by_user_id')->nullable();
            $table->unsignedBigInteger('ended_by_user_id')->nullable();

            $table->timestamp('started_at');
            $table->timestamp('planned_end_at');
            $table->timestamp('ended_at')->nullable();

            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->integer('overtime_surcharge_cents')->default(0);
            $table->boolean('surcharge_paid')->default(false);

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'started_at'], 'rr_tenant_started_idx');
            $table->index('physical_resource_id', 'rr_resource_idx');
            $table->index('ticket_id', 'rr_ticket_idx');
            $table->index(['tenant_id', 'ended_at'], 'rr_tenant_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_rentals');
    }
};

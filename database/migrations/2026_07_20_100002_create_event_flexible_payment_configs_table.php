<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-event flexible-payment configuration, set by the marketplace operator
 * in the event admin panel: which methods are enabled, the down payment, and
 * per-method knobs. The applicable plans are attached via event_installment_plan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_flexible_payment_configs')) {
            return;
        }

        Schema::create('event_flexible_payment_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->nullable();
            $table->foreignId('marketplace_event_id')->nullable();

            // Per-method toggles (operator decides per event).
            $table->boolean('enable_installments')->default(false);
            $table->boolean('enable_bnpl')->default(false);
            $table->boolean('enable_delegated_pay')->default(false);

            // Which ticket types are eligible for flexible payment. NULL/empty
            // means ALL ticket types of the event are eligible (backward compat).
            $table->json('eligible_ticket_type_ids')->nullable();

            // Down payment for installments on this event.
            $table->enum('down_payment_type', ['none', 'percent', 'fixed'])->default('percent');
            $table->integer('down_payment_value')->default(2000); // percent*100 → 20% default

            // BNPL on this event.
            $table->integer('bnpl_max_horizon_days')->default(30);

            // Delegated pay on this event.
            $table->integer('delegated_hold_hours')->default(24);
            $table->integer('delegated_max_locked_tickets')->nullable();

            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique('event_id');
            $table->unique('marketplace_event_id');
        });

        if (! Schema::hasTable('event_installment_plan')) {
            Schema::create('event_installment_plan', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_flexible_payment_config_id');
                $table->foreignId('installment_plan_id');
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(
                    ['event_flexible_payment_config_id', 'installment_plan_id'],
                    'efp_config_plan_unique'
                );
                $table->index('installment_plan_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_installment_plan');
        Schema::dropIfExists('event_flexible_payment_configs');
    }
};

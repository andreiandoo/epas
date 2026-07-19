<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reusable plan templates for the Flexible Payments microservice.
 * Owned by a marketplace client OR a tenant (both nullable — one is set).
 * plan_type distinguishes multi-installment plans from single-charge BNPL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('installment_plans')) {
            return;
        }

        Schema::create('installment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->nullable();
            $table->foreignId('tenant_id')->nullable();

            $table->json('name');                 // translatable
            $table->string('slug');
            $table->json('description')->nullable();
            $table->enum('plan_type', ['installments', 'bnpl_single'])->default('installments');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->string('currency', 3)->default('RON');

            // Optional default down payment (real value is set per event).
            $table->enum('down_payment_default_type', ['none', 'percent', 'fixed'])->nullable();
            $table->integer('down_payment_default_value')->nullable();

            // Schedule
            $table->integer('number_of_installments')->default(1);
            $table->enum('schedule_type', ['interval', 'fixed_dates'])->default('interval');
            $table->enum('interval_unit', ['day', 'week', 'month'])->default('month');
            $table->integer('interval_count')->default(1);
            $table->json('fixed_dates')->nullable();
            $table->enum('distribution', ['equal', 'custom_percent'])->default('equal');
            $table->json('installments_percentages')->nullable();

            // Costs — marketplace surcharge (customer-facing markup), percent AND/OR fixed.
            $table->integer('surcharge_percent')->default(0);      // percent * 100
            $table->integer('surcharge_fixed_cents')->default(0);

            // Eligibility
            $table->unsignedBigInteger('min_order_cents')->nullable();
            $table->unsignedBigInteger('max_order_cents')->nullable();
            $table->integer('days_before_event_fully_paid')->default(1); // min 1 (never on event day)
            $table->boolean('compress_schedule')->default(false);
            $table->integer('max_duration_days')->default(90);          // hard cap ≤ 3 months
            $table->json('eligibility')->nullable();

            // Policies
            $table->string('ticket_issuance_policy')->default('issue_invalid_until_paid');
            $table->json('default_policy')->nullable();   // grace_days, max_retries, retry_backoff, forfeit
            $table->json('refund_policy')->nullable();    // non-refundable fees
            $table->string('terms_url')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['marketplace_client_id', 'is_active']);
            $table->index(['tenant_id', 'is_active']);
            $table->index('plan_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_plans');
    }
};

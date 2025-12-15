<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Default commission settings
            $table->enum('default_commission_type', ['percent', 'fixed'])->default('percent');
            $table->decimal('default_commission_value', 8, 2)->default(10.00);

            // Cookie settings
            $table->string('cookie_name')->default('aff_ref');
            $table->integer('cookie_duration_days')->default(90);

            // Self-registration settings
            $table->boolean('allow_self_registration')->default(true);
            $table->boolean('require_approval')->default(true);
            $table->text('registration_terms')->nullable();

            // Withdrawal settings
            $table->decimal('min_withdrawal_amount', 10, 2)->default(50.00);
            $table->string('currency')->default('RON');
            $table->json('payment_methods')->nullable(); // ['bank_transfer', 'paypal']
            $table->integer('withdrawal_processing_days')->default(14);
            $table->boolean('auto_approve_withdrawals')->default(false);

            // Commission rules
            $table->boolean('exclude_taxes')->default(true);
            $table->boolean('exclude_shipping')->default(true);
            $table->boolean('prevent_self_purchase')->default(true);
            $table->integer('commission_hold_days')->default(30); // Hold period before commission becomes available

            // Landing page for affiliate program
            $table->string('program_name')->nullable();
            $table->text('program_description')->nullable();
            $table->json('program_benefits')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_settings');
    }
};

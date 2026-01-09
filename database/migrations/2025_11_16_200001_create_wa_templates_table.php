<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('wa_templates')) {
            return;
        }

        Schema::create('wa_templates', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();

            // Template identifier (unique per tenant)
            $table->string('name')->index();

            // Language code (e.g., en, ro, en_US)
            $table->string('language', 10)->default('en');

            // Category for template (order_confirm, reminder, promo, otp, other)
            $table->enum('category', ['order_confirm', 'reminder', 'promo', 'otp', 'other'])
                ->default('other');

            // Template body with variable placeholders: {{1}}, {{2}} or {first_name}, {event_name}
            $table->text('body');

            // Variable definitions
            $table->json('variables')->nullable()->comment('Array of variable names and types');

            // BSP approval lifecycle
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'disabled'])
                ->default('draft')
                ->index();

            // BSP-specific metadata (template ID, rejection reason, etc.)
            $table->json('provider_meta')->nullable()->comment('BSP template ID, rejection reasons, etc.');

            // Submission and approval timestamps
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Unique constraint: template name per tenant
            $table->unique(['tenant_id', 'name'], 'unique_tenant_template');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wa_templates');
    }
};

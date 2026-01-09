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
        if (Schema::hasTable('wa_optin')) {
            return;
        }

        Schema::create('wa_optin', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();

            // Generic user reference (can be user_id, email, customer_ref, etc.)
            $table->string('user_ref')->nullable()->index();

            // E.164 formatted phone number (e.g., +40722123456)
            $table->string('phone_e164')->index();

            // Consent status
            $table->enum('status', ['opt_in', 'opt_out'])->default('opt_out');

            // Source of consent (checkout, settings_page, import, api, etc.)
            $table->string('source')->nullable();

            // Consent timestamp and metadata
            $table->timestamp('consented_at')->nullable();
            $table->json('metadata')->nullable()->comment('IP, user_agent, consent_text_version, etc.');

            $table->timestamps();

            // Unique constraint: one opt-in record per tenant + phone
            $table->unique(['tenant_id', 'phone_e164'], 'unique_tenant_phone');

            // Index for quick lookups
            $table->index(['tenant_id', 'status'], 'idx_tenant_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wa_optin');
    }
};

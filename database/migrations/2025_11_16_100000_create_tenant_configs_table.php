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
        if (Schema::hasTable('tenant_configs')) {
            return;
        }

        Schema::create('tenant_configs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();

            // Configuration key (e.g., 'whatsapp_credentials', 'efactura_credentials', 'timezone')
            $table->string('key')->index();

            // Configuration value (encrypted for sensitive data)
            $table->text('value')->nullable();

            // Metadata about the configuration
            $table->json('metadata')->nullable()->comment('Type, description, validation rules, etc.');

            // Encryption flag
            $table->boolean('is_encrypted')->default(false);

            $table->timestamps();

            // Unique constraint: one key per tenant
            $table->unique(['tenant_id', 'key'], 'unique_tenant_key');

            // Indexes for common queries
            $table->index(['tenant_id', 'key'], 'idx_tenant_key_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_configs');
    }
};

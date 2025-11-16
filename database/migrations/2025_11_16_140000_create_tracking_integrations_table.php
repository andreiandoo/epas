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
        Schema::create('tracking_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');

            // Provider type
            $table->enum('provider', ['ga4', 'gtm', 'meta', 'tiktok'])
                ->comment('Tracking provider type');

            // Status
            $table->boolean('enabled')->default(false)
                ->comment('Whether this integration is active');

            // Consent category for GDPR compliance
            $table->enum('consent_category', ['analytics', 'marketing'])
                ->comment('Required consent category');

            // Provider-specific settings stored as JSON
            // GA4: {measurement_id, inject_at, page_scope}
            // GTM: {container_id, inject_at, page_scope}
            // Meta: {pixel_id, inject_at, page_scope}
            // TikTok: {pixel_id, inject_at, page_scope}
            $table->json('settings')
                ->comment('Provider-specific configuration');

            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'provider']);
            $table->index(['tenant_id', 'enabled']);
            $table->index('consent_category');

            // Unique constraint: one integration per provider per tenant
            $table->unique(['tenant_id', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracking_integrations');
    }
};

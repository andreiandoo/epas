<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds marketplace-specific fields to the tenants table.
     * A tenant with tenant_type='marketplace' can host multiple organizers.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Tenant type distinction: 'standard' (default) or 'marketplace'
            $table->string('tenant_type')->default('standard')->after('type');

            // Marketplace commission settings
            // Commission can be: 'percent' (percentage only), 'fixed' (fixed amount), or 'both' (percentage + fixed)
            $table->string('marketplace_commission_type')->nullable()->after('tenant_type');
            $table->decimal('marketplace_commission_percent', 5, 2)->nullable()->after('marketplace_commission_type');
            $table->decimal('marketplace_commission_fixed', 10, 2)->nullable()->after('marketplace_commission_percent');

            // Marketplace-specific settings
            $table->json('marketplace_settings')->nullable()->after('marketplace_commission_fixed');

            // Index for efficient queries
            $table->index('tenant_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['tenant_type']);
            $table->dropColumn([
                'tenant_type',
                'marketplace_commission_type',
                'marketplace_commission_percent',
                'marketplace_commission_fixed',
                'marketplace_settings',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Add new 'tenant_type' for tenant business model (keep existing 'type' intact)
            $table->string('tenant_type', 32)->nullable()->after('type')
                ->comment('Tenant business model: tenant-artist|agency|theater|festival');

            // Theater subtype (only relevant when tenant_type=theater)
            $table->string('theater_subtype', 32)->nullable()->after('tenant_type')
                ->comment('Theater subtype: theater|opera|philharmonic');

            // JSON settings specific to the tenant type
            $table->json('type_settings')->nullable()->after('theater_subtype')
                ->comment('Type-specific configuration');

            $table->index('tenant_type');
            $table->index('theater_subtype');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['tenant_type']);
            $table->dropIndex(['theater_subtype']);
            $table->dropColumn(['tenant_type', 'theater_subtype', 'type_settings']);
        });
    }
};

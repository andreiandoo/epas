<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Rename existing 'type' (single|small|medium|large|premium) to 'size'
            $table->renameColumn('type', 'size');
        });

        Schema::table('tenants', function (Blueprint $table) {
            // Add new 'type' for tenant business model
            $table->string('type', 32)->nullable()->after('size')
                ->comment('Tenant type: tenant-artist|agency|theater');

            // Theater subtype (only relevant when type=theater)
            $table->string('theater_subtype', 32)->nullable()->after('type')
                ->comment('Theater subtype: theater|opera|philharmonic');

            // JSON settings specific to the tenant type
            $table->json('type_settings')->nullable()->after('theater_subtype')
                ->comment('Type-specific configuration');

            $table->index('type');
            $table->index('theater_subtype');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropIndex(['theater_subtype']);
            $table->dropColumn(['type', 'theater_subtype', 'type_settings']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->renameColumn('size', 'type');
        });
    }
};

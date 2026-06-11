<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add commission fields to tenants table (default settings)
        Schema::table('tenants', function (Blueprint $table) {
            // Commission mode: 'included' (in ticket price) or 'added_on_top' (added to ticket price)
            $table->string('commission_mode', 32)
                ->default('included')
                ->after('plan')
                ->comment('included|added_on_top');

            // Commission rate in percentage (e.g., 5.00 = 5%)
            $table->decimal('commission_rate', 5, 2)
                ->default(5.00)
                ->after('commission_mode')
                ->comment('Commission percentage rate');

            $table->index('commission_mode');
        });

        // Add commission fields to events table (can override tenant default)
        Schema::table('events', function (Blueprint $table) {
            // Commission mode: nullable - if null, uses tenant's default
            $table->string('commission_mode', 32)
                ->nullable()
                ->after('tenant_id')
                ->comment('Override tenant commission mode: included|added_on_top|null=use tenant default');

            // Commission rate: nullable - if null, uses tenant's default
            $table->decimal('commission_rate', 5, 2)
                ->nullable()
                ->after('commission_mode')
                ->comment('Override tenant commission rate|null=use tenant default');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['commission_mode', 'commission_rate']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['commission_mode']);
            $table->dropColumn(['commission_mode', 'commission_rate']);
        });
    }
};

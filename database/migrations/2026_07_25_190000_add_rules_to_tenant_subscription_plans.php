<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parametri operabili pentru planurile de abonament: nr. spectacole/bilete
 * incluse, mod de alegere a locului + zone permise, valabilitate, acces prioritar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_subscription_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('tenant_subscription_plans', 'shows_included')) {
                $table->integer('shows_included')->nullable()->after('currency')
                    ->comment('Nr. de spectacole pe care le poate alege abonatul');
            }
            if (! Schema::hasColumn('tenant_subscription_plans', 'tickets_included')) {
                $table->integer('tickets_included')->default(1)->after('shows_included')
                    ->comment('Nr. de locuri/bilete per spectacol');
            }
            if (! Schema::hasColumn('tenant_subscription_plans', 'seat_mode')) {
                $table->string('seat_mode', 32)->default('client_choice')->after('tickets_included')
                    ->comment('client_choice|predefined');
            }
            if (! Schema::hasColumn('tenant_subscription_plans', 'allowed_sections')) {
                $table->jsonb('allowed_sections')->nullable()->after('seat_mode')
                    ->comment('Zone/secțiuni permise pentru alegerea locului');
            }
            if (! Schema::hasColumn('tenant_subscription_plans', 'validity_mode')) {
                $table->string('validity_mode', 32)->default('season')->after('allowed_sections')
                    ->comment('season|date_range');
            }
            if (! Schema::hasColumn('tenant_subscription_plans', 'valid_from')) {
                $table->date('valid_from')->nullable()->after('validity_mode');
            }
            if (! Schema::hasColumn('tenant_subscription_plans', 'valid_until')) {
                $table->date('valid_until')->nullable()->after('valid_from');
            }
            if (! Schema::hasColumn('tenant_subscription_plans', 'priority_access')) {
                $table->boolean('priority_access')->default(false)->after('valid_until');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenant_subscription_plans', function (Blueprint $table) {
            foreach (['shows_included', 'tickets_included', 'seat_mode', 'allowed_sections', 'validity_mode', 'valid_from', 'valid_until', 'priority_access'] as $col) {
                if (Schema::hasColumn('tenant_subscription_plans', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

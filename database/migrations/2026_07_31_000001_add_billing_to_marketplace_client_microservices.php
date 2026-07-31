<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-marketplace, per-microservice billing so Tixello can set IF and HOW MUCH
 * it charges each marketplace for a given microservice (monthly / one-time /
 * not billed). Defaults to 'none' so nothing is charged unless explicitly set
 * — e.g. Ambilet stays at 0. Revenue Analytics reads these instead of the
 * microservice's global price.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_client_microservices', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_client_microservices', 'billing_amount')) {
                $table->decimal('billing_amount', 12, 2)->nullable()->after('sort_order');
            }
            if (!Schema::hasColumn('marketplace_client_microservices', 'billing_cycle')) {
                // none | monthly | one_time
                $table->string('billing_cycle', 20)->default('none')->after('billing_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_client_microservices', function (Blueprint $table) {
            foreach (['billing_amount', 'billing_cycle'] as $col) {
                if (Schema::hasColumn('marketplace_client_microservices', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

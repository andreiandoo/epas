<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fus orar de afișare per tenant. Null = Europe/Bucharest (comportamentul de până acum).
 * Datele rămân stocate în UTC; coloana influențează doar afișarea în panoul tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tenants', 'timezone')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('timezone', 64)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenants', 'timezone')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('timezone');
            });
        }
    }
};

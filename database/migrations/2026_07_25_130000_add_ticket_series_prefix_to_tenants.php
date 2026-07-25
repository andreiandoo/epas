<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant ticket series prefix (replaces the hardcoded 'AMB').
 * Nullable → existing behavior ('AMB') preserved when unset.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'ticket_series_prefix')) {
                $table->string('ticket_series_prefix', 20)->nullable()->after('slug');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('ticket_series_prefix');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each column checked individually to handle partial migration state
        Schema::table('marketplace_clients', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_clients', 'billing_starts_at')) {
                $table->date('billing_starts_at')->nullable()->after('commission_mode');
            }
        });
        Schema::table('marketplace_clients', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_clients', 'billing_cycle_days')) {
                $table->unsignedInteger('billing_cycle_days')->default(30)->after('billing_starts_at');
            }
        });
        Schema::table('marketplace_clients', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_clients', 'next_billing_date')) {
                $table->date('next_billing_date')->nullable()->after('billing_cycle_days');
            }
        });
        Schema::table('marketplace_clients', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_clients', 'last_billing_date')) {
                $table->date('last_billing_date')->nullable()->after('next_billing_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_clients', function (Blueprint $table) {
            $columns = [];
            foreach (['billing_starts_at', 'billing_cycle_days', 'next_billing_date', 'last_billing_date'] as $col) {
                if (Schema::hasColumn('marketplace_clients', $col)) {
                    $columns[] = $col;
                }
            }
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};

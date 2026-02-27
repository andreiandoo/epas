<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_clients', function (Blueprint $table) {
            $table->date('billing_starts_at')->nullable()->after('commission_mode');
            $table->unsignedInteger('billing_cycle_days')->default(30)->after('billing_starts_at');
            $table->date('next_billing_date')->nullable()->after('billing_cycle_days');
            $table->date('last_billing_date')->nullable()->after('next_billing_date');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_clients', function (Blueprint $table) {
            $table->dropColumn(['billing_starts_at', 'billing_cycle_days', 'next_billing_date', 'last_billing_date']);
        });
    }
};

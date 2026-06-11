<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_tax_registries', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_tax_registries', 'invoice_series')) {
                $table->string('invoice_series', 16)->nullable()->after('tax_rate');
            }
            if (!Schema::hasColumn('marketplace_tax_registries', 'last_invoice_number')) {
                $table->unsignedInteger('last_invoice_number')->default(0)->after('invoice_series');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_tax_registries', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_tax_registries', 'last_invoice_number')) {
                $table->dropColumn('last_invoice_number');
            }
            if (Schema::hasColumn('marketplace_tax_registries', 'invoice_series')) {
                $table->dropColumn('invoice_series');
            }
        });
    }
};

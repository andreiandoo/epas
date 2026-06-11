<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'marketplace_tax_registry_id')) {
                $table->unsignedBigInteger('marketplace_tax_registry_id')->nullable()->after('marketplace_organizer_id');
                $table->index('marketplace_tax_registry_id', 'inv_mkt_tax_registry_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'marketplace_tax_registry_id')) {
                $table->dropIndex('inv_mkt_tax_registry_idx');
                $table->dropColumn('marketplace_tax_registry_id');
            }
        });
    }
};

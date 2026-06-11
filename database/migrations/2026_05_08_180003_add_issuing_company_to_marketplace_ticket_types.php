<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_ticket_types', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_ticket_types', 'issuing_company')) {
                $table->string('issuing_company', 16)->nullable()->after('issuing_tax_registry_id');
                $table->index('issuing_company', 'mtt_issuing_company_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_ticket_types', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_ticket_types', 'issuing_company')) {
                $table->dropIndex('mtt_issuing_company_idx');
                $table->dropColumn('issuing_company');
            }
        });
    }
};

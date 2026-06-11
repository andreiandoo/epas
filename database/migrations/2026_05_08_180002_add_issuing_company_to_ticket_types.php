<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * issuing_company: care dintre cele 2 societati ale organizatorului emite facturi
 * pentru biletele acestui tip. NULL = primary (fallback default), 'secondary' = a doua.
 *
 * Inlocuieste functional issuing_tax_registry_id (ramane in DB ca dead column ca sa nu
 * stricam migrations table; va fi sters intr-o migratie de cleanup la F5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            if (!Schema::hasColumn('ticket_types', 'issuing_company')) {
                $table->string('issuing_company', 16)->nullable()->after('issuing_tax_registry_id');
                $table->index('issuing_company', 'tt_issuing_company_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            if (Schema::hasColumn('ticket_types', 'issuing_company')) {
                $table->dropIndex('tt_issuing_company_idx');
                $table->dropColumn('issuing_company');
            }
        });
    }
};

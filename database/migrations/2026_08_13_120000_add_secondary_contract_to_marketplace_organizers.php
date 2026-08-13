<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contract number/series + date for the organizer's SECOND issuing company
     * (e.g. Csomadcom SRL for Lacul Sf. Ana). The primary already has
     * contract_number_series + contract_date; these are their secondary
     * counterparts, needed when generating deconturi/invoices on that society.
     * Nullable / non-breaking.
     */
    public function up(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_organizers', 'secondary_contract_number_series')) {
                $table->string('secondary_contract_number_series')->nullable()->after('secondary_vat_rate');
            }
            if (!Schema::hasColumn('marketplace_organizers', 'secondary_contract_date')) {
                $table->date('secondary_contract_date')->nullable()->after('secondary_contract_number_series');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            foreach (['secondary_contract_date', 'secondary_contract_number_series'] as $c) {
                if (Schema::hasColumn('marketplace_organizers', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};

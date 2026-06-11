<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add contract reference fields to organizers
        if (!Schema::hasColumn('marketplace_organizers', 'contract_number_series')) {
            Schema::table('marketplace_organizers', function (Blueprint $table) {
                $table->string('contract_number_series', 50)->nullable()->after('iban');
                $table->date('contract_date')->nullable()->after('contract_number_series');
            });
        }

        // Add payout_id to organizer_documents for linking deconts to payouts
        if (!Schema::hasColumn('organizer_documents', 'marketplace_payout_id')) {
            Schema::table('organizer_documents', function (Blueprint $table) {
                $table->foreignId('marketplace_payout_id')->nullable()->after('event_id')
                    ->constrained('marketplace_payouts')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            $table->dropColumn(['contract_number_series', 'contract_date']);
        });

        Schema::table('organizer_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('marketplace_payout_id');
        });
    }
};

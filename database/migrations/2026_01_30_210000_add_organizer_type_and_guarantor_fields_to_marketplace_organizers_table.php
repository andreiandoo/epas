<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            // Organizer Type Fields
            $table->string('person_type')->nullable()->after('phone'); // 'pf' (Persoana Fizica) or 'pj' (Persoana Juridica)
            $table->string('work_mode')->nullable()->after('person_type'); // 'exclusive' or 'non_exclusive'
            $table->string('organizer_type')->nullable()->after('work_mode'); // agency, promoter, venue, artist, ngo, other

            // Company Additional Fields
            $table->boolean('vat_payer')->default(false)->after('company_registration');
            $table->string('representative_first_name')->nullable()->after('company_county');
            $table->string('representative_last_name')->nullable()->after('representative_first_name');

            // Guarantor / Personal Details Section
            $table->string('guarantor_first_name')->nullable()->after('representative_last_name');
            $table->string('guarantor_last_name')->nullable()->after('guarantor_first_name');
            $table->string('guarantor_cnp', 13)->nullable()->after('guarantor_last_name');
            $table->string('guarantor_address')->nullable()->after('guarantor_cnp');
            $table->string('guarantor_city')->nullable()->after('guarantor_address');
            $table->string('guarantor_id_type')->nullable()->after('guarantor_city'); // 'ci' or 'bi'
            $table->string('guarantor_id_series', 2)->nullable()->after('guarantor_id_type');
            $table->string('guarantor_id_number', 6)->nullable()->after('guarantor_id_series');
            $table->string('guarantor_id_issued_by')->nullable()->after('guarantor_id_number');
            $table->date('guarantor_id_issued_date')->nullable()->after('guarantor_id_issued_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            $table->dropColumn([
                'person_type',
                'work_mode',
                'organizer_type',
                'vat_payer',
                'representative_first_name',
                'representative_last_name',
                'guarantor_first_name',
                'guarantor_last_name',
                'guarantor_cnp',
                'guarantor_address',
                'guarantor_city',
                'guarantor_id_type',
                'guarantor_id_series',
                'guarantor_id_number',
                'guarantor_id_issued_by',
                'guarantor_id_issued_date',
            ]);
        });
    }
};

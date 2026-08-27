<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            $table->string('individual_full_name', 255)->nullable()->after('company_zip');
            $table->string('individual_cnp', 13)->nullable()->after('individual_full_name');
            $table->string('individual_id_series_number', 40)->nullable()->after('individual_cnp');
            $table->text('individual_address')->nullable()->after('individual_id_series_number');
            $table->string('individual_city', 100)->nullable()->after('individual_address');
            $table->string('individual_county', 100)->nullable()->after('individual_city');
            $table->string('individual_country', 100)->nullable()->default('România')->after('individual_county');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            $table->dropColumn([
                'individual_full_name',
                'individual_cnp',
                'individual_id_series_number',
                'individual_address',
                'individual_city',
                'individual_county',
                'individual_country',
            ]);
        });
    }
};

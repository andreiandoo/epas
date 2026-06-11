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
            $table->string('company_city', 100)->nullable()->after('company_address');
            $table->string('company_county', 100)->nullable()->after('company_city');
            $table->string('company_zip', 20)->nullable()->after('company_county');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            $table->dropColumn(['company_city', 'company_county', 'company_zip']);
        });
    }
};

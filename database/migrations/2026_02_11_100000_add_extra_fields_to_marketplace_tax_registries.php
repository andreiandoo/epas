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
        Schema::table('marketplace_tax_registries', function (Blueprint $table) {
            $table->string('commune')->nullable()->after('city');
            $table->string('website_url')->nullable()->after('email');
            $table->string('email2')->nullable()->after('email');
            $table->text('directions')->nullable()->after('address')->comment('Indicații');
            $table->string('siruta_code')->nullable()->after('iban')->comment('Cod SIRUTA');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_tax_registries', function (Blueprint $table) {
            $table->dropColumn(['commune', 'website_url', 'email2', 'directions', 'siruta_code']);
        });
    }
};

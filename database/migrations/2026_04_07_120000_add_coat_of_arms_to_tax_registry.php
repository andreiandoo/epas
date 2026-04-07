<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_tax_registries', function (Blueprint $table) {
            $table->string('coat_of_arms')->nullable()->after('siruta_code');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_tax_registries', function (Blueprint $table) {
            $table->dropColumn('coat_of_arms');
        });
    }
};

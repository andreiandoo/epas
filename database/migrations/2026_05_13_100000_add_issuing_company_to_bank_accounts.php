<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_organizer_bank_accounts', function (Blueprint $table) {
            // 'primary' | 'secondary' — leagă contul de societatea emitentă;
            // default 'primary' pentru a nu rupe conturile existente.
            $table->string('issuing_company', 16)->default('primary')->after('account_holder');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_organizer_bank_accounts', function (Blueprint $table) {
            $table->dropColumn('issuing_company');
        });
    }
};

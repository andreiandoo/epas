<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('festival_editions', 'cashless_mode')) {
            Schema::table('festival_editions', function (Blueprint $table) {
                $table->string('cashless_mode', 10)->default('nfc')->after('currency');
            });
        }
    }

    public function down(): void
    {
        Schema::table('festival_editions', function (Blueprint $table) {
            $table->dropColumn('cashless_mode');
        });
    }
};

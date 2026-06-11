<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_clients')) {
            return;
        }

        if (! Schema::hasColumn('marketplace_clients', 'timezone')) {
            Schema::table('marketplace_clients', function (Blueprint $table) {
                $table->string('timezone', 64)->default('Europe/Bucharest')->after('currency');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('marketplace_clients') && Schema::hasColumn('marketplace_clients', 'timezone')) {
            Schema::table('marketplace_clients', function (Blueprint $table) {
                $table->dropColumn('timezone');
            });
        }
    }
};

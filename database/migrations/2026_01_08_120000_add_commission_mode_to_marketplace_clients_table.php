<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_clients', function (Blueprint $table) {
            $table->string('commission_mode', 20)->default('included')->after('commission_rate');
            // Values: 'included' (price includes commission) or 'add_on_top' (commission added to price)
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_clients', function (Blueprint $table) {
            $table->dropColumn('commission_mode');
        });
    }
};

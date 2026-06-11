<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('marketplace_customers', 'avatar')) {
            return;
        }

        Schema::table('marketplace_customers', function (Blueprint $table) {
            $table->string('avatar', 500)->nullable()->after('last_name');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_customers', function (Blueprint $table) {
            $table->dropColumn('avatar');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add operating_hours field to marketplace_clients table.
     * This field stores business operating hours (program functionare).
     */
    public function up(): void
    {
        Schema::table('marketplace_clients', function (Blueprint $table) {
            $table->string('operating_hours')->nullable()->after('contact_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_clients', function (Blueprint $table) {
            $table->dropColumn('operating_hours');
        });
    }
};

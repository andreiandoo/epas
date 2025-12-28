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
        // Add organizer_type and has_own_website to tenants table
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('organizer_type')->nullable()->after('work_method');
            $table->boolean('has_own_website')->default(true)->after('organizer_type');
        });

        // Add is_subdomain flag to domains table
        Schema::table('domains', function (Blueprint $table) {
            $table->boolean('is_subdomain')->default(false)->after('is_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['organizer_type', 'has_own_website']);
        });

        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn('is_subdomain');
        });
    }
};

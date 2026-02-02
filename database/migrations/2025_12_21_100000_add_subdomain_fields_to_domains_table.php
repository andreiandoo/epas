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
        Schema::table('domains', function (Blueprint $table) {
            $table->boolean('is_managed_subdomain')->default(false)->after('is_primary');
            $table->string('subdomain', 63)->nullable()->after('is_managed_subdomain');
            $table->string('base_domain', 190)->nullable()->after('subdomain');
            $table->string('cloudflare_record_id', 32)->nullable()->after('base_domain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn([
                'is_managed_subdomain',
                'subdomain',
                'base_domain',
                'cloudflare_record_id',
            ]);
        });
    }
};

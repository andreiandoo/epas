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
        // Add marketplace_client_id to coupon_campaigns and make tenant_id nullable
        Schema::table('coupon_campaigns', function (Blueprint $table) {
            // Drop foreign key constraint first
            $table->dropForeign(['tenant_id']);
        });

        Schema::table('coupon_campaigns', function (Blueprint $table) {
            // Make tenant_id nullable
            $table->unsignedBigInteger('tenant_id')->nullable()->change();

            // Add marketplace_client_id
            $table->foreignId('marketplace_client_id')->nullable()->after('tenant_id')->constrained('marketplace_clients')->onDelete('cascade');

            // Re-add foreign key for tenant_id as nullable
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // Add marketplace_client_id to coupon_codes and make tenant_id nullable
        Schema::table('coupon_codes', function (Blueprint $table) {
            // Drop foreign key and unique constraint first
            $table->dropUnique(['tenant_id', 'code']);
            $table->dropForeign(['tenant_id']);
        });

        Schema::table('coupon_codes', function (Blueprint $table) {
            // Make tenant_id nullable
            $table->unsignedBigInteger('tenant_id')->nullable()->change();

            // Add marketplace_client_id
            $table->foreignId('marketplace_client_id')->nullable()->after('tenant_id')->constrained('marketplace_clients')->onDelete('cascade');

            // Re-add foreign key for tenant_id as nullable
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            // Re-add unique constraint with marketplace_client_id
            $table->unique(['marketplace_client_id', 'code'], 'coupon_codes_marketplace_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coupon_codes', function (Blueprint $table) {
            $table->dropUnique('coupon_codes_marketplace_code_unique');
            $table->dropForeign(['marketplace_client_id']);
            $table->dropColumn('marketplace_client_id');
        });

        Schema::table('coupon_campaigns', function (Blueprint $table) {
            $table->dropForeign(['marketplace_client_id']);
            $table->dropColumn('marketplace_client_id');
        });
    }
};

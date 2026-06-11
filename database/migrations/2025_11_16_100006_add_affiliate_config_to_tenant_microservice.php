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
        // Add specific columns for affiliate tracking configuration
        Schema::table('tenant_microservice', function (Blueprint $table) {
            // The configuration JSON field already exists, but we'll document the expected structure:
            // {
            //   "cookie_name": "aff_ref",
            //   "cookie_duration_days": 90,
            //   "commission_type": "percent|fixed",
            //   "commission_value": 10.00,
            //   "self_purchase_guard": true,
            //   "exclude_taxes_from_commission": true
            // }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No changes needed
    }
};

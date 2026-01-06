<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenant_payment_configs', function (Blueprint $table) {
            // Noda Open Banking credentials
            $table->string('noda_api_key')->nullable()->after('sms_fallback_processor');
            $table->string('noda_shop_id')->nullable()->after('noda_api_key');
            $table->string('noda_signature_key')->nullable()->after('noda_shop_id');
        });

        // Update PostgreSQL enum to include 'noda' processor
        // First, get current allowed values and add 'noda' if not present
        DB::statement("ALTER TABLE tenant_payment_configs DROP CONSTRAINT IF EXISTS tenant_payment_configs_processor_check");
        DB::statement("ALTER TABLE tenant_payment_configs ADD CONSTRAINT tenant_payment_configs_processor_check CHECK (processor IN ('stripe', 'netopia', 'euplatesc', 'payu', 'revolut', 'paypal', 'klarna', 'sms', 'noda'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_payment_configs', function (Blueprint $table) {
            $table->dropColumn([
                'noda_api_key',
                'noda_shop_id',
                'noda_signature_key',
            ]);
        });

        // Restore previous constraint
        DB::statement("ALTER TABLE tenant_payment_configs DROP CONSTRAINT IF EXISTS tenant_payment_configs_processor_check");
        DB::statement("ALTER TABLE tenant_payment_configs ADD CONSTRAINT tenant_payment_configs_processor_check CHECK (processor IN ('stripe', 'netopia', 'euplatesc', 'payu', 'revolut', 'paypal', 'klarna', 'sms'))");
    }
};

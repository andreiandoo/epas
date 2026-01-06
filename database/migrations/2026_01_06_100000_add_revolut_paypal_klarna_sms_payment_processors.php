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
        // First, alter the processor enum to include new processors
        // We need to drop and recreate the constraint for PostgreSQL
        DB::statement("ALTER TABLE tenant_payment_configs DROP CONSTRAINT IF EXISTS tenant_payment_configs_processor_check");
        DB::statement("ALTER TABLE tenant_payment_configs ADD CONSTRAINT tenant_payment_configs_processor_check CHECK (processor::text = ANY (ARRAY['stripe'::text, 'netopia'::text, 'euplatesc'::text, 'payu'::text, 'revolut'::text, 'paypal'::text, 'klarna'::text, 'sms'::text]))");

        Schema::table('tenant_payment_configs', function (Blueprint $table) {
            // Revolut credentials
            $table->text('revolut_api_key')->nullable()->after('payu_secret_key');
            $table->text('revolut_merchant_id')->nullable()->after('revolut_api_key');
            $table->text('revolut_webhook_secret')->nullable()->after('revolut_merchant_id');

            // PayPal credentials
            $table->text('paypal_client_id')->nullable()->after('revolut_webhook_secret');
            $table->text('paypal_client_secret')->nullable()->after('paypal_client_id');
            $table->text('paypal_webhook_id')->nullable()->after('paypal_client_secret');

            // Klarna credentials
            $table->text('klarna_api_username')->nullable()->after('paypal_webhook_id');
            $table->text('klarna_api_password')->nullable()->after('klarna_api_username');
            $table->text('klarna_region')->nullable()->after('klarna_api_password'); // eu, na, oc

            // SMS Payment credentials (using Twilio for SMS delivery)
            $table->text('sms_twilio_sid')->nullable()->after('klarna_region');
            $table->text('sms_twilio_auth_token')->nullable()->after('sms_twilio_sid');
            $table->text('sms_twilio_phone_number')->nullable()->after('sms_twilio_auth_token');
            $table->text('sms_fallback_processor')->nullable()->after('sms_twilio_phone_number'); // Which processor to use for actual payment
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_payment_configs', function (Blueprint $table) {
            // Remove Revolut columns
            $table->dropColumn([
                'revolut_api_key',
                'revolut_merchant_id',
                'revolut_webhook_secret',
            ]);

            // Remove PayPal columns
            $table->dropColumn([
                'paypal_client_id',
                'paypal_client_secret',
                'paypal_webhook_id',
            ]);

            // Remove Klarna columns
            $table->dropColumn([
                'klarna_api_username',
                'klarna_api_password',
                'klarna_region',
            ]);

            // Remove SMS columns
            $table->dropColumn([
                'sms_twilio_sid',
                'sms_twilio_auth_token',
                'sms_twilio_phone_number',
                'sms_fallback_processor',
            ]);
        });

        // Restore original processor enum
        DB::statement("ALTER TABLE tenant_payment_configs DROP CONSTRAINT IF EXISTS tenant_payment_configs_processor_check");
        DB::statement("ALTER TABLE tenant_payment_configs ADD CONSTRAINT tenant_payment_configs_processor_check CHECK (processor::text = ANY (ARRAY['stripe'::text, 'netopia'::text, 'euplatesc'::text, 'payu'::text]))");
    }
};

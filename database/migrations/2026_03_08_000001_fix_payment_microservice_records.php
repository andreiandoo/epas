<?php

use App\Models\Microservice;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $payuSchema = [
            'type' => 'payment_gateway',
            'supported_currencies' => ['RON', 'EUR', 'USD', 'PLN', 'HUF'],
            'settings_schema' => [
                ['key' => 'test_mode', 'label' => 'Enable Sandbox Mode', 'type' => 'boolean', 'default' => true, 'section' => 'mode'],
                ['key' => 'test_merchant_id', 'label' => 'Sandbox Merchant ID', 'type' => 'text', 'required' => false, 'section' => 'test'],
                ['key' => 'test_secret_key', 'label' => 'Sandbox Secret Key', 'type' => 'password', 'required' => false, 'section' => 'test'],
                ['key' => 'live_merchant_id', 'label' => 'Live Merchant ID', 'type' => 'text', 'required' => false, 'section' => 'live'],
                ['key' => 'live_secret_key', 'label' => 'Live Secret Key', 'type' => 'password', 'required' => false, 'section' => 'live'],
            ],
            'settings_sections' => [
                'mode' => ['label' => 'Environment', 'description' => 'Select which environment to use'],
                'test' => ['label' => 'Sandbox Credentials', 'description' => 'Use these credentials for testing in sandbox mode'],
                'live' => ['label' => 'Live/Production Credentials', 'description' => 'Use these credentials for real transactions'],
            ],
        ];

        $euplatescSchema = [
            'type' => 'payment_gateway',
            'supported_currencies' => ['RON', 'EUR'],
            'settings_schema' => [
                ['key' => 'test_mode', 'label' => 'Enable Sandbox Mode', 'type' => 'boolean', 'default' => true, 'section' => 'mode'],
                ['key' => 'test_merchant_id', 'label' => 'Sandbox Merchant ID', 'type' => 'text', 'required' => false, 'section' => 'test'],
                ['key' => 'test_secret_key', 'label' => 'Sandbox Secret Key', 'type' => 'password', 'required' => false, 'section' => 'test'],
                ['key' => 'live_merchant_id', 'label' => 'Live Merchant ID', 'type' => 'text', 'required' => false, 'section' => 'live'],
                ['key' => 'live_secret_key', 'label' => 'Live Secret Key', 'type' => 'password', 'required' => false, 'section' => 'live'],
            ],
            'settings_sections' => [
                'mode' => ['label' => 'Environment', 'description' => 'Select which environment to use'],
                'test' => ['label' => 'Sandbox Credentials', 'description' => 'Use these credentials for testing in sandbox mode'],
                'live' => ['label' => 'Live/Production Credentials', 'description' => 'Use these credentials for real transactions'],
            ],
        ];

        // Fix PayU record if it exists with wrong category
        Microservice::where('slug', 'payment-payu')->update([
            'category' => 'payment',
            'metadata' => $payuSchema,
        ]);

        // Fix EuPlatesc record if it exists with wrong category
        Microservice::where('slug', 'payment-euplatesc')->update([
            'category' => 'payment',
            'metadata' => $euplatescSchema,
        ]);
    }

    public function down(): void
    {
        // No rollback needed
    }
};

<?php

namespace Database\Seeders;

use App\Models\Microservice;
use Illuminate\Database\Seeder;

class PaymentMicroservicesSeeder extends Seeder
{
    public function run(): void
    {
        $paymentMethods = [
            [
                'name' => ['en' => 'Stripe', 'ro' => 'Stripe'],
                'slug' => 'payment-stripe',
                'description' => [
                    'en' => 'Accept credit card payments via Stripe. Supports Visa, Mastercard, American Express, and more.',
                    'ro' => 'Acceptă plăți cu cardul prin Stripe. Suportă Visa, Mastercard, American Express și altele.',
                ],
                'short_description' => [
                    'en' => 'Credit card payments via Stripe',
                    'ro' => 'Plăți cu cardul prin Stripe',
                ],
                'icon' => 'credit-card',
                'category' => 'payment',
                'is_active' => true,
                'sort_order' => 1,
                'metadata' => [
                    'type' => 'payment_gateway',
                    'supported_currencies' => ['RON', 'EUR', 'USD'],
                    'settings_schema' => [
                        // Mode selection
                        ['key' => 'test_mode', 'label' => 'Enable Test Mode', 'type' => 'boolean', 'default' => true, 'section' => 'mode'],

                        // Test/Sandbox credentials
                        ['key' => 'test_publishable_key', 'label' => 'Test Publishable Key', 'type' => 'text', 'required' => false, 'section' => 'test', 'placeholder' => 'pk_test_...'],
                        ['key' => 'test_secret_key', 'label' => 'Test Secret Key', 'type' => 'password', 'required' => false, 'section' => 'test', 'placeholder' => 'sk_test_...'],
                        ['key' => 'test_webhook_secret', 'label' => 'Test Webhook Secret', 'type' => 'password', 'required' => false, 'section' => 'test', 'placeholder' => 'whsec_...'],

                        // Live/Production credentials
                        ['key' => 'live_publishable_key', 'label' => 'Live Publishable Key', 'type' => 'text', 'required' => false, 'section' => 'live', 'placeholder' => 'pk_live_...'],
                        ['key' => 'live_secret_key', 'label' => 'Live Secret Key', 'type' => 'password', 'required' => false, 'section' => 'live', 'placeholder' => 'sk_live_...'],
                        ['key' => 'live_webhook_secret', 'label' => 'Live Webhook Secret', 'type' => 'password', 'required' => false, 'section' => 'live', 'placeholder' => 'whsec_...'],
                    ],
                    'settings_sections' => [
                        'mode' => ['label' => 'Environment', 'description' => 'Select which environment to use'],
                        'test' => ['label' => 'Test/Sandbox Credentials', 'description' => 'Use these credentials for testing'],
                        'live' => ['label' => 'Live/Production Credentials', 'description' => 'Use these credentials for real transactions'],
                    ],
                ],
            ],
            [
                'name' => ['en' => 'Netopia', 'ro' => 'Netopia'],
                'slug' => 'payment-netopia',
                'description' => [
                    'en' => 'Accept payments via Netopia mobilPay. Popular payment gateway in Romania.',
                    'ro' => 'Acceptă plăți prin Netopia mobilPay. Procesor de plăți popular în România.',
                ],
                'short_description' => [
                    'en' => 'Netopia mobilPay payments',
                    'ro' => 'Plăți prin Netopia mobilPay',
                ],
                'icon' => 'banknotes',
                'category' => 'payment',
                'is_active' => true,
                'sort_order' => 2,
                'metadata' => [
                    'type' => 'payment_gateway',
                    'supported_currencies' => ['RON', 'EUR'],
                    'settings_schema' => [
                        // Mode selection
                        ['key' => 'test_mode', 'label' => 'Enable Sandbox Mode', 'type' => 'boolean', 'default' => true, 'section' => 'mode'],

                        // Sandbox credentials
                        ['key' => 'test_merchant_id', 'label' => 'Sandbox Merchant ID (Signature)', 'type' => 'text', 'required' => false, 'section' => 'test'],
                        ['key' => 'test_public_key', 'label' => 'Sandbox Public Key (Certificate)', 'type' => 'textarea', 'required' => false, 'section' => 'test'],
                        ['key' => 'test_private_key', 'label' => 'Sandbox Private Key', 'type' => 'textarea', 'required' => false, 'section' => 'test'],

                        // Live credentials
                        ['key' => 'live_merchant_id', 'label' => 'Live Merchant ID (Signature)', 'type' => 'text', 'required' => false, 'section' => 'live'],
                        ['key' => 'live_public_key', 'label' => 'Live Public Key (Certificate)', 'type' => 'textarea', 'required' => false, 'section' => 'live'],
                        ['key' => 'live_private_key', 'label' => 'Live Private Key', 'type' => 'textarea', 'required' => false, 'section' => 'live'],
                    ],
                    'settings_sections' => [
                        'mode' => ['label' => 'Environment', 'description' => 'Select which environment to use'],
                        'test' => ['label' => 'Sandbox Credentials', 'description' => 'Use these credentials for testing in sandbox mode'],
                        'live' => ['label' => 'Live/Production Credentials', 'description' => 'Use these credentials for real transactions'],
                    ],
                ],
            ],
            [
                'name' => ['en' => 'SMS Payment', 'ro' => 'Plată prin SMS'],
                'slug' => 'payment-sms',
                'description' => [
                    'en' => 'Accept payments via premium SMS. Users can pay by sending an SMS to a short number.',
                    'ro' => 'Acceptă plăți prin SMS premium. Utilizatorii pot plăti trimițând un SMS la un număr scurt.',
                ],
                'short_description' => [
                    'en' => 'Premium SMS payments',
                    'ro' => 'Plăți prin SMS premium',
                ],
                'icon' => 'device-phone-mobile',
                'category' => 'payment',
                'is_active' => true,
                'sort_order' => 3,
                'metadata' => [
                    'type' => 'payment_gateway',
                    'supported_currencies' => ['RON'],
                    'settings_schema' => [
                        ['key' => 'provider', 'label' => 'SMS Provider', 'type' => 'select', 'options' => ['telekom', 'orange', 'vodafone', 'digi'], 'required' => true],
                        ['key' => 'short_code', 'label' => 'Short Code Number', 'type' => 'text', 'required' => true],
                        ['key' => 'keyword', 'label' => 'SMS Keyword', 'type' => 'text', 'required' => true],
                        ['key' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true],
                        ['key' => 'price_per_sms', 'label' => 'Price per SMS (RON)', 'type' => 'number', 'required' => true],
                    ],
                ],
            ],
            [
                'name' => ['en' => 'Bank Transfer', 'ro' => 'Transfer Bancar'],
                'slug' => 'payment-bank-transfer',
                'description' => [
                    'en' => 'Accept payments via bank transfer. Orders are confirmed manually after payment verification.',
                    'ro' => 'Acceptă plăți prin transfer bancar. Comenzile sunt confirmate manual după verificarea plății.',
                ],
                'short_description' => [
                    'en' => 'Manual bank transfer',
                    'ro' => 'Transfer bancar manual',
                ],
                'icon' => 'building-library',
                'category' => 'payment',
                'is_active' => true,
                'sort_order' => 4,
                'metadata' => [
                    'type' => 'payment_gateway',
                    'supported_currencies' => ['RON', 'EUR'],
                    'requires_manual_confirmation' => true,
                    'settings_schema' => [
                        ['key' => 'bank_name', 'label' => 'Bank Name', 'type' => 'text', 'required' => true],
                        ['key' => 'account_holder', 'label' => 'Account Holder Name', 'type' => 'text', 'required' => true],
                        ['key' => 'iban', 'label' => 'IBAN', 'type' => 'text', 'required' => true],
                        ['key' => 'swift', 'label' => 'SWIFT/BIC', 'type' => 'text', 'required' => false],
                        ['key' => 'payment_instructions', 'label' => 'Payment Instructions', 'type' => 'textarea', 'required' => false],
                    ],
                ],
            ],
            [
                'name' => ['en' => 'Cash on Delivery', 'ro' => 'Plata la Livrare'],
                'slug' => 'payment-cod',
                'description' => [
                    'en' => 'Accept cash payments at the event entrance or at ticket pickup points.',
                    'ro' => 'Acceptă plăți în numerar la intrarea în eveniment sau la punctele de ridicare bilete.',
                ],
                'short_description' => [
                    'en' => 'Cash payment at venue',
                    'ro' => 'Plată în numerar la locație',
                ],
                'icon' => 'currency-dollar',
                'category' => 'payment',
                'is_active' => true,
                'sort_order' => 5,
                'metadata' => [
                    'type' => 'payment_gateway',
                    'supported_currencies' => ['RON'],
                    'requires_manual_confirmation' => true,
                    'settings_schema' => [
                        ['key' => 'pickup_locations', 'label' => 'Pickup Locations', 'type' => 'textarea', 'required' => false],
                        ['key' => 'additional_fee', 'label' => 'Additional Fee (RON)', 'type' => 'number', 'required' => false],
                        ['key' => 'max_order_value', 'label' => 'Max Order Value (RON)', 'type' => 'number', 'required' => false],
                    ],
                ],
            ],
        ];

        foreach ($paymentMethods as $data) {
            Microservice::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }

        $this->command->info('Payment microservices seeded successfully!');
    }
}

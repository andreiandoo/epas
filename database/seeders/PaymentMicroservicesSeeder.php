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
                        ['key' => 'test_mode', 'label' => 'Enable Test Mode', 'type' => 'boolean', 'default' => true, 'section' => 'mode',
                            'help_html' => 'When ON, the marketplace uses Test credentials and the Test webhook secret to validate incoming events. Switch OFF only when you are ready to accept real payments.'],

                        // Test/Sandbox credentials
                        ['key' => 'test_publishable_key', 'label' => 'Test Publishable Key', 'type' => 'text', 'required' => false, 'section' => 'test', 'placeholder' => 'pk_test_...',
                            'help_html' => 'Find it in Stripe Dashboard → <a href="https://dashboard.stripe.com/test/apikeys" target="_blank" rel="noopener">Developers → API keys</a> (Test mode). Copy the value from <strong>Publishable key</strong>.'],
                        ['key' => 'test_secret_key', 'label' => 'Test Secret Key', 'type' => 'password', 'required' => false, 'section' => 'test', 'placeholder' => 'sk_test_...',
                            'help_html' => 'Same page (<a href="https://dashboard.stripe.com/test/apikeys" target="_blank" rel="noopener">Developers → API keys</a>, Test mode), Reveal & copy <strong>Secret key</strong>. Never share or commit.'],
                        ['key' => 'test_webhook_secret', 'label' => 'Test Webhook Secret', 'type' => 'password', 'required' => false, 'section' => 'test', 'placeholder' => 'whsec_...',
                            'help_html' => '<strong>How to generate this value:</strong><ol class="list-decimal ml-5 mt-1 space-y-1"><li>Open <a href="https://dashboard.stripe.com/test/webhooks" target="_blank" rel="noopener">Stripe Dashboard → Developers → Webhooks</a> (Test mode).</li><li>Click <strong>+ Add endpoint</strong>.</li><li>For <strong>Endpoint URL</strong> paste exactly:<br><code class="select-all">{WEBHOOK_URL}</code></li><li>Click <strong>Select events</strong> and enable: <code>payment_intent.succeeded</code>, <code>payment_intent.payment_failed</code>, <code>charge.refunded</code>.</li><li>Click <strong>Add endpoint</strong>.</li><li>On the endpoint page, click <strong>Reveal</strong> next to <strong>Signing secret</strong> and copy the <code>whsec_…</code> value into this field.</li></ol>'],

                        // Live/Production credentials
                        ['key' => 'live_publishable_key', 'label' => 'Live Publishable Key', 'type' => 'text', 'required' => false, 'section' => 'live', 'placeholder' => 'pk_live_...',
                            'help_html' => 'Find it in Stripe Dashboard → <a href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener">Developers → API keys</a> (Live mode — toggle in top-left). Copy <strong>Publishable key</strong>.'],
                        ['key' => 'live_secret_key', 'label' => 'Live Secret Key', 'type' => 'password', 'required' => false, 'section' => 'live', 'placeholder' => 'sk_live_...',
                            'help_html' => 'Same page (<a href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener">Developers → API keys</a>, Live mode), Reveal & copy <strong>Secret key</strong>. Treat as production password.'],
                        ['key' => 'live_webhook_secret', 'label' => 'Live Webhook Secret', 'type' => 'password', 'required' => false, 'section' => 'live', 'placeholder' => 'whsec_...',
                            'help_html' => '<strong>How to generate this value:</strong><ol class="list-decimal ml-5 mt-1 space-y-1"><li>Open <a href="https://dashboard.stripe.com/webhooks" target="_blank" rel="noopener">Stripe Dashboard → Developers → Webhooks</a> (Live mode — toggle in top-left).</li><li>Click <strong>+ Add endpoint</strong>.</li><li>For <strong>Endpoint URL</strong> paste exactly:<br><code class="select-all">{WEBHOOK_URL}</code></li><li>Click <strong>Select events</strong> and enable: <code>payment_intent.succeeded</code>, <code>payment_intent.payment_failed</code>, <code>charge.refunded</code>.</li><li>Click <strong>Add endpoint</strong>.</li><li>On the endpoint page, click <strong>Reveal</strong> next to <strong>Signing secret</strong> and copy the <code>whsec_…</code> value into this field.</li><li>Important: the <strong>Live</strong> secret is different from the Test one — they are issued separately per Stripe mode.</li></ol>'],
                    ],
                    'settings_sections' => [
                        'mode' => ['label' => 'Environment', 'description' => 'Select which environment (Test/Live) the marketplace uses.'],
                        'test' => ['label' => 'Test / Sandbox Credentials', 'description' => 'Credentials for testing. No real money is moved.',
                            'info_html' => '<div class="text-xs leading-relaxed space-y-2"><p><strong>Webhook endpoint URL for this marketplace (Test):</strong><br><code class="select-all break-all">{WEBHOOK_URL}</code></p><p><strong>Events to enable in Stripe Dashboard:</strong></p><table class="text-xs w-full mt-1"><thead><tr class="text-left text-gray-500 dark:text-gray-400"><th class="py-0.5 pr-3">Event</th><th class="py-0.5">What it triggers in EventPilot</th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-700"><tr><td class="py-1 pr-3"><code>payment_intent.succeeded</code></td><td class="py-1">Order → <em>paid</em>, tickets pending→valid, seats held→sold.</td></tr><tr><td class="py-1 pr-3"><code>payment_intent.payment_failed</code></td><td class="py-1">Order → <em>failed</em> + error stored on the order.</td></tr><tr><td class="py-1 pr-3"><code>charge.refunded</code></td><td class="py-1">Order → <em>refunded</em> (full) or <em>partially_refunded</em>, refunded_at + refunded_amount written.</td></tr></tbody></table><p class="text-gray-500 dark:text-gray-400"><strong>Do NOT</strong> add subscription / invoice events here (<code>customer.subscription.*</code>, <code>invoice.paid</code>) — those are handled by Tixello\'s own webhook at <code>/webhooks/stripe</code> for platform subscriptions, not by marketplace customer orders. Enabling them here just adds noise and "unhandled" log lines.</p></div>'],
                        'live' => ['label' => 'Live / Production Credentials', 'description' => 'Credentials for real customer transactions. Treat the Live Secret Key like a production password.',
                            'info_html' => '<div class="text-xs leading-relaxed space-y-2"><p><strong>Webhook endpoint URL for this marketplace (Live):</strong><br><code class="select-all break-all">{WEBHOOK_URL}</code></p><p>Use the <strong>same</strong> URL for Live mode — the URL is per-marketplace, NOT per-mode. Stripe handles the Test/Live split on their side: events fired from your Test dashboard go to the endpoint registered in Test, events from Live go to the endpoint registered in Live, and each has its own <code>whsec_…</code> signing secret. The marketplace picks which secret to validate against based on the <em>Enable Test Mode</em> toggle above.</p><p class="text-gray-500 dark:text-gray-400">Reminder: register two webhook endpoints in Stripe (one in Test dashboard, one in Live dashboard), both pointing at the same URL, paste each <code>whsec_…</code> into the matching field here.</p></div>'],
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

                        // Cultural card
                        ['key' => 'cultural_card_enabled', 'label' => 'Activează plata prin Carduri Culturale', 'type' => 'boolean', 'default' => false, 'section' => 'cultural_card'],
                        ['key' => 'cultural_card_surcharge_percent', 'label' => 'Procent suplimentar card cultural (%)', 'type' => 'number', 'required' => false, 'placeholder' => '4', 'section' => 'cultural_card'],
                    ],
                    'settings_sections' => [
                        'mode' => ['label' => 'Environment', 'description' => 'Select which environment to use'],
                        'test' => ['label' => 'Sandbox Credentials', 'description' => 'Use these credentials for testing in sandbox mode'],
                        'live' => ['label' => 'Live/Production Credentials', 'description' => 'Use these credentials for real transactions'],
                        'cultural_card' => ['label' => 'Card Cultural', 'description' => 'Setări pentru plata prin carduri culturale (Edenred, Sodexo, Up România). Procesarea se face tot prin Netopia.'],
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
                'name' => ['en' => 'PayU', 'ro' => 'PayU'],
                'slug' => 'payment-payu',
                'description' => [
                    'en' => 'Accept payments via PayU. International payment gateway popular in Eastern Europe.',
                    'ro' => 'Acceptă plăți prin PayU. Procesor de plăți internațional popular în Europa de Est.',
                ],
                'short_description' => [
                    'en' => 'PayU payments',
                    'ro' => 'Plăți prin PayU',
                ],
                'icon' => 'banknotes',
                'category' => 'payment',
                'is_active' => true,
                'sort_order' => 6,
                'metadata' => [
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
                ],
            ],
            [
                'name' => ['en' => 'EuPlatesc', 'ro' => 'EuPlatesc'],
                'slug' => 'payment-euplatesc',
                'description' => [
                    'en' => 'Accept payments via EuPlatesc. Romanian payment processor with competitive rates.',
                    'ro' => 'Acceptă plăți prin EuPlatesc. Procesor de plăți românesc cu rate competitive.',
                ],
                'short_description' => [
                    'en' => 'EuPlatesc payments',
                    'ro' => 'Plăți prin EuPlatesc',
                ],
                'icon' => 'banknotes',
                'category' => 'payment',
                'is_active' => true,
                'sort_order' => 7,
                'metadata' => [
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

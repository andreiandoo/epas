<?php

namespace App\Filament\Resources\Tenants\Pages;

use App\Filament\Resources\Tenants\TenantResource;
use App\Models\TenantPaymentConfig;
use App\Services\PaymentProcessors\PaymentProcessorFactory;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components as SC;
use Filament\Notifications\Notification;

class ManagePaymentConfig extends ManageRecords
{
    protected static string $resource = TenantResource::class;

    protected static ?string $title = 'Payment Configuration';

    public function getBreadcrumb(): string
    {
        return 'Payment Config';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('test_connection')
                ->label('Test Connection')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->action(function () {
                    $tenant = $this->record;
                    $config = $tenant->activePaymentConfig();

                    if (!$config) {
                        Notification::make()
                            ->title('No Configuration Found')
                            ->body('Please configure your payment processor first.')
                            ->danger()
                            ->send();
                        return;
                    }

                    try {
                        $processor = PaymentProcessorFactory::makeFromConfig($config);

                        if (!$processor->isConfigured()) {
                            Notification::make()
                                ->title('Incomplete Configuration')
                                ->body('Please fill in all required fields.')
                                ->warning()
                                ->send();
                            return;
                        }

                        Notification::make()
                            ->title('Connection Successful!')
                            ->body("Your {$processor->getName()} configuration is valid.")
                            ->success()
                            ->send();

                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Connection Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    protected function getFormSchema(): array
    {
        $tenant = $this->record;

        return [
            SC\Section::make('Payment Processor Selection')
                ->description('Select and configure your payment processor for processing customer payments')
                ->schema([
                    Forms\Components\Select::make('payment_processor')
                        ->label('Payment Processor')
                        ->options([
                            'stripe' => 'Stripe',
                            'netopia' => 'Netopia Payments (mobilPay)',
                            'euplatesc' => 'EuPlatesc',
                            'payu' => 'PayU',
                            'revolut' => 'Revolut',
                            'paypal' => 'PayPal',
                            'klarna' => 'Klarna (Buy Now Pay Later)',
                            'sms' => 'SMS Payment',
                            'noda' => 'Noda Open Banking (Pay by Bank)',
                        ])
                        ->required()
                        ->live()
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('Selected during registration. Contact support to change.')
                        ->default($tenant->payment_processor),

                    Forms\Components\Select::make('payment_processor_mode')
                        ->label('Mode')
                        ->options([
                            'test' => 'Test / Sandbox',
                            'live' => 'Live / Production',
                        ])
                        ->required()
                        ->live()
                        ->default($tenant->payment_processor_mode ?? 'test')
                        ->helperText('Start in test mode and switch to live when ready'),
                ]),

            // Stripe Configuration
            SC\Section::make('Stripe Configuration')
                ->description('Enter your Stripe API keys from the Stripe Dashboard')
                ->schema([
                    Forms\Components\TextInput::make('stripe_publishable_key')
                        ->label('Publishable Key')
                        ->placeholder('pk_test_... or pk_live_...')
                        ->helperText('Public key for frontend integration')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('stripe_secret_key')
                        ->label('Secret Key')
                        ->password()
                        ->revealable()
                        ->placeholder('sk_test_... or sk_live_...')
                        ->helperText('Secret key for backend API calls')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('stripe_webhook_secret')
                        ->label('Webhook Secret (Optional)')
                        ->password()
                        ->revealable()
                        ->placeholder('whsec_...')
                        ->helperText('For webhook signature verification')
                        ->maxLength(255),

                    Forms\Components\Placeholder::make('stripe_webhook_url')
                        ->label('Webhook URL')
                        ->content(fn () => route('webhooks.tenant-payment', ['tenant' => $tenant->id, 'processor' => 'stripe']))
                        ->helperText('Add this URL to your Stripe webhook settings'),
                ])
                ->visible(fn (Forms\Get $get) => $tenant->payment_processor === 'stripe')
                ->columns(1),

            // Netopia Configuration
            SC\Section::make('Netopia Payments Configuration')
                ->description('Enter your Netopia (mobilPay) credentials')
                ->schema([
                    Forms\Components\TextInput::make('netopia_signature')
                        ->label('Merchant Signature')
                        ->placeholder('Your Netopia signature')
                        ->helperText('Merchant signature from Netopia dashboard')
                        ->maxLength(255),

                    Forms\Components\Textarea::make('netopia_api_key')
                        ->label('Private Key (PEM)')
                        ->placeholder('-----BEGIN PRIVATE KEY-----' . "\n" . '...' . "\n" . '-----END PRIVATE KEY-----')
                        ->helperText('Your private key in PEM format')
                        ->rows(8),

                    Forms\Components\Textarea::make('netopia_public_key')
                        ->label('Public Certificate (PEM)')
                        ->placeholder('-----BEGIN CERTIFICATE-----' . "\n" . '...' . "\n" . '-----END CERTIFICATE-----')
                        ->helperText('Your public certificate in PEM format')
                        ->rows(8),

                    Forms\Components\Placeholder::make('netopia_callback_url')
                        ->label('Callback URL')
                        ->content(fn () => route('webhooks.tenant-payment', ['tenant' => $tenant->id, 'processor' => 'netopia']))
                        ->helperText('Add this URL to your Netopia account settings'),
                ])
                ->visible(fn (Forms\Get $get) => $tenant->payment_processor === 'netopia')
                ->columns(1),

            // Euplatesc Configuration
            SC\Section::make('EuPlatesc Configuration')
                ->description('Enter your EuPlatesc merchant credentials')
                ->schema([
                    Forms\Components\TextInput::make('euplatesc_merchant_id')
                        ->label('Merchant ID')
                        ->placeholder('Your EuPlatesc merchant ID')
                        ->helperText('Merchant ID from EuPlatesc account')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('euplatesc_secret_key')
                        ->label('Secret Key')
                        ->password()
                        ->revealable()
                        ->placeholder('Your secret key')
                        ->helperText('Secret key for HMAC signature generation')
                        ->maxLength(255),

                    Forms\Components\Placeholder::make('euplatesc_callback_url')
                        ->label('Callback URL')
                        ->content(fn () => route('webhooks.tenant-payment', ['tenant' => $tenant->id, 'processor' => 'euplatesc']))
                        ->helperText('Add this URL to your EuPlatesc account settings'),
                ])
                ->visible(fn (Forms\Get $get) => $tenant->payment_processor === 'euplatesc')
                ->columns(1),

            // PayU Configuration
            SC\Section::make('PayU Configuration')
                ->description('Enter your PayU merchant credentials')
                ->schema([
                    Forms\Components\TextInput::make('payu_merchant_id')
                        ->label('Merchant Code')
                        ->placeholder('Your PayU merchant code')
                        ->helperText('Merchant code from PayU account')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('payu_secret_key')
                        ->label('Secret Key')
                        ->password()
                        ->revealable()
                        ->placeholder('Your secret key')
                        ->helperText('Secret key for HMAC signature generation')
                        ->maxLength(255),

                    Forms\Components\Placeholder::make('payu_callback_url')
                        ->label('IPN/IOS URL')
                        ->content(fn () => route('webhooks.tenant-payment', ['tenant' => $tenant->id, 'processor' => 'payu']))
                        ->helperText('Add this URL to your PayU account for IPN/IOS notifications'),
                ])
                ->visible(fn (Forms\Get $get) => $tenant->payment_processor === 'payu')
                ->columns(1),

            // Revolut Configuration
            SC\Section::make('Revolut Configuration')
                ->description('Enter your Revolut Merchant API credentials')
                ->schema([
                    Forms\Components\TextInput::make('revolut_api_key')
                        ->label('API Key (Secret Key)')
                        ->password()
                        ->revealable()
                        ->placeholder('sk_...')
                        ->helperText('Secret key from Revolut Business API')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('revolut_merchant_id')
                        ->label('Merchant ID (Public Key)')
                        ->placeholder('pk_...')
                        ->helperText('Public key for frontend widget (optional)')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('revolut_webhook_secret')
                        ->label('Webhook Secret')
                        ->password()
                        ->revealable()
                        ->placeholder('Your webhook signing secret')
                        ->helperText('For webhook signature verification (optional)')
                        ->maxLength(255),

                    Forms\Components\Placeholder::make('revolut_callback_url')
                        ->label('Webhook URL')
                        ->content(fn () => route('webhooks.tenant-payment', ['tenant' => $tenant->id, 'processor' => 'revolut']))
                        ->helperText('Add this URL to your Revolut Business webhook settings'),
                ])
                ->visible(fn (Forms\Get $get) => $tenant->payment_processor === 'revolut')
                ->columns(1),

            // PayPal Configuration
            SC\Section::make('PayPal Configuration')
                ->description('Enter your PayPal REST API credentials')
                ->schema([
                    Forms\Components\TextInput::make('paypal_client_id')
                        ->label('Client ID')
                        ->placeholder('Your PayPal client ID')
                        ->helperText('Client ID from PayPal Developer Dashboard')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('paypal_client_secret')
                        ->label('Client Secret')
                        ->password()
                        ->revealable()
                        ->placeholder('Your PayPal client secret')
                        ->helperText('Client secret for API authentication')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('paypal_webhook_id')
                        ->label('Webhook ID (Optional)')
                        ->placeholder('Your PayPal webhook ID')
                        ->helperText('For webhook signature verification')
                        ->maxLength(255),

                    Forms\Components\Placeholder::make('paypal_callback_url')
                        ->label('Webhook URL')
                        ->content(fn () => route('webhooks.tenant-payment', ['tenant' => $tenant->id, 'processor' => 'paypal']))
                        ->helperText('Add this URL to your PayPal Developer webhook settings'),
                ])
                ->visible(fn (Forms\Get $get) => $tenant->payment_processor === 'paypal')
                ->columns(1),

            // Klarna Configuration
            SC\Section::make('Klarna Configuration')
                ->description('Enter your Klarna Payments API credentials')
                ->schema([
                    Forms\Components\TextInput::make('klarna_api_username')
                        ->label('API Username (UID)')
                        ->placeholder('K12345_abcdef123456...')
                        ->helperText('Username from Klarna Merchant Portal')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('klarna_api_password')
                        ->label('API Password')
                        ->password()
                        ->revealable()
                        ->placeholder('Your Klarna API password')
                        ->helperText('Password for API authentication')
                        ->maxLength(255),

                    Forms\Components\Select::make('klarna_region')
                        ->label('Region')
                        ->options([
                            'eu' => 'Europe (EU)',
                            'na' => 'North America (NA)',
                            'oc' => 'Oceania (OC)',
                        ])
                        ->default('eu')
                        ->helperText('Select the Klarna region for your account'),

                    Forms\Components\Placeholder::make('klarna_callback_url')
                        ->label('Webhook URL')
                        ->content(fn () => route('webhooks.tenant-payment', ['tenant' => $tenant->id, 'processor' => 'klarna']))
                        ->helperText('Add this URL to your Klarna Merchant Portal'),

                    Forms\Components\Placeholder::make('klarna_payment_methods')
                        ->label('Supported Payment Methods')
                        ->content(new \Illuminate\Support\HtmlString('
                            <ul class="list-disc pl-4 text-sm">
                                <li><strong>Pay Later</strong> - Pay in 30 days</li>
                                <li><strong>Pay in 3</strong> - Split into 3 interest-free payments</li>
                                <li><strong>Financing</strong> - Monthly installments</li>
                            </ul>
                        ')),
                ])
                ->visible(fn (Forms\Get $get) => $tenant->payment_processor === 'klarna')
                ->columns(1),

            // SMS Payment Configuration
            SC\Section::make('SMS Payment Configuration')
                ->description('Configure SMS-based payment collection using Twilio')
                ->schema([
                    Forms\Components\TextInput::make('sms_twilio_sid')
                        ->label('Twilio Account SID')
                        ->placeholder('AC...')
                        ->helperText('Account SID from Twilio Console')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('sms_twilio_auth_token')
                        ->label('Twilio Auth Token')
                        ->password()
                        ->revealable()
                        ->placeholder('Your Twilio auth token')
                        ->helperText('Auth token for API authentication')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('sms_twilio_phone_number')
                        ->label('Twilio Phone Number')
                        ->placeholder('+1234567890')
                        ->helperText('Your Twilio phone number in E.164 format')
                        ->maxLength(20),

                    Forms\Components\Select::make('sms_fallback_processor')
                        ->label('Fallback Payment Processor')
                        ->options([
                            'stripe' => 'Stripe',
                            'paypal' => 'PayPal',
                            'revolut' => 'Revolut',
                            'klarna' => 'Klarna',
                        ])
                        ->default('stripe')
                        ->helperText('The processor that will handle the actual payment after SMS link click'),

                    Forms\Components\Placeholder::make('sms_how_it_works')
                        ->label('How SMS Payment Works')
                        ->content(new \Illuminate\Support\HtmlString('
                            <ol class="list-decimal pl-4 text-sm space-y-1">
                                <li>Customer receives SMS with payment link</li>
                                <li>Customer clicks link to open secure payment page</li>
                                <li>Payment is processed via the fallback processor</li>
                                <li>Customer receives SMS confirmation on success</li>
                            </ol>
                        ')),

                    Forms\Components\Placeholder::make('sms_status_webhook_url')
                        ->label('Twilio Status Webhook URL')
                        ->content(fn () => url('/webhooks/twilio-sms-status/' . $tenant->slug))
                        ->helperText('Optional: Add this URL in Twilio for delivery status updates'),
                ])
                ->visible(fn (Forms\Get $get) => $tenant->payment_processor === 'sms')
                ->columns(1),

            // Noda Open Banking Configuration
            SC\Section::make('Noda Open Banking Configuration')
                ->description('Configure Pay by Bank - instant account-to-account payments via SEPA Instant (EUR) and Plăți Instant (RON)')
                ->schema([
                    Forms\Components\TextInput::make('noda_api_key')
                        ->label('API Key')
                        ->password()
                        ->revealable()
                        ->placeholder('Your Noda API key')
                        ->helperText('Get your API key from ui.noda.live/hub after registration')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('noda_shop_id')
                        ->label('Shop ID')
                        ->placeholder('Your Noda shop/merchant ID')
                        ->helperText('Shop identifier for your merchant account')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('noda_signature_key')
                        ->label('Webhook Signature Key')
                        ->password()
                        ->revealable()
                        ->placeholder('Webhook signature key')
                        ->helperText('Used to verify webhook authenticity (optional but recommended)')
                        ->maxLength(255),

                    Forms\Components\Toggle::make('noda_sandbox')
                        ->label('Sandbox Mode')
                        ->default(true)
                        ->helperText('Use test environment for development. Disable for production.')
                        ->live(),

                    Forms\Components\Placeholder::make('noda_supported_currencies')
                        ->label('Supported Currencies & Payment Rails')
                        ->content(new \Illuminate\Support\HtmlString('
                            <div class="text-sm space-y-2">
                                <p><strong>🇷🇴 RON</strong> - Romania (Plăți Instant via TRANSFOND) - <span class="text-green-600">Instant settlement</span></p>
                                <p><strong>🇪🇺 EUR</strong> - SEPA countries (SEPA Instant) - <span class="text-green-600">Instant settlement</span></p>
                                <p><strong>🇬🇧 GBP</strong> - UK (Faster Payments) - <span class="text-green-600">Instant settlement</span></p>
                                <p><strong>🇵🇱 PLN</strong> - Poland (Express Elixir) - <span class="text-green-600">Instant settlement</span></p>
                                <p class="text-gray-500">Also supports: CZK, BGN, HUF, SEK, DKK, NOK, CHF</p>
                            </div>
                        ')),

                    Forms\Components\Placeholder::make('noda_benefits')
                        ->label('Benefits')
                        ->content(new \Illuminate\Support\HtmlString('
                            <ul class="list-disc pl-4 text-sm space-y-1">
                                <li><strong>Ultra-low fees:</strong> From 0.1% vs 1.5-2.5% for cards</li>
                                <li><strong>Instant settlement:</strong> Funds arrive in ~10 seconds</li>
                                <li><strong>No chargebacks:</strong> Bank-to-bank payments cannot be disputed</li>
                                <li><strong>PSD2 compliant:</strong> Strong Customer Authentication (SCA) built-in</li>
                                <li><strong>2,000+ banks:</strong> Coverage across 28 European countries</li>
                            </ul>
                        ')),

                    Forms\Components\Placeholder::make('noda_webhook_url')
                        ->label('Webhook URL')
                        ->content(fn () => url('/payment/webhook/noda'))
                        ->helperText('Configure this URL in your Noda dashboard for payment notifications'),
                ])
                ->visible(fn (Forms\Get $get) => $tenant->payment_processor === 'noda')
                ->columns(1),

            SC\Section::make('Important Notes')
                ->description('Security and best practices')
                ->schema([
                    Forms\Components\Placeholder::make('security_notes')
                        ->label('')
                        ->content(new \Illuminate\Support\HtmlString('
                            <div class="space-y-2 text-sm">
                                <p><strong>🔒 Security:</strong> All API keys and secrets are encrypted in the database.</p>
                                <p><strong>🧪 Testing:</strong> Start with test/sandbox mode before switching to live.</p>
                                <p><strong>🔔 Webhooks:</strong> Configure webhooks in your payment processor dashboard for automatic order updates.</p>
                                <p><strong>📚 Documentation:</strong> Refer to your payment processor\'s documentation for obtaining API credentials.</p>
                            </div>
                        ')),
                ])
                ->collapsible(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $tenant = $this->record;

        // Load existing payment config if exists
        $config = $tenant->activePaymentConfig();

        if ($config) {
            $keys = $config->getActiveKeys();

            // Merge config data based on processor
            switch ($tenant->payment_processor) {
                case 'stripe':
                    $data['stripe_publishable_key'] = $config->stripe_publishable_key;
                    $data['stripe_secret_key'] = $config->stripe_secret_key;
                    $data['stripe_webhook_secret'] = $config->stripe_webhook_secret;
                    break;

                case 'netopia':
                    $data['netopia_signature'] = $config->netopia_signature;
                    $data['netopia_api_key'] = $config->netopia_api_key;
                    $data['netopia_public_key'] = $config->netopia_public_key;
                    break;

                case 'euplatesc':
                    $data['euplatesc_merchant_id'] = $config->euplatesc_merchant_id;
                    $data['euplatesc_secret_key'] = $config->euplatesc_secret_key;
                    break;

                case 'payu':
                    $data['payu_merchant_id'] = $config->payu_merchant_id;
                    $data['payu_secret_key'] = $config->payu_secret_key;
                    break;

                case 'revolut':
                    $data['revolut_api_key'] = $config->revolut_api_key;
                    $data['revolut_merchant_id'] = $config->revolut_merchant_id;
                    $data['revolut_webhook_secret'] = $config->revolut_webhook_secret;
                    break;

                case 'paypal':
                    $data['paypal_client_id'] = $config->paypal_client_id;
                    $data['paypal_client_secret'] = $config->paypal_client_secret;
                    $data['paypal_webhook_id'] = $config->paypal_webhook_id;
                    break;

                case 'klarna':
                    $data['klarna_api_username'] = $config->klarna_api_username;
                    $data['klarna_api_password'] = $config->klarna_api_password;
                    $data['klarna_region'] = $config->klarna_region ?? 'eu';
                    break;

                case 'sms':
                    $data['sms_twilio_sid'] = $config->sms_twilio_sid;
                    $data['sms_twilio_auth_token'] = $config->sms_twilio_auth_token;
                    $data['sms_twilio_phone_number'] = $config->sms_twilio_phone_number;
                    $data['sms_fallback_processor'] = $config->sms_fallback_processor ?? 'stripe';
                    break;

                case 'noda':
                    $data['noda_api_key'] = $config->noda_api_key;
                    $data['noda_shop_id'] = $config->noda_shop_id;
                    $data['noda_signature_key'] = $config->noda_signature_key;
                    $data['noda_sandbox'] = $config->additional_config['sandbox'] ?? true;
                    break;
            }
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $tenant = $this->record;

        // Update tenant mode
        if (isset($data['payment_processor_mode'])) {
            $tenant->update([
                'payment_processor_mode' => $data['payment_processor_mode'],
            ]);
        }

        // Update or create payment config
        $configData = [
            'processor' => $tenant->payment_processor,
            'mode' => $data['payment_processor_mode'] ?? 'test',
            'is_active' => true,
        ];

        // Add processor-specific fields
        switch ($tenant->payment_processor) {
            case 'stripe':
                $configData['stripe_publishable_key'] = $data['stripe_publishable_key'] ?? null;
                $configData['stripe_secret_key'] = $data['stripe_secret_key'] ?? null;
                $configData['stripe_webhook_secret'] = $data['stripe_webhook_secret'] ?? null;
                break;

            case 'netopia':
                $configData['netopia_signature'] = $data['netopia_signature'] ?? null;
                $configData['netopia_api_key'] = $data['netopia_api_key'] ?? null;
                $configData['netopia_public_key'] = $data['netopia_public_key'] ?? null;
                break;

            case 'euplatesc':
                $configData['euplatesc_merchant_id'] = $data['euplatesc_merchant_id'] ?? null;
                $configData['euplatesc_secret_key'] = $data['euplatesc_secret_key'] ?? null;
                break;

            case 'payu':
                $configData['payu_merchant_id'] = $data['payu_merchant_id'] ?? null;
                $configData['payu_secret_key'] = $data['payu_secret_key'] ?? null;
                break;

            case 'revolut':
                $configData['revolut_api_key'] = $data['revolut_api_key'] ?? null;
                $configData['revolut_merchant_id'] = $data['revolut_merchant_id'] ?? null;
                $configData['revolut_webhook_secret'] = $data['revolut_webhook_secret'] ?? null;
                break;

            case 'paypal':
                $configData['paypal_client_id'] = $data['paypal_client_id'] ?? null;
                $configData['paypal_client_secret'] = $data['paypal_client_secret'] ?? null;
                $configData['paypal_webhook_id'] = $data['paypal_webhook_id'] ?? null;
                break;

            case 'klarna':
                $configData['klarna_api_username'] = $data['klarna_api_username'] ?? null;
                $configData['klarna_api_password'] = $data['klarna_api_password'] ?? null;
                $configData['klarna_region'] = $data['klarna_region'] ?? 'eu';
                break;

            case 'sms':
                $configData['sms_twilio_sid'] = $data['sms_twilio_sid'] ?? null;
                $configData['sms_twilio_auth_token'] = $data['sms_twilio_auth_token'] ?? null;
                $configData['sms_twilio_phone_number'] = $data['sms_twilio_phone_number'] ?? null;
                $configData['sms_fallback_processor'] = $data['sms_fallback_processor'] ?? 'stripe';
                break;

            case 'noda':
                $configData['noda_api_key'] = $data['noda_api_key'] ?? null;
                $configData['noda_shop_id'] = $data['noda_shop_id'] ?? null;
                $configData['noda_signature_key'] = $data['noda_signature_key'] ?? null;
                $configData['additional_config'] = [
                    'sandbox' => $data['noda_sandbox'] ?? true,
                ];
                break;
        }

        // Validate configuration
        $errors = PaymentProcessorFactory::validateConfig($tenant->payment_processor, $configData);

        if (!empty($errors)) {
            Notification::make()
                ->title('Validation Errors')
                ->body(implode(' ', $errors))
                ->danger()
                ->send();

            throw new \Exception('Please correct the validation errors.');
        }

        // Update or create config
        TenantPaymentConfig::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'processor' => $tenant->payment_processor,
            ],
            $configData
        );

        Notification::make()
            ->title('Configuration Saved')
            ->body('Your payment processor configuration has been saved successfully.')
            ->success()
            ->send();

        return $data;
    }

    protected function afterSave(): void
    {
        // Clear any caches related to this tenant's payment config
        // cache()->forget("tenant.{$this->record->id}.payment_config");
    }
}

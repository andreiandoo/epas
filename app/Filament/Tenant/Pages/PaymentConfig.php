<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Microservice;
use App\Models\TenantPaymentConfig;
use App\Services\ApplePayDomainService;
use App\Services\PaymentProcessors\PaymentProcessorFactory;
use BackedEnum;
use Filament\Forms;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Illuminate\Support\HtmlString;

class PaymentConfig extends Page
{
    use Forms\Concerns\InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Payment Processor';
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 10;
    protected string $view = 'filament.tenant.pages.payment-config';

    public ?array $data = [];
    public ?string $activeProcessor = null;
    public ?string $processorLabel = null;
    public array $applePayDomains = [];
    public ?string $domainToRegister = null;

    /**
     * Payment processor microservice slugs mapping
     */
    protected static array $paymentProcessorSlugs = [
        'payment-stripe' => 'stripe',
        'payment-netopia' => 'netopia',
        'payment-euplatesc' => 'euplatesc',
        'payment-payu' => 'payu',
        'payment-revolut' => 'revolut',
        'payment-paypal' => 'paypal',
        'payment-klarna' => 'klarna',
        'payment-sms' => 'sms',
    ];

    /**
     * Only show in navigation if tenant has a payment processor microservice activated
     */
    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()?->tenant;

        if (!$tenant) {
            return false;
        }

        return static::getActivePaymentProcessor($tenant) !== null;
    }

    /**
     * Get the active payment processor for a tenant
     */
    protected static function getActivePaymentProcessor($tenant): ?string
    {
        $paymentMicroserviceSlugs = array_keys(static::$paymentProcessorSlugs);

        // Use pivot table relationship
        $activeMicroservice = $tenant->microservices()
            ->whereIn('microservices.slug', $paymentMicroserviceSlugs)
            ->wherePivot('is_active', true)
            ->first();

        if ($activeMicroservice) {
            return static::$paymentProcessorSlugs[$activeMicroservice->slug] ?? null;
        }

        return null;
    }

    public function mount(): void
    {
        $tenant = auth()->user()->tenant;

        if (!$tenant) {
            abort(404);
        }

        // Get active payment processor from microservice
        $this->activeProcessor = static::getActivePaymentProcessor($tenant);

        if (!$this->activeProcessor) {
            // Redirect to microservices page if no payment processor is activated
            Notification::make()
                ->warning()
                ->title('No Payment Processor')
                ->body('You need to activate a payment processor microservice first.')
                ->send();

            redirect()->route('filament.tenant.pages.microservices');
            return;
        }

        // Set processor label for display
        $this->processorLabel = match ($this->activeProcessor) {
            'stripe' => 'Stripe',
            'netopia' => 'Netopia Payments (mobilPay)',
            'euplatesc' => 'EuPlatesc',
            'payu' => 'PayU',
            'revolut' => 'Revolut',
            'paypal' => 'PayPal',
            'klarna' => 'Klarna (Buy Now Pay Later)',
            'sms' => 'SMS Payment',
            default => ucfirst($this->activeProcessor),
        };

        // Auto-set the processor on tenant if not set
        if ($tenant->payment_processor !== $this->activeProcessor) {
            $tenant->update(['payment_processor' => $this->activeProcessor]);
        }

        $formData = [
            'payment_processor' => $this->activeProcessor,
            'payment_processor_mode' => $tenant->payment_processor_mode ?? 'test',
        ];

        // Load existing payment config
        $config = $tenant->activePaymentConfig();

        if ($config) {
            $additionalConfig = $config->additional_config ?? [];

            switch ($this->activeProcessor) {
                case 'stripe':
                    // Live credentials (stored in main columns)
                    $formData['stripe_live_publishable_key'] = $config->stripe_publishable_key;
                    $formData['stripe_live_secret_key'] = $config->stripe_secret_key;
                    $formData['stripe_live_webhook_secret'] = $config->stripe_webhook_secret;
                    // Test credentials (stored in additional_config)
                    $formData['stripe_test_publishable_key'] = $additionalConfig['stripe_test_publishable_key'] ?? null;
                    $formData['stripe_test_secret_key'] = $additionalConfig['stripe_test_secret_key'] ?? null;
                    $formData['stripe_test_webhook_secret'] = $additionalConfig['stripe_test_webhook_secret'] ?? null;
                    // Load Apple Pay domains
                    $this->loadApplePayDomains($tenant);
                    break;

                case 'netopia':
                    $formData['netopia_signature'] = $config->netopia_signature;
                    $formData['netopia_api_key'] = $config->netopia_api_key;
                    $formData['netopia_public_key'] = $config->netopia_public_key;
                    break;

                case 'euplatesc':
                    $formData['euplatesc_merchant_id'] = $config->euplatesc_merchant_id;
                    $formData['euplatesc_secret_key'] = $config->euplatesc_secret_key;
                    break;

                case 'payu':
                    $formData['payu_merchant_id'] = $config->payu_merchant_id;
                    $formData['payu_secret_key'] = $config->payu_secret_key;
                    break;

                case 'revolut':
                    $formData['revolut_api_key'] = $config->revolut_api_key;
                    $formData['revolut_merchant_id'] = $config->revolut_merchant_id;
                    $formData['revolut_webhook_secret'] = $config->revolut_webhook_secret;
                    break;

                case 'paypal':
                    $formData['paypal_client_id'] = $config->paypal_client_id;
                    $formData['paypal_client_secret'] = $config->paypal_client_secret;
                    $formData['paypal_webhook_id'] = $config->paypal_webhook_id;
                    break;

                case 'klarna':
                    $formData['klarna_api_username'] = $config->klarna_api_username;
                    $formData['klarna_api_password'] = $config->klarna_api_password;
                    $formData['klarna_region'] = $config->klarna_region ?? 'eu';
                    break;

                case 'sms':
                    $formData['sms_twilio_sid'] = $config->sms_twilio_sid;
                    $formData['sms_twilio_auth_token'] = $config->sms_twilio_auth_token;
                    $formData['sms_twilio_phone_number'] = $config->sms_twilio_phone_number;
                    $formData['sms_fallback_processor'] = $config->sms_fallback_processor ?? 'stripe';
                    break;
            }
        }

        $this->form->fill($formData);
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
                    $tenant = auth()->user()->tenant;
                    $config = $tenant->activePaymentConfig();

                    if (!$config) {
                        Notification::make()
                            ->title('No Configuration Found')
                            ->body('Please save your configuration first.')
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

    public function form(Schema $form): Schema
    {
        $tenant = auth()->user()->tenant;
        $processor = $this->activeProcessor;

        return $form
            ->schema([
                SC\Section::make('Payment Processor')
                    ->description('Configure your payment processor to accept payments from customers')
                    ->schema([
                        // Hidden field to store the processor
                        Forms\Components\Hidden::make('payment_processor')
                            ->default($processor),

                        // Display the active processor (read-only)
                        Forms\Components\Placeholder::make('active_processor_display')
                            ->label('Active Processor')
                            ->content(fn () => new HtmlString(
                                '<span class="inline-flex items-center px-3 py-1 text-sm font-medium text-green-800 bg-green-100 rounded-full">' .
                                $this->processorLabel .
                                '</span>'
                            ))
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'This processor is enabled via your microservices subscription'),

                        Forms\Components\Select::make('payment_processor_mode')
                            ->label('Active Mode')
                            ->options([
                                'test' => 'Test / Sandbox',
                                'live' => 'Live / Production',
                            ])
                            ->required()
                            ->default('test')
                            ->live()
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Select which credentials to use for payments'),
                    ])->columns(2),

                // Stripe Test Configuration
                SC\Section::make('Test Credentials (Sandbox)')
                    ->description('Enter your Stripe TEST API keys (pk_test_..., sk_test_...)')
                    ->icon('heroicon-o-beaker')
                    ->schema([
                        Forms\Components\TextInput::make('stripe_test_publishable_key')
                            ->label('Test Publishable Key')
                            ->placeholder('pk_test_...')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Public key for frontend integration (test mode)')
                            ->maxLength(255)
                            ->extraInputAttributes(['autocomplete' => 'off', 'data-1p-ignore' => 'true', 'data-lpignore' => 'true']),

                        Forms\Components\TextInput::make('stripe_test_secret_key')
                            ->label('Test Secret Key')
                            ->password()
                            ->revealable()
                            ->placeholder('sk_test_...')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Secret key for backend API calls (test mode)')
                            ->maxLength(255)
                            ->extraInputAttributes(['autocomplete' => 'off', 'data-1p-ignore' => 'true', 'data-lpignore' => 'true']),

                        Forms\Components\TextInput::make('stripe_test_webhook_secret')
                            ->label('Test Webhook Secret')
                            ->password()
                            ->revealable()
                            ->placeholder('whsec_...')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'For webhook signature verification (test mode)')
                            ->maxLength(255)
                            ->extraInputAttributes(['autocomplete' => 'off', 'data-1p-ignore' => 'true', 'data-lpignore' => 'true']),
                    ])
                    ->visible(fn () => $processor === 'stripe')
                    ->columns(1)
                    ->collapsible(),

                // Stripe Live Configuration
                SC\Section::make('Live Credentials (Production)')
                    ->description('Enter your Stripe LIVE API keys (pk_live_..., sk_live_...)')
                    ->icon('heroicon-o-bolt')
                    ->schema([
                        Forms\Components\TextInput::make('stripe_live_publishable_key')
                            ->label('Live Publishable Key')
                            ->placeholder('pk_live_...')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Public key for frontend integration (live mode)')
                            ->maxLength(255)
                            ->extraInputAttributes(['autocomplete' => 'off', 'data-1p-ignore' => 'true', 'data-lpignore' => 'true']),

                        Forms\Components\TextInput::make('stripe_live_secret_key')
                            ->label('Live Secret Key')
                            ->password()
                            ->revealable()
                            ->placeholder('sk_live_...')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Secret key for backend API calls (live mode)')
                            ->maxLength(255)
                            ->extraInputAttributes(['autocomplete' => 'off', 'data-1p-ignore' => 'true', 'data-lpignore' => 'true']),

                        Forms\Components\TextInput::make('stripe_live_webhook_secret')
                            ->label('Live Webhook Secret')
                            ->password()
                            ->revealable()
                            ->placeholder('whsec_...')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'For webhook signature verification (live mode)')
                            ->maxLength(255)
                            ->extraInputAttributes(['autocomplete' => 'off', 'data-1p-ignore' => 'true', 'data-lpignore' => 'true']),
                    ])
                    ->visible(fn () => $processor === 'stripe')
                    ->columns(1)
                    ->collapsible(),

                // Stripe Webhook URL
                SC\Section::make('Webhook Configuration')
                    ->schema([
                        Forms\Components\Placeholder::make('stripe_webhook_url')
                            ->label('Webhook URL')
                            ->content(fn () => new HtmlString(
                                '<code class="px-2 py-1 text-sm bg-gray-100 rounded select-all dark:bg-gray-800">' .
                                ($tenant ? route('webhooks.tenant-payment', ['tenant' => $tenant->id, 'processor' => 'stripe']) : '-') .
                                '</code>'
                            ))
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Add this URL to your Stripe webhook settings for both test and live modes'),
                    ])
                    ->visible(fn () => $processor === 'stripe'),

                // Netopia Configuration
                SC\Section::make('Netopia Payments Configuration')
                    ->description('Enter your Netopia (mobilPay) credentials')
                    ->schema([
                        Forms\Components\TextInput::make('netopia_signature')
                            ->label('Merchant Signature')
                            ->placeholder('Your Netopia signature')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Merchant signature from Netopia dashboard')
                            ->maxLength(255)
                            ->extraInputAttributes(['autocomplete' => 'off', 'data-1p-ignore' => 'true', 'data-lpignore' => 'true']),

                        Forms\Components\Textarea::make('netopia_api_key')
                            ->label('Private Key (PEM)')
                            ->placeholder('-----BEGIN PRIVATE KEY-----' . "\n" . '...' . "\n" . '-----END PRIVATE KEY-----')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Your private key in PEM format')
                            ->rows(6)
                            ->extraInputAttributes(['autocomplete' => 'off', 'data-1p-ignore' => 'true', 'data-lpignore' => 'true']),

                        Forms\Components\Textarea::make('netopia_public_key')
                            ->label('Public Certificate (PEM)')
                            ->placeholder('-----BEGIN CERTIFICATE-----' . "\n" . '...' . "\n" . '-----END CERTIFICATE-----')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Your public certificate in PEM format')
                            ->rows(6)
                            ->extraInputAttributes(['autocomplete' => 'off', 'data-1p-ignore' => 'true', 'data-lpignore' => 'true']),

                        Forms\Components\Placeholder::make('netopia_callback_url')
                            ->label('Callback URL')
                            ->content(fn () => new HtmlString(
                                '<code class="px-2 py-1 text-sm bg-gray-100 rounded select-all dark:bg-gray-800">' .
                                ($tenant ? route('webhooks.tenant-payment', ['tenant' => $tenant->id, 'processor' => 'netopia']) : '-') .
                                '</code>'
                            ))
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Add this URL to your Netopia account settings'),
                    ])
                    ->visible(fn () => $processor === 'netopia')
                    ->columns(1),

                // Euplatesc Configuration
                SC\Section::make('EuPlatesc Configuration')
                    ->description('Enter your EuPlatesc merchant credentials')
                    ->schema([
                        Forms\Components\TextInput::make('euplatesc_merchant_id')
                            ->label('Merchant ID')
                            ->placeholder('Your EuPlatesc merchant ID')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Merchant ID from EuPlatesc account')
                            ->maxLength(255)
                            ->extraInputAttributes(['autocomplete' => 'off', 'data-1p-ignore' => 'true', 'data-lpignore' => 'true']),

                        Forms\Components\TextInput::make('euplatesc_secret_key')
                            ->label('Secret Key')
                            ->password()
                            ->revealable()
                            ->placeholder('Your secret key')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Secret key for HMAC signature generation')
                            ->maxLength(255)
                            ->extraInputAttributes(['autocomplete' => 'off', 'data-1p-ignore' => 'true', 'data-lpignore' => 'true']),

                        Forms\Components\Placeholder::make('euplatesc_callback_url')
                            ->label('Callback URL')
                            ->content(fn () => new HtmlString(
                                '<code class="px-2 py-1 text-sm bg-gray-100 rounded select-all dark:bg-gray-800">' .
                                ($tenant ? route('webhooks.tenant-payment', ['tenant' => $tenant->id, 'processor' => 'euplatesc']) : '-') .
                                '</code>'
                            ))
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Add this URL to your EuPlatesc account settings'),
                    ])
                    ->visible(fn () => $processor === 'euplatesc')
                    ->columns(1),

                // PayU Configuration
                SC\Section::make('PayU Configuration')
                    ->description('Enter your PayU merchant credentials')
                    ->schema([
                        Forms\Components\TextInput::make('payu_merchant_id')
                            ->label('Merchant Code')
                            ->placeholder('Your PayU merchant code')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Merchant code from PayU account')
                            ->maxLength(255)
                            ->extraInputAttributes(['autocomplete' => 'off', 'data-1p-ignore' => 'true', 'data-lpignore' => 'true']),

                        Forms\Components\TextInput::make('payu_secret_key')
                            ->label('Secret Key')
                            ->password()
                            ->revealable()
                            ->placeholder('Your secret key')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Secret key for HMAC signature generation')
                            ->maxLength(255)
                            ->extraInputAttributes(['autocomplete' => 'off', 'data-1p-ignore' => 'true', 'data-lpignore' => 'true']),

                        Forms\Components\Placeholder::make('payu_callback_url')
                            ->label('IPN/IOS URL')
                            ->content(fn () => new HtmlString(
                                '<code class="px-2 py-1 text-sm bg-gray-100 rounded select-all dark:bg-gray-800">' .
                                ($tenant ? route('webhooks.tenant-payment', ['tenant' => $tenant->id, 'processor' => 'payu']) : '-') .
                                '</code>'
                            ))
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Add this URL to your PayU account for IPN/IOS notifications'),
                    ])
                    ->visible(fn () => $processor === 'payu')
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
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Secret key from Revolut Business API')
                            ->maxLength(255)
                            ->extraInputAttributes(['autocomplete' => 'off', 'data-1p-ignore' => 'true', 'data-lpignore' => 'true']),

                        Forms\Components\TextInput::make('revolut_merchant_id')
                            ->label('Merchant ID (Public Key)')
                            ->placeholder('pk_...')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Public key for frontend widget (optional)')
                            ->maxLength(255)
                            ->extraInputAttributes(['autocomplete' => 'off', 'data-1p-ignore' => 'true', 'data-lpignore' => 'true']),

                        Forms\Components\TextInput::make('revolut_webhook_secret')
                            ->label('Webhook Secret')
                            ->password()
                            ->revealable()
                            ->placeholder('Your webhook signing secret')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'For webhook signature verification (optional)')
                            ->maxLength(255)
                            ->extraInputAttributes(['autocomplete' => 'off', 'data-1p-ignore' => 'true', 'data-lpignore' => 'true']),

                        Forms\Components\Placeholder::make('revolut_callback_url')
                            ->label('Webhook URL')
                            ->content(fn () => new HtmlString(
                                '<code class="px-2 py-1 text-sm bg-gray-100 rounded select-all dark:bg-gray-800">' .
                                ($tenant ? route('webhooks.tenant-payment', ['tenant' => $tenant->id, 'processor' => 'revolut']) : '-') .
                                '</code>'
                            ))
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Add this URL to your Revolut Business webhook settings'),
                    ])
                    ->visible(fn () => $processor === 'revolut')
                    ->columns(1),

                // PayPal Configuration
                SC\Section::make('PayPal Configuration')
                    ->description('Enter your PayPal REST API credentials')
                    ->schema([
                        Forms\Components\TextInput::make('paypal_client_id')
                            ->label('Client ID')
                            ->placeholder('Your PayPal client ID')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Client ID from PayPal Developer Dashboard')
                            ->maxLength(255)
                            ->extraInputAttributes(['autocomplete' => 'off', 'data-1p-ignore' => 'true', 'data-lpignore' => 'true']),

                        Forms\Components\TextInput::make('paypal_client_secret')
                            ->label('Client Secret')
                            ->password()
                            ->revealable()
                            ->placeholder('Your PayPal client secret')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Client secret for API authentication')
                            ->maxLength(255)
                            ->extraInputAttributes(['autocomplete' => 'off', 'data-1p-ignore' => 'true', 'data-lpignore' => 'true']),

                        Forms\Components\TextInput::make('paypal_webhook_id')
                            ->label('Webhook ID (Optional)')
                            ->placeholder('Your PayPal webhook ID')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'For webhook signature verification')
                            ->maxLength(255)
                            ->extraInputAttributes(['autocomplete' => 'off', 'data-1p-ignore' => 'true', 'data-lpignore' => 'true']),

                        Forms\Components\Placeholder::make('paypal_callback_url')
                            ->label('Webhook URL')
                            ->content(fn () => new HtmlString(
                                '<code class="px-2 py-1 text-sm bg-gray-100 rounded select-all dark:bg-gray-800">' .
                                ($tenant ? route('webhooks.tenant-payment', ['tenant' => $tenant->id, 'processor' => 'paypal']) : '-') .
                                '</code>'
                            ))
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Add this URL to your PayPal Developer webhook settings'),
                    ])
                    ->visible(fn () => $processor === 'paypal')
                    ->columns(1),

                // Klarna Configuration
                SC\Section::make('Klarna Configuration')
                    ->description('Enter your Klarna Payments API credentials')
                    ->schema([
                        Forms\Components\TextInput::make('klarna_api_username')
                            ->label('API Username (UID)')
                            ->placeholder('K12345_abcdef123456...')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Username from Klarna Merchant Portal')
                            ->maxLength(255)
                            ->extraInputAttributes(['autocomplete' => 'off', 'data-1p-ignore' => 'true', 'data-lpignore' => 'true']),

                        Forms\Components\TextInput::make('klarna_api_password')
                            ->label('API Password')
                            ->password()
                            ->revealable()
                            ->placeholder('Your Klarna API password')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Password for API authentication')
                            ->maxLength(255)
                            ->extraInputAttributes(['autocomplete' => 'off', 'data-1p-ignore' => 'true', 'data-lpignore' => 'true']),

                        Forms\Components\Select::make('klarna_region')
                            ->label('Region')
                            ->options([
                                'eu' => 'Europe (EU)',
                                'na' => 'North America (NA)',
                                'oc' => 'Oceania (OC)',
                            ])
                            ->default('eu')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Select the Klarna region for your account'),

                        Forms\Components\Placeholder::make('klarna_callback_url')
                            ->label('Webhook URL')
                            ->content(fn () => new HtmlString(
                                '<code class="px-2 py-1 text-sm bg-gray-100 rounded select-all dark:bg-gray-800">' .
                                ($tenant ? route('webhooks.tenant-payment', ['tenant' => $tenant->id, 'processor' => 'klarna']) : '-') .
                                '</code>'
                            ))
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Add this URL to your Klarna Merchant Portal'),

                        Forms\Components\Placeholder::make('klarna_payment_methods')
                            ->label('Supported Payment Methods')
                            ->content(new HtmlString('
                                <ul class="list-disc pl-4 text-sm">
                                    <li><strong>Pay Later</strong> - Pay in 30 days</li>
                                    <li><strong>Pay in 3</strong> - Split into 3 interest-free payments</li>
                                    <li><strong>Financing</strong> - Monthly installments</li>
                                </ul>
                            ')),
                    ])
                    ->visible(fn () => $processor === 'klarna')
                    ->columns(1),

                // SMS Payment Configuration
                SC\Section::make('SMS Payment Configuration')
                    ->description('Configure SMS-based payment collection using Twilio')
                    ->schema([
                        Forms\Components\TextInput::make('sms_twilio_sid')
                            ->label('Twilio Account SID')
                            ->placeholder('AC...')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Account SID from Twilio Console')
                            ->maxLength(255)
                            ->extraInputAttributes(['autocomplete' => 'off', 'data-1p-ignore' => 'true', 'data-lpignore' => 'true']),

                        Forms\Components\TextInput::make('sms_twilio_auth_token')
                            ->label('Twilio Auth Token')
                            ->password()
                            ->revealable()
                            ->placeholder('Your Twilio auth token')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Auth token for API authentication')
                            ->maxLength(255)
                            ->extraInputAttributes(['autocomplete' => 'off', 'data-1p-ignore' => 'true', 'data-lpignore' => 'true']),

                        Forms\Components\TextInput::make('sms_twilio_phone_number')
                            ->label('Twilio Phone Number')
                            ->placeholder('+1234567890')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Your Twilio phone number in E.164 format')
                            ->maxLength(20)
                            ->extraInputAttributes(['autocomplete' => 'off', 'data-1p-ignore' => 'true', 'data-lpignore' => 'true']),

                        Forms\Components\Select::make('sms_fallback_processor')
                            ->label('Fallback Payment Processor')
                            ->options([
                                'stripe' => 'Stripe',
                                'paypal' => 'PayPal',
                                'revolut' => 'Revolut',
                                'klarna' => 'Klarna',
                            ])
                            ->default('stripe')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'The processor that will handle the actual payment after SMS link click'),

                        Forms\Components\Placeholder::make('sms_how_it_works')
                            ->label('How SMS Payment Works')
                            ->content(new HtmlString('
                                <ol class="list-decimal pl-4 text-sm space-y-1">
                                    <li>Customer receives SMS with payment link</li>
                                    <li>Customer clicks link to open secure payment page</li>
                                    <li>Payment is processed via the fallback processor</li>
                                    <li>Customer receives SMS confirmation on success</li>
                                </ol>
                            ')),

                        Forms\Components\Placeholder::make('sms_status_webhook_url')
                            ->label('Twilio Status Webhook URL')
                            ->content(fn () => new HtmlString(
                                '<code class="px-2 py-1 text-sm bg-gray-100 rounded select-all dark:bg-gray-800">' .
                                ($tenant ? url('/webhooks/twilio-sms-status/' . $tenant->slug) : '-') .
                                '</code>'
                            ))
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Optional: Add this URL in Twilio for delivery status updates'),
                    ])
                    ->visible(fn () => $processor === 'sms')
                    ->columns(1),

                SC\Section::make('Security Notes')
                    ->schema([
                        Forms\Components\Placeholder::make('security_notes')
                            ->label('')
                            ->content(new HtmlString('
                                <div class="space-y-2 text-sm">
                                    <p><strong>🔒 Security:</strong> All API keys and secrets are encrypted in the database.</p>
                                    <p><strong>🧪 Testing:</strong> Start with test/sandbox mode before switching to live.</p>
                                    <p><strong>🔔 Webhooks:</strong> Configure webhooks in your payment processor dashboard.</p>
                                </div>
                            ')),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $tenant = auth()->user()->tenant;

        if (!$tenant || !$this->activeProcessor) {
            return;
        }

        // Use the active processor (from microservice) - ignore form data for processor
        $processor = $this->activeProcessor;

        // Update tenant payment processor
        $tenant->update([
            'payment_processor' => $processor,
            'payment_processor_mode' => $data['payment_processor_mode'],
        ]);

        // Prepare config data
        $configData = [
            'processor' => $processor,
            'mode' => $data['payment_processor_mode'],
            'is_active' => true,
        ];

        // Add processor-specific fields
        switch ($processor) {
            case 'stripe':
                // Live credentials go in main columns
                $configData['stripe_publishable_key'] = $data['stripe_live_publishable_key'] ?? null;
                $configData['stripe_secret_key'] = $data['stripe_live_secret_key'] ?? null;
                $configData['stripe_webhook_secret'] = $data['stripe_live_webhook_secret'] ?? null;
                // Test credentials go in additional_config
                $configData['additional_config'] = [
                    'stripe_test_publishable_key' => $data['stripe_test_publishable_key'] ?? null,
                    'stripe_test_secret_key' => $data['stripe_test_secret_key'] ?? null,
                    'stripe_test_webhook_secret' => $data['stripe_test_webhook_secret'] ?? null,
                ];
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
                $configData['additional_config'] = [
                    'sandbox' => $data['revolut_sandbox'] ?? true,
                ];
                break;

            case 'paypal':
                $configData['paypal_client_id'] = $data['paypal_client_id'] ?? null;
                $configData['paypal_client_secret'] = $data['paypal_client_secret'] ?? null;
                $configData['paypal_webhook_id'] = $data['paypal_webhook_id'] ?? null;
                $configData['additional_config'] = [
                    'sandbox' => $data['paypal_sandbox'] ?? true,
                ];
                break;

            case 'klarna':
                $configData['klarna_username'] = $data['klarna_username'] ?? null;
                $configData['klarna_password'] = $data['klarna_password'] ?? null;
                $configData['klarna_region'] = $data['klarna_region'] ?? 'eu';
                $configData['additional_config'] = [
                    'sandbox' => $data['klarna_sandbox'] ?? true,
                ];
                break;

            case 'sms':
                $configData['sms_twilio_sid'] = $data['sms_twilio_sid'] ?? null;
                $configData['sms_twilio_auth_token'] = $data['sms_twilio_auth_token'] ?? null;
                $configData['sms_twilio_phone_number'] = $data['sms_twilio_phone_number'] ?? null;
                $configData['sms_fallback_processor'] = $data['sms_fallback_processor'] ?? 'stripe';
                break;
        }

        // Update or create config
        TenantPaymentConfig::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'processor' => $processor,
            ],
            $configData
        );

        Notification::make()
            ->success()
            ->title('Payment configuration saved')
            ->body('Your payment processor settings have been updated.')
            ->send();
    }

    public function getTitle(): string
    {
        return 'Payment Processor';
    }

    /**
     * Load Apple Pay registered domains from Stripe
     */
    protected function loadApplePayDomains($tenant): void
    {
        try {
            $service = new ApplePayDomainService();
            $this->applePayDomains = $service->listDomains($tenant);
        } catch (\Exception $e) {
            $this->applePayDomains = [];
        }
    }

    /**
     * Register a domain for Apple Pay
     */
    public function registerApplePayDomain(): void
    {
        $tenant = auth()->user()->tenant;

        if (!$tenant || !$this->domainToRegister) {
            Notification::make()
                ->warning()
                ->title('Domeniu lipsă')
                ->body('Te rog introdu un domeniu pentru înregistrare.')
                ->send();
            return;
        }

        // Clean the domain (remove http(s)://, trailing slashes, etc.)
        $domain = $this->domainToRegister;
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = rtrim($domain, '/');

        $service = new ApplePayDomainService();
        $result = $service->registerDomain($tenant, $domain);

        if ($result['success']) {
            Notification::make()
                ->success()
                ->title('Domeniu înregistrat')
                ->body($result['message'])
                ->send();

            // Reload domains
            $this->loadApplePayDomains($tenant);
            $this->domainToRegister = null;
        } else {
            Notification::make()
                ->danger()
                ->title('Înregistrare eșuată')
                ->body($result['message'])
                ->send();
        }
    }

    /**
     * Delete a registered Apple Pay domain
     */
    public function deleteApplePayDomain(string $domainId): void
    {
        $tenant = auth()->user()->tenant;

        if (!$tenant) {
            return;
        }

        $service = new ApplePayDomainService();
        $result = $service->deleteDomain($tenant, $domainId);

        if ($result['success']) {
            Notification::make()
                ->success()
                ->title('Domeniu șters')
                ->body($result['message'])
                ->send();

            // Reload domains
            $this->loadApplePayDomains($tenant);
        } else {
            Notification::make()
                ->danger()
                ->title('Ștergere eșuată')
                ->body($result['message'])
                ->send();
        }
    }
}

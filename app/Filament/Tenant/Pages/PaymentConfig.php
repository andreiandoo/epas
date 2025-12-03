<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Microservice;
use App\Models\TenantMicroservice;
use App\Models\TenantPaymentConfig;
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

    /**
     * Payment processor microservice slugs mapping
     */
    protected static array $paymentProcessorSlugs = [
        'payment-stripe' => 'stripe',
        'payment-netopia' => 'netopia',
        'payment-euplatesc' => 'euplatesc',
        'payment-payu' => 'payu',
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

        $activeMicroservice = TenantMicroservice::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereHas('microservice', function ($query) use ($paymentMicroserviceSlugs) {
                $query->whereIn('slug', $paymentMicroserviceSlugs);
            })
            ->with('microservice')
            ->first();

        if ($activeMicroservice && $activeMicroservice->microservice) {
            return static::$paymentProcessorSlugs[$activeMicroservice->microservice->slug] ?? null;
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
            switch ($this->activeProcessor) {
                case 'stripe':
                    $formData['stripe_publishable_key'] = $config->stripe_publishable_key;
                    $formData['stripe_secret_key'] = $config->stripe_secret_key;
                    $formData['stripe_webhook_secret'] = $config->stripe_webhook_secret;
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
                                '<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">' .
                                $this->processorLabel .
                                '</span>'
                            ))
                            ->helperText('This processor is enabled via your microservices subscription'),

                        Forms\Components\Select::make('payment_processor_mode')
                            ->label('Mode')
                            ->options([
                                'test' => 'Test / Sandbox',
                                'live' => 'Live / Production',
                            ])
                            ->required()
                            ->default('test')
                            ->helperText('Start in test mode and switch to live when ready'),
                    ])->columns(2),

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
                            ->content(fn () => $tenant ? route('webhooks.tenant-payment', ['tenant' => $tenant->id, 'processor' => 'stripe']) : '-')
                            ->helperText('Add this URL to your Stripe webhook settings'),
                    ])
                    ->visible(fn () => $processor === 'stripe')
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
                            ->rows(6),

                        Forms\Components\Textarea::make('netopia_public_key')
                            ->label('Public Certificate (PEM)')
                            ->placeholder('-----BEGIN CERTIFICATE-----' . "\n" . '...' . "\n" . '-----END CERTIFICATE-----')
                            ->helperText('Your public certificate in PEM format')
                            ->rows(6),

                        Forms\Components\Placeholder::make('netopia_callback_url')
                            ->label('Callback URL')
                            ->content(fn () => $tenant ? route('webhooks.tenant-payment', ['tenant' => $tenant->id, 'processor' => 'netopia']) : '-')
                            ->helperText('Add this URL to your Netopia account settings'),
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
                            ->content(fn () => $tenant ? route('webhooks.tenant-payment', ['tenant' => $tenant->id, 'processor' => 'euplatesc']) : '-')
                            ->helperText('Add this URL to your EuPlatesc account settings'),
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
                            ->content(fn () => $tenant ? route('webhooks.tenant-payment', ['tenant' => $tenant->id, 'processor' => 'payu']) : '-')
                            ->helperText('Add this URL to your PayU account for IPN/IOS notifications'),
                    ])
                    ->visible(fn () => $processor === 'payu')
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
}

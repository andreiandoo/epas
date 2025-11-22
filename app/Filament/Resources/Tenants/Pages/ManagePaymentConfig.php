<?php

namespace App\Filament\Resources\Tenants\Pages;

use App\Filament\Resources\Tenants\TenantResource;
use App\Models\TenantPaymentConfig;
use App\Services\PaymentProcessors\PaymentProcessorFactory;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
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
            Forms\Components\Section::make('Payment Processor Selection')
                ->description('Select and configure your payment processor for processing customer payments')
                ->schema([
                    Forms\Components\Select::make('payment_processor')
                        ->label('Payment Processor')
                        ->options([
                            'stripe' => 'Stripe',
                            'netopia' => 'Netopia Payments (mobilPay)',
                            'euplatesc' => 'EuPlatesc',
                            'payu' => 'PayU',
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
            Forms\Components\Section::make('Stripe Configuration')
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
            Forms\Components\Section::make('Netopia Payments Configuration')
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
            Forms\Components\Section::make('EuPlatesc Configuration')
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
            Forms\Components\Section::make('PayU Configuration')
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

            Forms\Components\Section::make('Important Notes')
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

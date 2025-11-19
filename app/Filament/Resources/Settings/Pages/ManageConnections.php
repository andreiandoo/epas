<?php

namespace App\Filament\Resources\Settings\Pages;

use App\Filament\Resources\Settings\SettingsResource;
use App\Models\Setting;
use Filament\Actions;
use Filament\Resources\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;

class ManageConnections extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = SettingsResource::class;

    protected static string $view = 'filament.resources.settings.pages.manage-connections';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = Setting::current();
        $this->form->fill([
            'stripe_mode' => $settings->stripe_mode,
            'stripe_test_public_key' => $settings->stripe_test_public_key,
            'stripe_test_secret_key' => $settings->stripe_test_secret_key,
            'stripe_live_public_key' => $settings->stripe_live_public_key,
            'stripe_live_secret_key' => $settings->stripe_live_secret_key,
            'stripe_webhook_secret' => $settings->stripe_webhook_secret,
            'vat_enabled' => $settings->vat_enabled ?? false,
            'vat_rate' => $settings->vat_rate ?? 21.00,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Stripe Payment Gateway')
                    ->description('Configure Stripe for processing microservice payments')
                    ->schema([
                        Forms\Components\Select::make('stripe_mode')
                            ->label('Stripe Mode')
                            ->options([
                                'test' => 'Test Mode',
                                'live' => 'Live Mode',
                            ])
                            ->default('test')
                            ->required()
                            ->live()
                            ->helperText('Switch between test and live mode. Always test first!'),

                        Forms\Components\Fieldset::make('Test Mode Keys')
                            ->schema([
                                Forms\Components\TextInput::make('stripe_test_public_key')
                                    ->label('Test Publishable Key')
                                    ->placeholder('pk_test_...')
                                    ->maxLength(255)
                                    ->helperText('Your Stripe test publishable key (starts with pk_test_)'),

                                Forms\Components\TextInput::make('stripe_test_secret_key')
                                    ->label('Test Secret Key')
                                    ->placeholder('sk_test_...')
                                    ->password()
                                    ->revealable()
                                    ->maxLength(255)
                                    ->helperText('Your Stripe test secret key (starts with sk_test_) - stored encrypted'),
                            ])
                            ->columns(2),

                        Forms\Components\Fieldset::make('Live Mode Keys')
                            ->schema([
                                Forms\Components\TextInput::make('stripe_live_public_key')
                                    ->label('Live Publishable Key')
                                    ->placeholder('pk_live_...')
                                    ->maxLength(255)
                                    ->helperText('Your Stripe live publishable key (starts with pk_live_)'),

                                Forms\Components\TextInput::make('stripe_live_secret_key')
                                    ->label('Live Secret Key')
                                    ->placeholder('sk_live_...')
                                    ->password()
                                    ->revealable()
                                    ->maxLength(255)
                                    ->helperText('Your Stripe live secret key (starts with sk_live_) - stored encrypted'),
                            ])
                            ->columns(2),

                        Forms\Components\Fieldset::make('Webhook Configuration')
                            ->schema([
                                Forms\Components\TextInput::make('stripe_webhook_secret')
                                    ->label('Webhook Signing Secret')
                                    ->placeholder('whsec_...')
                                    ->password()
                                    ->revealable()
                                    ->maxLength(255)
                                    ->helperText('Your Stripe webhook signing secret (starts with whsec_) - stored encrypted'),

                                Forms\Components\Placeholder::make('webhook_url')
                                    ->label('Webhook Endpoint URL')
                                    ->content(function () {
                                        return url('/webhooks/stripe');
                                    })
                                    ->helperText('Add this URL to your Stripe webhook endpoints. Listen for: checkout.session.completed, invoice.paid, customer.subscription.created'),
                            ])
                            ->columns(1),

                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('test_connection')
                                ->label('Test Stripe Connection')
                                ->icon('heroicon-o-bolt')
                                ->color('info')
                                ->action('testStripeConnection'),

                            Forms\Components\Actions\Action::make('view_stripe_docs')
                                ->label('View Stripe Documentation')
                                ->icon('heroicon-o-book-open')
                                ->url('https://stripe.com/docs/keys', shouldOpenInNewTab: true)
                                ->color('gray'),
                        ]),
                    ])
                    ->columnSpanFull(),

                Forms\Components\Section::make('VAT Configuration')
                    ->description('Configure VAT/Tax settings for invoices and payments')
                    ->schema([
                        Forms\Components\Toggle::make('vat_enabled')
                            ->label('Enable VAT')
                            ->helperText('Enable this if your company is VAT registered and needs to charge VAT on invoices')
                            ->live()
                            ->default(false),

                        Forms\Components\TextInput::make('vat_rate')
                            ->label('VAT Rate (%)')
                            ->numeric()
                            ->default(21.00)
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%')
                            ->helperText('Default VAT rate for Romania is 21%')
                            ->visible(fn (Forms\Get $get) => $get('vat_enabled')),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $settings = Setting::current();
        $settings->update($data);

        Notification::make()
            ->success()
            ->title('Connection settings saved')
            ->body('Stripe configuration has been updated successfully.')
            ->send();
    }

    public function testStripeConnection(): void
    {
        $data = $this->form->getState();

        $secretKey = $data['stripe_mode'] === 'live'
            ? $data['stripe_live_secret_key']
            : $data['stripe_test_secret_key'];

        if (empty($secretKey)) {
            Notification::make()
                ->warning()
                ->title('No API key provided')
                ->body('Please enter a secret key for the selected mode.')
                ->send();
            return;
        }

        try {
            \Stripe\Stripe::setApiKey($secretKey);
            $account = \Stripe\Account::retrieve();

            Notification::make()
                ->success()
                ->title('Connection successful!')
                ->body("Connected to Stripe account: {$account->email}")
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Connection failed')
                ->body("Error: {$e->getMessage()}")
                ->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('save')
                ->label('Save Configuration')
                ->submit('save'),
        ];
    }

    public function getTitle(): string
    {
        return 'Connections';
    }

    public static function getNavigationLabel(): string
    {
        return 'Connections';
    }
}

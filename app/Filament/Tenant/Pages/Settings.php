<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Microservice;
use BackedEnum;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Illuminate\Support\Str;
use Illuminate\Support\HtmlString;

class Settings extends Page
{
    use Forms\Concerns\InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Settings';
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.tenant.pages.settings';

    public ?array $data = [];

    public function mount(): void
    {
        $tenant = auth()->user()->tenant;

        if ($tenant) {
            $settings = $tenant->settings ?? [];

            $this->form->fill([
                // Business Details
                'company_name' => $tenant->company_name,
                'cui' => $tenant->cui,
                'reg_com' => $tenant->reg_com,
                'address' => $tenant->address,
                'city' => $tenant->city,
                'state' => $tenant->state,
                'country' => $tenant->country,
                'postal_code' => $tenant->postal_code ?? '',
                'contact_email' => $tenant->contact_email,
                'contact_phone' => $tenant->contact_phone,
                'website' => $tenant->website ?? '',
                'bank_name' => $tenant->bank_name,
                'bank_account' => $tenant->bank_account,

                // Personalization
                'site_title' => $settings['site_title'] ?? $tenant->public_name ?? $tenant->name ?? '',
                'site_language' => $settings['site_language'] ?? 'en',
                'logo' => $settings['branding']['logo'] ?? null,
                'favicon' => $settings['branding']['favicon'] ?? null,
                'site_description' => $settings['site_description'] ?? '',
                'site_tagline' => $settings['site_tagline'] ?? '',
                'ticket_terms' => $tenant->ticket_terms ?? '',
                'primary_color' => $settings['theme']['primary_color'] ?? '#3B82F6',
                'secondary_color' => $settings['theme']['secondary_color'] ?? '#1E40AF',
                'site_template' => $settings['site_template'] ?? 'default',

                // Legal Pages
                'terms_content' => $settings['legal']['terms'] ?? '',
                'privacy_content' => $settings['legal']['privacy'] ?? '',

                // Payment Credentials
                'stripe_public_key' => $tenant->payment_credentials['stripe']['public_key'] ?? '',
                'stripe_secret_key' => $tenant->payment_credentials['stripe']['secret_key'] ?? '',
                'stripe_webhook_secret' => $tenant->payment_credentials['stripe']['webhook_secret'] ?? '',
                'netopia_merchant_id' => $tenant->payment_credentials['netopia']['merchant_id'] ?? '',
                'netopia_public_key' => $tenant->payment_credentials['netopia']['public_key'] ?? '',
                'netopia_private_key' => $tenant->payment_credentials['netopia']['private_key'] ?? '',
                'payu_merchant' => $tenant->payment_credentials['payu']['merchant'] ?? '',
                'payu_secret_key' => $tenant->payment_credentials['payu']['secret_key'] ?? '',
                'euplatesc_merchant_id' => $tenant->payment_credentials['euplatesc']['merchant_id'] ?? '',
                'euplatesc_key' => $tenant->payment_credentials['euplatesc']['key'] ?? '',
            ]);
        }
    }

    protected function getActivePaymentProcessor(): ?string
    {
        $tenant = auth()->user()->tenant;
        if (!$tenant) return null;

        $activeProcessor = $tenant->microservices()
            ->whereIn('slug', ['payment-stripe', 'payment-netopia', 'payment-payu', 'payment-euplatesc'])
            ->wherePivot('is_active', true)
            ->first();

        return $activeProcessor?->slug;
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                SC\Tabs::make('Settings')
                    ->tabs([
                        SC\Tabs\Tab::make('Business Details')
                            ->icon('heroicon-o-building-office')
                            ->schema([
                                SC\Section::make('Company Information')
                                    ->schema([
                                        Forms\Components\TextInput::make('company_name')
                                            ->label('Legal Company Name')
                                            ->disabled()
                                            ->dehydrated(true)
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('cui')
                                            ->label('CUI / VAT Number')
                                            ->disabled()
                                            ->dehydrated(true)
                                            ->maxLength(50),

                                        Forms\Components\TextInput::make('reg_com')
                                            ->label('Trade Register')
                                            ->disabled()
                                            ->dehydrated(true)
                                            ->maxLength(50),

                                        Forms\Components\TextInput::make('bank_name')
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('bank_account')
                                            ->label('IBAN')
                                            ->maxLength(50),
                                    ])->columns(3),

                                SC\Section::make('Address')
                                    ->schema([
                                        Forms\Components\TextInput::make('address')
                                            ->label('Street Address')
                                            ->maxLength(255)
                                            ->columnSpanFull(),

                                        Forms\Components\TextInput::make('city')
                                            ->maxLength(100),

                                        Forms\Components\TextInput::make('state')
                                            ->label('State / County')
                                            ->maxLength(100),

                                        Forms\Components\TextInput::make('country')
                                            ->maxLength(100)
                                            ->helperText('Full country name (e.g., Romania)'),

                                        Forms\Components\TextInput::make('postal_code')
                                            ->maxLength(20),
                                    ])->columns(2),

                                SC\Section::make('Contact')
                                    ->schema([
                                        Forms\Components\TextInput::make('contact_email')
                                            ->email()
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('contact_phone')
                                            ->tel()
                                            ->maxLength(50),

                                        Forms\Components\TextInput::make('website')
                                            ->url()
                                            ->maxLength(255),
                                    ])->columns(3),
                            ]),

                        SC\Tabs\Tab::make('Personalization')
                            ->icon('heroicon-o-paint-brush')
                            ->schema([
                                SC\Section::make('Branding')
                                    ->schema([
                                        Forms\Components\FileUpload::make('logo')
                                            ->label('Logo')
                                            ->image()
                                            ->directory('tenant-branding')
                                            ->maxSize(2048)
                                            ->helperText('Recommended: 200x60px, PNG or SVG'),

                                        Forms\Components\FileUpload::make('favicon')
                                            ->label('Favicon')
                                            ->image()
                                            ->directory('tenant-branding')
                                            ->maxSize(512)
                                            ->helperText('Recommended: 32x32px or 64x64px, ICO or PNG'),
                                    ])->columns(2),

                                SC\Section::make('Site Information')
                                    ->schema([
                                        Forms\Components\TextInput::make('site_title')
                                            ->label('Site Title')
                                            ->required()
                                            ->maxLength(255)
                                            ->helperText('The name of your site displayed in browser tab and header'),

                                        Forms\Components\Select::make('site_language')
                                            ->label('Site Language')
                                            ->options([
                                                'en' => 'English',
                                                'ro' => 'Romanian (Română)',
                                            ])
                                            ->default('en')
                                            ->required()
                                            ->helperText('Primary language for your public site'),

                                        Forms\Components\Textarea::make('site_description')
                                            ->label('Site Description')
                                            ->rows(3)
                                            ->helperText('Brief description for SEO and social sharing')
                                            ->maxLength(500),

                                        Forms\Components\TextInput::make('site_tagline')
                                            ->label('Site Tagline')
                                            ->maxLength(255)
                                            ->helperText('Short tagline displayed on the site'),

                                        Forms\Components\RichEditor::make('ticket_terms')
                                            ->label('Ticket Terms')
                                            ->toolbarButtons([
                                                'bold',
                                                'italic',
                                                'underline',
                                                'bulletList',
                                                'orderedList',
                                                'link',
                                            ])
                                            ->helperText('Terms displayed on tickets'),
                                    ]),

                                SC\Section::make('Theme & Colors')
                                    ->schema([
                                        Forms\Components\ColorPicker::make('primary_color')
                                            ->label('Primary Color'),

                                        Forms\Components\ColorPicker::make('secondary_color')
                                            ->label('Secondary Color'),

                                        Forms\Components\Select::make('site_template')
                                            ->label('Site Template')
                                            ->options([
                                                'default' => 'Default',
                                                'modern' => 'Modern',
                                                'classic' => 'Classic',
                                                'minimal' => 'Minimal',
                                            ])
                                            ->default('default'),
                                    ])->columns(3),
                            ]),

                        SC\Tabs\Tab::make('Legal Pages')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                SC\Section::make('Terms & Conditions')
                                    ->description('Content displayed on your Terms & Conditions page')
                                    ->schema([
                                        Forms\Components\RichEditor::make('terms_content')
                                            ->label('')
                                            ->toolbarButtons([
                                                'bold',
                                                'italic',
                                                'underline',
                                                'strike',
                                                'link',
                                                'orderedList',
                                                'bulletList',
                                                'h2',
                                                'h3',
                                                'blockquote',
                                                'redo',
                                                'undo',
                                            ])
                                            ->columnSpanFull(),
                                    ]),

                                SC\Section::make('Privacy Policy')
                                    ->description('Content displayed on your Privacy Policy page')
                                    ->schema([
                                        Forms\Components\RichEditor::make('privacy_content')
                                            ->label('')
                                            ->toolbarButtons([
                                                'bold',
                                                'italic',
                                                'underline',
                                                'strike',
                                                'link',
                                                'orderedList',
                                                'bulletList',
                                                'h2',
                                                'h3',
                                                'blockquote',
                                                'redo',
                                                'undo',
                                            ])
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        SC\Tabs\Tab::make('Payment Processor')
                            ->icon('heroicon-o-credit-card')
                            ->schema($this->getPaymentProcessorSchema()),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected function getPaymentProcessorSchema(): array
    {
        $activeProcessor = $this->getActivePaymentProcessor();

        if (!$activeProcessor) {
            return [
                SC\Section::make('No Payment Processor Active')
                    ->description('You need to activate a payment processor microservice first.')
                    ->schema([
                        Forms\Components\Placeholder::make('no_processor')
                            ->content(new HtmlString(
                                '<div class="text-amber-600">' .
                                '<p>Go to <strong>Microservices</strong> to activate one of the following:</p>' .
                                '<ul class="list-disc ml-5 mt-2">' .
                                '<li>Stripe Integration</li>' .
                                '<li>Netopia Integration</li>' .
                                '<li>PayU Integration</li>' .
                                '<li>Euplatesc Integration</li>' .
                                '</ul>' .
                                '<p class="mt-2 text-sm">Note: Only one payment processor can be active at a time.</p>' .
                                '</div>'
                            )),
                    ]),
            ];
        }

        return match ($activeProcessor) {
            'payment-stripe' => $this->getStripeFields(),
            'payment-netopia' => $this->getNetopiaFields(),
            'payment-payu' => $this->getPayuFields(),
            'payment-euplatesc' => $this->getEuplatescFields(),
            default => [],
        };
    }

    protected function getStripeFields(): array
    {
        return [
            SC\Section::make('Stripe Configuration')
                ->description('Enter your Stripe API keys. Find them at dashboard.stripe.com/apikeys')
                ->schema([
                    Forms\Components\TextInput::make('stripe_public_key')
                        ->label('Publishable Key')
                        ->placeholder('pk_live_...')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('stripe_secret_key')
                        ->label('Secret Key')
                        ->password()
                        ->placeholder('sk_live_...')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('stripe_webhook_secret')
                        ->label('Webhook Secret')
                        ->password()
                        ->placeholder('whsec_...')
                        ->maxLength(255)
                        ->helperText('Required for receiving payment confirmations'),
                ])->columns(1),
        ];
    }

    protected function getNetopiaFields(): array
    {
        return [
            SC\Section::make('Netopia Configuration')
                ->description('Enter your Netopia merchant credentials')
                ->schema([
                    Forms\Components\TextInput::make('netopia_merchant_id')
                        ->label('Merchant ID')
                        ->maxLength(255),

                    Forms\Components\Textarea::make('netopia_public_key')
                        ->label('Public Key (Certificate)')
                        ->rows(4)
                        ->helperText('Paste the contents of your public.cer file'),

                    Forms\Components\Textarea::make('netopia_private_key')
                        ->label('Private Key')
                        ->rows(4)
                        ->helperText('Paste the contents of your private.key file'),
                ])->columns(1),
        ];
    }

    protected function getPayuFields(): array
    {
        return [
            SC\Section::make('PayU Configuration')
                ->description('Enter your PayU merchant credentials')
                ->schema([
                    Forms\Components\TextInput::make('payu_merchant')
                        ->label('Merchant Code')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('payu_secret_key')
                        ->label('Secret Key')
                        ->password()
                        ->maxLength(255),
                ])->columns(1),
        ];
    }

    protected function getEuplatescFields(): array
    {
        return [
            SC\Section::make('Euplatesc Configuration')
                ->description('Enter your Euplatesc merchant credentials')
                ->schema([
                    Forms\Components\TextInput::make('euplatesc_merchant_id')
                        ->label('Merchant ID')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('euplatesc_key')
                        ->label('Key')
                        ->password()
                        ->maxLength(255),
                ])->columns(1),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $tenant = auth()->user()->tenant;

        if (!$tenant) {
            return;
        }

        // Update tenant fields
        $tenant->update([
            'company_name' => $data['company_name'],
            'cui' => $data['cui'],
            'reg_com' => $data['reg_com'],
            'address' => $data['address'],
            'city' => $data['city'],
            'state' => $data['state'],
            'country' => $data['country'],
            'postal_code' => $data['postal_code'],
            'contact_email' => $data['contact_email'],
            'contact_phone' => $data['contact_phone'],
            'website' => $data['website'],
            'bank_name' => $data['bank_name'],
            'bank_account' => $data['bank_account'],
            'ticket_terms' => $data['ticket_terms'],
        ]);

        // Update settings JSON
        $settings = $tenant->settings ?? [];
        $settings['site_title'] = $data['site_title'];
        $settings['site_language'] = $data['site_language'];
        $settings['branding'] = [
            'logo' => $data['logo'],
            'favicon' => $data['favicon'],
        ];
        $settings['site_description'] = $data['site_description'];
        $settings['site_tagline'] = $data['site_tagline'];
        $settings['theme'] = [
            'primary_color' => $data['primary_color'],
            'secondary_color' => $data['secondary_color'],
        ];
        $settings['site_template'] = $data['site_template'];
        $settings['legal'] = [
            'terms' => $data['terms_content'],
            'privacy' => $data['privacy_content'],
        ];

        // Update payment credentials
        $paymentCredentials = $tenant->payment_credentials ?? [];
        $paymentCredentials['stripe'] = [
            'public_key' => $data['stripe_public_key'] ?? '',
            'secret_key' => $data['stripe_secret_key'] ?? '',
            'webhook_secret' => $data['stripe_webhook_secret'] ?? '',
        ];
        $paymentCredentials['netopia'] = [
            'merchant_id' => $data['netopia_merchant_id'] ?? '',
            'public_key' => $data['netopia_public_key'] ?? '',
            'private_key' => $data['netopia_private_key'] ?? '',
        ];
        $paymentCredentials['payu'] = [
            'merchant' => $data['payu_merchant'] ?? '',
            'secret_key' => $data['payu_secret_key'] ?? '',
        ];
        $paymentCredentials['euplatesc'] = [
            'merchant_id' => $data['euplatesc_merchant_id'] ?? '',
            'key' => $data['euplatesc_key'] ?? '',
        ];

        $tenant->update([
            'settings' => $settings,
            'payment_credentials' => $paymentCredentials,
        ]);

        Notification::make()
            ->success()
            ->title('Settings saved')
            ->body('Your settings have been updated successfully.')
            ->send();
    }

    public function getTitle(): string
    {
        return 'Settings';
    }
}

<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Illuminate\Support\HtmlString;

class Settings extends Page
{
    use Forms\Concerns\InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Settings';
    protected static ?string $navigationGroup = 'System';
    protected static ?int $navigationSort = 100;
    protected string $view = 'filament.pages.settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = Setting::current();
        $meta = $settings->meta ?? [];

        $this->form->fill([
            // Company Info
            'company_name' => $settings->company_name,
            'cui' => $settings->cui,
            'reg_com' => $settings->reg_com,
            'address' => $settings->address,
            'city' => $settings->city,
            'state' => $settings->state,
            'country' => $settings->country,
            'postal_code' => $settings->postal_code,
            'phone' => $settings->phone,
            'email' => $settings->email,
            'website' => $settings->website,

            // Branding / Logos
            'logo_admin_light' => $meta['logo_admin_light'] ?? null,
            'logo_admin_dark' => $meta['logo_admin_dark'] ?? null,
            'logo_tenant_light' => $meta['logo_tenant_light'] ?? null,
            'logo_tenant_dark' => $meta['logo_tenant_dark'] ?? null,
            'logo_public_light' => $meta['logo_public_light'] ?? null,
            'logo_public_dark' => $meta['logo_public_dark'] ?? null,

            // Invoice
            'invoice_prefix' => $settings->invoice_prefix,
            'invoice_series' => $settings->invoice_series,
            'invoice_next_number' => $settings->invoice_next_number,
            'default_payment_terms_days' => $settings->default_payment_terms_days,
            'invoice_footer' => $settings->invoice_footer,

            // Bank
            'bank_name' => $settings->bank_name,
            'bank_account' => $settings->bank_account,
            'bank_swift' => $settings->bank_swift,
            'default_currency' => $settings->default_currency ?? 'EUR',

            // VAT
            'vat_enabled' => $settings->vat_enabled ?? false,
            'vat_rate' => $settings->vat_rate ?? 19.00,
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                SC\Tabs::make('Settings')
                    ->tabs([
                        SC\Tabs\Tab::make('Branding')
                            ->icon('heroicon-o-paint-brush')
                            ->schema([
                                SC\Section::make('Admin Panel Logos')
                                    ->description('SVG or PNG logos for the admin panel sidebar. Recommended size: 200x50px')
                                    ->schema([
                                        Forms\Components\FileUpload::make('logo_admin_light')
                                            ->label('Logo (Light Mode)')
                                            ->image()
                                            ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/jpeg'])
                                            ->directory('platform-branding')
                                            ->disk('public')
                                            ->visibility('public')
                                            ->maxSize(1024)
                                            ->helperText('Logo shown on white/light backgrounds'),

                                        Forms\Components\FileUpload::make('logo_admin_dark')
                                            ->label('Logo (Dark Mode)')
                                            ->image()
                                            ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/jpeg'])
                                            ->directory('platform-branding')
                                            ->disk('public')
                                            ->visibility('public')
                                            ->maxSize(1024)
                                            ->helperText('Logo shown on dark backgrounds'),
                                    ])->columns(2),

                                SC\Section::make('Tenant Panel Logos')
                                    ->description('SVG or PNG logos for tenant panel sidebar. Recommended size: 200x50px')
                                    ->schema([
                                        Forms\Components\FileUpload::make('logo_tenant_light')
                                            ->label('Logo (Light Mode)')
                                            ->image()
                                            ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/jpeg'])
                                            ->directory('platform-branding')
                                            ->disk('public')
                                            ->visibility('public')
                                            ->maxSize(1024)
                                            ->helperText('Logo shown on white/light backgrounds'),

                                        Forms\Components\FileUpload::make('logo_tenant_dark')
                                            ->label('Logo (Dark Mode)')
                                            ->image()
                                            ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/jpeg'])
                                            ->directory('platform-branding')
                                            ->disk('public')
                                            ->visibility('public')
                                            ->maxSize(1024)
                                            ->helperText('Logo shown on dark backgrounds'),
                                    ])->columns(2),

                                SC\Section::make('Public Website Logos')
                                    ->description('SVG or PNG logos for the public frontend. Recommended size: 300x80px')
                                    ->schema([
                                        Forms\Components\FileUpload::make('logo_public_light')
                                            ->label('Logo (Light Mode)')
                                            ->image()
                                            ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/jpeg'])
                                            ->directory('platform-branding')
                                            ->disk('public')
                                            ->visibility('public')
                                            ->maxSize(1024)
                                            ->helperText('Logo shown on white/light backgrounds'),

                                        Forms\Components\FileUpload::make('logo_public_dark')
                                            ->label('Logo (Dark Mode)')
                                            ->image()
                                            ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/jpeg'])
                                            ->directory('platform-branding')
                                            ->disk('public')
                                            ->visibility('public')
                                            ->maxSize(1024)
                                            ->helperText('Logo shown on dark backgrounds'),
                                    ])->columns(2),
                            ]),

                        SC\Tabs\Tab::make('Company')
                            ->icon('heroicon-o-building-office')
                            ->schema([
                                SC\Section::make('Company Information')
                                    ->schema([
                                        Forms\Components\TextInput::make('company_name')
                                            ->label('Company Name')
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('cui')
                                            ->label('CUI / Tax ID')
                                            ->maxLength(50),

                                        Forms\Components\TextInput::make('reg_com')
                                            ->label('Trade Register')
                                            ->maxLength(50),
                                    ])->columns(3),

                                SC\Section::make('Address')
                                    ->schema([
                                        Forms\Components\TextInput::make('address')
                                            ->maxLength(255)
                                            ->columnSpanFull(),

                                        Forms\Components\TextInput::make('city')
                                            ->maxLength(100),

                                        Forms\Components\TextInput::make('state')
                                            ->label('State / County')
                                            ->maxLength(100),

                                        Forms\Components\TextInput::make('country')
                                            ->maxLength(100),

                                        Forms\Components\TextInput::make('postal_code')
                                            ->maxLength(20),
                                    ])->columns(2),

                                SC\Section::make('Contact')
                                    ->schema([
                                        Forms\Components\TextInput::make('email')
                                            ->email()
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('phone')
                                            ->tel()
                                            ->maxLength(50),

                                        Forms\Components\TextInput::make('website')
                                            ->url()
                                            ->maxLength(255),
                                    ])->columns(3),
                            ]),

                        SC\Tabs\Tab::make('Invoicing')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                SC\Section::make('Invoice Numbering')
                                    ->schema([
                                        Forms\Components\TextInput::make('invoice_prefix')
                                            ->label('Prefix')
                                            ->placeholder('INV')
                                            ->maxLength(10),

                                        Forms\Components\TextInput::make('invoice_series')
                                            ->label('Series')
                                            ->placeholder('2024')
                                            ->maxLength(20),

                                        Forms\Components\TextInput::make('invoice_next_number')
                                            ->label('Next Number')
                                            ->numeric()
                                            ->default(1),

                                        Forms\Components\TextInput::make('default_payment_terms_days')
                                            ->label('Payment Terms (Days)')
                                            ->numeric()
                                            ->default(30),
                                    ])->columns(4),

                                SC\Section::make('Bank Details')
                                    ->schema([
                                        Forms\Components\TextInput::make('bank_name')
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('bank_account')
                                            ->label('IBAN')
                                            ->maxLength(50),

                                        Forms\Components\TextInput::make('bank_swift')
                                            ->label('SWIFT/BIC')
                                            ->maxLength(20),

                                        Forms\Components\Select::make('default_currency')
                                            ->label('Default Currency')
                                            ->options([
                                                'EUR' => 'EUR - Euro',
                                                'USD' => 'USD - US Dollar',
                                                'RON' => 'RON - Romanian Leu',
                                                'GBP' => 'GBP - British Pound',
                                            ])
                                            ->default('EUR'),
                                    ])->columns(2),

                                SC\Section::make('VAT')
                                    ->schema([
                                        Forms\Components\Toggle::make('vat_enabled')
                                            ->label('VAT Enabled')
                                            ->default(false),

                                        Forms\Components\TextInput::make('vat_rate')
                                            ->label('VAT Rate (%)')
                                            ->numeric()
                                            ->step(0.01)
                                            ->default(19.00)
                                            ->suffix('%'),
                                    ])->columns(2),

                                SC\Section::make('Footer')
                                    ->schema([
                                        Forms\Components\Textarea::make('invoice_footer')
                                            ->label('Invoice Footer Text')
                                            ->rows(3)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = Setting::current();

        // Update main fields
        $settings->update([
            'company_name' => $data['company_name'],
            'cui' => $data['cui'],
            'reg_com' => $data['reg_com'],
            'address' => $data['address'],
            'city' => $data['city'],
            'state' => $data['state'],
            'country' => $data['country'],
            'postal_code' => $data['postal_code'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'website' => $data['website'],
            'invoice_prefix' => $data['invoice_prefix'],
            'invoice_series' => $data['invoice_series'],
            'invoice_next_number' => $data['invoice_next_number'],
            'default_payment_terms_days' => $data['default_payment_terms_days'],
            'bank_name' => $data['bank_name'],
            'bank_account' => $data['bank_account'],
            'bank_swift' => $data['bank_swift'],
            'default_currency' => $data['default_currency'],
            'vat_enabled' => $data['vat_enabled'],
            'vat_rate' => $data['vat_rate'],
            'invoice_footer' => $data['invoice_footer'],
        ]);

        // Update meta for logos
        $meta = $settings->meta ?? [];
        $meta['logo_admin_light'] = $data['logo_admin_light'];
        $meta['logo_admin_dark'] = $data['logo_admin_dark'];
        $meta['logo_tenant_light'] = $data['logo_tenant_light'];
        $meta['logo_tenant_dark'] = $data['logo_tenant_dark'];
        $meta['logo_public_light'] = $data['logo_public_light'];
        $meta['logo_public_dark'] = $data['logo_public_dark'];

        $settings->update(['meta' => $meta]);

        // Clear any cached settings
        cache()->forget('platform_settings');

        Notification::make()
            ->success()
            ->title('Settings saved')
            ->body('Platform settings have been updated.')
            ->send();
    }

    public function getTitle(): string
    {
        return 'Platform Settings';
    }
}

<?php

namespace App\Filament\Resources\Settings;

use App\Filament\Resources\Settings\Pages;
use App\Models\Setting;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Forms;

class SettingsResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static \UnitEnum|string|null $navigationGroup = 'System';
    protected static ?int $navigationSort = 100;
    protected static ?string $navigationLabel = 'Settings';
    protected static ?string $modelLabel = 'Settings';
    protected static ?string $pluralModelLabel = 'Settings';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Section::make('Company Information')->schema([
                Forms\Components\TextInput::make('company_name')
                    ->label('Company Name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                SC\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('cui')
                        ->label('CUI/CIF')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('reg_com')
                        ->label('Reg. Com.')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('vat_number')
                        ->label('VAT Number')
                        ->maxLength(255),
                ]),
            ]),

            SC\Section::make('Address')->schema([
                Forms\Components\Textarea::make('address')
                    ->label('Street Address')
                    ->rows(2)
                    ->columnSpanFull(),

                SC\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('city')
                        ->label('City')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('state')
                        ->label('State/County')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('postal_code')
                        ->label('Postal Code')
                        ->maxLength(20),
                ]),

                Forms\Components\TextInput::make('country')
                    ->label('Country Code')
                    ->default('RO')
                    ->maxLength(2),
            ])->columns(1),

            SC\Section::make('Contact Information')->schema([
                Forms\Components\TextInput::make('phone')
                    ->label('Phone')
                    ->tel()
                    ->maxLength(64),

                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(255),

                Forms\Components\TextInput::make('website')
                    ->label('Website')
                    ->url()
                    ->maxLength(255),

                Forms\Components\Select::make('default_currency')
                    ->label('Default Currency')
                    ->options([
                        'RON' => 'RON - Romanian Leu',
                        'EUR' => 'EUR - Euro',
                        'USD' => 'USD - US Dollar',
                        'GBP' => 'GBP - British Pound',
                        'CHF' => 'CHF - Swiss Franc',
                        'HUF' => 'HUF - Hungarian Forint',
                        'BGN' => 'BGN - Bulgarian Lev',
                        'CZK' => 'CZK - Czech Koruna',
                        'PLN' => 'PLN - Polish Zloty',
                    ])
                    ->default('RON')
                    ->required()
                    ->searchable(),
            ])->columns(2),

            SC\Section::make('Banking Details')->schema([
                Forms\Components\TextInput::make('bank_name')
                    ->label('Bank Name')
                    ->maxLength(255),

                Forms\Components\TextInput::make('bank_account')
                    ->label('IBAN')
                    ->maxLength(255),

                Forms\Components\TextInput::make('bank_swift')
                    ->label('SWIFT/BIC')
                    ->maxLength(255),
            ])->columns(3),

            SC\Section::make('Invoice Settings')->schema([
                SC\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('invoice_prefix')
                        ->label('Invoice Prefix')
                        ->default('INV')
                        ->required()
                        ->maxLength(10),

                    Forms\Components\TextInput::make('invoice_series')
                        ->label('Invoice Series')
                        ->maxLength(10),

                    Forms\Components\TextInput::make('invoice_next_number')
                        ->label('Next Invoice Number')
                        ->numeric()
                        ->default(1)
                        ->required()
                        ->minValue(1),
                ]),

                Forms\Components\TextInput::make('default_payment_terms_days')
                    ->label('Default Payment Terms (Days)')
                    ->numeric()
                    ->default(5)
                    ->required()
                    ->minValue(0)
                    ->hint('Number of days after issue date for payment'),
            ])->columns(1),

            SC\Section::make('Branding')->schema([
                Forms\Components\FileUpload::make('logo_path')
                    ->label('Company Logo (Invoices)')
                    ->image()
                    ->directory('settings')
                    ->visibility('public')
                    ->hint('Logo displayed on invoices'),

                Forms\Components\Textarea::make('invoice_footer')
                    ->label('Invoice Footer Text')
                    ->rows(3)
                    ->columnSpanFull()
                    ->hint('Text displayed at the bottom of invoices'),

                SC\Group::make()
                    ->statePath('meta')
                    ->schema([
                        SC\Grid::make(2)->schema([
                            Forms\Components\FileUpload::make('logo_admin_light')
                                ->label('Admin Panel Logo (Light Mode)')
                                ->disk('public')
                                ->directory('logos')
                                ->preserveFilenames()
                                ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/jpeg', 'image/webp'])
                                ->hint('Logo for admin panel on light backgrounds'),

                            Forms\Components\FileUpload::make('logo_admin_dark')
                                ->label('Admin Panel Logo (Dark Mode)')
                                ->disk('public')
                                ->directory('logos')
                                ->preserveFilenames()
                                ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/jpeg', 'image/webp'])
                                ->hint('Logo for admin panel on dark backgrounds'),

                            Forms\Components\FileUpload::make('logo_tenant_light')
                                ->label('Tenant Panel Logo (Light Mode)')
                                ->disk('public')
                                ->directory('logos')
                                ->preserveFilenames()
                                ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/jpeg', 'image/webp'])
                                ->hint('Logo for tenant panel on light backgrounds'),

                            Forms\Components\FileUpload::make('logo_tenant_dark')
                                ->label('Tenant Panel Logo (Dark Mode)')
                                ->disk('public')
                                ->directory('logos')
                                ->preserveFilenames()
                                ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/jpeg', 'image/webp'])
                                ->hint('Logo for tenant panel on dark backgrounds'),

                            Forms\Components\FileUpload::make('logo_public_light')
                                ->label('Public Site Logo (Light Mode)')
                                ->disk('public')
                                ->directory('logos')
                                ->preserveFilenames()
                                ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/jpeg', 'image/webp'])
                                ->hint('Logo for public website on light backgrounds'),

                            Forms\Components\FileUpload::make('logo_public_dark')
                                ->label('Public Site Logo (Dark Mode)')
                                ->disk('public')
                                ->directory('logos')
                                ->preserveFilenames()
                                ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/jpeg', 'image/webp'])
                                ->hint('Logo for public website on dark backgrounds'),
                        ]),
                    ]),
            ])->columns(1),

            SC\Section::make('Email Settings')->schema([
                Forms\Components\RichEditor::make('email_footer')
                    ->label('Email Footer (HTML)')
                    ->toolbarButtons([
                        'bold',
                        'italic',
                        'underline',
                        'link',
                        'bulletList',
                        'orderedList',
                    ])
                    ->columnSpanFull()
                    ->hint('HTML footer added to all email templates. Use for company info, social links, unsubscribe, etc.'),
            ])->columns(1),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSettings::route('/'),
            'connections' => Pages\ManageConnections::route('/connections'),
        ];
    }

    public static function getNavigationItems(): array
    {
        return [
            ...parent::getNavigationItems(),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}

<?php

namespace App\Filament\Marketplace\Pages;

use BackedEnum;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Illuminate\Support\Str;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Collection;

class Settings extends Page
{
    use HasMarketplaceContext;

    use Forms\Concerns\InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Setări';
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.marketplace.pages.settings';

    public ?array $data = [];
    public Collection $domains;

    public function mount(): void
    {
        $marketplace = static::getMarketplaceClient();

        // Domains - MarketplaceClient uses single domain field, not a domains relation
        $this->domains = collect();

        if ($marketplace) {
            $settings = $marketplace->settings ?? [];
            $smtp = $marketplace->smtp_settings ?? [];
            $txSmtp = $marketplace->transactional_smtp_settings ?? [];

            $this->form->fill([
                // Business Details
                'company_name' => $marketplace->company_name,
                'cui' => $marketplace->cui,
                'reg_com' => $marketplace->reg_com,
                'vat_payer' => (bool) $marketplace->vat_payer,
                'tax_display_mode' => $marketplace->tax_display_mode ?? 'included',
                'fixed_commission' => $marketplace->fixed_commission,
                'address' => $marketplace->address,
                'city' => $marketplace->city,
                'state' => $marketplace->state,
                'country' => $marketplace->country,
                'postal_code' => $marketplace->postal_code ?? '',
                'contact_email' => $marketplace->contact_email,
                'contact_phone' => $marketplace->contact_phone,
                'operating_hours' => $marketplace->operating_hours ?? '',
                'website' => $marketplace->website ?? '',
                'bank_name' => $marketplace->bank_name,
                'bank_account' => $marketplace->bank_account,
                'currency' => $marketplace->currency ?? 'EUR',
                'invoice_preparer' => $settings['invoice_preparer'] ?? '',
                'general_invoice_client_name' => $settings['general_invoice_client_name'] ?? 'Client general',
                'general_invoice_client_cui' => $settings['general_invoice_client_cui'] ?? '',
                'general_invoice_client_address' => $settings['general_invoice_client_address'] ?? '',
                'admin_notification_orders_email' => $settings['admin_notifications']['orders_email'] ?? '',
                'admin_notification_service_orders_email' => $settings['admin_notifications']['service_orders_email'] ?? '',

                // Personalization
                'site_title' => $settings['site_title'] ?? $marketplace->name ?? $marketplace->name ?? '',
                // Language is set in Core Admin (Tenant Edit page, not here)
                // 'site_language' => $settings['site_language'] ?? 'en',
                'logo' => $settings['branding']['logo'] ?? null,
                'favicon' => $settings['branding']['favicon'] ?? null,
                'site_description' => $settings['site_description'] ?? '',
                'site_tagline' => $settings['site_tagline'] ?? '',
                'ticket_terms' => $marketplace->ticket_terms ?? '',
                'primary_color' => $settings['theme']['primary_color'] ?? '#3B82F6',
                'secondary_color' => $settings['theme']['secondary_color'] ?? '#1E40AF',
                'site_template' => $settings['site_template'] ?? 'default',

                // Document Series
                'order_prefix' => $settings['order_prefix'] ?? 'CMD',
                'order_next_number' => $settings['order_next_number'] ?? 1,
                'invoice_prefix' => $settings['invoice_prefix'] ?? 'FACT',
                'invoice_next_number' => $settings['invoice_next_number'] ?? 1,
                'invoice_due_days' => $settings['invoice_due_days'] ?? 30,

                // Legal Pages
                'terms_title' => $settings['legal']['terms_title'] ?? 'Terms & Conditions',
                'terms_content' => $settings['legal']['terms'] ?? '',
                'privacy_title' => $settings['legal']['privacy_title'] ?? 'Privacy Policy',
                'privacy_content' => $settings['legal']['privacy'] ?? '',

                // Social Links
                'social_facebook' => $settings['social']['facebook'] ?? '',
                'social_instagram' => $settings['social']['instagram'] ?? '',
                'social_twitter' => $settings['social']['twitter'] ?? '',
                'social_youtube' => $settings['social']['youtube'] ?? '',
                'social_tiktok' => $settings['social']['tiktok'] ?? '',
                'social_linkedin' => $settings['social']['linkedin'] ?? '',

                // Mail Settings — read from smtp_settings column (primary) with settings.mail fallback
                'mail_driver' => $smtp['driver'] ?? $settings['mail']['driver'] ?? '',
                'mail_host' => $smtp['host'] ?? $settings['mail']['host'] ?? '',
                'mail_port' => $smtp['port'] ?? $settings['mail']['port'] ?? '',
                'mail_username' => $smtp['username'] ?? $settings['mail']['username'] ?? '',
                'mail_password' => $this->decryptSetting($smtp['password'] ?? $settings['mail']['password'] ?? ''),
                'mail_api_key' => $this->decryptSetting($smtp['api_key'] ?? $settings['mail']['api_key'] ?? ''),
                'mail_api_secret' => $this->decryptSetting($smtp['api_secret'] ?? $settings['mail']['api_secret'] ?? ''),
                'mail_encryption' => $smtp['encryption'] ?? $settings['mail']['encryption'] ?? '',
                'mail_from_address' => $smtp['from_address'] ?? $settings['mail']['from_address'] ?? '',
                'mail_from_name' => $smtp['from_name'] ?? $settings['mail']['from_name'] ?? '',
                'mail_domain' => $smtp['domain'] ?? $settings['mail']['domain'] ?? '',
                'mail_region' => $smtp['region'] ?? $settings['mail']['region'] ?? '',

                // Transactional Mail Settings — second provider for the 14 transactional templates
                'transactional_mail_driver' => $txSmtp['driver'] ?? '',
                'transactional_mail_host' => $txSmtp['host'] ?? '',
                'transactional_mail_port' => $txSmtp['port'] ?? '',
                'transactional_mail_username' => $txSmtp['username'] ?? '',
                'transactional_mail_password' => $this->decryptSetting($txSmtp['password'] ?? ''),
                'transactional_mail_api_key' => $this->decryptSetting($txSmtp['api_key'] ?? ''),
                'transactional_mail_api_secret' => $this->decryptSetting($txSmtp['api_secret'] ?? ''),
                'transactional_mail_encryption' => $txSmtp['encryption'] ?? '',
                'transactional_mail_from_address' => $txSmtp['from_address'] ?? '',
                'transactional_mail_from_name' => $txSmtp['from_name'] ?? '',
                'transactional_mail_domain' => $txSmtp['domain'] ?? '',
                'transactional_mail_region' => $txSmtp['region'] ?? '',

                // Stock alert settings
                'stock_alert_threshold' => $settings['stock_alert_threshold'] ?? null,
                'stock_alert_email' => $settings['stock_alert_email'] ?? null,
            ]);
        }
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
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('cui')
                                            ->label('CUI / VAT Number')
                                            ->maxLength(50),

                                        Forms\Components\TextInput::make('reg_com')
                                            ->label('Trade Register')
                                            ->maxLength(50),

                                        Forms\Components\Toggle::make('vat_payer')
                                            ->label('Platitor TVA')
                                            ->helperText('Bifati daca sunteti inregistrat ca platitor de TVA. Aceasta afecteaza calculul taxelor si afisarea TVA-ului in checkout.')
                                            ->onColor('success')
                                            ->offColor('gray'),

                                        Forms\Components\Select::make('tax_display_mode')
                                            ->label('Modul de afișare taxe')
                                            ->options([
                                                'included' => 'Incluse în preț (prețul afișat include taxele)',
                                                'added' => 'Adăugate la preț (taxele se adaugă la checkout)',
                                            ])
                                            ->default('included')
                                            ->helperText('Alegeți cum vor fi afișate taxele pe website: incluse în prețul biletului sau adăugate separat la checkout.')
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Aceasta setare afecteaza modul in care clientii vad preturile pe website.'),

                                        Forms\Components\TextInput::make('fixed_commission')
                                            ->label('Comision Fix')
                                            ->numeric()
                                            ->step(0.01)
                                            ->minValue(0)
                                            ->suffix('LEI')
                                            ->helperText('Comision fix per bilet (în LEI). Dacă este setat, poate fi folosit în locul comisionului procentual.')
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Această sumă fixă poate fi aplicată per bilet în loc de comisionul procentual.'),

                                        Forms\Components\TextInput::make('bank_name')
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('bank_account')
                                            ->label('IBAN')
                                            ->maxLength(50),

                                        Forms\Components\Select::make('currency')
                                            ->label('Currency')
                                            ->options([
                                                'RON' => 'RON - Romanian Leu',
                                                'EUR' => 'EUR - Euro',
                                                'USD' => 'USD - US Dollar',
                                                'GBP' => 'GBP - British Pound',
                                            ])
                                            ->default('EUR')
                                            ->required()
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Default currency for sales and invoices'),

                                        Forms\Components\TextInput::make('invoice_preparer')
                                            ->label('Persoana care completează documentele')
                                            ->maxLength(255)
                                            ->helperText('Numele persoanei care semnează/completează facturile și documentele fiscale.'),
                                    ])->columns(3),

                                SC\Section::make('Client general (facturi comision peste preț)')
                                    ->description('Folosit ca destinatar pe facturile de comision când evenimentul are modul "comision peste preț" (comisionul a fost achitat de clienții finali, nu de organizator).')
                                    ->schema([
                                        Forms\Components\TextInput::make('general_invoice_client_name')
                                            ->label('Denumire')
                                            ->maxLength(255)
                                            ->default('Client general'),
                                        Forms\Components\TextInput::make('general_invoice_client_cui')
                                            ->label('CUI / CIF')
                                            ->maxLength(50),
                                        Forms\Components\TextInput::make('general_invoice_client_address')
                                            ->label('Adresă')
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                    ])->columns(2),

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
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Full country name (e.g., Romania)'),

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

                                        Forms\Components\TextInput::make('operating_hours')
                                            ->label('Program de funcționare')
                                            ->placeholder('Luni - Vineri: 09:00 - 18:00')
                                            ->maxLength(255)
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Programul de lucru afișat pe site și în materialele de contact'),

                                        Forms\Components\TextInput::make('website')
                                            ->url()
                                            ->maxLength(255),
                                    ])->columns(2),
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
                                            ->disk('public')
                                            ->visibility('public')
                                            ->maxSize(2048)
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Recommended: 200x60px, PNG or SVG'),

                                        Forms\Components\FileUpload::make('favicon')
                                            ->label('Favicon')
                                            ->image()
                                            ->directory('tenant-branding')
                                            ->disk('public')
                                            ->visibility('public')
                                            ->maxSize(512)
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Recommended: 32x32px or 64x64px, ICO or PNG'),
                                    ])->columns(2),

                                SC\Section::make('Site Information')
                                    ->schema([
                                        Forms\Components\TextInput::make('site_title')
                                            ->label('Site Title')
                                            ->required()
                                            ->maxLength(255)
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'The name of your site displayed in browser tab and header'),

                                        // Language is set in Core Admin (Tenant Edit page)
                                        // Forms\Components\Select::make('site_language')
                                        //     ->label('Site Language')
                                        //     ->options([
                                        //         'en' => 'English',
                                        //         'ro' => 'Romanian (Română)',
                                        //     ])
                                        //     ->default('en')
                                        //     ->required()
                                        //     ->hintIcon('heroicon-o-information-circle', tooltip: 'Primary language for your public site'),

                                        Forms\Components\Textarea::make('site_description')
                                            ->label('Site Description')
                                            ->rows(3)
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Brief description for SEO and social sharing')
                                            ->maxLength(500),

                                        Forms\Components\TextInput::make('site_tagline')
                                            ->label('Site Tagline')
                                            ->maxLength(255)
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Short tagline displayed on the site'),

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
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Terms displayed on tickets'),
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
                                                'sleek' => 'Sleek (Minimalist)',
                                                'theater' => 'Theater (Dark)',
                                                'pub' => 'Pub (Warm)',
                                            ])
                                            ->default('default'),
                                    ])->columns(3),

                                SC\Section::make('Serii documente')
                                    ->description('Configurează prefixele și numerotarea pentru comenzi, facturi și deconturi')
                                    ->schema([
                                        Forms\Components\TextInput::make('order_prefix')
                                            ->label('Prefix serie comenzi')
                                            ->default('CMD')
                                            ->maxLength(10)
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Ex: CMD → CMD0001, CMD0002...'),

                                        Forms\Components\TextInput::make('order_next_number')
                                            ->label('Număr curent comenzi')
                                            ->numeric()
                                            ->default(1)
                                            ->minValue(1),

                                        Forms\Components\TextInput::make('invoice_prefix')
                                            ->label('Prefix serie facturi')
                                            ->default('FACT')
                                            ->maxLength(10)
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Ex: FACT → FACT0001, FACT0002...'),

                                        Forms\Components\TextInput::make('invoice_next_number')
                                            ->label('Număr curent facturi')
                                            ->numeric()
                                            ->default(1)
                                            ->minValue(1),

                                        Forms\Components\TextInput::make('invoice_due_days')
                                            ->label('Zile scadență facturi')
                                            ->numeric()
                                            ->default(30)
                                            ->minValue(1)
                                            ->maxValue(365)
                                            ->suffix('zile')
                                            ->helperText('Numărul de zile între data emiterii și data scadenței. Poate fi suprascris per organizator.'),

                                        Forms\Components\TextInput::make('decont_prefix')
                                            ->label('Prefix serie deconturi')
                                            ->default('DEC')
                                            ->maxLength(10)
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Ex: DEC → DEC0001, DEC0002...'),

                                        Forms\Components\TextInput::make('decont_next_number')
                                            ->label('Număr curent deconturi')
                                            ->numeric()
                                            ->default(1)
                                            ->minValue(1),
                                    ])->columns(2),
                            ]),

                        SC\Tabs\Tab::make('Legal Pages')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                SC\Section::make('Terms & Conditions')
                                    ->description('Content displayed on your Terms & Conditions page')
                                    ->schema([
                                        Forms\Components\TextInput::make('terms_title')
                                            ->label('Page Title')
                                            ->default('Terms & Conditions')
                                            ->maxLength(255)
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'The title displayed on the Terms page'),

                                        Forms\Components\RichEditor::make('terms_content')
                                            ->label('Content')
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
                                        Forms\Components\TextInput::make('privacy_title')
                                            ->label('Page Title')
                                            ->default('Privacy Policy')
                                            ->maxLength(255)
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'The title displayed on the Privacy page'),

                                        Forms\Components\RichEditor::make('privacy_content')
                                            ->label('Content')
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

                        SC\Tabs\Tab::make('Links')
                            ->icon('heroicon-o-link')
                            ->schema([
                                SC\Section::make('Social Media Links')
                                    ->description('Add links to your social media profiles. Icons will appear in the footer.')
                                    ->schema([
                                        Forms\Components\TextInput::make('social_facebook')
                                            ->label('Facebook')
                                            ->url()
                                            ->placeholder('https://facebook.com/yourpage')
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('social_instagram')
                                            ->label('Instagram')
                                            ->url()
                                            ->placeholder('https://instagram.com/yourprofile')
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('social_twitter')
                                            ->label('Twitter / X')
                                            ->url()
                                            ->placeholder('https://twitter.com/yourhandle')
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('social_youtube')
                                            ->label('YouTube')
                                            ->url()
                                            ->placeholder('https://youtube.com/@yourchannel')
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('social_tiktok')
                                            ->label('TikTok')
                                            ->url()
                                            ->placeholder('https://tiktok.com/@yourprofile')
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('social_linkedin')
                                            ->label('LinkedIn')
                                            ->url()
                                            ->placeholder('https://linkedin.com/company/yourcompany')
                                            ->maxLength(255),
                                    ])->columns(2),
                            ]),


                        SC\Tabs\Tab::make('Emails')
                            ->icon('heroicon-o-envelope')
                            ->schema([
                                SC\Section::make('Email Configuration')
                                    ->description('Configure custom mail settings for sending emails. Leave empty to use platform default.')
                                    ->schema([
                                        Forms\Components\Select::make('mail_driver')
                                            ->label('Mail Provider')
                                            ->options($this->getMailDriverOptions())
                                            ->placeholder('Select mail provider')
                                            ->live()
                                            ->afterStateUpdated(fn (Forms\Components\Select $component) => $component
                                                ->getContainer()
                                                ->getComponent('mailProviderFields')
                                                ?->getChildComponentContainer()
                                                ->fill())
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Select your email service provider')
                                            ->columnSpanFull(),

                                        // Conditional fields based on mail provider
                                        SC\Group::make()
                                            ->key('mailProviderFields')
                                            ->schema(fn (\Filament\Schemas\Components\Utilities\Get $get): array => match ($get('mail_driver')) {
                                                'smtp' => $this->getSmtpFields(),
                                                'brevo' => $this->getBrevoFields(),
                                                'postmark' => $this->getPostmarkFields(),
                                                'mailgun' => $this->getMailgunFields(),
                                                'sendgrid' => $this->getSendgridFields(),
                                                'ses' => $this->getSesFields(),
                                                'gmail' => $this->getGmailFields(),
                                                'outlook' => $this->getOutlookFields(),
                                                default => [],
                                            })
                                            ->columnSpanFull(),

                                        // Test Connection Button (shown only when provider is selected)
                                        SC\Actions::make([
                                            \Filament\Actions\Action::make('testConnection')
                                                ->label('Test Email Connection')
                                                ->icon('heroicon-o-paper-airplane')
                                                ->color('gray')
                                                ->requiresConfirmation()
                                                ->modalHeading('Test Email Connection')
                                                ->modalDescription('A test email will be sent to verify your mail configuration is working correctly.')
                                                ->modalSubmitActionLabel('Send Test Email')
                                                ->form([
                                                    Forms\Components\TextInput::make('test_email')
                                                        ->label('Send test email to')
                                                        ->email()
                                                        ->required()
                                                        ->default(fn () => auth()->user()?->email)
                                                        ->helperText('Enter the email address where you want to receive the test email'),
                                                ])
                                                ->action(function (array $data) {
                                                    // First save current form state to the marketplace
                                                    $this->save();

                                                    // Now send test email
                                                    $marketplace = static::getMarketplaceClient();

                                                    if (!$marketplace) {
                                                        Notification::make()
                                                            ->danger()
                                                            ->title('Error')
                                                            ->body('Could not find marketplace configuration.')
                                                            ->send();
                                                        return;
                                                    }

                                                    $result = $marketplace->sendTestEmail($data['test_email']);

                                                    if ($result['success']) {
                                                        Notification::make()
                                                            ->success()
                                                            ->title('Test email sent!')
                                                            ->body($result['message'])
                                                            ->send();
                                                    } else {
                                                        Notification::make()
                                                            ->danger()
                                                            ->title('Failed to send test email')
                                                            ->body($result['error'] ?? 'Unknown error occurred')
                                                            ->duration(10000)
                                                            ->send();
                                                    }
                                                }),
                                        ])
                                        ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => filled($get('mail_driver')))
                                        ->columnSpanFull(),
                                    ])->columns(2),

                                SC\Section::make('Provider tranzacțional (SMTP propriu platformă)')
                                    ->description('Provider dedicat pentru emailurile tranzacționale (confirmări comandă, parolă reset, livrare bilete, alerte stoc, etc.). Lasă gol pentru a folosi providerul principal de mai sus.')
                                    ->icon('heroicon-o-shield-check')
                                    ->schema([
                                        Forms\Components\Select::make('transactional_mail_driver')
                                            ->label('Mail Provider')
                                            ->options($this->getMailDriverOptions())
                                            ->placeholder('Select mail provider')
                                            ->live()
                                            ->afterStateUpdated(fn (Forms\Components\Select $component) => $component
                                                ->getContainer()
                                                ->getComponent('transactionalMailProviderFields')
                                                ?->getChildComponentContainer()
                                                ->fill())
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Folosit doar pentru cele 14 tipuri de emailuri tranzacționale.')
                                            ->columnSpanFull(),

                                        SC\Group::make()
                                            ->key('transactionalMailProviderFields')
                                            ->schema(fn (\Filament\Schemas\Components\Utilities\Get $get): array => match ($get('transactional_mail_driver')) {
                                                'smtp' => $this->getSmtpFields('transactional_mail_'),
                                                'brevo' => $this->getBrevoFields('transactional_mail_'),
                                                'postmark' => $this->getPostmarkFields('transactional_mail_'),
                                                'mailgun' => $this->getMailgunFields('transactional_mail_'),
                                                'sendgrid' => $this->getSendgridFields('transactional_mail_'),
                                                'ses' => $this->getSesFields('transactional_mail_'),
                                                'gmail' => $this->getGmailFields('transactional_mail_'),
                                                'outlook' => $this->getOutlookFields('transactional_mail_'),
                                                default => [],
                                            })
                                            ->columnSpanFull(),

                                        SC\Actions::make([
                                            \Filament\Actions\Action::make('testTransactionalConnection')
                                                ->label('Test Transactional Email Connection')
                                                ->icon('heroicon-o-paper-airplane')
                                                ->color('gray')
                                                ->requiresConfirmation()
                                                ->modalHeading('Test Transactional Email Connection')
                                                ->modalDescription('A test email will be sent through the transactional provider to verify the configuration.')
                                                ->modalSubmitActionLabel('Send Test Email')
                                                ->form([
                                                    Forms\Components\TextInput::make('test_email')
                                                        ->label('Send test email to')
                                                        ->email()
                                                        ->required()
                                                        ->default(fn () => auth()->user()?->email),
                                                ])
                                                ->action(function (array $data) {
                                                    $this->save();

                                                    $marketplace = static::getMarketplaceClient();
                                                    if (!$marketplace) {
                                                        Notification::make()
                                                            ->danger()
                                                            ->title('Error')
                                                            ->body('Could not find marketplace configuration.')
                                                            ->send();
                                                        return;
                                                    }

                                                    $result = $marketplace->sendTransactionalTestEmail($data['test_email']);

                                                    if ($result['success']) {
                                                        Notification::make()
                                                            ->success()
                                                            ->title('Test email sent!')
                                                            ->body($result['message'])
                                                            ->send();
                                                    } else {
                                                        Notification::make()
                                                            ->danger()
                                                            ->title('Failed to send test email')
                                                            ->body($result['error'] ?? 'Unknown error occurred')
                                                            ->duration(10000)
                                                            ->send();
                                                    }
                                                }),
                                        ])
                                        ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => filled($get('transactional_mail_driver')))
                                        ->columnSpanFull(),
                                    ])->columns(2),

                                SC\Section::make('Alerte stoc bilete')
                                    ->description('Primește notificări automate când stocul unui tip de bilet scade sub un anumit prag.')
                                    ->icon('heroicon-o-bell-alert')
                                    ->schema([
                                        Forms\Components\TextInput::make('stock_alert_threshold')
                                            ->label('Prag alertă stoc')
                                            ->helperText('Când un tip de bilet are mai puțin sau egal cu acest număr de bilete disponibile, se trimite o alertă.')
                                            ->numeric()
                                            ->minValue(0)
                                            ->placeholder('ex: 5')
                                            ->suffix('bilete'),
                                        Forms\Components\TextInput::make('stock_alert_email')
                                            ->label('Email notificări stoc')
                                            ->helperText('Adresa pe care se trimit alertele de stoc scăzut.')
                                            ->email()
                                            ->placeholder('alerts@exemplu.ro'),
                                    ])->columns(2),

                                SC\Section::make('Notificări admin')
                                    ->description('Adrese de email către care se trimit notificări automate. Lasă gol pentru a dezactiva un anumit tip de notificare.')
                                    ->schema([
                                        Forms\Components\TextInput::make('admin_notification_orders_email')
                                            ->label('Comenzi bilete & cereri de retur')
                                            ->email()
                                            ->placeholder('admin@exemplu.ro')
                                            ->helperText('Primește notificare la fiecare comandă plătită și la fiecare cerere de retur.'),
                                        Forms\Components\TextInput::make('admin_notification_service_orders_email')
                                            ->label('Comenzi servicii extra')
                                            ->email()
                                            ->placeholder('admin@exemplu.ro')
                                            ->helperText('Primește notificare la fiecare comandă de servicii noi (featuring, email, tracking, campaign).'),
                                    ])->columns(2),
                            ]),

                        SC\Tabs\Tab::make('Domains')
                            ->icon('heroicon-o-globe-alt')
                            ->schema([
                                Forms\Components\Placeholder::make('domains_list')
                                    ->label('')
                                    ->content(fn () => new HtmlString(view('filament.marketplace.components.domains-list', ['domains' => $this->domains])->render())),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $marketplace = static::getMarketplaceClient();

        if (!$marketplace) {
            return;
        }

        // Diagnostic: capture the state of every settings-bound field BEFORE
        // we run the save logic, so we can see whether the user-typed value
        // ever reaches \$data (versus being stripped by the form layer) and
        // whether the existing DB value is still there. Will be removed after
        // root cause is confirmed.
        \Log::info('[Settings::save] start', [
            'marketplace_id' => $marketplace->id,
            'data_has_stock_alert_threshold' => array_key_exists('stock_alert_threshold', $data),
            'data_stock_alert_threshold' => $data['stock_alert_threshold'] ?? '(missing)',
            'data_has_stock_alert_email' => array_key_exists('stock_alert_email', $data),
            'data_stock_alert_email' => $data['stock_alert_email'] ?? '(missing)',
            'data_has_admin_orders' => array_key_exists('admin_notification_orders_email', $data),
            'data_admin_orders' => $data['admin_notification_orders_email'] ?? '(missing)',
            'data_has_admin_services' => array_key_exists('admin_notification_service_orders_email', $data),
            'data_admin_services' => $data['admin_notification_service_orders_email'] ?? '(missing)',
            'data_mail_driver' => $data['mail_driver'] ?? '(missing)',
            'data_transactional_mail_driver' => $data['transactional_mail_driver'] ?? '(missing)',
            'db_stock_alert_threshold' => $marketplace->settings['stock_alert_threshold'] ?? '(missing)',
            'db_stock_alert_email' => $marketplace->settings['stock_alert_email'] ?? '(missing)',
            'db_admin_orders' => $marketplace->settings['admin_notifications']['orders_email'] ?? '(missing)',
            'db_admin_services' => $marketplace->settings['admin_notifications']['service_orders_email'] ?? '(missing)',
        ]);

        // Update tenant fields
        $marketplace->update([
            'company_name' => $data['company_name'],
            'cui' => $data['cui'],
            'reg_com' => $data['reg_com'],
            'vat_payer' => (bool) ($data['vat_payer'] ?? false),
            'tax_display_mode' => $data['tax_display_mode'] ?? 'included',
            'fixed_commission' => $data['fixed_commission'] ?? null,
            'address' => $data['address'],
            'city' => $data['city'],
            'state' => $data['state'],
            'country' => $data['country'],
            'postal_code' => $data['postal_code'],
            'contact_email' => $data['contact_email'],
            'contact_phone' => $data['contact_phone'],
            'operating_hours' => $data['operating_hours'] ?? null,
            'website' => $data['website'],
            'bank_name' => $data['bank_name'],
            'bank_account' => $data['bank_account'],
            'currency' => $data['currency'],
            'ticket_terms' => $data['ticket_terms'],
        ]);

        // Update settings JSON — start from existing to preserve all keys
        $settings = $marketplace->settings ?? [];
        $settings['site_title'] = $data['site_title'];
        // Language is set in Core Admin (Tenant Edit page)
        // $settings['site_language'] = $data['site_language'];
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
        $settings['invoice_preparer'] = $data['invoice_preparer'] ?? '';
        $settings['general_invoice_client_name'] = $data['general_invoice_client_name'] ?? 'Client general';
        $settings['general_invoice_client_cui'] = $data['general_invoice_client_cui'] ?? '';
        $settings['general_invoice_client_address'] = $data['general_invoice_client_address'] ?? '';
        $existingNotif = $settings['admin_notifications'] ?? [];
        $settings['admin_notifications'] = [
            'orders_email' => filled($data['admin_notification_orders_email'] ?? null) ? $data['admin_notification_orders_email'] : ($existingNotif['orders_email'] ?? ''),
            'service_orders_email' => filled($data['admin_notification_service_orders_email'] ?? null) ? $data['admin_notification_service_orders_email'] : ($existingNotif['service_orders_email'] ?? ''),
        ];

        // Document Series
        $settings['order_prefix'] = $data['order_prefix'] ?? 'CMD';
        $settings['order_next_number'] = (int) ($data['order_next_number'] ?? 1);
        $settings['invoice_prefix'] = $data['invoice_prefix'] ?? 'FACT';
        $settings['invoice_next_number'] = (int) ($data['invoice_next_number'] ?? 1);
        $settings['invoice_due_days'] = (int) ($data['invoice_due_days'] ?? 30);

        $settings['legal'] = [
            'terms_title' => $data['terms_title'] ?? 'Terms & Conditions',
            'terms' => $data['terms_content'],
            'privacy_title' => $data['privacy_title'] ?? 'Privacy Policy',
            'privacy' => $data['privacy_content'],
        ];
        $settings['social'] = [
            'facebook' => $data['social_facebook'] ?? '',
            'instagram' => $data['social_instagram'] ?? '',
            'twitter' => $data['social_twitter'] ?? '',
            'youtube' => $data['social_youtube'] ?? '',
            'tiktok' => $data['social_tiktok'] ?? '',
            'linkedin' => $data['social_linkedin'] ?? '',
        ];

        // Update mail settings — saved in dedicated smtp_settings column (not settings JSON)
        // to prevent loss when settings JSON is rebuilt on save.
        $currentSmtp = $marketplace->smtp_settings ?? [];
        $incomingDriver = $data['mail_driver'] ?? null;

        // Only update if driver is explicitly provided
        if (filled($incomingDriver)) {
            $currentSmtp['driver'] = $incomingDriver;

            // Common fields
            if (array_key_exists('mail_from_address', $data) && filled($data['mail_from_address'])) {
                $currentSmtp['from_address'] = $data['mail_from_address'];
            }
            if (array_key_exists('mail_from_name', $data) && filled($data['mail_from_name'])) {
                $currentSmtp['from_name'] = $data['mail_from_name'];
            }

            // SMTP-specific fields
            if (!empty($data['mail_host'])) $currentSmtp['host'] = $data['mail_host'];
            if (!empty($data['mail_port'])) $currentSmtp['port'] = $data['mail_port'];
            if (array_key_exists('mail_username', $data) && filled($data['mail_username'])) {
                $currentSmtp['username'] = $data['mail_username'];
            }
            if (!empty($data['mail_password'])) $currentSmtp['password'] = encrypt($data['mail_password']);
            if (isset($data['mail_encryption'])) $currentSmtp['encryption'] = $data['mail_encryption'];

            // API-based providers
            if (!empty($data['mail_api_key'])) $currentSmtp['api_key'] = encrypt($data['mail_api_key']);
            if (!empty($data['mail_api_secret'])) $currentSmtp['api_secret'] = encrypt($data['mail_api_secret']);

            // Mailgun/SES specific
            if (!empty($data['mail_domain'])) $currentSmtp['domain'] = $data['mail_domain'];
            if (!empty($data['mail_region'])) $currentSmtp['region'] = $data['mail_region'];
        }
        // If no driver selected, don't touch smtp_settings at all

        // Save mail settings to dedicated column (survives settings JSON rebuilds)
        $marketplace->smtp_settings = $currentSmtp;

        // Clear settings.mail to avoid stale data (smtp_settings is the source of truth now)
        unset($settings['mail']);

        // Transactional mail settings — secondary provider stored in its own column.
        // Mirrors the primary save logic; when no driver is selected we leave the
        // existing config alone (or reset to empty when explicitly cleared).
        $currentTxSmtp = $marketplace->transactional_smtp_settings ?? [];
        $incomingTxDriver = $data['transactional_mail_driver'] ?? null;

        if (filled($incomingTxDriver)) {
            $currentTxSmtp['driver'] = $incomingTxDriver;

            if (array_key_exists('transactional_mail_from_address', $data) && filled($data['transactional_mail_from_address'])) {
                $currentTxSmtp['from_address'] = $data['transactional_mail_from_address'];
            }
            if (array_key_exists('transactional_mail_from_name', $data) && filled($data['transactional_mail_from_name'])) {
                $currentTxSmtp['from_name'] = $data['transactional_mail_from_name'];
            }

            if (!empty($data['transactional_mail_host'])) $currentTxSmtp['host'] = $data['transactional_mail_host'];
            if (!empty($data['transactional_mail_port'])) $currentTxSmtp['port'] = $data['transactional_mail_port'];
            if (array_key_exists('transactional_mail_username', $data) && filled($data['transactional_mail_username'])) {
                $currentTxSmtp['username'] = $data['transactional_mail_username'];
            }
            if (!empty($data['transactional_mail_password'])) $currentTxSmtp['password'] = encrypt($data['transactional_mail_password']);
            if (isset($data['transactional_mail_encryption'])) $currentTxSmtp['encryption'] = $data['transactional_mail_encryption'];

            if (!empty($data['transactional_mail_api_key'])) $currentTxSmtp['api_key'] = encrypt($data['transactional_mail_api_key']);
            if (!empty($data['transactional_mail_api_secret'])) $currentTxSmtp['api_secret'] = encrypt($data['transactional_mail_api_secret']);

            if (!empty($data['transactional_mail_domain'])) $currentTxSmtp['domain'] = $data['transactional_mail_domain'];
            if (!empty($data['transactional_mail_region'])) $currentTxSmtp['region'] = $data['transactional_mail_region'];
        } else {
            // Driver was cleared — wipe the secondary config so we fall back to primary
            $currentTxSmtp = [];
        }

        $marketplace->transactional_smtp_settings = $currentTxSmtp;

        // Stock alert settings — preserve existing if form fields are empty
        if (!empty($data['stock_alert_threshold'])) {
            $settings['stock_alert_threshold'] = (int) $data['stock_alert_threshold'];
        }
        if (filled($data['stock_alert_email'] ?? null)) {
            $settings['stock_alert_email'] = $data['stock_alert_email'];
        }

        $marketplace->update([
            'settings' => $settings,
        ]);

        \Log::info('[Settings::save] done', [
            'marketplace_id' => $marketplace->id,
            'saved_stock_alert_threshold' => $settings['stock_alert_threshold'] ?? '(missing)',
            'saved_stock_alert_email' => $settings['stock_alert_email'] ?? '(missing)',
            'saved_admin_orders' => $settings['admin_notifications']['orders_email'] ?? '(missing)',
            'saved_admin_services' => $settings['admin_notifications']['service_orders_email'] ?? '(missing)',
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

    /**
     * Decrypt a setting value safely
     */
    private function decryptSetting(?string $value): string
    {
        if (empty($value)) {
            return '';
        }

        try {
            return decrypt($value);
        } catch (\Exception $e) {
            // If decryption fails, return empty (value might not be encrypted)
            return '';
        }
    }

    /**
     * Shared mail-driver options used by both the primary and transactional providers.
     */
    private function getMailDriverOptions(): array
    {
        return [
            '' => 'Use Platform Default',
            'smtp' => 'SMTP (Generic)',
            'brevo' => 'Brevo (Sendinblue)',
            'postmark' => 'Postmark',
            'mailgun' => 'Mailgun',
            'sendgrid' => 'SendGrid',
            'ses' => 'Amazon SES',
            'gmail' => 'Gmail',
            'outlook' => 'Microsoft 365 / Outlook',
        ];
    }

    /**
     * SMTP provider fields. $prefix lets the same helper render either the
     * primary ('mail_') or the transactional ('transactional_mail_') section.
     */
    private function getSmtpFields(string $prefix = 'mail_'): array
    {
        return [
            SC\Grid::make(2)->schema([
                Forms\Components\TextInput::make($prefix . 'host')
                    ->label('SMTP Host')
                    ->placeholder('smtp.example.com')
                    ->maxLength(255)
                    ->required()
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Your mail server hostname'),

                Forms\Components\TextInput::make($prefix . 'port')
                    ->label('SMTP Port')
                    ->numeric()
                    ->default(587)
                    ->placeholder('587')
                    ->required()
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Usually 587 for TLS, 465 for SSL'),

                Forms\Components\TextInput::make($prefix . 'username')
                    ->label('Username')
                    ->maxLength(255)
                    ->placeholder('your-username')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'SMTP authentication username'),

                Forms\Components\TextInput::make($prefix . 'password')
                    ->label('Password')
                    ->password()
                    ->maxLength(255)
                    ->autocomplete('new-password')
                    ->placeholder('••••••••')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Leave empty to keep existing')
                    ->dehydrated(fn ($state) => filled($state)),

                Forms\Components\Select::make($prefix . 'encryption')
                    ->label('Encryption')
                    ->options([
                        'tls' => 'TLS (Recommended)',
                        'ssl' => 'SSL',
                        '' => 'None',
                    ])
                    ->default('tls')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Security protocol'),

                Forms\Components\TextInput::make($prefix . 'from_address')
                    ->label('From Email')
                    ->email()
                    ->maxLength(255)
                    ->required()
                    ->placeholder('noreply@yourdomain.com')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Sender email address'),

                Forms\Components\TextInput::make($prefix . 'from_name')
                    ->label('From Name')
                    ->maxLength(255)
                    ->required()
                    ->placeholder('Your Company')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Sender display name'),
            ]),
        ];
    }

    /**
     * Brevo (Sendinblue) provider fields
     */
    private function getBrevoFields(string $prefix = 'mail_'): array
    {
        return [
            SC\Grid::make(2)->schema([
                Forms\Components\TextInput::make($prefix . 'username')
                    ->label('SMTP Login (Account Email)')
                    ->email()
                    ->maxLength(255)
                    ->required()
                    ->placeholder('your-brevo-account@email.com')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Your Brevo account email used for SMTP login'),

                Forms\Components\TextInput::make($prefix . 'api_key')
                    ->label('SMTP Key')
                    ->password()
                    ->maxLength(255)
                    ->autocomplete('new-password')
                    ->placeholder('xsmtpsib-...')
                    ->required()
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'SMTP Key from Brevo Settings > SMTP & API (different from API Key)')
                    ->dehydrated(fn ($state) => filled($state)),

                Forms\Components\TextInput::make($prefix . 'from_address')
                    ->label('From Email')
                    ->email()
                    ->maxLength(255)
                    ->required()
                    ->placeholder('noreply@yourdomain.com')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Must be a verified sender in Brevo'),

                Forms\Components\TextInput::make($prefix . 'from_name')
                    ->label('From Name')
                    ->maxLength(255)
                    ->required()
                    ->placeholder('Your Company')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Sender display name'),
            ]),
        ];
    }

    /**
     * Postmark provider fields
     */
    private function getPostmarkFields(string $prefix = 'mail_'): array
    {
        return [
            SC\Grid::make(2)->schema([
                Forms\Components\TextInput::make($prefix . 'api_key')
                    ->label('Server API Token')
                    ->password()
                    ->maxLength(255)
                    ->autocomplete('new-password')
                    ->placeholder('xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx')
                    ->required()
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Found in Server → API Tokens')
                    ->dehydrated(fn ($state) => filled($state))
                    ->columnSpanFull(),

                Forms\Components\TextInput::make($prefix . 'from_address')
                    ->label('From Email')
                    ->email()
                    ->maxLength(255)
                    ->required()
                    ->placeholder('noreply@yourdomain.com')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Must be verified sender signature'),

                Forms\Components\TextInput::make($prefix . 'from_name')
                    ->label('From Name')
                    ->maxLength(255)
                    ->required()
                    ->placeholder('Your Company')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Sender display name'),
            ]),
        ];
    }

    /**
     * Mailgun provider fields
     */
    private function getMailgunFields(string $prefix = 'mail_'): array
    {
        return [
            SC\Grid::make(2)->schema([
                Forms\Components\TextInput::make($prefix . 'api_key')
                    ->label('API Key')
                    ->password()
                    ->maxLength(255)
                    ->autocomplete('new-password')
                    ->placeholder('key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx')
                    ->required()
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Private API key from Mailgun')
                    ->dehydrated(fn ($state) => filled($state)),

                Forms\Components\TextInput::make($prefix . 'domain')
                    ->label('Sending Domain')
                    ->maxLength(255)
                    ->placeholder('mg.yourdomain.com')
                    ->required()
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Verified sending domain'),

                Forms\Components\Select::make($prefix . 'region')
                    ->label('Region')
                    ->options([
                        'us' => 'US (api.mailgun.net)',
                        'eu' => 'EU (api.eu.mailgun.net)',
                    ])
                    ->default('us')
                    ->required()
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Mailgun API region'),

                Forms\Components\TextInput::make($prefix . 'from_address')
                    ->label('From Email')
                    ->email()
                    ->maxLength(255)
                    ->required()
                    ->placeholder('noreply@mg.yourdomain.com')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Must use verified domain'),

                Forms\Components\TextInput::make($prefix . 'from_name')
                    ->label('From Name')
                    ->maxLength(255)
                    ->required()
                    ->placeholder('Your Company')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Sender display name'),
            ]),
        ];
    }

    /**
     * SendGrid provider fields
     */
    private function getSendgridFields(string $prefix = 'mail_'): array
    {
        return [
            SC\Grid::make(2)->schema([
                Forms\Components\TextInput::make($prefix . 'api_key')
                    ->label('API Key')
                    ->password()
                    ->maxLength(255)
                    ->autocomplete('new-password')
                    ->placeholder('SG.xxxxxxxxxxxxxxxxxxxx')
                    ->required()
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'SendGrid API key with Mail Send permission')
                    ->dehydrated(fn ($state) => filled($state))
                    ->columnSpanFull(),

                Forms\Components\TextInput::make($prefix . 'from_address')
                    ->label('From Email')
                    ->email()
                    ->maxLength(255)
                    ->required()
                    ->placeholder('noreply@yourdomain.com')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Must be verified sender'),

                Forms\Components\TextInput::make($prefix . 'from_name')
                    ->label('From Name')
                    ->maxLength(255)
                    ->required()
                    ->placeholder('Your Company')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Sender display name'),
            ]),
        ];
    }

    /**
     * Amazon SES provider fields
     */
    private function getSesFields(string $prefix = 'mail_'): array
    {
        return [
            SC\Grid::make(2)->schema([
                Forms\Components\TextInput::make($prefix . 'api_key')
                    ->label('Access Key ID')
                    ->password()
                    ->maxLength(255)
                    ->autocomplete('new-password')
                    ->placeholder('AKIAIOSFODNN7EXAMPLE')
                    ->required()
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'AWS IAM access key')
                    ->dehydrated(fn ($state) => filled($state)),

                Forms\Components\TextInput::make($prefix . 'api_secret')
                    ->label('Secret Access Key')
                    ->password()
                    ->maxLength(255)
                    ->autocomplete('new-password')
                    ->placeholder('••••••••')
                    ->required()
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'AWS IAM secret key')
                    ->dehydrated(fn ($state) => filled($state)),

                Forms\Components\Select::make($prefix . 'region')
                    ->label('AWS Region')
                    ->options([
                        'us-east-1' => 'US East (N. Virginia)',
                        'us-east-2' => 'US East (Ohio)',
                        'us-west-1' => 'US West (N. California)',
                        'us-west-2' => 'US West (Oregon)',
                        'eu-west-1' => 'EU (Ireland)',
                        'eu-west-2' => 'EU (London)',
                        'eu-west-3' => 'EU (Paris)',
                        'eu-central-1' => 'EU (Frankfurt)',
                    ])
                    ->required()
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'SES region'),

                Forms\Components\TextInput::make($prefix . 'from_address')
                    ->label('From Email')
                    ->email()
                    ->maxLength(255)
                    ->required()
                    ->placeholder('noreply@yourdomain.com')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Verified email or domain'),

                Forms\Components\TextInput::make($prefix . 'from_name')
                    ->label('From Name')
                    ->maxLength(255)
                    ->required()
                    ->placeholder('Your Company')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Sender display name'),
            ]),
        ];
    }

    /**
     * Gmail provider fields
     */
    private function getGmailFields(string $prefix = 'mail_'): array
    {
        return [
            SC\Grid::make(2)->schema([
                Forms\Components\Placeholder::make($prefix . 'gmail_info')
                    ->label('')
                    ->content(new HtmlString('
                        <div class="p-3 text-sm text-gray-600 rounded-lg dark:text-gray-400 bg-blue-50 dark:bg-blue-900/20">
                            <strong>Important:</strong> Use an App Password, not your regular Gmail password.
                            <a href="https://myaccount.google.com/apppasswords" target="_blank" class="underline text-primary-600">Generate App Password</a>
                        </div>
                    '))
                    ->columnSpanFull(),

                Forms\Components\TextInput::make($prefix . 'username')
                    ->label('Gmail Address')
                    ->email()
                    ->maxLength(255)
                    ->placeholder('your-email@gmail.com')
                    ->required()
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Your Gmail address'),

                Forms\Components\TextInput::make($prefix . 'password')
                    ->label('App Password')
                    ->password()
                    ->maxLength(255)
                    ->autocomplete('new-password')
                    ->placeholder('xxxx xxxx xxxx xxxx')
                    ->required()
                    ->hintIcon('heroicon-o-information-circle', tooltip: '16-character app password')
                    ->dehydrated(fn ($state) => filled($state)),

                Forms\Components\TextInput::make($prefix . 'from_address')
                    ->label('From Email')
                    ->email()
                    ->maxLength(255)
                    ->placeholder('your-email@gmail.com')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Usually same as Gmail address'),

                Forms\Components\TextInput::make($prefix . 'from_name')
                    ->label('From Name')
                    ->maxLength(255)
                    ->required()
                    ->placeholder('Your Company')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Sender display name'),
            ]),
        ];
    }

    /**
     * Microsoft 365 / Outlook provider fields
     */
    private function getOutlookFields(string $prefix = 'mail_'): array
    {
        return [
            SC\Grid::make(2)->schema([
                Forms\Components\Placeholder::make($prefix . 'outlook_info')
                    ->label('')
                    ->content(new HtmlString('
                        <div class="p-3 text-sm text-gray-600 rounded-lg dark:text-gray-400 bg-blue-50 dark:bg-blue-900/20">
                            <strong>Important:</strong> Use an App Password if 2FA is enabled.
                            <a href="https://account.live.com/proofs/AppPassword" target="_blank" class="underline text-primary-600">Generate App Password</a>
                        </div>
                    '))
                    ->columnSpanFull(),

                Forms\Components\TextInput::make($prefix . 'username')
                    ->label('Email Address')
                    ->email()
                    ->maxLength(255)
                    ->placeholder('your-email@outlook.com')
                    ->required()
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Your Microsoft 365 / Outlook email'),

                Forms\Components\TextInput::make($prefix . 'password')
                    ->label('Password / App Password')
                    ->password()
                    ->maxLength(255)
                    ->autocomplete('new-password')
                    ->placeholder('••••••••')
                    ->required()
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Account or app password')
                    ->dehydrated(fn ($state) => filled($state)),

                Forms\Components\TextInput::make($prefix . 'from_address')
                    ->label('From Email')
                    ->email()
                    ->maxLength(255)
                    ->placeholder('your-email@outlook.com')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Usually same as login email'),

                Forms\Components\TextInput::make($prefix . 'from_name')
                    ->label('From Name')
                    ->maxLength(255)
                    ->required()
                    ->placeholder('Your Company')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Sender display name'),
            ]),
        ];
    }
}

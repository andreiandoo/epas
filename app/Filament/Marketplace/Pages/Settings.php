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
                'timezone' => $marketplace->timezone ?? \App\Support\MarketplaceTz::DEFAULT_TIMEZONE,
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
                'decont_prefix' => $settings['decont_prefix'] ?? 'DEC',
                'decont_next_number' => $settings['decont_next_number'] ?? 1,

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

                // Transactional routing mode ('auto' | 'primary_only' | 'transactional_only')
                'transactional_mode' => $marketplace->transactional_mode ?: 'auto',

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

                // Payment processing fees — unpack JSONB column into form fields.
                // The DB stores providers as an associative dict keyed by slug;
                // the Repeater needs a positional array. Convert here, re-pack on save.
                'payment_fees_pass_to_customer' => (bool) ($marketplace->payment_fees['pass_to_customer'] ?? false),
                'payment_fees_providers' => collect($marketplace->payment_fees['providers'] ?? [])
                    ->map(fn ($cfg, $slug) => [
                        'slug'          => is_string($slug) ? $slug : ($cfg['slug'] ?? ''),
                        'label'         => $cfg['label'] ?? '',
                        'percent_rate'  => $cfg['percent_rate'] ?? null,
                        'fixed_lei'     => isset($cfg['fixed_cents']) ? round(((int) $cfg['fixed_cents']) / 100, 2) : null,
                    ])
                    ->values()
                    ->all(),
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

                                        Forms\Components\Select::make('timezone')
                                            ->label('Fus orar')
                                            ->options(fn () => collect(\DateTimeZone::listIdentifiers())
                                                ->mapWithKeys(fn ($tz) => [$tz => $tz])
                                                ->all())
                                            ->default(\App\Support\MarketplaceTz::DEFAULT_TIMEZONE)
                                            ->searchable()
                                            ->required()
                                            ->helperText('Toate orele afișate (comenzi, bilete, rapoarte) vor fi convertite în acest fus orar. Datele rămân stocate în UTC.')
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Default: Europe/Bucharest. Schimbarea afectează doar afișarea — nu modifică datele istorice.'),

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

                        // ============================================================
                        // Taxe procesare card — opt-in per marketplace.
                        // Leave the Providers repeater empty (or this tab untouched)
                        // to keep the marketplace's payment_fees = NULL → kill switch
                        // active. Marketplaces that don't fill anything here are
                        // observationally identical to before this feature shipped.
                        // ============================================================
                        SC\Tabs\Tab::make('Taxe procesare card')
                            ->icon('heroicon-o-credit-card')
                            ->schema([
                                SC\Section::make('Procesare plăți online')
                                    ->description('Configurează ratele de comision percepute de procesatorii de plăți (Stripe, Netopia, RoPay) și cine plătește această taxă: clientul final sau marketplace-ul prin comisionul tău. Lasă providers gol pentru a dezactiva complet — comportamentul este identic cu cel actual.')
                                    ->schema([
                                        Forms\Components\Toggle::make('payment_fees_pass_to_customer')
                                            ->label('Transferă taxa de procesare clientului')
                                            ->helperText('Activ: linie nouă în checkout "Taxă procesare card", clientul plătește subtotal + taxă. Inactiv: marketplace-ul absoarbe taxa din comisionul propriu, clientul nu vede nimic în plus. Poți suprascrie per organizator pe pagina organizatorului.')
                                            ->onColor('success')
                                            ->offColor('gray')
                                            ->default(false)
                                            ->columnSpanFull(),

                                        Forms\Components\Repeater::make('payment_fees_providers')
                                            ->label('Provideri configurați')
                                            ->helperText('Adaugă fiecare procesator de plăți pe care îl folosești. Formula taxei: (procent% × subtotal) + sumă fixă. Subtotal = bilet + comision marketplace + asigurare. Lasă gol = feature dezactivat complet.')
                                            ->schema([
                                                Forms\Components\TextInput::make('slug')
                                                    ->label('Slug provider')
                                                    ->placeholder('stripe / netopia / ropay')
                                                    ->required()
                                                    ->maxLength(32)
                                                    ->rule('alpha_dash')
                                                    ->columnSpan(1),
                                                Forms\Components\TextInput::make('label')
                                                    ->label('Etichetă afișată')
                                                    ->placeholder('Stripe')
                                                    ->maxLength(64)
                                                    ->columnSpan(1),
                                                Forms\Components\TextInput::make('percent_rate')
                                                    ->label('Procent (%)')
                                                    ->numeric()
                                                    ->step(0.01)
                                                    ->minValue(0)
                                                    ->maxValue(100)
                                                    ->suffix('%')
                                                    ->placeholder('1.50')
                                                    ->columnSpan(1),
                                                Forms\Components\TextInput::make('fixed_lei')
                                                    ->label('Fix (lei)')
                                                    ->numeric()
                                                    ->step(0.01)
                                                    ->minValue(0)
                                                    ->suffix('lei')
                                                    ->placeholder('1.00')
                                                    ->helperText('Stocat ca bani (× 100) intern.')
                                                    ->columnSpan(1),
                                            ])
                                            ->columns(4)
                                            ->reorderable()
                                            ->collapsible()
                                            ->cloneable()
                                            ->itemLabel(function (array $state): ?string {
                                                $slug = $state['slug'] ?? null;
                                                $rate = $state['percent_rate'] ?? '?';
                                                $fix = $state['fixed_lei'] ?? '?';
                                                return $slug ? strtoupper($slug) . " — {$rate}% + {$fix} lei" : null;
                                            })
                                            ->addActionLabel('Adaugă provider')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1),
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

                                // Country geo importer — one-click bootstrap for a
                                // marketplace's region/county/city catalogue from the
                                // central per-country curated taxonomies. Idempotent
                                // and safe to re-run; bilete.online's existing data
                                // is the production-tested example.
                                SC\Section::make('Geo Catalog Importer')
                                    ->description('Import macro-regions, counties and the official city catalogue for one country at a time. Existing rows are preserved — re-running just no-ops on what already exists.')
                                    ->icon('heroicon-o-globe-europe-africa')
                                    ->collapsed()
                                    ->schema([
                                        Forms\Components\Placeholder::make('current_geo_stats')
                                            ->label('Current state of this marketplace')
                                            ->content(function () {
                                                $client = static::getMarketplaceClient();
                                                if (!$client) {
                                                    return new \Illuminate\Support\HtmlString('<em>No marketplace selected.</em>');
                                                }
                                                $regions  = \App\Models\MarketplaceRegion::where('marketplace_client_id', $client->id)->count();
                                                $counties = \App\Models\MarketplaceCounty::where('marketplace_client_id', $client->id)->count();
                                                $cities   = \App\Models\MarketplaceCity::where('marketplace_client_id', $client->id)->count();
                                                return new \Illuminate\Support\HtmlString(
                                                    "<strong>{$regions}</strong> regions · <strong>{$counties}</strong> counties · <strong>{$cities}</strong> cities"
                                                );
                                            }),

                                        Forms\Components\Select::make('geo_import_country')
                                            ->label('Country to import')
                                            ->options(array_combine(
                                                \App\Services\Geo\CountryGeoImporter::SUPPORTED_COUNTRIES,
                                                array_map(
                                                    fn ($iso) => match ($iso) {
                                                        'RO' => 'România (8 regions · 42 counties · 319 cities)',
                                                        default => $iso,
                                                    },
                                                    \App\Services\Geo\CountryGeoImporter::SUPPORTED_COUNTRIES
                                                )
                                            ))
                                            ->placeholder('Pick a country to import')
                                            ->helperText('Importing replaces this marketplace\'s region + county lookup tables but does NOT delete existing city rows — only their region_id + county_id are re-linked. Custom descriptions, GYG ids, lat/lng, etc. on each city are preserved.'),

                                        SC\Actions::make([
                                            \Filament\Actions\Action::make('runGeoImport')
                                                ->label('Run geo import')
                                                ->icon('heroicon-o-arrow-down-tray')
                                                ->color('warning')
                                                ->requiresConfirmation()
                                                ->modalHeading('Import country geo data?')
                                                ->modalDescription('This will rebuild the marketplace\'s region + county lookup tables for the chosen country and link existing cities to the new IDs. City rows themselves are not deleted; their custom data is preserved. Safe to re-run.')
                                                ->modalSubmitActionLabel('Yes, import')
                                                ->action(fn () => $this->runGeoImport()),
                                        ]),
                                    ]),

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
                                SC\Section::make('Routare mailuri tranzacționale')
                                    ->description('Alege prin ce provider pleacă toate mailurile tranzacționale (bilete, facturi, resetare parolă, alerte stoc, refunds, etc.). Pentru testarea comparativă Brevo vs SMTP propriu.')
                                    ->icon('heroicon-o-arrows-right-left')
                                    ->schema([
                                        Forms\Components\Radio::make('transactional_mode')
                                            ->label(false)
                                            ->options([
                                                'auto' => 'Automat (recomandat) — încearcă SMTP propriu, cu fallback la providerul primar dacă cel tranzacțional pică',
                                                'primary_only' => 'Forțează providerul primar (Brevo) — pentru testare A/B',
                                                'transactional_only' => 'Forțează SMTP propriu — fără fallback (surprinde erorile silențioase ale tranzacționalului)',
                                            ])
                                            ->default('auto')
                                            ->required()
                                            ->columnSpanFull(),
                                    ])->columns(1),

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

                                            \Filament\Actions\Action::make('clearTransactionalConfig')
                                                ->label('Șterge configurare')
                                                ->icon('heroicon-o-trash')
                                                ->color('danger')
                                                ->requiresConfirmation()
                                                ->modalHeading('Șterge configurarea providerului tranzacțional')
                                                ->modalDescription('După ștergere, emailurile tranzacționale vor pleca prin providerul principal (de mai sus). Această acțiune nu afectează providerul principal.')
                                                ->modalSubmitActionLabel('Da, șterge')
                                                ->action(function () {
                                                    $marketplace = static::getMarketplaceClient();
                                                    if (!$marketplace) {
                                                        return;
                                                    }
                                                    $marketplace->update(['transactional_smtp_settings' => []]);
                                                    Notification::make()
                                                        ->success()
                                                        ->title('Configurare ștearsă')
                                                        ->body('Providerul tranzacțional a fost resetat. Reîncarcă pagina pentru a vedea valorile actualizate.')
                                                        ->send();
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
            'data_has_tx_host' => array_key_exists('transactional_mail_host', $data),
            'data_tx_host' => $data['transactional_mail_host'] ?? '(missing)',
            'db_stock_alert_threshold' => $marketplace->settings['stock_alert_threshold'] ?? '(missing)',
            'db_stock_alert_email' => $marketplace->settings['stock_alert_email'] ?? '(missing)',
            'db_admin_orders' => $marketplace->settings['admin_notifications']['orders_email'] ?? '(missing)',
            'db_admin_services' => $marketplace->settings['admin_notifications']['service_orders_email'] ?? '(missing)',
            'db_tx_driver' => $marketplace->transactional_smtp_settings['driver'] ?? '(missing)',
            'db_tx_host' => $marketplace->transactional_smtp_settings['host'] ?? '(missing)',
        ]);

        // Build the full update array first; commit in a SINGLE update at the end
        // so smtp_settings, transactional_smtp_settings and settings all land in
        // the same UPDATE row. Splitting it into multiple update()s previously
        // caused the SMTP columns to occasionally be reset depending on the
        // order in which Eloquent flushed dirty attributes.
        $update = [
            'company_name' => $data['company_name'] ?? $marketplace->company_name,
            'cui' => $data['cui'] ?? $marketplace->cui,
            'reg_com' => $data['reg_com'] ?? $marketplace->reg_com,
            'vat_payer' => (bool) ($data['vat_payer'] ?? false),
            'tax_display_mode' => $data['tax_display_mode'] ?? 'included',
            'fixed_commission' => $data['fixed_commission'] ?? null,
            'address' => $data['address'] ?? $marketplace->address,
            'city' => $data['city'] ?? $marketplace->city,
            'state' => $data['state'] ?? $marketplace->state,
            'country' => $data['country'] ?? $marketplace->country,
            'postal_code' => $data['postal_code'] ?? $marketplace->postal_code,
            'contact_email' => $data['contact_email'] ?? $marketplace->contact_email,
            'contact_phone' => $data['contact_phone'] ?? $marketplace->contact_phone,
            'operating_hours' => $data['operating_hours'] ?? null,
            'website' => $data['website'] ?? $marketplace->website,
            'bank_name' => $data['bank_name'] ?? $marketplace->bank_name,
            'bank_account' => $data['bank_account'] ?? $marketplace->bank_account,
            'currency' => $data['currency'] ?? $marketplace->currency,
            'timezone' => $data['timezone'] ?? $marketplace->timezone ?? \App\Support\MarketplaceTz::DEFAULT_TIMEZONE,
            'ticket_terms' => $data['ticket_terms'] ?? $marketplace->ticket_terms,
        ];

        // ============================================================
        // Payment processing fees — re-pack Repeater rows back into the
        // associative `providers` dictionary keyed by slug. Empty providers
        // list = explicit NULL on payment_fees → kill switch (status quo).
        // ============================================================
        $repeaterRows = $data['payment_fees_providers'] ?? [];
        $providers = [];
        if (is_array($repeaterRows)) {
            foreach ($repeaterRows as $row) {
                $slug = trim((string) ($row['slug'] ?? ''));
                if ($slug === '') continue;
                $providers[$slug] = [
                    'label'        => trim((string) ($row['label'] ?? ucfirst($slug))),
                    'percent_rate' => (float) ($row['percent_rate'] ?? 0),
                    'fixed_cents'  => (int) round(((float) ($row['fixed_lei'] ?? 0)) * 100),
                ];
            }
        }
        if (empty($providers)) {
            // Nothing configured → store NULL so the calculator's kill switch trips.
            $update['payment_fees'] = null;
        } else {
            $update['payment_fees'] = [
                'pass_to_customer' => (bool) ($data['payment_fees_pass_to_customer'] ?? false),
                'providers'        => $providers,
            ];
        }

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
        $settings['decont_prefix'] = $data['decont_prefix'] ?? 'DEC';
        $settings['decont_next_number'] = (int) ($data['decont_next_number'] ?? 1);

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
        $currentSmtp = $this->mergeSmtpFromFormData(
            existing: $marketplace->smtp_settings ?? [],
            data: $data,
            prefix: 'mail_'
        );

        // Clear settings.mail to avoid stale data (smtp_settings is the source of truth now)
        unset($settings['mail']);

        // Transactional mail settings — secondary provider stored in its own column.
        // IMPORTANT: never wipe just because the driver field arrived empty in $data
        // (the conditional Group in the form can shed values when other tabs save).
        // The only way to clear it is to explicitly pick "Use Platform Default" AND
        // be looking at the Emails tab with the secondary section visible.
        $currentTxSmtp = $this->mergeSmtpFromFormData(
            existing: $marketplace->transactional_smtp_settings ?? [],
            data: $data,
            prefix: 'transactional_mail_'
        );

        // Stock alert settings — preserve existing if form fields are empty
        if (!empty($data['stock_alert_threshold'])) {
            $settings['stock_alert_threshold'] = (int) $data['stock_alert_threshold'];
        }
        if (filled($data['stock_alert_email'] ?? null)) {
            $settings['stock_alert_email'] = $data['stock_alert_email'];
        }

        // Single atomic commit — settings JSON + both SMTP columns in one UPDATE.
        $update['settings'] = $settings;
        $update['smtp_settings'] = $currentSmtp;
        $update['transactional_smtp_settings'] = $currentTxSmtp;

        // Transactional routing mode ('auto' | 'primary_only' | 'transactional_only').
        // Only persist when the form actually submitted a valid value — a save
        // from another tab may not carry the field and we don't want to
        // silently reset the mode back to 'auto' on those.
        if (isset($data['transactional_mode'])
            && in_array($data['transactional_mode'], ['auto', 'primary_only', 'transactional_only'], true)
        ) {
            $update['transactional_mode'] = $data['transactional_mode'];
        }

        $marketplace->update($update);

        \Log::info('[Settings::save] done', [
            'marketplace_id' => $marketplace->id,
            'saved_stock_alert_threshold' => $settings['stock_alert_threshold'] ?? '(missing)',
            'saved_stock_alert_email' => $settings['stock_alert_email'] ?? '(missing)',
            'saved_admin_orders' => $settings['admin_notifications']['orders_email'] ?? '(missing)',
            'saved_admin_services' => $settings['admin_notifications']['service_orders_email'] ?? '(missing)',
            'saved_smtp_driver' => $currentSmtp['driver'] ?? '(empty)',
            'saved_tx_driver' => $currentTxSmtp['driver'] ?? '(empty)',
            'saved_tx_host' => $currentTxSmtp['host'] ?? '(empty)',
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
     * Merge SMTP fields from the form $data into an existing SMTP config array.
     *
     * Non-destructive: when the driver in $data is empty (which can happen on a
     * save initiated from another tab where the conditional fields haven't fully
     * rehydrated), the existing config is kept as-is. The only way to clear the
     * config is the explicit Clear button (or a manual UPDATE in DB).
     */
    private function mergeSmtpFromFormData(array $existing, array $data, string $prefix): array
    {
        $incomingDriver = $data[$prefix . 'driver'] ?? null;

        // Defensive: never wipe an existing config because the form happened to
        // submit an empty driver. Only patch if a real driver value is present.
        if (!filled($incomingDriver)) {
            return $existing;
        }

        $merged = $existing;
        $merged['driver'] = $incomingDriver;

        // Common across all providers
        if (array_key_exists($prefix . 'from_address', $data) && filled($data[$prefix . 'from_address'])) {
            $merged['from_address'] = $data[$prefix . 'from_address'];
        }
        if (array_key_exists($prefix . 'from_name', $data) && filled($data[$prefix . 'from_name'])) {
            $merged['from_name'] = $data[$prefix . 'from_name'];
        }

        // SMTP-specific
        if (!empty($data[$prefix . 'host'])) $merged['host'] = $data[$prefix . 'host'];
        if (!empty($data[$prefix . 'port'])) $merged['port'] = $data[$prefix . 'port'];
        if (array_key_exists($prefix . 'username', $data) && filled($data[$prefix . 'username'])) {
            $merged['username'] = $data[$prefix . 'username'];
        }
        if (!empty($data[$prefix . 'password'])) $merged['password'] = encrypt($data[$prefix . 'password']);
        if (isset($data[$prefix . 'encryption'])) $merged['encryption'] = $data[$prefix . 'encryption'];

        // API-based providers (Brevo, Postmark, SendGrid, SES, Mailgun)
        if (!empty($data[$prefix . 'api_key'])) $merged['api_key'] = encrypt($data[$prefix . 'api_key']);
        if (!empty($data[$prefix . 'api_secret'])) $merged['api_secret'] = encrypt($data[$prefix . 'api_secret']);

        // Mailgun / SES specific
        if (!empty($data[$prefix . 'domain'])) $merged['domain'] = $data[$prefix . 'domain'];
        if (!empty($data[$prefix . 'region'])) $merged['region'] = $data[$prefix . 'region'];

        return $merged;
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

    /**
     * Action handler for the Geo Catalog Importer section in
     * Personalization. Reads the picked country from the form state and
     * delegates to CountryGeoImporter — its DB-transaction wrapper makes
     * a half-broken run impossible.
     */
    public function runGeoImport(): void
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) {
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('No marketplace context')
                ->body('Could not determine which marketplace to import for. Try the marketplace switcher first.')
                ->send();
            return;
        }

        $country = $this->data['geo_import_country'] ?? null;
        if (!$country) {
            \Filament\Notifications\Notification::make()
                ->warning()
                ->title('Pick a country first')
                ->body('Select a country from the dropdown above before running the import.')
                ->send();
            return;
        }

        try {
            $stats = app(\App\Services\Geo\CountryGeoImporter::class)
                ->importCountry($marketplace->id, $country);

            $delta = $stats['delta'];
            \Filament\Notifications\Notification::make()
                ->success()
                ->title("Geo import for {$stats['country']} complete")
                ->body(sprintf(
                    'Regions: %s → %s · Counties: %s → %s · Cities: %s → %s (Δ %s, %s, %s)',
                    $stats['before']['regions'],
                    $stats['after']['regions'],
                    $stats['before']['counties'],
                    $stats['after']['counties'],
                    $stats['before']['cities'],
                    $stats['after']['cities'],
                    $delta['regions']  >= 0 ? "+{$delta['regions']}"  : $delta['regions'],
                    $delta['counties'] >= 0 ? "+{$delta['counties']}" : $delta['counties'],
                    $delta['cities']   >= 0 ? "+{$delta['cities']}"   : $delta['cities'],
                ))
                ->persistent()
                ->send();

            // Refresh the placeholder so the next render shows the new
            // counts immediately instead of waiting for a manual reload.
            $this->dispatch('$refresh');
        } catch (\Throwable $e) {
            \Log::error('[Settings::runGeoImport] failed', [
                'marketplace_id' => $marketplace->id,
                'country' => $country,
                'error' => $e->getMessage(),
            ]);
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('Geo import failed')
                ->body($e->getMessage())
                ->persistent()
                ->send();
        }
    }
}

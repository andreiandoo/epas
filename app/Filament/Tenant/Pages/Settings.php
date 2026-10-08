<?php

namespace App\Filament\Tenant\Pages;

use BackedEnum;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Illuminate\Support\Str;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Collection;

class Settings extends Page
{
    use Forms\Concerns\InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Settings';

    public static function getNavigationLabel(): string
    {
        return __('Settings');
    }
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('Settings');
    }
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.tenant.pages.settings';

    public ?array $data = [];
    public Collection $domains;

    public function mount(): void
    {
        $tenant = auth()->user()->tenant;

        // Load domains
        $this->domains = $tenant ? $tenant->domains()->orderBy('is_primary', 'desc')->orderBy('created_at', 'desc')->get() : collect();

        if ($tenant) {
            $settings = $tenant->settings ?? [];

            $this->form->fill([
                // Business Details
                'company_name' => $tenant->company_name,
                'cui' => $tenant->cui,
                'reg_com' => $tenant->reg_com,
                'vat_payer' => (bool) $tenant->vat_payer,
                'tax_display_mode' => $tenant->tax_display_mode ?? 'included',
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
                'currency' => $tenant->currency ?? 'EUR',
                'ticket_series_prefix' => $tenant->ticket_series_prefix ?? '',

                // Personalization
                'site_title' => $settings['site_title'] ?? $tenant->public_name ?? $tenant->name ?? '',
                // Language is set in Core Admin (Tenant Edit page, not here)
                // 'site_language' => $settings['site_language'] ?? 'en',
                'logo' => $settings['branding']['logo'] ?? null,
                'favicon' => $settings['branding']['favicon'] ?? null,
                'site_description' => $settings['site_description'] ?? '',
                'site_tagline' => $settings['site_tagline'] ?? '',
                'ticket_terms' => $tenant->ticket_terms ?? '',
                'primary_color' => $settings['theme']['primary_color'] ?? '#3B82F6',
                'secondary_color' => $settings['theme']['secondary_color'] ?? '#1E40AF',
                'site_template' => $settings['site_template'] ?? 'default',

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

                // Mail Settings
                'mail_driver' => $settings['mail']['driver'] ?? '',
                'mail_host' => $settings['mail']['host'] ?? '',
                'mail_port' => $settings['mail']['port'] ?? '',
                'mail_username' => $settings['mail']['username'] ?? '',
                'mail_password' => '', // Never load password from DB for security
                'mail_api_key' => '', // Never load API key from DB for security
                'mail_api_secret' => '', // Never load secret from DB for security
                'mail_encryption' => $settings['mail']['encryption'] ?? '',
                'mail_from_address' => $settings['mail']['from_address'] ?? '',
                'mail_from_name' => $settings['mail']['from_name'] ?? '',
                'mail_domain' => $settings['mail']['domain'] ?? '',
                'mail_region' => $settings['mail']['region'] ?? '',

                // Payment processing fee (settings.payment_fees)
                'fiscal_vat_rate' => isset($settings['fiscal']['vat_rate']) ? (float) $settings['fiscal']['vat_rate'] : 21,
                'payment_fee_pass' => ! empty($settings['payment_fees']['pass_to_customer']),
                'payment_fee_percent' => round((float) ($settings['payment_fees']['percent_rate'] ?? 0), 2),
                'payment_fee_fixed' => round(((int) ($settings['payment_fees']['fixed_cents'] ?? 0)) / 100, 2),
            ]);
        }
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                SC\Tabs::make('Settings')
                    ->tabs([
                        SC\Tabs\Tab::make(__('Business Details'))
                            ->icon('heroicon-o-building-office')
                            ->schema([
                                SC\Section::make(__('Company Information'))
                                    ->schema([
                                        Forms\Components\TextInput::make('company_name')
                                            ->label(__('Legal Company Name'))
                                            ->disabled()
                                            ->dehydrated(true)
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('cui')
                                            ->label(__('CUI / VAT Number'))
                                            ->disabled()
                                            ->dehydrated(true)
                                            ->maxLength(50),

                                        Forms\Components\TextInput::make('reg_com')
                                            ->label(__('Trade Register'))
                                            ->disabled()
                                            ->dehydrated(true)
                                            ->maxLength(50),

                                        Forms\Components\Toggle::make('vat_payer')
                                            ->label(__('Platitor TVA'))
                                            ->helperText(__('Bifati daca sunteti inregistrat ca platitor de TVA. Aceasta afecteaza calculul taxelor si afisarea TVA-ului in checkout.'))
                                            ->onColor('success')
                                            ->offColor('gray')
                                            ->live(),

                                        Forms\Components\TextInput::make('fiscal_vat_rate')
                                            ->label('Cota de TVA la bilete (%)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(30)
                                            ->step(0.01)
                                            ->default(21)
                                            ->suffix('%')
                                            ->helperText('Folosită în declarația de impozit pe spectacole: impozitul se calculează la valoarea fără TVA.')
                                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => (bool) $get('vat_payer')),

                                        Forms\Components\Select::make('tax_display_mode')
                                            ->label('Modul de afișare taxe')
                                            ->options([
                                                'included' => 'Incluse în preț (prețul afișat include taxele)',
                                                'added' => 'Adăugate la preț (taxele se adaugă la checkout)',
                                            ])
                                            ->default('included')
                                            ->helperText('Alegeți cum vor fi afișate taxele pe website: incluse în prețul biletului sau adăugate separat la checkout.')
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Aceasta setare afecteaza modul in care clientii vad preturile pe website.'),

                                        Forms\Components\TextInput::make('bank_name')
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('bank_account')
                                            ->label('IBAN')
                                            ->maxLength(50),

                                        Forms\Components\Select::make('currency')
                                            ->label(__('Currency'))
                                            ->options([
                                                'RON' => __('RON - Romanian Leu'),
                                                'EUR' => __('EUR - Euro'),
                                                'USD' => __('USD - US Dollar'),
                                                'GBP' => __('GBP - British Pound'),
                                            ])
                                            ->default('EUR')
                                            ->required()
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Default currency for sales and invoices'),

                                        Forms\Components\TextInput::make('ticket_series_prefix')
                                            ->label('Prefix serie bilete')
                                            ->placeholder(__('ex: TNB'))
                                            ->maxLength(20)
                                            ->rule('alpha_dash')
                                            ->helperText('Prefixul folosit la seriile de bilete (ex: TNB-12-00001). Lasă gol pentru implicit.')
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Se aplică la evenimentele create după setare.'),
                                    ])->columns(3),

                                SC\Section::make(__('Address'))
                                    ->schema([
                                        Forms\Components\TextInput::make('address')
                                            ->label(__('Street Address'))
                                            ->maxLength(255)
                                            ->columnSpanFull(),

                                        Forms\Components\TextInput::make('city')
                                            ->maxLength(100),

                                        Forms\Components\TextInput::make('state')
                                            ->label(__('State / County'))
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

                                        Forms\Components\TextInput::make('website')
                                            ->url()
                                            ->maxLength(255),
                                    ])->columns(3),
                            ]),

                        SC\Tabs\Tab::make(__('Personalization'))
                            ->icon('heroicon-o-paint-brush')
                            ->schema([
                                SC\Section::make(__('Branding'))
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

                                SC\Section::make(__('Site Information'))
                                    ->schema([
                                        Forms\Components\TextInput::make('site_title')
                                            ->label(__('Site Title'))
                                            ->required()
                                            ->maxLength(255)
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'The name of your site displayed in browser tab and header'),

                                        // Language is set in Core Admin (Tenant Edit page)
                                        // Forms\Components\Select::make('site_language')
                                        //     ->label(__('Site Language'))
                                        //     ->options([
                                        //         'en' => __('English'),
                                        //         'ro' => 'Romanian (Română)',
                                        //     ])
                                        //     ->default('en')
                                        //     ->required()
                                        //     ->hintIcon('heroicon-o-information-circle', tooltip: 'Primary language for your public site'),

                                        Forms\Components\Textarea::make('site_description')
                                            ->label(__('Site Description'))
                                            ->rows(3)
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Brief description for SEO and social sharing')
                                            ->maxLength(500),

                                        Forms\Components\TextInput::make('site_tagline')
                                            ->label(__('Site Tagline'))
                                            ->maxLength(255)
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Short tagline displayed on the site'),

                                        Forms\Components\RichEditor::make('ticket_terms')
                                            ->label(__('Ticket Terms'))
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

                                SC\Section::make(__('Theme & Colors'))
                                    ->schema([
                                        Forms\Components\ColorPicker::make('primary_color')
                                            ->label(__('Primary Color')),

                                        Forms\Components\ColorPicker::make('secondary_color')
                                            ->label(__('Secondary Color')),

                                        Forms\Components\Select::make('site_template')
                                            ->label(__('Site Template'))
                                            ->options([
                                                'default' => __('Default'),
                                                'modern' => 'Modern',
                                                'sleek' => __('Sleek (Minimalist)'),
                                                'theater' => __('Theater (Dark)'),
                                                'pub' => __('Pub (Warm)'),
                                            ])
                                            ->default('default'),
                                    ])->columns(3),
                            ]),

                        SC\Tabs\Tab::make(__('Legal Pages'))
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                SC\Section::make(__('Terms & Conditions'))
                                    ->description(__('Content displayed on your Terms & Conditions page'))
                                    ->schema([
                                        Forms\Components\TextInput::make('terms_title')
                                            ->label(__('Page Title'))
                                            ->default('Terms & Conditions')
                                            ->maxLength(255)
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'The title displayed on the Terms page'),

                                        Forms\Components\RichEditor::make('terms_content')
                                            ->label(__('Content'))
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

                                SC\Section::make(__('Privacy Policy'))
                                    ->description(__('Content displayed on your Privacy Policy page'))
                                    ->schema([
                                        Forms\Components\TextInput::make('privacy_title')
                                            ->label(__('Page Title'))
                                            ->default('Privacy Policy')
                                            ->maxLength(255)
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'The title displayed on the Privacy page'),

                                        Forms\Components\RichEditor::make('privacy_content')
                                            ->label(__('Content'))
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

                        SC\Tabs\Tab::make(__('Links'))
                            ->icon('heroicon-o-link')
                            ->schema([
                                SC\Section::make(__('Social Media Links'))
                                    ->description(__('Add links to your social media profiles. Icons will appear in the footer.'))
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


                        SC\Tabs\Tab::make(__('Emails'))
                            ->icon('heroicon-o-envelope')
                            ->schema([
                                SC\Section::make(__('Email Configuration'))
                                    ->description(__('Configure custom mail settings for sending emails. Leave empty to use platform default.'))
                                    ->schema([
                                        Forms\Components\Select::make('mail_driver')
                                            ->label(__('Mail Provider'))
                                            ->options([
                                                '' => __('Use Platform Default'),
                                                'smtp' => __('SMTP (Generic)'),
                                                'brevo' => 'Brevo (Sendinblue)',
                                                'postmark' => 'Postmark',
                                                'mailgun' => 'Mailgun',
                                                'sendgrid' => 'SendGrid',
                                                'ses' => 'Amazon SES',
                                                'gmail' => 'Gmail',
                                                'outlook' => 'Microsoft 365 / Outlook',
                                            ])
                                            ->placeholder(__('Select mail provider'))
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
                                                ->label(__('Test Email Connection'))
                                                ->icon('heroicon-o-paper-airplane')
                                                ->color('gray')
                                                ->action(function () {
                                                    // TODO: Implement test email
                                                    \Filament\Notifications\Notification::make()
                                                        ->info()
                                                        ->title(__('Test email feature'))
                                                        ->body(__('Test email functionality coming soon.'))
                                                        ->send();
                                                }),
                                        ])
                                        ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => filled($get('mail_driver')))
                                        ->columnSpanFull(),
                                    ])->columns(2),
                            ]),

                        SC\Tabs\Tab::make('Plăți')
                            ->icon('heroicon-o-credit-card')
                            ->schema([
                                SC\Section::make('Taxa de procesare a plății')
                                    ->description('Alege cine suportă taxa percepută de procesatorul de plăți (Stripe, Netopia etc.) pentru fiecare comandă.')
                                    ->schema([
                                        Forms\Components\Toggle::make('payment_fee_pass')
                                            ->label('Mută taxa procesatorului la cumpărător')
                                            ->helperText('Când este activă, taxa se adaugă la totalul comenzii ca o linie separată, pe care cumpărătorul o vede în coș și la checkout. Când este oprită, taxa rămâne în sarcina organizatorului, iar cumpărătorul plătește doar prețul biletelor.')
                                            ->default(false)
                                            ->onColor('success')
                                            ->offColor('gray')
                                            ->live()
                                            ->columnSpanFull(),

                                        Forms\Components\TextInput::make('payment_fee_percent')
                                            ->label('Procent (%)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(20)
                                            ->step(0.01)
                                            ->default(0)
                                            ->suffix('%')
                                            ->helperText('Procentul din contractul tău cu procesatorul (ex: 1.9). Se aplică la valoarea comenzii.')
                                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => (bool) $get('payment_fee_pass')),

                                        Forms\Components\TextInput::make('payment_fee_fixed')
                                            ->label('Sumă fixă (lei)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(20)
                                            ->step(0.01)
                                            ->default(0)
                                            ->suffix('lei')
                                            ->helperText('Suma fixă per comandă din contractul tău cu procesatorul (ex: 1.00). Lasă 0 dacă nu ai una.')
                                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => (bool) $get('payment_fee_pass')),

                                        Forms\Components\Placeholder::make('payment_fee_info')
                                            ->label('')
                                            ->content(new HtmlString('
                                                <div class="p-3 text-sm text-gray-600 rounded-lg dark:text-gray-400 bg-blue-50 dark:bg-blue-900/20">
                                                    Introdu exact tarifele din contractul tău cu procesatorul de plăți. Taxa afișată cumpărătorului se calculează astfel: valoarea comenzii × procent, la care se adaugă suma fixă.
                                                </div>
                                            '))
                                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => (bool) $get('payment_fee_pass'))
                                            ->columnSpanFull(),
                                    ])->columns(2),
                            ]),

                        SC\Tabs\Tab::make(__('Domains'))
                            ->icon('heroicon-o-globe-alt')
                            ->schema([
                                Forms\Components\Placeholder::make('domains_list')
                                    ->label('')
                                    ->content(fn () => new HtmlString(view('filament.tenant.components.domains-list', ['domains' => $this->domains])->render())),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
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
            'vat_payer' => (bool) ($data['vat_payer'] ?? false),
            'tax_display_mode' => $data['tax_display_mode'] ?? 'included',
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
            'currency' => $data['currency'],
            'ticket_terms' => $data['ticket_terms'],
            'ticket_series_prefix' => $data['ticket_series_prefix'] ?: null,
        ]);

        // Update settings JSON
        $settings = $tenant->settings ?? [];
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

        // Update mail settings
        $mailSettings = $settings['mail'] ?? [];

        // Always save driver (even if empty, to clear settings)
        $mailSettings['driver'] = $data['mail_driver'] ?? '';

        // Only save settings if a driver is selected
        if (!empty($data['mail_driver'])) {
            // Common fields for all providers
            if (!empty($data['mail_from_address'])) {
                $mailSettings['from_address'] = $data['mail_from_address'];
            }
            if (!empty($data['mail_from_name'])) {
                $mailSettings['from_name'] = $data['mail_from_name'];
            }

            // SMTP-specific fields
            if (!empty($data['mail_host'])) {
                $mailSettings['host'] = $data['mail_host'];
            }
            if (!empty($data['mail_port'])) {
                $mailSettings['port'] = $data['mail_port'];
            }
            if (!empty($data['mail_username'])) {
                $mailSettings['username'] = $data['mail_username'];
            }
            if (!empty($data['mail_password'])) {
                $mailSettings['password'] = encrypt($data['mail_password']);
            }
            if (isset($data['mail_encryption'])) {
                $mailSettings['encryption'] = $data['mail_encryption'];
            }

            // API-based providers
            if (!empty($data['mail_api_key'])) {
                $mailSettings['api_key'] = encrypt($data['mail_api_key']);
            }
            if (!empty($data['mail_api_secret'])) {
                $mailSettings['api_secret'] = encrypt($data['mail_api_secret']);
            }

            // Mailgun/SES specific
            if (!empty($data['mail_domain'])) {
                $mailSettings['domain'] = $data['mail_domain'];
            }
            if (!empty($data['mail_region'])) {
                $mailSettings['region'] = $data['mail_region'];
            }
        }

        $settings['mail'] = $mailSettings;

        // Payment processing fee: only the payment_fees key is touched, other keys inside it
        // (e.g. provider) are preserved. Hidden rate fields are not dehydrated when the toggle
        // is off, so the previously stored rates are kept in that case.
        $existingFees = is_array($settings['payment_fees'] ?? null) ? $settings['payment_fees'] : [];
        $feePercent = array_key_exists('payment_fee_percent', $data)
            ? (float) ($data['payment_fee_percent'] ?? 0)
            : (float) ($existingFees['percent_rate'] ?? 0);
        $feeFixedCents = array_key_exists('payment_fee_fixed', $data)
            ? (int) round(((float) ($data['payment_fee_fixed'] ?? 0)) * 100)
            : (int) ($existingFees['fixed_cents'] ?? 0);

        // Cota de TVA (câmpul e ascuns când tenantul nu e plătitor: atunci păstrăm valoarea veche)
        if (array_key_exists('fiscal_vat_rate', $data) && $data['fiscal_vat_rate'] !== null && $data['fiscal_vat_rate'] !== '') {
            $settings['fiscal'] = array_merge(is_array($settings['fiscal'] ?? null) ? $settings['fiscal'] : [], [
                'vat_rate' => round(min(30, max(0, (float) $data['fiscal_vat_rate'])), 2),
            ]);
        }

        $settings['payment_fees'] = array_merge($existingFees, [
            'pass_to_customer' => (bool) ($data['payment_fee_pass'] ?? false),
            'percent_rate' => round(min(20, max(0, $feePercent)), 2),
            'fixed_cents' => min(2000, max(0, $feeFixedCents)),
        ]);

        $tenant->update([
            'settings' => $settings,
        ]);

        // The public API caches the resolved tenant for 30 minutes (ResolvesTenant);
        // drop those entries so the storefront picks the change up immediately.
        try {
            \Illuminate\Support\Facades\Cache::forget("tenant_{$tenant->id}");

            foreach ($tenant->domains()->pluck('domain') as $domainName) {
                if (filled($domainName)) {
                    \Illuminate\Support\Facades\Cache::forget("domain_tenant_{$domainName}");
                }
            }
        } catch (\Throwable $e) {
            // A cache failure must never break saving the settings.
        }

        Notification::make()
            ->success()
            ->title(__('Settings saved'))
            ->body(__('Your settings have been updated successfully.'))
            ->send();
    }

    public function getTitle(): string
    {
        return __('Settings');
    }

    /**
     * SMTP provider fields
     */
    private function getSmtpFields(): array
    {
        return [
            SC\Grid::make(2)->schema([
                Forms\Components\TextInput::make('mail_host')
                    ->label('SMTP Host')
                    ->placeholder('smtp.example.com')
                    ->maxLength(255)
                    ->required()
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Your mail server hostname'),

                Forms\Components\TextInput::make('mail_port')
                    ->label('SMTP Port')
                    ->numeric()
                    ->default(587)
                    ->placeholder('587')
                    ->required()
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Usually 587 for TLS, 465 for SSL'),

                Forms\Components\TextInput::make('mail_username')
                    ->label(__('Username'))
                    ->maxLength(255)
                    ->placeholder('your-username')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'SMTP authentication username'),

                Forms\Components\TextInput::make('mail_password')
                    ->label(__('Password'))
                    ->password()
                    ->maxLength(255)
                    ->autocomplete('new-password')
                    ->placeholder('••••••••')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Leave empty to keep existing')
                    ->dehydrated(fn ($state) => filled($state)),

                Forms\Components\Select::make('mail_encryption')
                    ->label(__('Encryption'))
                    ->options([
                        'tls' => __('TLS (Recommended)'),
                        'ssl' => 'SSL',
                        '' => __('None'),
                    ])
                    ->default('tls')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Security protocol'),

                Forms\Components\TextInput::make('mail_from_address')
                    ->label(__('From Email'))
                    ->email()
                    ->maxLength(255)
                    ->required()
                    ->placeholder('noreply@yourdomain.com')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Sender email address'),

                Forms\Components\TextInput::make('mail_from_name')
                    ->label(__('From Name'))
                    ->maxLength(255)
                    ->required()
                    ->placeholder(__('Your Company'))
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Sender display name'),
            ]),
        ];
    }

    /**
     * Brevo (Sendinblue) provider fields
     */
    private function getBrevoFields(): array
    {
        return [
            SC\Grid::make(2)->schema([
                Forms\Components\TextInput::make('mail_api_key')
                    ->label(__('API Key'))
                    ->password()
                    ->maxLength(255)
                    ->autocomplete('new-password')
                    ->placeholder('xkeysib-...')
                    ->required()
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Your Brevo API key (v3)')
                    ->dehydrated(fn ($state) => filled($state))
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('mail_from_address')
                    ->label(__('From Email'))
                    ->email()
                    ->maxLength(255)
                    ->required()
                    ->placeholder('noreply@yourdomain.com')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Must be verified in Brevo'),

                Forms\Components\TextInput::make('mail_from_name')
                    ->label(__('From Name'))
                    ->maxLength(255)
                    ->required()
                    ->placeholder(__('Your Company'))
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Sender display name'),
            ]),
        ];
    }

    /**
     * Postmark provider fields
     */
    private function getPostmarkFields(): array
    {
        return [
            SC\Grid::make(2)->schema([
                Forms\Components\TextInput::make('mail_api_key')
                    ->label(__('Server API Token'))
                    ->password()
                    ->maxLength(255)
                    ->autocomplete('new-password')
                    ->placeholder('xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx')
                    ->required()
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Found in Server → API Tokens')
                    ->dehydrated(fn ($state) => filled($state))
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('mail_from_address')
                    ->label(__('From Email'))
                    ->email()
                    ->maxLength(255)
                    ->required()
                    ->placeholder('noreply@yourdomain.com')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Must be verified sender signature'),

                Forms\Components\TextInput::make('mail_from_name')
                    ->label(__('From Name'))
                    ->maxLength(255)
                    ->required()
                    ->placeholder(__('Your Company'))
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Sender display name'),
            ]),
        ];
    }

    /**
     * Mailgun provider fields
     */
    private function getMailgunFields(): array
    {
        return [
            SC\Grid::make(2)->schema([
                Forms\Components\TextInput::make('mail_api_key')
                    ->label(__('API Key'))
                    ->password()
                    ->maxLength(255)
                    ->autocomplete('new-password')
                    ->placeholder('key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx')
                    ->required()
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Private API key from Mailgun')
                    ->dehydrated(fn ($state) => filled($state)),

                Forms\Components\TextInput::make('mail_domain')
                    ->label(__('Sending Domain'))
                    ->maxLength(255)
                    ->placeholder('mg.yourdomain.com')
                    ->required()
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Verified sending domain'),

                Forms\Components\Select::make('mail_region')
                    ->label(__('Region'))
                    ->options([
                        'us' => __('US (api.mailgun.net)'),
                        'eu' => __('EU (api.eu.mailgun.net)'),
                    ])
                    ->default('us')
                    ->required()
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Mailgun API region'),

                Forms\Components\TextInput::make('mail_from_address')
                    ->label(__('From Email'))
                    ->email()
                    ->maxLength(255)
                    ->required()
                    ->placeholder('noreply@mg.yourdomain.com')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Must use verified domain'),

                Forms\Components\TextInput::make('mail_from_name')
                    ->label(__('From Name'))
                    ->maxLength(255)
                    ->required()
                    ->placeholder(__('Your Company'))
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Sender display name'),
            ]),
        ];
    }

    /**
     * SendGrid provider fields
     */
    private function getSendgridFields(): array
    {
        return [
            SC\Grid::make(2)->schema([
                Forms\Components\TextInput::make('mail_api_key')
                    ->label(__('API Key'))
                    ->password()
                    ->maxLength(255)
                    ->autocomplete('new-password')
                    ->placeholder('SG.xxxxxxxxxxxxxxxxxxxx')
                    ->required()
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'SendGrid API key with Mail Send permission')
                    ->dehydrated(fn ($state) => filled($state))
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('mail_from_address')
                    ->label(__('From Email'))
                    ->email()
                    ->maxLength(255)
                    ->required()
                    ->placeholder('noreply@yourdomain.com')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Must be verified sender'),

                Forms\Components\TextInput::make('mail_from_name')
                    ->label(__('From Name'))
                    ->maxLength(255)
                    ->required()
                    ->placeholder(__('Your Company'))
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Sender display name'),
            ]),
        ];
    }

    /**
     * Amazon SES provider fields
     */
    private function getSesFields(): array
    {
        return [
            SC\Grid::make(2)->schema([
                Forms\Components\TextInput::make('mail_api_key')
                    ->label(__('Access Key ID'))
                    ->password()
                    ->maxLength(255)
                    ->autocomplete('new-password')
                    ->placeholder('AKIAIOSFODNN7EXAMPLE')
                    ->required()
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'AWS IAM access key')
                    ->dehydrated(fn ($state) => filled($state)),

                Forms\Components\TextInput::make('mail_api_secret')
                    ->label(__('Secret Access Key'))
                    ->password()
                    ->maxLength(255)
                    ->autocomplete('new-password')
                    ->placeholder('••••••••')
                    ->required()
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'AWS IAM secret key')
                    ->dehydrated(fn ($state) => filled($state)),

                Forms\Components\Select::make('mail_region')
                    ->label(__('AWS Region'))
                    ->options([
                        'us-east-1' => __('US East (N. Virginia)'),
                        'us-east-2' => __('US East (Ohio)'),
                        'us-west-1' => __('US West (N. California)'),
                        'us-west-2' => __('US West (Oregon)'),
                        'eu-west-1' => __('EU (Ireland)'),
                        'eu-west-2' => __('EU (London)'),
                        'eu-west-3' => __('EU (Paris)'),
                        'eu-central-1' => __('EU (Frankfurt)'),
                    ])
                    ->required()
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'SES region'),

                Forms\Components\TextInput::make('mail_from_address')
                    ->label(__('From Email'))
                    ->email()
                    ->maxLength(255)
                    ->required()
                    ->placeholder('noreply@yourdomain.com')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Verified email or domain'),

                Forms\Components\TextInput::make('mail_from_name')
                    ->label(__('From Name'))
                    ->maxLength(255)
                    ->required()
                    ->placeholder(__('Your Company'))
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Sender display name'),
            ]),
        ];
    }

    /**
     * Gmail provider fields
     */
    private function getGmailFields(): array
    {
        return [
            SC\Grid::make(2)->schema([
                Forms\Components\Placeholder::make('gmail_info')
                    ->label('')
                    ->content(new HtmlString('
                        <div class="p-3 text-sm text-gray-600 rounded-lg dark:text-gray-400 bg-blue-50 dark:bg-blue-900/20">
                            <strong>Important:</strong> Use an App Password, not your regular Gmail password.
                            <a href="https://myaccount.google.com/apppasswords" target="_blank" class="underline text-primary-600">Generate App Password</a>
                        </div>
                    '))
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('mail_username')
                    ->label(__('Gmail Address'))
                    ->email()
                    ->maxLength(255)
                    ->placeholder('your-email@gmail.com')
                    ->required()
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Your Gmail address'),

                Forms\Components\TextInput::make('mail_password')
                    ->label(__('App Password'))
                    ->password()
                    ->maxLength(255)
                    ->autocomplete('new-password')
                    ->placeholder('xxxx xxxx xxxx xxxx')
                    ->required()
                    ->hintIcon('heroicon-o-information-circle', tooltip: '16-character app password')
                    ->dehydrated(fn ($state) => filled($state)),

                Forms\Components\TextInput::make('mail_from_address')
                    ->label(__('From Email'))
                    ->email()
                    ->maxLength(255)
                    ->placeholder('your-email@gmail.com')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Usually same as Gmail address'),

                Forms\Components\TextInput::make('mail_from_name')
                    ->label(__('From Name'))
                    ->maxLength(255)
                    ->required()
                    ->placeholder(__('Your Company'))
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Sender display name'),
            ]),
        ];
    }

    /**
     * Microsoft 365 / Outlook provider fields
     */
    private function getOutlookFields(): array
    {
        return [
            SC\Grid::make(2)->schema([
                Forms\Components\Placeholder::make('outlook_info')
                    ->label('')
                    ->content(new HtmlString('
                        <div class="p-3 text-sm text-gray-600 rounded-lg dark:text-gray-400 bg-blue-50 dark:bg-blue-900/20">
                            <strong>Important:</strong> Use an App Password if 2FA is enabled.
                            <a href="https://account.live.com/proofs/AppPassword" target="_blank" class="underline text-primary-600">Generate App Password</a>
                        </div>
                    '))
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('mail_username')
                    ->label(__('Email Address'))
                    ->email()
                    ->maxLength(255)
                    ->placeholder('your-email@outlook.com')
                    ->required()
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Your Microsoft 365 / Outlook email'),

                Forms\Components\TextInput::make('mail_password')
                    ->label(__('Password / App Password'))
                    ->password()
                    ->maxLength(255)
                    ->autocomplete('new-password')
                    ->placeholder('••••••••')
                    ->required()
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Account or app password')
                    ->dehydrated(fn ($state) => filled($state)),

                Forms\Components\TextInput::make('mail_from_address')
                    ->label(__('From Email'))
                    ->email()
                    ->maxLength(255)
                    ->placeholder('your-email@outlook.com')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Usually same as login email'),

                Forms\Components\TextInput::make('mail_from_name')
                    ->label(__('From Name'))
                    ->maxLength(255)
                    ->required()
                    ->placeholder(__('Your Company'))
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Sender display name'),
            ]),
        ];
    }
}

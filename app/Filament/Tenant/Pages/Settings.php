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
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
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
                                            ->disk('public')
                                            ->visibility('public')
                                            ->maxSize(2048)
                                            ->helperText('Recommended: 200x60px, PNG or SVG'),

                                        Forms\Components\FileUpload::make('favicon')
                                            ->label('Favicon')
                                            ->image()
                                            ->directory('tenant-branding')
                                            ->disk('public')
                                            ->visibility('public')
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

                                        // Language is set in Core Admin (Tenant Edit page)
                                        // Forms\Components\Select::make('site_language')
                                        //     ->label('Site Language')
                                        //     ->options([
                                        //         'en' => 'English',
                                        //         'ro' => 'Romanian (Română)',
                                        //     ])
                                        //     ->default('en')
                                        //     ->required()
                                        //     ->helperText('Primary language for your public site'),

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
                                        Forms\Components\TextInput::make('terms_title')
                                            ->label('Page Title')
                                            ->default('Terms & Conditions')
                                            ->maxLength(255)
                                            ->helperText('The title displayed on the Terms page'),

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
                                            ->helperText('The title displayed on the Privacy page'),

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
                                            ->options([
                                                '' => 'Use Platform Default',
                                                'smtp' => 'SMTP (Generic)',
                                                'brevo' => 'Brevo (Sendinblue)',
                                                'postmark' => 'Postmark',
                                                'mailgun' => 'Mailgun',
                                                'sendgrid' => 'SendGrid',
                                                'ses' => 'Amazon SES',
                                                'gmail' => 'Gmail',
                                                'outlook' => 'Microsoft 365 / Outlook',
                                            ])
                                            ->placeholder('Select mail provider')
                                            ->live()
                                            ->afterStateUpdated(fn (Forms\Components\Select $component) => $component
                                                ->getContainer()
                                                ->getComponent('mailProviderFields')
                                                ?->getChildComponentContainer()
                                                ->fill())
                                            ->helperText('Select your email service provider')
                                            ->columnSpanFull(),

                                        // Conditional fields based on mail provider
                                        SC\Group::make()
                                            ->key('mailProviderFields')
                                            ->schema(fn (Forms\Get $get): array => match ($get('mail_driver')) {
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
                                            SC\Actions\Action::make('testConnection')
                                                ->label('Test Email Connection')
                                                ->icon('heroicon-o-paper-airplane')
                                                ->color('gray')
                                                ->action(function () {
                                                    // TODO: Implement test email
                                                    \Filament\Notifications\Notification::make()
                                                        ->info()
                                                        ->title('Test email feature')
                                                        ->body('Test email functionality coming soon.')
                                                        ->send();
                                                }),
                                        ])
                                        ->visible(fn (Forms\Get $get): bool => filled($get('mail_driver')))
                                        ->columnSpanFull(),
                                    ])->columns(2),
                            ]),

                        SC\Tabs\Tab::make('Domains')
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

        $tenant->update([
            'settings' => $settings,
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
     * SMTP provider fields
     */
    private function getSmtpFields(): array
    {
        return [
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('mail_host')
                    ->label('SMTP Host')
                    ->placeholder('smtp.example.com')
                    ->maxLength(255)
                    ->required()
                    ->helperText('Your mail server hostname'),

                Forms\Components\TextInput::make('mail_port')
                    ->label('SMTP Port')
                    ->numeric()
                    ->default(587)
                    ->placeholder('587')
                    ->required()
                    ->helperText('Usually 587 for TLS, 465 for SSL'),

                Forms\Components\TextInput::make('mail_username')
                    ->label('Username')
                    ->maxLength(255)
                    ->placeholder('your-username')
                    ->helperText('SMTP authentication username'),

                Forms\Components\TextInput::make('mail_password')
                    ->label('Password')
                    ->password()
                    ->maxLength(255)
                    ->autocomplete('new-password')
                    ->placeholder('••••••••')
                    ->helperText('Leave empty to keep existing')
                    ->dehydrated(fn ($state) => filled($state)),

                Forms\Components\Select::make('mail_encryption')
                    ->label('Encryption')
                    ->options([
                        'tls' => 'TLS (Recommended)',
                        'ssl' => 'SSL',
                        '' => 'None',
                    ])
                    ->default('tls')
                    ->helperText('Security protocol'),

                Forms\Components\TextInput::make('mail_from_address')
                    ->label('From Email')
                    ->email()
                    ->maxLength(255)
                    ->required()
                    ->placeholder('noreply@yourdomain.com')
                    ->helperText('Sender email address'),

                Forms\Components\TextInput::make('mail_from_name')
                    ->label('From Name')
                    ->maxLength(255)
                    ->required()
                    ->placeholder('Your Company')
                    ->helperText('Sender display name'),
            ]),
        ];
    }

    /**
     * Brevo (Sendinblue) provider fields
     */
    private function getBrevoFields(): array
    {
        return [
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('mail_api_key')
                    ->label('API Key')
                    ->password()
                    ->maxLength(255)
                    ->autocomplete('new-password')
                    ->placeholder('xkeysib-...')
                    ->required()
                    ->helperText('Your Brevo API key (v3)')
                    ->dehydrated(fn ($state) => filled($state))
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('mail_from_address')
                    ->label('From Email')
                    ->email()
                    ->maxLength(255)
                    ->required()
                    ->placeholder('noreply@yourdomain.com')
                    ->helperText('Must be verified in Brevo'),

                Forms\Components\TextInput::make('mail_from_name')
                    ->label('From Name')
                    ->maxLength(255)
                    ->required()
                    ->placeholder('Your Company')
                    ->helperText('Sender display name'),
            ]),
        ];
    }

    /**
     * Postmark provider fields
     */
    private function getPostmarkFields(): array
    {
        return [
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('mail_api_key')
                    ->label('Server API Token')
                    ->password()
                    ->maxLength(255)
                    ->autocomplete('new-password')
                    ->placeholder('xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx')
                    ->required()
                    ->helperText('Found in Server → API Tokens')
                    ->dehydrated(fn ($state) => filled($state))
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('mail_from_address')
                    ->label('From Email')
                    ->email()
                    ->maxLength(255)
                    ->required()
                    ->placeholder('noreply@yourdomain.com')
                    ->helperText('Must be verified sender signature'),

                Forms\Components\TextInput::make('mail_from_name')
                    ->label('From Name')
                    ->maxLength(255)
                    ->required()
                    ->placeholder('Your Company')
                    ->helperText('Sender display name'),
            ]),
        ];
    }

    /**
     * Mailgun provider fields
     */
    private function getMailgunFields(): array
    {
        return [
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('mail_api_key')
                    ->label('API Key')
                    ->password()
                    ->maxLength(255)
                    ->autocomplete('new-password')
                    ->placeholder('key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx')
                    ->required()
                    ->helperText('Private API key from Mailgun')
                    ->dehydrated(fn ($state) => filled($state)),

                Forms\Components\TextInput::make('mail_domain')
                    ->label('Sending Domain')
                    ->maxLength(255)
                    ->placeholder('mg.yourdomain.com')
                    ->required()
                    ->helperText('Verified sending domain'),

                Forms\Components\Select::make('mail_region')
                    ->label('Region')
                    ->options([
                        'us' => 'US (api.mailgun.net)',
                        'eu' => 'EU (api.eu.mailgun.net)',
                    ])
                    ->default('us')
                    ->required()
                    ->helperText('Mailgun API region'),

                Forms\Components\TextInput::make('mail_from_address')
                    ->label('From Email')
                    ->email()
                    ->maxLength(255)
                    ->required()
                    ->placeholder('noreply@mg.yourdomain.com')
                    ->helperText('Must use verified domain'),

                Forms\Components\TextInput::make('mail_from_name')
                    ->label('From Name')
                    ->maxLength(255)
                    ->required()
                    ->placeholder('Your Company')
                    ->helperText('Sender display name'),
            ]),
        ];
    }

    /**
     * SendGrid provider fields
     */
    private function getSendgridFields(): array
    {
        return [
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('mail_api_key')
                    ->label('API Key')
                    ->password()
                    ->maxLength(255)
                    ->autocomplete('new-password')
                    ->placeholder('SG.xxxxxxxxxxxxxxxxxxxx')
                    ->required()
                    ->helperText('SendGrid API key with Mail Send permission')
                    ->dehydrated(fn ($state) => filled($state))
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('mail_from_address')
                    ->label('From Email')
                    ->email()
                    ->maxLength(255)
                    ->required()
                    ->placeholder('noreply@yourdomain.com')
                    ->helperText('Must be verified sender'),

                Forms\Components\TextInput::make('mail_from_name')
                    ->label('From Name')
                    ->maxLength(255)
                    ->required()
                    ->placeholder('Your Company')
                    ->helperText('Sender display name'),
            ]),
        ];
    }

    /**
     * Amazon SES provider fields
     */
    private function getSesFields(): array
    {
        return [
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('mail_api_key')
                    ->label('Access Key ID')
                    ->password()
                    ->maxLength(255)
                    ->autocomplete('new-password')
                    ->placeholder('AKIAIOSFODNN7EXAMPLE')
                    ->required()
                    ->helperText('AWS IAM access key')
                    ->dehydrated(fn ($state) => filled($state)),

                Forms\Components\TextInput::make('mail_api_secret')
                    ->label('Secret Access Key')
                    ->password()
                    ->maxLength(255)
                    ->autocomplete('new-password')
                    ->placeholder('••••••••')
                    ->required()
                    ->helperText('AWS IAM secret key')
                    ->dehydrated(fn ($state) => filled($state)),

                Forms\Components\Select::make('mail_region')
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
                    ->helperText('SES region'),

                Forms\Components\TextInput::make('mail_from_address')
                    ->label('From Email')
                    ->email()
                    ->maxLength(255)
                    ->required()
                    ->placeholder('noreply@yourdomain.com')
                    ->helperText('Verified email or domain'),

                Forms\Components\TextInput::make('mail_from_name')
                    ->label('From Name')
                    ->maxLength(255)
                    ->required()
                    ->placeholder('Your Company')
                    ->helperText('Sender display name'),
            ]),
        ];
    }

    /**
     * Gmail provider fields
     */
    private function getGmailFields(): array
    {
        return [
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Placeholder::make('gmail_info')
                    ->label('')
                    ->content(new HtmlString('
                        <div class="text-sm text-gray-600 dark:text-gray-400 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                            <strong>Important:</strong> Use an App Password, not your regular Gmail password.
                            <a href="https://myaccount.google.com/apppasswords" target="_blank" class="text-primary-600 underline">Generate App Password</a>
                        </div>
                    '))
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('mail_username')
                    ->label('Gmail Address')
                    ->email()
                    ->maxLength(255)
                    ->placeholder('your-email@gmail.com')
                    ->required()
                    ->helperText('Your Gmail address'),

                Forms\Components\TextInput::make('mail_password')
                    ->label('App Password')
                    ->password()
                    ->maxLength(255)
                    ->autocomplete('new-password')
                    ->placeholder('xxxx xxxx xxxx xxxx')
                    ->required()
                    ->helperText('16-character app password')
                    ->dehydrated(fn ($state) => filled($state)),

                Forms\Components\TextInput::make('mail_from_address')
                    ->label('From Email')
                    ->email()
                    ->maxLength(255)
                    ->placeholder('your-email@gmail.com')
                    ->helperText('Usually same as Gmail address'),

                Forms\Components\TextInput::make('mail_from_name')
                    ->label('From Name')
                    ->maxLength(255)
                    ->required()
                    ->placeholder('Your Company')
                    ->helperText('Sender display name'),
            ]),
        ];
    }

    /**
     * Microsoft 365 / Outlook provider fields
     */
    private function getOutlookFields(): array
    {
        return [
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Placeholder::make('outlook_info')
                    ->label('')
                    ->content(new HtmlString('
                        <div class="text-sm text-gray-600 dark:text-gray-400 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                            <strong>Important:</strong> Use an App Password if 2FA is enabled.
                            <a href="https://account.live.com/proofs/AppPassword" target="_blank" class="text-primary-600 underline">Generate App Password</a>
                        </div>
                    '))
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('mail_username')
                    ->label('Email Address')
                    ->email()
                    ->maxLength(255)
                    ->placeholder('your-email@outlook.com')
                    ->required()
                    ->helperText('Your Microsoft 365 / Outlook email'),

                Forms\Components\TextInput::make('mail_password')
                    ->label('Password / App Password')
                    ->password()
                    ->maxLength(255)
                    ->autocomplete('new-password')
                    ->placeholder('••••••••')
                    ->required()
                    ->helperText('Account or app password')
                    ->dehydrated(fn ($state) => filled($state)),

                Forms\Components\TextInput::make('mail_from_address')
                    ->label('From Email')
                    ->email()
                    ->maxLength(255)
                    ->placeholder('your-email@outlook.com')
                    ->helperText('Usually same as login email'),

                Forms\Components\TextInput::make('mail_from_name')
                    ->label('From Name')
                    ->maxLength(255)
                    ->required()
                    ->placeholder('Your Company')
                    ->helperText('Sender display name'),
            ]),
        ];
    }
}

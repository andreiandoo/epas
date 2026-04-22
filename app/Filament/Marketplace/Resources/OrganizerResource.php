<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\OrganizerResource\Pages;
use App\Filament\Marketplace\Resources\EventResource;
use App\Models\MarketplaceOrganizer;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Event;
use App\Models\MarketplaceClient;
use App\Models\Order;

class OrganizerResource extends Resource
{
    protected static ?string $model = MarketplaceOrganizer::class;
    protected static ?string $navigationLabel = 'Organizatori';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';

    protected static \UnitEnum|string|null $navigationGroup = 'Organizers';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        $marketplaceAdmin = Auth::guard('marketplace_admin')->user();
        if (!$marketplaceAdmin) return null;

        return (string) static::getEloquentQuery()->count();
    }

    public static function getEloquentQuery(): Builder
    {
        $marketplaceAdmin = Auth::guard('marketplace_admin')->user();

        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $marketplaceAdmin?->marketplace_client_id);
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            SC\Grid::make(4)->schema([
                SC\Group::make()->columnSpan(3)->schema([
                    SC\Tabs::make('OrganizerTabs')
                        ->persistTabInQueryString()
                        ->tabs([

                            // ── TAB 1: Cont ──
                            SC\Tabs\Tab::make('Cont')
                                ->key('cont')
                                ->icon('heroicon-o-user')
                                ->schema([

                    Section::make('Organizer Information')
                        ->icon('heroicon-o-user')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->required()
                                ->maxLength(255),

                            Forms\Components\TextInput::make('email')
                                ->email()
                                ->required()
                                ->maxLength(255)
                                ->unique(
                                    table: 'marketplace_organizers',
                                    column: 'email',
                                    ignoreRecord: true,
                                    modifyRuleUsing: fn (Unique $rule) => $rule->where(
                                        'marketplace_client_id',
                                        Auth::guard('marketplace_admin')->user()?->marketplace_client_id
                                    ),
                                )
                                ->validationMessages([
                                    'unique' => 'Acest email este deja înregistrat la un alt organizator.',
                                ])
                                ->live(onBlur: true)
                                ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set, ?MarketplaceOrganizer $record) {
                                    if (empty($state)) return;
                                    $marketplaceClientId = Auth::guard('marketplace_admin')->user()?->marketplace_client_id;
                                    $exists = MarketplaceOrganizer::where('email', $state)
                                        ->where('marketplace_client_id', $marketplaceClientId)
                                        ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                        ->exists();
                                    if ($exists) {
                                        \Filament\Notifications\Notification::make()
                                            ->title('Email duplicat!')
                                            ->body("Adresa \"{$state}\" este deja folosită de un alt organizator.")
                                            ->warning()
                                            ->persistent()
                                            ->send();
                                    }
                                }),

                            Forms\Components\TextInput::make('password')
                                ->label('Parolă')
                                ->password()
                                ->required(fn (string $context): bool => $context === 'create')
                                ->dehydrated(fn ($state) => filled($state))
                                ->helperText(fn (string $context): string =>
                                    $context === 'create'
                                        ? 'Parola pentru contul organizatorului'
                                        : 'Lasă gol pentru a păstra parola existentă')
                                ->revealable()
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('contact_name')
                                ->maxLength(255),

                            Forms\Components\TextInput::make('phone')
                                ->tel()
                                ->maxLength(50),

                            Forms\Components\Textarea::make('description')
                                ->rows(3)
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('website')
                                ->url()
                                ->maxLength(255),

                            SC\Grid::make(2)->schema([
                                Forms\Components\Toggle::make('is_public')
                                    ->label('Fă profil public')
                                    ->helperText('Activează profilul public al organizatorului pe site-ul marketplace.')
                                    ->live(),

                                Forms\Components\Placeholder::make('public_profile_link')
                                    ->label('Link profil public')
                                    ->visible(fn ($get, $record) => $get('is_public') && $record)
                                    ->content(function ($record) {
                                        if (!$record) return '—';
                                        $url = $record->getPublicProfileUrl();
                                        if (!$url) return new \Illuminate\Support\HtmlString('<span style="color:#ef4444;">Organizatorul nu are slug sau marketplace-ul nu are domeniu setat.</span>');
                                        return new \Illuminate\Support\HtmlString(
                                            '<a href="' . e($url) . '" target="_blank" rel="noopener" style="color:#2563eb;text-decoration:underline;font-weight:600;">' . e($url) . '</a>'
                                        );
                                    }),
                            ]),
                        ])
                        ->columns(2),

                    Section::make('Media')
                        ->icon('heroicon-o-photo')
                        ->schema([
                            Forms\Components\FileUpload::make('logo')
                                ->label('Logo organizator')
                                ->image()
                                ->imageResizeMode('cover')
                                ->imageCropAspectRatio('1:1')
                                ->imageResizeTargetWidth('400')
                                ->imageResizeTargetHeight('400')
                                ->directory('organizer-logos')
                                ->disk('public')
                                ->helperText('Imagine pătrată, recomandat 400×400 px.'),

                            Forms\Components\FileUpload::make('cover_image')
                                ->label('Imagine cover')
                                ->image()
                                ->imageResizeMode('cover')
                                ->imageResizeTargetWidth('1920')
                                ->imageResizeTargetHeight('600')
                                ->directory('organizer-covers')
                                ->disk('public')
                                ->helperText('Imagine landscape, recomandat 1920×600 px.'),
                        ])
                        ->columns(2),

                    Section::make('Organizer Type')
                        ->icon('heroicon-o-tag')
                        ->description('Classification and work mode settings')
                        ->schema([
                            Forms\Components\Select::make('person_type')
                                ->label('Person Type')
                                ->options([
                                    'pj' => 'Persoana Juridica (Legal Entity)',
                                    'pf' => 'Persoana Fizica (Individual)',
                                ])
                                ->native(false),

                            Forms\Components\Select::make('work_mode')
                                ->label('Work Mode')
                                ->options([
                                    'exclusive' => 'Exclusive (sells only through this platform)',
                                    'non_exclusive' => 'Non-Exclusive (sells through multiple channels)',
                                ])
                                ->native(false),

                            Forms\Components\Select::make('organizer_type')
                                ->label('Organizer Type')
                                ->options([
                                    'agency' => 'Event Agency',
                                    'promoter' => 'Independent Promoter',
                                    'venue' => 'Venue / Hall',
                                    'artist' => 'Artist / Manager',
                                    'ngo' => 'NGO / Foundation',
                                    'other' => 'Other',
                                ])
                                ->native(false),
                        ])
                        ->columns(3),

                    Section::make('Widget & Embed')
                        ->icon('heroicon-o-code-bracket')
                        ->description('Configurare domenii permise pentru widget-uri embed. Organizatorul va putea genera coduri de embed din dashboardul propriu doar dacă are cel puțin un domeniu configurat.')
                        ->collapsed()
                        ->schema([
                            Forms\Components\Toggle::make('settings.widget_enabled')
                                ->label('Activează widget-uri embed')
                                ->helperText('Permite organizatorului să genereze coduri de widget pentru site-ul propriu')
                                ->default(false)
                                ->live(),
                            Forms\Components\TagsInput::make('settings.embed_domains')
                                ->label('Domenii permise pentru embed')
                                ->placeholder('ex: https://site-organizator.ro')
                                ->helperText('Domeniile unde organizatorul poate folosi widget-urile. iframe-ul va fi blocat pe alte domenii. Format: https://domeniu.ro sau *.domeniu.ro pentru toate subdomeniile')
                                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => (bool) ($get('settings.widget_enabled') ?? false)),
                        ]),

                                ]), // end Tab 1 (Cont)

                            // ── TAB 2: Date legale ──
                            SC\Tabs\Tab::make('Date legale')
                                ->key('date-legale')
                                ->icon('heroicon-o-building-office')
                                ->schema([

                    Section::make('Company Information')
                        ->icon('heroicon-o-building-office')
                        ->description('Legal entity details (for Persoana Juridica)')
                        ->schema([
                            Forms\Components\TextInput::make('company_name')
                                ->maxLength(255),

                            Forms\Components\TextInput::make('company_tax_id')
                                ->label('CUI / Tax ID')
                                ->maxLength(50),

                            Forms\Components\TextInput::make('company_registration')
                                ->label('Reg. Com. Number')
                                ->maxLength(100),

                            Forms\Components\Toggle::make('vat_payer')
                                ->label('VAT Payer')
                                ->helperText('Is the company a VAT payer?'),

                            Forms\Components\Textarea::make('company_address')
                                ->label('Company Address')
                                ->rows(2)
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('company_city')
                                ->label('City')
                                ->maxLength(100),

                            Forms\Components\TextInput::make('company_county')
                                ->label('County')
                                ->maxLength(100),

                            Forms\Components\TextInput::make('company_zip')
                                ->label('Postal Code')
                                ->maxLength(20),

                            Forms\Components\TextInput::make('representative_first_name')
                                ->label('Representative First Name')
                                ->maxLength(100)
                                ->helperText('Legal representative'),

                            Forms\Components\TextInput::make('representative_last_name')
                                ->label('Representative Last Name')
                                ->maxLength(100),
                        ])
                        ->columns(2),

                    Section::make('Guarantor / Personal Details')
                        ->icon('heroicon-o-identification')
                        ->description('Personal identification for contract purposes')
                        ->schema([
                            Forms\Components\TextInput::make('guarantor_first_name')
                                ->label('First Name')
                                ->maxLength(100),

                            Forms\Components\TextInput::make('guarantor_last_name')
                                ->label('Last Name')
                                ->maxLength(100),

                            Forms\Components\TextInput::make('guarantor_cnp')
                                ->label('CNP (Personal ID Number)')
                                ->maxLength(13)
                                ->helperText('13 digit Romanian CNP'),

                            Forms\Components\TextInput::make('guarantor_address')
                                ->label('Home Address')
                                ->maxLength(255),

                            Forms\Components\TextInput::make('guarantor_city')
                                ->label('City')
                                ->maxLength(100),

                            Forms\Components\Select::make('guarantor_id_type')
                                ->label('ID Document Type')
                                ->options([
                                    'ci' => 'Carte de Identitate (CI)',
                                    'bi' => 'Buletin de Identitate (BI)',
                                ])
                                ->native(false),

                            Forms\Components\TextInput::make('guarantor_id_series')
                                ->label('ID Series')
                                ->maxLength(2)
                                ->extraInputAttributes(['style' => 'text-transform: uppercase']),

                            Forms\Components\TextInput::make('guarantor_id_number')
                                ->label('ID Number')
                                ->maxLength(6),

                            Forms\Components\TextInput::make('guarantor_id_issued_by')
                                ->label('Issued By')
                                ->maxLength(100)
                                ->helperText('e.g., SPCLEP Sector 1'),

                            Forms\Components\DatePicker::make('guarantor_id_issued_date')
                                ->label('Issue Date')
                                ->native(false),
                        ])
                        ->columns(2),

                    Section::make('Uploaded Documents')
                        ->icon('heroicon-o-document-arrow-up')
                        ->description('Identity and company documents for verification')
                        ->schema([
                            Forms\Components\FileUpload::make('id_card_document')
                                ->label('CI / ID Card Copy')
                                ->disk('public')
                                ->directory('organizer-documents')
                                ->acceptedFileTypes(['image/*', 'application/pdf'])
                                ->maxSize(5120)
                                ->helperText('Personal ID card for the guarantor/representative')
                                ->downloadable()
                                ->openable(),

                            Forms\Components\FileUpload::make('cui_document')
                                ->label('CUI / Company Registration Copy')
                                ->disk('public')
                                ->directory('organizer-documents')
                                ->acceptedFileTypes(['image/*', 'application/pdf'])
                                ->maxSize(5120)
                                ->helperText('Company registration certificate (CUI)')
                                ->downloadable()
                                ->openable(),
                        ])
                        ->columns(2),

                    Section::make(function () {
                            $marketplaceAdmin = Auth::guard('marketplace_admin')->user();
                            $marketplaceName = $marketplaceAdmin?->marketplaceClient?->public_name
                                ?? $marketplaceAdmin?->marketplaceClient?->name
                                ?? 'Marketplace';
                            return 'Împuternicire ' . $marketplaceName;
                        })
                        ->icon('heroicon-o-shield-check')
                        ->description('Organizatorul împuternicește marketplace-ul prin intermediul unui reprezentant.')
                        ->schema([
                            Forms\Components\Toggle::make('has_proxy_authorization')
                                ->label('Există împuternicire')
                                ->default(false)
                                ->live()
                                ->columnSpanFull(),

                            Forms\Components\FileUpload::make('proxy_authorization_file')
                                ->label('Document de împuternicire')
                                ->disk('public')
                                ->directory('organizer-documents/proxy-authorizations')
                                ->acceptedFileTypes(['image/*', 'application/pdf'])
                                ->maxSize(10240)
                                ->downloadable()
                                ->openable()
                                ->helperText('Drag & drop documentul de împuternicire (PDF sau imagine)')
                                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => (bool) $get('has_proxy_authorization'))
                                ->columnSpanFull(),

                            Forms\Components\Select::make('proxy_admin_id')
                                ->label('Împuternicit din partea marketplace')
                                ->options(function () {
                                    $marketplaceAdmin = Auth::guard('marketplace_admin')->user();
                                    return \App\Models\MarketplaceAdmin::query()
                                        ->where('marketplace_client_id', $marketplaceAdmin?->marketplace_client_id)
                                        ->whereNotNull('proxy_full_name')
                                        ->where('proxy_full_name', '!=', '')
                                        ->orderBy('proxy_full_name')
                                        ->pluck('proxy_full_name', 'id')
                                        ->toArray();
                                })
                                ->searchable()
                                ->placeholder('Selectează împuternicitul')
                                ->helperText('Doar admin-ii cu datele de împuternicit completate apar în listă')
                                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => (bool) $get('has_proxy_authorization'))
                                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => (bool) $get('has_proxy_authorization'))
                                ->columnSpanFull(),
                        ]),

                                ]), // end Tab 2 (Date legale)

                            // ── TAB 3: Financiar ──
                            SC\Tabs\Tab::make('Financiar')
                                ->key('financiar')
                                ->icon('heroicon-o-banknotes')
                                ->schema([

                    Section::make('Bank Accounts')
                        ->icon('heroicon-o-credit-card')
                        ->description('Manage organizer bank accounts for payouts. The primary account will be used for payments.')
                        ->schema([
                            Forms\Components\Placeholder::make('bank_accounts_list')
                                ->hiddenLabel()
                                ->visible(fn (?MarketplaceOrganizer $record): bool => $record !== null)
                                ->content(fn (?MarketplaceOrganizer $record) => self::renderBankAccounts($record)),

                            Forms\Components\Repeater::make('bankAccounts')
                                ->relationship('bankAccounts')
                                ->schema([
                                    Forms\Components\TextInput::make('bank_name')
                                        ->label('Bank Name')
                                        ->required()
                                        ->maxLength(100)
                                        ->placeholder('e.g., ING Bank, BRD, BCR'),
                                    Forms\Components\TextInput::make('iban')
                                        ->label('IBAN')
                                        ->required()
                                        ->maxLength(34)
                                        ->placeholder('RO49AAAA1B31007593840000')
                                        ->dehydrateStateUsing(fn ($state) => strtoupper(preg_replace('/\s+/', '', $state ?? '')))
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn ($state, \Filament\Schemas\Components\Utilities\Set $set, string $path) => $set($path, strtoupper(preg_replace('/\s+/', '', $state ?? ''))))
                                        ->regex('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/')
                                        ->validationMessages([
                                            'regex' => 'Invalid IBAN format. Must start with 2 letters, 2 digits, then alphanumeric characters.',
                                        ]),
                                    Forms\Components\TextInput::make('account_holder')
                                        ->label('Account Holder')
                                        ->required()
                                        ->maxLength(255)
                                        ->placeholder('Account holder name'),
                                    Forms\Components\Toggle::make('is_primary')
                                        ->label('Primary Account')
                                        ->helperText('This account will be used for payouts'),
                                ])
                                ->columns(4)
                                ->addActionLabel('Add Bank Account')
                                ->reorderable(false)
                                ->defaultItems(0)
                                ->minItems(0)
                                ->maxItems(5)
                                ->collapsible()
                                ->collapsed()
                                ->itemLabel(fn (array $state): ?string => $state['bank_name'] ?? 'New Account')
                                ->extraAttributes(['class' => 'bank-accounts-repeater']),
                        ]),

                    Section::make('Contract Details')
                        ->icon('heroicon-o-document-check')
                        ->description('Contract number/series and date. Auto-filled when a contract is generated, but can be overridden by admin.')
                        ->schema([
                            Forms\Components\TextInput::make('contract_number_series')
                                ->label('Contract Number & Series')
                                ->maxLength(50)
                                ->placeholder('e.g., AMB/1322')
                                ->helperText('Auto-filled on contract generation. Format: PREFIX/NUMBER'),

                            Forms\Components\DatePicker::make('contract_date')
                                ->label('Contract Date')
                                ->native(false)
                                ->minDate('2000-01-01')
                                ->helperText('Auto-filled on contract generation date'),

                            Forms\Components\TextInput::make('invoice_due_days')
                                ->label('Zile scadență facturi')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(365)
                                ->suffix('zile')
                                ->placeholder('Default din setări')
                                ->helperText('Suprascrie setarea globală de zile scadență. Lasă gol pentru a folosi valoarea din Setări.'),
                        ])
                        ->columns(2),

                                ]), // end Tab 3 (Financiar)

                            // ── TAB 4: Bilete & Termeni ──
                            SC\Tabs\Tab::make('Bilete & Termeni')
                                ->key('bilete-termeni')
                                ->icon('heroicon-o-ticket')
                                ->schema([

                    Section::make('Termeni și Condiții Bilete')
                        ->icon('heroicon-o-document-text')
                        ->description('Acești termeni vor fi preluați automat în câmpul "Termeni bilete" când creați un eveniment nou pentru acest organizator')
                        ->schema([
                            Forms\Components\RichEditor::make('ticket_terms')
                                ->label('Termeni și condiții standard')
                                ->helperText('Textul de aici va fi copiat automat în secțiunea Ticket Terms la crearea unui eveniment')
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

                            Section::make('Feature Access')
                                ->icon('heroicon-o-cog-6-tooth')
                                ->schema([
                                    Forms\Components\Toggle::make('gamification_enabled')
                                        ->label('Gamification Enabled')
                                        ->helperText('Allow this organizer to use customer points for discounts on their events'),

                                    Forms\Components\Toggle::make('invitations_enabled')
                                        ->label('Invitations Enabled')
                                        ->default(true)
                                        ->helperText('Allow this organizer to create and manage event invitations'),
                                ])
                                ->columns(2),

                            Section::make('Extra Servicii')
                                ->icon('heroicon-o-puzzle-piece')
                                ->description('Activare/dezactivare servicii extra și model de pricing per organizator')
                                ->schema([
                                    Forms\Components\Toggle::make('service_settings.featuring_enabled')
                                        ->label('Promovare Eveniment')
                                        ->default(true)
                                        ->afterStateHydrated(fn ($component, $state) => $component->state($state ?? true))
                                        ->helperText('Permite organizatorului să promoveze evenimente pe platformă'),

                                    Forms\Components\Toggle::make('service_settings.email_enabled')
                                        ->label('Email Marketing')
                                        ->default(true)
                                        ->afterStateHydrated(fn ($component, $state) => $component->state($state ?? true))
                                        ->helperText('Permite organizatorului să trimită campanii email'),

                                    Forms\Components\Toggle::make('service_settings.tracking_enabled')
                                        ->label('Ad Tracking')
                                        ->default(true)
                                        ->afterStateHydrated(fn ($component, $state) => $component->state($state ?? true))
                                        ->live()
                                        ->helperText('Permite organizatorului să folosească tracking pentru campanii'),

                                    Forms\Components\Select::make('service_settings.tracking_pricing_model')
                                        ->label('Model pricing Tracking')
                                        ->options([
                                            'monthly' => 'Lunar',
                                            'biannual' => 'Bianual (6 luni)',
                                            'annual' => 'Anual (12 luni)',
                                            'one_time' => 'One-time (plată unică)',
                                        ])
                                        ->default('monthly')
                                        ->afterStateHydrated(fn ($component, $state) => $component->state($state ?? 'monthly'))
                                        ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => (bool) $get('service_settings.tracking_enabled'))
                                        ->helperText('Modelul de pricing aplicat acestui organizator pentru serviciul de tracking'),

                                    Forms\Components\Toggle::make('service_settings.campaign_enabled')
                                        ->label('Creare Campanii')
                                        ->default(true)
                                        ->afterStateHydrated(fn ($component, $state) => $component->state($state ?? true))
                                        ->helperText('Permite organizatorului să comande servicii de creare campanii'),
                                ])
                                ->columns(2),

                            Section::make('Pixeli organizator')
                                ->icon('heroicon-o-chart-bar')
                                ->description('Coduri de tracking proprii ale organizatorului. Sunt injectate suplimentar față de pixelii marketplace pe paginile evenimentelor sale, cart, checkout și thank-you.')
                                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => (bool) $get('service_settings.tracking_enabled'))
                                ->schema([
                                    SC\Grid::make(2)->schema([
                                        Forms\Components\Toggle::make('tracking_integrations.ga4_enabled')
                                            ->label('Google Analytics 4')
                                            ->live(),
                                        Forms\Components\TextInput::make('tracking_integrations.ga4_id')
                                            ->label('GA4 Measurement ID')
                                            ->placeholder('G-XXXXXXXXXX')
                                            ->maxLength(20)
                                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => (bool) $get('tracking_integrations.ga4_enabled')),
                                    ]),
                                    SC\Grid::make(2)->schema([
                                        Forms\Components\Toggle::make('tracking_integrations.gtm_enabled')
                                            ->label('Google Tag Manager')
                                            ->live(),
                                        Forms\Components\TextInput::make('tracking_integrations.gtm_id')
                                            ->label('GTM Container ID')
                                            ->placeholder('GTM-XXXXXX')
                                            ->maxLength(15)
                                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => (bool) $get('tracking_integrations.gtm_enabled')),
                                    ]),
                                    SC\Grid::make(2)->schema([
                                        Forms\Components\Toggle::make('tracking_integrations.meta_enabled')
                                            ->label('Meta (Facebook) Pixel')
                                            ->live(),
                                        Forms\Components\TextInput::make('tracking_integrations.meta_id')
                                            ->label('Meta Pixel ID')
                                            ->placeholder('1234567890123456')
                                            ->maxLength(20)
                                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => (bool) $get('tracking_integrations.meta_enabled')),
                                    ]),
                                    SC\Grid::make(2)->schema([
                                        Forms\Components\Toggle::make('tracking_integrations.tiktok_enabled')
                                            ->label('TikTok Pixel')
                                            ->live(),
                                        Forms\Components\TextInput::make('tracking_integrations.tiktok_id')
                                            ->label('TikTok Pixel ID')
                                            ->placeholder('CXXXXXXXXXXXXXXXXX')
                                            ->maxLength(25)
                                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => (bool) $get('tracking_integrations.tiktok_enabled')),
                                    ]),
                                ]),
                        ]),

                                ]), // end Tab 4 (Bilete & Termeni)

                            // ── TAB 5: Stats ──
                            SC\Tabs\Tab::make('Stats')
                                ->key('stats')
                                ->icon('heroicon-o-chart-bar')
                                ->schema([

                                    Section::make('Financial Summary')
                                        ->icon('heroicon-o-currency-dollar')
                                        ->visible(fn (?MarketplaceOrganizer $record): bool => $record !== null)
                                        ->extraAttributes(['class' => 'bg-gradient-to-r from-emerald-500/10 to-emerald-600/5 border-emerald-500/30'])
                                        ->schema([
                                            Forms\Components\Placeholder::make('financial_stats')
                                                ->hiddenLabel()
                                                ->content(fn (?MarketplaceOrganizer $record) => self::renderFinancialStats($record)),
                                        ]),

                                    Section::make('Events Stats')
                                        ->icon('heroicon-o-chart-bar')
                                        ->visible(fn (?MarketplaceOrganizer $record): bool => $record !== null)
                                        ->schema([
                                            Forms\Components\Placeholder::make('events_stats')
                                                ->hiddenLabel()
                                                ->content(fn (?MarketplaceOrganizer $record) => self::renderEventsStats($record)),
                                        ]),

                                    Section::make('Listă evenimente')
                                        ->icon('heroicon-o-list-bullet')
                                        ->description('Toate evenimentele organizatorului cu vânzări, încasări nete și comisioane')
                                        ->visible(fn (?MarketplaceOrganizer $record): bool => $record !== null)
                                        ->extraAttributes(['class' => 'ep-events-list-section'])
                                        ->schema([
                                            Forms\Components\Placeholder::make('events_list')
                                                ->hiddenLabel()
                                                ->extraAttributes(['class' => 'ep-events-list-placeholder'])
                                                ->content(fn (?MarketplaceOrganizer $record) => self::renderEventsList($record)),
                                        ]),

                                ]), // end Tab 5 (Stats)

                            // ── TAB 6: Mesaje ──
                            SC\Tabs\Tab::make('Mesaje')
                                ->key('mesaje')
                                ->icon('heroicon-o-envelope')
                                ->badge(fn (?MarketplaceOrganizer $record) => $record?->contactMessages()->where('status', 'unread')->count() ?: null)
                                ->badgeColor('danger')
                                ->schema([
                                    Forms\Components\Placeholder::make('contact_messages_list')
                                        ->hiddenLabel()
                                        ->visible(fn (?MarketplaceOrganizer $record): bool => $record !== null)
                                        ->content(function (?MarketplaceOrganizer $record) {
                                            if (!$record) return '';
                                            $messages = $record->contactMessages()->orderByDesc('created_at')->limit(50)->get();
                                            if ($messages->isEmpty()) {
                                                return new \Illuminate\Support\HtmlString(
                                                    '<div class="py-8 text-center text-gray-400">'
                                                    . '<p class="text-lg">Niciun mesaj</p>'
                                                    . '<p class="text-sm">Mesajele trimise prin formularul de contact de pe profilul public vor apărea aici.</p>'
                                                    . '</div>'
                                                );
                                            }

                                            // Mark all unread as read when viewing tab
                                            $record->contactMessages()->where('status', 'unread')->update(['status' => 'read']);

                                            $orgName = e($record->name ?? '');
                                            $html = '<div class="space-y-3">';
                                            foreach ($messages as $msg) {
                                                $statusBadge = match ($msg->status) {
                                                    'unread' => '<span style="display:inline-block;padding:2px 8px;font-size:11px;font-weight:600;border-radius:9999px;background:#fee2e2;color:#b91c1c;">Necitit</span>',
                                                    'read' => '<span style="display:inline-block;padding:2px 8px;font-size:11px;font-weight:600;border-radius:9999px;background:#fef9c3;color:#a16207;">Citit</span>',
                                                    'replied' => '<span style="display:inline-block;padding:2px 8px;font-size:11px;font-weight:600;border-radius:9999px;background:#dcfce7;color:#15803d;">Răspuns</span>',
                                                    default => '',
                                                };

                                                $senderName = e($msg->first_name . ' ' . $msg->last_name);
                                                $senderEmail = e($msg->email);
                                                $replySubject = rawurlencode('Re: Mesaj de la ' . $msg->first_name . ' ' . $msg->last_name . ' pe ' . ($record->name ?? 'profil'));
                                                $replyBody = rawurlencode("\n\n---\nMesaj original de la {$msg->first_name} {$msg->last_name} ({$msg->email}):\n{$msg->message}");
                                                $mailtoUrl = 'mailto:' . e($msg->email) . '?subject=' . $replySubject . '&body=' . $replyBody;

                                                $bgStyle = $msg->status === 'unread'
                                                    ? 'background:#eff6ff;border-color:#bfdbfe;'
                                                    : 'background:#fff;border-color:#e5e7eb;';

                                                $html .= '<div style="padding:16px;border:1px solid;border-radius:12px;' . $bgStyle . '">'
                                                    . '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;flex-wrap:wrap;gap:8px;">'
                                                    . '<div>'
                                                    . '<span style="font-weight:600;color:#374151;">' . $senderName . '</span>'
                                                    . ' <span style="font-size:13px;color:#6b7280;">' . $senderEmail . '</span>'
                                                    . ($msg->phone ? ' · <span style="font-size:13px;color:#6b7280;">' . e($msg->phone) . '</span>' : '')
                                                    . '</div>'
                                                    . '<div style="display:flex;align-items:center;gap:8px;">'
                                                    . $statusBadge
                                                    . '<span style="font-size:12px;color:#9ca3af;">' . $msg->created_at->timezone('Europe/Bucharest')->format('d.m.Y H:i') . '</span>'
                                                    . '</div>'
                                                    . '</div>'
                                                    . '<p style="font-size:14px;color:#374151;white-space:pre-wrap;margin:0 0 12px;">' . e($msg->message) . '</p>'
                                                    . '<a href="' . $mailtoUrl . '" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;font-size:13px;font-weight:600;color:#fff;background:#2563eb;border-radius:8px;text-decoration:none;">'
                                                    . '<svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>'
                                                    . 'Răspunde'
                                                    . '</a>'
                                                    . '</div>';
                                            }
                                            $html .= '</div>';
                                            return new \Illuminate\Support\HtmlString($html);
                                        }),
                                ]), // end Tab 6 (Mesaje)

                        ]), // end Tabs
                ]),
                SC\Group::make()->columnSpan(1)->schema([
                    // Organizer Preview Card (doar pe Edit/View)
                    Section::make('')
                        ->compact()
                        ->visible(fn (?MarketplaceOrganizer $record): bool => $record !== null)
                        ->schema([
                            Forms\Components\Placeholder::make('organizer_preview')
                                ->hiddenLabel()
                                ->content(fn (?MarketplaceOrganizer $record) => self::renderOrganizerPreview($record)),
                        ]),

                    Section::make('Status & Commission')
                        ->icon('heroicon-o-check-circle')
                        ->compact()
                        ->schema([
                            Forms\Components\Select::make('status')
                                ->options([
                                    'pending' => 'Pending',
                                    'active' => 'Active',
                                    'suspended' => 'Suspended',
                                ])
                                ->required()
                                ->default('pending'),

                            Forms\Components\TextInput::make('commission_rate')
                                ->label('Commission Rate (%)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(50)
                                ->step(0.5)
                                ->suffix('%')
                                ->helperText('Leave empty to use marketplace default'),

                            Forms\Components\TextInput::make('fixed_commission_default')
                                ->label('Fixed Commission (RON)')
                                ->numeric()
                                ->minValue(0)
                                ->step(0.5)
                                ->suffix('RON')
                                ->helperText('Fixed amount per ticket. Leave empty to use only percentage rate.'),

                            Forms\Components\Select::make('default_commission_mode')
                                ->label('Default Commission Mode')
                                ->options([
                                    'included' => 'Included in price',
                                    'added_on_top' => 'Added on top of price',
                                ])
                                ->placeholder('Use marketplace default')
                                ->helperText('Applied automatically when creating events for this organizer'),

                            Forms\Components\DateTimePicker::make('verified_at')
                                ->label('Verified At'),
                        ])
                        ->columns(1),

                    // Meta Info (doar pe Edit/View, collapsed)
                    Section::make('Meta Info')
                        ->icon('heroicon-o-information-circle')
                        ->compact()
                        ->collapsible()
                        ->collapsed()
                        ->visible(fn (?MarketplaceOrganizer $record): bool => $record !== null)
                        ->schema([
                            Forms\Components\Placeholder::make('meta_info')
                                ->hiddenLabel()
                                ->content(fn (?MarketplaceOrganizer $record) => self::renderMetaInfo($record)),
                        ]),
                ]),
            ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&background=10b981&color=fff')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'suspended' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(),

                Tables\Columns\IconColumn::make('verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-circle')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_events')
                    ->label('Events')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_revenue')
                    ->label('Revenue')
                    ->money('RON')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('available_balance')
                    ->label('Balance')
                    ->money('RON')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                    ]),

                Tables\Filters\TernaryFilter::make('verified')
                    ->label('Verified')
                    ->nullable()
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('verified_at'),
                        false: fn (Builder $query) => $query->whereNull('verified_at'),
                    ),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (MarketplaceOrganizer $record): bool => $record->status === 'pending')
                    ->action(function (MarketplaceOrganizer $record): void {
                        $record->update(['status' => 'active']);
                    }),

                Action::make('verify')
                    ->label('Verify')
                    ->icon('heroicon-o-check-badge')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (MarketplaceOrganizer $record): bool => $record->verified_at === null && $record->status === 'active')
                    ->action(function (MarketplaceOrganizer $record): void {
                        $record->update(['verified_at' => now()]);
                    }),

                Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (MarketplaceOrganizer $record): bool => $record->status === 'active')
                    ->action(function (MarketplaceOrganizer $record): void {
                        $record->update(['status' => 'suspended']);
                    }),

                Action::make('reactivate')
                    ->label('Reactivate')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (MarketplaceOrganizer $record): bool => $record->status === 'suspended')
                    ->action(function (MarketplaceOrganizer $record): void {
                        $record->update(['status' => 'active']);
                    }),

                Action::make('login_as')
                    ->label('Login as')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color('warning')
                    ->url(fn (MarketplaceOrganizer $record) => url('/marketplace/organizers/' . $record->id . '/login-as'), shouldOpenInNewTab: true),

                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve_selected')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            $records->each(fn ($record) => $record->update(['status' => 'active']));
                        }),

                    BulkAction::make('send_password_reset')
                        ->label('Send Password Reset')
                        ->icon('heroicon-o-key')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Trimite email de resetare parolă')
                        ->modalDescription('Se va trimite un email de resetare a parolei către toți organizatorii selectați. Sigur vrei să continui?')
                        ->modalSubmitActionLabel('Trimite')
                        ->action(function ($records): void {
                            $sent = 0;
                            $failed = 0;

                            foreach ($records as $organizer) {
                                try {
                                    $client = $organizer->marketplaceClient;
                                    if (!$client) {
                                        $failed++;
                                        continue;
                                    }

                                    // Delete any existing tokens
                                    DB::table('marketplace_password_resets')
                                        ->where('email', $organizer->email)
                                        ->where('type', 'organizer')
                                        ->where('marketplace_client_id', $client->id)
                                        ->delete();

                                    // Create new token
                                    $token = Str::random(64);
                                    DB::table('marketplace_password_resets')->insert([
                                        'email' => $organizer->email,
                                        'type' => 'organizer',
                                        'marketplace_client_id' => $client->id,
                                        'token' => Hash::make($token),
                                        'created_at' => now(),
                                    ]);

                                    // Build reset email HTML
                                    $domain = $client->domain ? rtrim($client->domain, '/') : config('app.url');
                                    if ($domain && !str_starts_with($domain, 'http')) {
                                        $domain = 'https://' . $domain;
                                    }

                                    $resetUrl = $domain . '/organizer/reset-password?' . http_build_query([
                                        'token' => $token,
                                        'email' => $organizer->email,
                                    ]);
                                    $organizerName = $organizer->name ?: 'Organizator';
                                    $siteName = $client->name ?? 'bilete.online';
                                    $expireMinutes = config('auth.passwords.marketplace.expire', 60);

                                    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;background:#f8fafc">'
                                        . '<div style="max-width:600px;margin:0 auto;padding:40px 20px">'
                                        . '<div style="background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08)">'
                                        . '<div style="background:linear-gradient(135deg,#A51C30 0%,#8B1728 100%);padding:32px;text-align:center">'
                                        . '<h1 style="color:white;margin:0;font-size:24px">Resetare parolă</h1>'
                                        . '</div>'
                                        . '<div style="padding:32px">'
                                        . '<p style="font-size:16px;color:#1e293b;margin:0 0 16px">Salut ' . htmlspecialchars($organizerName) . ',</p>'
                                        . '<p style="font-size:15px;color:#475569;margin:0 0 16px">Contul tău de organizator a fost migrat pe noua platformă. Te rugăm să îți setezi o parolă nouă folosind butonul de mai jos.</p>'
                                        . '<div style="text-align:center;margin:24px 0">'
                                        . '<a href="' . htmlspecialchars($resetUrl) . '" style="display:inline-block;background:#A51C30;color:white;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:600;font-size:16px">Setează parola</a>'
                                        . '</div>'
                                        . '<p style="font-size:13px;color:#94a3b8;margin:16px 0 0;text-align:center">Linkul expiră în ' . $expireMinutes . ' de minute.</p>'
                                        . '</div>'
                                        . '<div style="padding:16px 32px;background:#f8fafc;text-align:center;border-top:1px solid #e2e8f0">'
                                        . '<p style="font-size:13px;color:#94a3b8;margin:0">Echipa ' . htmlspecialchars($siteName) . '</p>'
                                        . '</div>'
                                        . '</div></div></body></html>';

                                    BaseController::sendViaMarketplace($client, $organizer->email, $organizerName, 'Setează parola - ' . $siteName, $html, [
                                        'template_slug' => 'organizer_password_reset',
                                    ]);

                                    $sent++;
                                } catch (\Throwable $e) {
                                    $failed++;
                                    \Illuminate\Support\Facades\Log::channel('marketplace')->error('Bulk password reset failed', [
                                        'organizer_id' => $organizer->id,
                                        'error' => $e->getMessage(),
                                    ]);
                                }
                            }

                            if ($sent > 0) {
                                Notification::make()
                                    ->title("Email-uri trimise: {$sent}" . ($failed > 0 ? " ({$failed} eșuate)" : ''))
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title("Niciun email trimis ({$failed} eșuate)")
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrganizers::route('/'),
            'create' => Pages\CreateOrganizer::route('/create'),
            'view' => Pages\ViewOrganizer::route('/{record}'),
            'edit' => Pages\EditOrganizer::route('/{record}/edit'),
        ];
    }

    protected static function renderOrganizerPreview(?MarketplaceOrganizer $record): HtmlString
    {
        if (!$record) return new HtmlString('');

        $initials = collect(explode(' ', $record->name))
            ->map(fn ($word) => mb_substr($word, 0, 1))
            ->take(2)
            ->join('');

        $statusBadge = match($record->status) {
            'active' => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: rgba(16, 185, 129, 0.15); color: #10B981;">✓ Active</span>',
            'pending' => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: rgba(245, 158, 11, 0.15); color: #F59E0B;">⏳ Pending</span>',
            'suspended' => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: rgba(239, 68, 68, 0.15); color: #EF4444;">✕ Suspended</span>',
            default => '',
        };

        $verifiedBadge = $record->verified_at 
            ? '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: rgba(59, 130, 246, 0.15); color: #60A5FA;">✓ Verified</span>'
            : '';

        return new HtmlString("
            <div style='display: flex; gap: 12px; align-items: center; margin-bottom: 12px;'>
                <div style='width: 56px; height: 56px; border-radius: 50%; background: linear-gradient(135deg, #10B981, #059669); display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 700; color: white;'>{$initials}</div>
                <div style='flex: 1;'>
                    <div style='font-size: 16px; font-weight: 700; color: white;'>" . e($record->name) . "</div>
                    <div style='font-size: 12px; color: #64748B;'>" . e($record->email) . "</div>
                </div>
            </div>
            <div style='display: flex; flex-wrap: wrap; gap: 6px;'>
                {$statusBadge}
                {$verifiedBadge}
            </div>
        ");
    }

    protected static function renderFinancialStats(?MarketplaceOrganizer $record): HtmlString
    {
        if (!$record) return new HtmlString('');

        return new HtmlString("
            <div style='display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;'>
                <div style='text-align: center; padding: 8px;'>
                    <div style='font-size: 16px; font-weight: 700; color: white;'>" . number_format($record->total_revenue, 2) . " RON</div>
                    <div style='font-size: 11px; color: #64748B;'>Total Revenue</div>
                </div>
                <div style='text-align: center; padding: 8px;'>
                    <div style='font-size: 16px; font-weight: 700; color: #10B981;'>" . number_format($record->available_balance, 2) . " RON</div>
                    <div style='font-size: 11px; color: #64748B;'>Available Balance</div>
                </div>
                <div style='text-align: center; padding: 8px;'>
                    <div style='font-size: 16px; font-weight: 700; color: #F59E0B;'>" . number_format($record->pending_balance, 2) . " RON</div>
                    <div style='font-size: 11px; color: #64748B;'>Pending Balance</div>
                </div>
                <div style='text-align: center; padding: 8px;'>
                    <div style='font-size: 16px; font-weight: 700; color: white;'>" . number_format($record->total_paid_out, 2) . " RON</div>
                    <div style='font-size: 11px; color: #64748B;'>Total Paid Out</div>
                </div>
            </div>
        ");
    }

    protected static function renderEventsStats(?MarketplaceOrganizer $record): HtmlString
    {
        if (!$record) return new HtmlString('');

        $totalEvents = $record->events()->count();
        $activeEvents = $record->events()
            ->whereIn('status', ['published', 'active'])
            ->count();
        $upcomingEvents = $record->events()->where('starts_at', '>=', now())->count();
        $completedEvents = $record->events()->where('starts_at', '<', now())->count();

        return new HtmlString("
            <div style='display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px;'>
                <div style='background: #0F172A; border-radius: 8px; padding: 12px; text-align: center;'>
                    <div style='font-size: 18px; font-weight: 700; color: white;'>{$totalEvents}</div>
                    <div style='font-size: 10px; color: #64748B; text-transform: uppercase;'>Total Events</div>
                </div>
                <div style='background: #0F172A; border-radius: 8px; padding: 12px; text-align: center;'>
                    <div style='font-size: 18px; font-weight: 700; color: #10B981;'>{$activeEvents}</div>
                    <div style='font-size: 10px; color: #64748B; text-transform: uppercase;'>Active</div>
                </div>
                <div style='background: #0F172A; border-radius: 8px; padding: 12px; text-align: center;'>
                    <div style='font-size: 18px; font-weight: 700; color: #F59E0B;'>{$upcomingEvents}</div>
                    <div style='font-size: 10px; color: #64748B; text-transform: uppercase;'>Upcoming</div>
                </div>
                <div style='background: #0F172A; border-radius: 8px; padding: 12px; text-align: center;'>
                    <div style='font-size: 18px; font-weight: 700; color: #64748B;'>{$completedEvents}</div>
                    <div style='font-size: 10px; color: #64748B; text-transform: uppercase;'>Completed</div>
                </div>
            </div>
        ");
    }

    protected static function renderEventsList(?MarketplaceOrganizer $record): HtmlString
    {
        if (!$record) return new HtmlString('');

        $events = Event::where('marketplace_organizer_id', $record->id)
            ->with('venue:id,name,city')
            ->orderByRaw('COALESCE(event_date, DATE(starts_at), range_start_date) DESC NULLS LAST')
            ->get();

        if ($events->isEmpty()) {
            return new HtmlString(
                '<div style="padding:24px;text-align:center;color:#64748B;">'
                . '<p style="font-size:14px;margin:0;">Acest organizator nu are evenimente.</p>'
                . '</div>'
            );
        }

        $eventIds = $events->pluck('id')->toArray();
        $validStatuses = ['paid', 'confirmed', 'completed', 'refunded'];

        // Aggregate orders per event (by marketplace_event_id OR event_id)
        $orderAgg = Order::where(function ($q) use ($eventIds) {
                $q->whereIn('marketplace_event_id', $eventIds)
                  ->orWhereIn('event_id', $eventIds);
            })
            ->whereIn('status', $validStatuses)
            ->where('source', '!=', 'test_order')->where('source', '!=', 'external_import')
            ->selectRaw('COALESCE(marketplace_event_id, event_id) as eid')
            ->selectRaw("SUM(CASE WHEN status = 'refunded' THEN 0 ELSE total END) as net_revenue")
            ->selectRaw('SUM(total) as gross_revenue_all')
            ->selectRaw('SUM(CASE WHEN commission_amount > 0 THEN commission_amount ELSE total * COALESCE(commission_rate, 0) / 100 END) as total_commission')
            ->groupBy('eid')
            ->get()
            ->keyBy('eid');

        // Aggregate sold tickets per event
        $ticketAgg = DB::table('tickets')
            ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
            ->whereIn('ticket_types.event_id', $eventIds)
            ->whereIn('tickets.status', ['valid', 'used'])
            ->selectRaw('ticket_types.event_id as eid, COUNT(tickets.id) as cnt')
            ->groupBy('eid')
            ->pluck('cnt', 'eid')
            ->toArray();

        $now = now();
        $rows = '';
        $totalNet = 0;
        $totalCommission = 0;
        $totalTickets = 0;

        foreach ($events as $event) {
            $name = $event->getTranslation('title', 'ro') ?: $event->getTranslation('title', 'en') ?: '(fără titlu)';
            $venueName = null;
            if ($event->venue) {
                $venueRaw = $event->venue->name;
                $venueName = is_array($venueRaw) ? ($venueRaw['ro'] ?? $venueRaw['en'] ?? array_values($venueRaw)[0] ?? null) : $venueRaw;
            }
            $venueCity = $event->venue?->city;

            // Resolve display date
            $eventDate = $event->event_date ?? $event->range_start_date ?? ($event->starts_at?->toDate());
            $eventDateStr = $eventDate ? \Carbon\Carbon::parse($eventDate)->format('d.m.Y') : '-';

            // Live vs ended
            $endDate = $event->range_end_date ?? $event->event_date ?? ($event->starts_at?->toDate());
            $endCarbon = $endDate ? \Carbon\Carbon::parse($endDate)->endOfDay() : null;
            $isCancelled = (bool) ($event->is_cancelled ?? false);
            if ($isCancelled) {
                $statusBadge = '<span style="display:inline-block;padding:2px 8px;font-size:10px;font-weight:600;border-radius:9999px;background:#fee2e2;color:#b91c1c;">Anulat</span>';
            } elseif ($endCarbon && $endCarbon->lt($now)) {
                $statusBadge = '<span style="display:inline-block;padding:2px 8px;font-size:10px;font-weight:600;border-radius:9999px;background:#e5e7eb;color:#4b5563;">Încheiat</span>';
            } else {
                $statusBadge = '<span style="display:inline-block;padding:2px 8px;font-size:10px;font-weight:600;border-radius:9999px;background:#dcfce7;color:#15803d;">Live</span>';
            }

            $agg = $orderAgg->get($event->id);
            $netRevenue = (float) ($agg->net_revenue ?? 0);
            $commission = (float) ($agg->total_commission ?? 0);
            // Net to organizer = revenue (excluding refunds) - commission
            $netToOrganizer = max(0, $netRevenue - $commission);
            $tickets = (int) ($ticketAgg[$event->id] ?? 0);

            $totalNet += $netToOrganizer;
            $totalCommission += $commission;
            $totalTickets += $tickets;

            $eventName = e($name);
            $venueDisplay = e(trim(implode(' · ', array_filter([$venueName, $venueCity]))) ?: '-');

            $editUrl = \App\Filament\Marketplace\Resources\EventResource::getUrl('edit', ['record' => $event->id]);

            $rows .= '<tr style="border-bottom:1px solid rgba(148,163,184,0.18);">'
                . '<td style="padding:8px 10px;font-size:13px;font-weight:500;">'
                    . '<a href="' . e($editUrl) . '" style="color:#ffffff;text-decoration:none;" onmouseover="this.style.color=\'#93c5fd\';this.style.textDecoration=\'underline\'" onmouseout="this.style.color=\'#ffffff\';this.style.textDecoration=\'none\'">' . $eventName . '</a>'
                . '</td>'
                . '<td style="padding:8px 10px;font-size:12px;color:#cbd5e1;white-space:nowrap;">' . $eventDateStr . '</td>'
                . '<td style="padding:8px 10px;font-size:12px;color:#cbd5e1;">' . $venueDisplay . '</td>'
                . '<td style="padding:8px 10px;text-align:center;">' . $statusBadge . '</td>'
                . '<td style="padding:8px 10px;text-align:right;font-size:13px;color:#ffffff;font-weight:600;white-space:nowrap;">' . number_format($tickets, 0, ',', '.') . '</td>'
                . '<td style="padding:8px 10px;text-align:right;font-size:13px;color:#34d399;font-weight:600;white-space:nowrap;">' . number_format($netToOrganizer, 2, ',', '.') . ' RON</td>'
                . '<td style="padding:8px 10px;text-align:right;font-size:13px;color:#f87171;font-weight:600;white-space:nowrap;">' . number_format($commission, 2, ',', '.') . ' RON</td>'
                . '</tr>';
        }

        $footer = '<tr style="background:rgba(148,163,184,0.08);font-weight:700;">'
            . '<td colspan="4" style="padding:10px;font-size:12px;color:#cbd5e1;text-transform:uppercase;letter-spacing:0.05em;">Total ' . count($events) . ' evenimente</td>'
            . '<td style="padding:10px;text-align:right;font-size:13px;color:#ffffff;">' . number_format($totalTickets, 0, ',', '.') . '</td>'
            . '<td style="padding:10px;text-align:right;font-size:13px;color:#34d399;">' . number_format($totalNet, 2, ',', '.') . ' RON</td>'
            . '<td style="padding:10px;text-align:right;font-size:13px;color:#f87171;">' . number_format($totalCommission, 2, ',', '.') . ' RON</td>'
            . '</tr>';

        // Reset section + placeholder padding so the table fills the full section width.
        $resetCss = '<style>'
            . '.ep-events-list-section .fi-section-content,'
            . '.ep-events-list-section .fi-section-content-ctn{padding:0!important;}'
            . '.ep-events-list-section .fi-fo-placeholder{padding:0!important;}'
            . '.ep-events-list-placeholder{padding:0!important;margin:0!important;}'
            . '</style>';

        $html = $resetCss
            . '<div style="overflow-x:auto;width:100%;">'
            . '<table style="width:100%;border-collapse:collapse;font-family:-apple-system,BlinkMacSystemFont,sans-serif;">'
            . '<thead>'
            . '<tr style="background:rgba(148,163,184,0.12);border-bottom:1px solid rgba(148,163,184,0.25);">'
            . '<th style="padding:10px;text-align:left;font-size:11px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;">Eveniment</th>'
            . '<th style="padding:10px;text-align:left;font-size:11px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;">Data</th>'
            . '<th style="padding:10px;text-align:left;font-size:11px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;">Venue / Oraș</th>'
            . '<th style="padding:10px;text-align:center;font-size:11px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;">Status</th>'
            . '<th style="padding:10px;text-align:right;font-size:11px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;">Bilete vândute</th>'
            . '<th style="padding:10px;text-align:right;font-size:11px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;">Încasări nete</th>'
            . '<th style="padding:10px;text-align:right;font-size:11px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;">Comisioane</th>'
            . '</tr>'
            . '</thead>'
            . '<tbody>' . $rows . $footer . '</tbody>'
            . '</table>'
            . '</div>';

        return new HtmlString($html);
    }

    protected static function renderMetaInfo(?MarketplaceOrganizer $record): HtmlString
    {
        if (!$record) return new HtmlString('');

        $createdAt = $record->created_at->format('M d, Y');
        $updatedAt = $record->updated_at->diffForHumans();

        return new HtmlString("
            <div>
                <div style='display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                    <span style='font-size: 13px; color: #64748B;'>Created</span>
                    <span style='font-size: 13px; font-weight: 600; color: #E2E8F0;'>{$createdAt}</span>
                </div>
                <div style='display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                    <span style='font-size: 13px; color: #64748B;'>Updated</span>
                    <span style='font-size: 13px; font-weight: 600; color: #E2E8F0;'>{$updatedAt}</span>
                </div>
                <div style='display: flex; justify-content: space-between; padding: 8px 0;'>
                    <span style='font-size: 13px; color: #64748B;'>ID</span>
                    <span style='font-size: 11px; font-weight: 600; color: #64748B; font-family: monospace;'>{$record->id}</span>
                </div>
            </div>
        ");
    }

    protected static function renderBankAccounts(?MarketplaceOrganizer $record): HtmlString
    {
        if (!$record) return new HtmlString('');

        $accounts = $record->bankAccounts()->orderByDesc('is_primary')->orderBy('created_at')->get();

        if ($accounts->isEmpty()) {
            return new HtmlString("
                <div style='text-align: center; padding: 24px; color: #64748B;'>
                    <svg style='width: 48px; height: 48px; margin: 0 auto 12px; opacity: 0.5;' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'/>
                    </svg>
                    <div style='font-size: 14px;'>No bank accounts added yet</div>
                    <div style='font-size: 12px; margin-top: 4px;'>Add accounts using the form below</div>
                </div>
            ");
        }

        $html = "<div style='display: flex; flex-direction: column; gap: 12px;'>";

        foreach ($accounts as $account) {
            $primaryBadge = $account->is_primary
                ? "<span style='display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 600; background: rgba(16, 185, 129, 0.15); color: #10B981;'>PRIMARY</span>"
                : "";

            $maskedIban = substr($account->iban, 0, 4) . str_repeat('•', strlen($account->iban) - 8) . substr($account->iban, -4);

            $html .= "
                <div style='display: flex; align-items: center; gap: 12px; padding: 12px; background: #0F172A; border-radius: 8px; border: 1px solid " . ($account->is_primary ? '#10B981' : '#1E293B') . ";'>
                    <div style='width: 40px; height: 40px; border-radius: 8px; background: #1E293B; display: flex; align-items: center; justify-content: center;'>
                        <svg style='width: 20px; height: 20px; color: #64748B;' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                            <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'/>
                        </svg>
                    </div>
                    <div style='flex: 1;'>
                        <div style='display: flex; align-items: center; gap: 8px;'>
                            <span style='font-size: 14px; font-weight: 600; color: white;'>" . e($account->bank_name) . "</span>
                            {$primaryBadge}
                        </div>
                        <div style='font-size: 12px; color: #64748B; font-family: monospace;'>{$maskedIban}</div>
                        <div style='font-size: 11px; color: #64748B;'>" . e($account->account_holder) . "</div>
                    </div>
                </div>
            ";
        }

        $html .= "</div>";

        return new HtmlString($html);
    }
}

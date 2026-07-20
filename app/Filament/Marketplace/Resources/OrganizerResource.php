<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\ActivityResource;
use App\Filament\Marketplace\Resources\OrganizerResource\Pages;
use App\Filament\Marketplace\Resources\EventResource;
use App\Models\Activity;
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
    use HasMarketplaceContext;

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

    public static function isFacebookCapiMicroserviceActive(?int $marketplaceClientId): bool
    {
        if (!$marketplaceClientId) {
            return false;
        }

        return DB::table('marketplace_client_microservices as mcm')
            ->join('microservices as m', 'm.id', '=', 'mcm.microservice_id')
            ->where('mcm.marketplace_client_id', $marketplaceClientId)
            ->where('m.slug', 'facebook-capi-integration')
            ->where('mcm.status', 'active')
            ->where(function ($q) {
                $q->whereNull('mcm.expires_at')->orWhere('mcm.expires_at', '>', now());
            })
            ->exists();
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
                                    'leisure' => '🏞️ Leisure Venue (rezervație, aquapark, castel, parc aventură, camping)',
                                    'other' => 'Other',
                                ])
                                ->live()
                                ->helperText('Pentru "Leisure Venue" se aplică un panou self-service custom (dashboard live, POS, schimburi echipă, raportare detaliată).')
                                ->native(false),
                            Forms\Components\Select::make('leisure_template_variant')
                                ->label('Template pagină publică (variant)')
                                ->options([
                                    'reserve'   => '🌲 Rezervație naturală / parc (Sf. Ana style)',
                                    'aquapark'  => '💦 Aquapark / Ștrand',
                                    'castle'    => '🏰 Castel / Muzeu',
                                    'adventure' => '🧗 Parc aventură / Karting',
                                    'camping'   => '🏕️ Camping standalone',
                                ])
                                ->placeholder('Lasă gol pentru template default (rezervație)')
                                ->helperText('Determină ce variantă de layout/temă vizuală se folosește pe pagina publică. Va fi extins în viitor (vezi LEISURE_TEMPLATES_PLAN.md).')
                                ->native(false)
                                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('organizer_type') === 'leisure')
                                ->columnSpan(3),
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
                                        ->afterStateUpdated(fn ($state, \Filament\Schemas\Components\Utilities\Set $set, $component) => $set($component->getStatePath(false), strtoupper(preg_replace('/\s+/', '', $state ?? ''))))
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

                    // ── A doua societate emitentă (cazul Lacul Sf. Ana) ──
                    Section::make('A doua societate emitentă')
                        ->icon('heroicon-o-building-office-2')
                        ->description('Activează dacă organizatorul are 2 societăți distincte care emit facturi (ex: bilete acces pe SC1, servicii conexe pe SC2). În formularul tipurilor de bilete poți alege pentru fiecare bilet ce societate emite factura.')
                        ->schema([
                            Forms\Components\Toggle::make('has_secondary_issuer')
                                ->label('Activează a doua societate')
                                ->helperText('Lasă dezactivat dacă organizatorul are o singură societate.')
                                ->live()
                                ->columnSpanFull(),

                            SC\Group::make([
                                Forms\Components\TextInput::make('secondary_company_name')
                                    ->label('Denumire societate')
                                    ->maxLength(255)
                                    ->placeholder('ex: SC Servicii Sf. Ana SRL')
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('secondary_company_tax_id')
                                    ->label('CUI / CIF')
                                    ->maxLength(32)
                                    ->placeholder('RO12345678'),
                                Forms\Components\TextInput::make('secondary_company_registration')
                                    ->label('Nr. Reg. Comerț')
                                    ->maxLength(32)
                                    ->placeholder('J40/1234/2020'),
                                Forms\Components\Textarea::make('secondary_company_address')
                                    ->label('Adresă sediu')
                                    ->rows(2)
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('secondary_company_city')
                                    ->label('Oraș')
                                    ->maxLength(100),
                                Forms\Components\TextInput::make('secondary_company_county')
                                    ->label('Județ')
                                    ->maxLength(100),
                                Forms\Components\TextInput::make('secondary_company_zip')
                                    ->label('Cod poștal')
                                    ->maxLength(20),
                                Forms\Components\TextInput::make('secondary_bank_name')
                                    ->label('Bancă')
                                    ->maxLength(255)
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('secondary_iban')
                                    ->label('IBAN')
                                    ->maxLength(34)
                                    ->placeholder('RO49...')
                                    ->dehydrateStateUsing(fn ($state) => $state ? strtoupper(preg_replace('/\s+/', '', $state)) : null)
                                    ->columnSpan(2),
                            ])
                                ->columns(3)
                                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => (bool) $get('has_secondary_issuer'))
                                ->columnSpanFull(),

                            // Numerotare facturi separată
                            SC\Group::make([
                                Forms\Components\TextInput::make('primary_invoice_series')
                                    ->label('Serie facturi — societate principală')
                                    ->maxLength(16)
                                    ->placeholder('ex: LSA-A'),
                                Forms\Components\TextInput::make('primary_last_invoice_number')
                                    ->label('Ultim nr. factură principală')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->helperText('Atenție: modifică doar la migrarea unei numerotări existente.'),
                                Forms\Components\TextInput::make('secondary_invoice_series')
                                    ->label('Serie facturi — societate secundară')
                                    ->maxLength(16)
                                    ->placeholder('ex: LSA-S'),
                                Forms\Components\TextInput::make('secondary_last_invoice_number')
                                    ->label('Ultim nr. factură secundară')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0),
                            ])
                                ->columns(2)
                                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => (bool) $get('has_secondary_issuer'))
                                ->columnSpanFull(),
                        ])
                        ->columns(1),

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

                                    Forms\Components\Toggle::make('allow_live_edits')
                                        ->label('Permite modificări live')
                                        ->default(false)
                                        ->helperText('Când e bifat, organizatorul poate modifica evenimentele deja publicate (live) direct din contul lui, iar modificările se publică imediat — fără să mai treacă prin aprobare.'),
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

                                    Forms\Components\Toggle::make('service_settings.mobile_card_nfc_enabled')
                                        ->label('Card prin NFC (mobile POS)')
                                        ->default(false)
                                        ->afterStateHydrated(fn ($component, $state) => $component->state((bool) $state))
                                        ->helperText('Adaugă în aplicația mobilă un buton "Card prin NFC" (plată Stripe Tap). Lasă oprit dacă organizatorul nu are NFC configurat — flow-ul normal Card POS (cu confirmare manuală) rămâne disponibil oricum.'),
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
                                    SC\Grid::make(2)->schema([
                                        Forms\Components\Toggle::make('tracking_integrations.google_ads_enabled')
                                            ->label('Google Ads')
                                            ->live(),
                                        Forms\Components\TextInput::make('tracking_integrations.google_ads_id')
                                            ->label('Google Ads Conversion ID')
                                            ->placeholder('AW-XXXXXXXXX')
                                            ->maxLength(25)
                                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => (bool) $get('tracking_integrations.google_ads_enabled')),
                                    ]),
                                ]),

                            // Server-side Conversion APIs (GA4 MP + TikTok EAPI) —
                            // optional per-organizer credentials. When set, the
                            // marketplace tracking bridge forwards events to
                            // Google Analytics and TikTok directly from Laravel,
                            // bypassing adblockers / iOS ATT / cookie deletion.
                            // Meta CAPI has its own dedicated section below with
                            // its own connection table (facebook_capi_connections).
                            Section::make('Conversions API GA4 + TikTok (Server-Side, opțional)')
                                ->icon('heroicon-o-bolt')
                                ->description('Trimite evenimente către GA4 Measurement Protocol și TikTok Events API server-side, pe lângă pixelul din browser. Recomandat pentru capturarea evenimentelor blocate de adblockers. Credentialele se salvează criptate în DB. Când sunt goale aici, fallback-ul folosește credentialele setate la nivel de marketplace (Setări → Personalization → Tracking & Pixels).')
                                ->collapsible()
                                ->collapsed()
                                ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => (bool) $get('service_settings.tracking_enabled'))
                                ->schema([
                                    Forms\Components\TextInput::make('tracking_integrations.ga4_api_secret')
                                        ->label('GA4 Measurement Protocol API Secret')
                                        ->password()
                                        ->revealable()
                                        ->autocomplete('new-password')
                                        ->placeholder('••••••••')
                                        ->helperText('GA4 Admin → Data Streams → your web stream → Measurement Protocol API secrets. Lasă gol pentru a păstra valoarea existentă. Necesar doar dacă vrei GA4 server-side pentru acest organizator; altfel se folosește tokenul marketplace-ului (dacă e setat).')
                                        ->dehydrated(fn ($state) => filled($state))
                                        ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => (bool) $get('tracking_integrations.ga4_enabled')),

                                    Forms\Components\TextInput::make('tracking_integrations.tiktok_access_token')
                                        ->label('TikTok Events API Access Token')
                                        ->password()
                                        ->revealable()
                                        ->autocomplete('new-password')
                                        ->placeholder('••••••••')
                                        ->helperText('Events Manager → Settings → Events API → Generate access token. Lasă gol pentru a păstra valoarea existentă. Necesar doar dacă vrei TikTok server-side pentru acest organizator; altfel se folosește tokenul marketplace-ului (dacă e setat).')
                                        ->dehydrated(fn ($state) => filled($state))
                                        ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => (bool) $get('tracking_integrations.tiktok_enabled')),
                                ]),

                            Section::make('Facebook Conversions API (Server-Side)')
                                ->icon('heroicon-o-bolt')
                                ->description('Tracking server-side direct către Meta Graph API. Trece de adblockere și restricțiile iOS 14.5+ ATT pentru capturarea aproape 100% a conversiilor. Funcționează independent de Meta Pixel browser-side. Pentru deduplicare optimă, folosește același Pixel ID ca la Meta Pixel.')
                                ->visible(fn (?MarketplaceOrganizer $record): bool => $record !== null && self::isFacebookCapiMicroserviceActive($record->marketplace_client_id))
                                ->schema([
                                    Forms\Components\Toggle::make('facebook_capi.enabled')
                                        ->label('Activează Facebook CAPI')
                                        ->helperText('Trimite evenimentele de conversie server-side la Meta')
                                        ->live(),

                                    SC\Grid::make(2)
                                        ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => (bool) $get('facebook_capi.enabled'))
                                        ->schema([
                                            Forms\Components\TextInput::make('facebook_capi.pixel_id')
                                                ->label('Meta Pixel ID')
                                                ->placeholder('1234567890123456')
                                                ->maxLength(50)
                                                ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => (bool) $get('facebook_capi.enabled'))
                                                ->helperText('ID-ul Pixel-ului Meta. De obicei același cu cel din Meta Pixel browser de mai sus.'),

                                            Forms\Components\TextInput::make('facebook_capi.test_event_code')
                                                ->label('Test Event Code (opțional)')
                                                ->placeholder('TEST12345')
                                                ->maxLength(50)
                                                ->helperText('Pentru testare în Events Manager → Test Events. Lasă gol în producție.'),
                                        ]),

                                    Forms\Components\TextInput::make('facebook_capi.access_token')
                                        ->label('System User Access Token')
                                        ->password()
                                        ->revealable()
                                        ->maxLength(500)
                                        ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => (bool) $get('facebook_capi.enabled'))
                                        ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => (bool) $get('facebook_capi.enabled'))
                                        ->helperText(new HtmlString('Token-ul se generează din <a href="https://business.facebook.com/settings/system-users" target="_blank" class="text-primary-600 underline">Meta Business Suite → System Users</a> cu permisiunea <code>ads_management</code>. Tokenul e criptat în baza de date.')),

                                    Forms\Components\TextInput::make('facebook_capi.ad_account_id')
                                        ->label('Ad Account ID (opțional, doar pentru Custom Audiences)')
                                        ->placeholder('123456789012345')
                                        ->maxLength(50)
                                        ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => (bool) $get('facebook_capi.enabled'))
                                        ->helperText('ID-ul contului de Ads (fără prefix `act_`). Necesar doar dacă vrei sincronizare Custom Audiences. Token-ul trebuie să aibă acces la acest cont.'),

                                    \Filament\Schemas\Components\Actions::make([
                                        \Filament\Actions\Action::make('test_facebook_capi_connection')
                                            ->label('Test conexiune cu Meta')
                                            ->icon('heroicon-o-paper-airplane')
                                            ->color('info')
                                            ->action(function (\Filament\Schemas\Components\Utilities\Get $get): void {
                                                $pixelId = trim((string) $get('facebook_capi.pixel_id'));
                                                $accessToken = trim((string) $get('facebook_capi.access_token'));
                                                $testEventCode = trim((string) $get('facebook_capi.test_event_code'));

                                                if ($pixelId === '' || $accessToken === '') {
                                                    Notification::make()
                                                        ->title('Date lipsă')
                                                        ->body('Completează Pixel ID și Access Token înainte de test.')
                                                        ->warning()
                                                        ->send();
                                                    return;
                                                }

                                                $service = app(\App\Services\Integrations\FacebookCapi\FacebookCapiService::class);
                                                $result = $service->testCredentials($pixelId, $accessToken, $testEventCode ?: null);

                                                if ($result['success']) {
                                                    Notification::make()
                                                        ->title('Conexiune reușită')
                                                        ->body($result['message'])
                                                        ->success()
                                                        ->duration(8000)
                                                        ->send();
                                                } else {
                                                    Notification::make()
                                                        ->title('Conexiune eșuată')
                                                        ->body($result['message'])
                                                        ->danger()
                                                        ->duration(10000)
                                                        ->send();
                                                }
                                            }),
                                    ])
                                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => (bool) $get('facebook_capi.enabled')),
                                ]),

                            Section::make('Custom Audiences sync (Meta)')
                                ->icon('heroicon-o-user-group')
                                ->description('Sincronizează automat segmente predefinite de customers în contul de Ads Meta — pentru retargeting, lookalike și suppression. Necesită CAPI activ + Ad Account ID completat. Sync zilnic la 04:15.')
                                ->visible(fn (?MarketplaceOrganizer $record): bool =>
                                    $record !== null
                                    && self::isFacebookCapiMicroserviceActive($record->marketplace_client_id)
                                )
                                ->schema([
                                    Forms\Components\CheckboxList::make('audience_subscriptions')
                                        ->label('Segmente active')
                                        ->options(fn () => \App\Models\CustomerAudienceSegment::where('is_active', true)
                                            ->orderBy('sort_order')
                                            ->pluck('name', 'id')
                                            ->toArray())
                                        ->descriptions(fn () => \App\Models\CustomerAudienceSegment::where('is_active', true)
                                            ->orderBy('sort_order')
                                            ->pluck('description', 'id')
                                            ->toArray())
                                        ->columns(1),

                                    Forms\Components\Placeholder::make('audience_sync_status')
                                        ->label('Stare ultimă sincronizare')
                                        ->visible(fn (?MarketplaceOrganizer $record) => $record !== null)
                                        ->content(function (?MarketplaceOrganizer $record) {
                                            if (!$record) return '—';
                                            $rows = \App\Models\MarketplaceOrganizerAudienceSubscription::where('marketplace_organizer_id', $record->id)
                                                ->with('segment')
                                                ->orderBy('id')
                                                ->get();
                                            if ($rows->isEmpty()) return new HtmlString('<span class="text-gray-500">Nicio sincronizare încă.</span>');
                                            $items = $rows->map(function ($r) {
                                                $when = $r->last_synced_at?->diffForHumans() ?? 'niciodată';
                                                $status = $r->last_sync_status ?? '—';
                                                $color = match ($status) {
                                                    'ok' => 'text-green-600',
                                                    'empty' => 'text-yellow-600',
                                                    'failed' => 'text-red-600',
                                                    default => 'text-gray-500',
                                                };
                                                $name = e($r->segment->name ?? '?');
                                                $member = (int) $r->member_count;
                                                $error = $r->last_sync_error ? ' — ' . e(mb_substr($r->last_sync_error, 0, 200)) : '';
                                                return "<li><strong>{$name}</strong> · <span class=\"{$color}\">{$status}</span> · {$member} membri · {$when}{$error}</li>";
                                            })->implode('');
                                            return new HtmlString('<ul class="list-disc list-inside text-sm space-y-1">' . $items . '</ul>');
                                        }),

                                    \Filament\Schemas\Components\Actions::make([
                                        \Filament\Actions\Action::make('audience_sync_now')
                                            ->label('Sincronizează acum')
                                            ->icon('heroicon-o-arrow-path')
                                            ->color('info')
                                            ->visible(fn (?MarketplaceOrganizer $record) => $record !== null)
                                            ->action(function (?MarketplaceOrganizer $record): void {
                                                if (!$record) return;
                                                $subs = \App\Models\MarketplaceOrganizerAudienceSubscription::where('marketplace_organizer_id', $record->id)
                                                    ->where('is_active', true)
                                                    ->get();
                                                if ($subs->isEmpty()) {
                                                    Notification::make()
                                                        ->title('Niciun segment activ')
                                                        ->body('Bifează cel puțin un segment și salvează înainte de sync.')
                                                        ->warning()
                                                        ->send();
                                                    return;
                                                }
                                                foreach ($subs as $sub) {
                                                    \App\Jobs\SyncMetaCustomAudienceJob::dispatch($sub->id);
                                                }
                                                Notification::make()
                                                    ->title('Sync pornit')
                                                    ->body('Au fost dispatch-uite ' . $subs->count() . ' job-uri. Status-ul apare aici după ce queue worker-ul le procesează (1-5 min).')
                                                    ->success()
                                                    ->send();
                                            }),
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

                                    // Activities Stats — only when the marketplace has the
                                    // activities-module microservice active. Mirror of the
                                    // Events Stats block above but counts from `activities`
                                    // + revenue from `activity_bookings` (paid/confirmed/
                                    // checked_in only).
                                    Section::make('Activities Stats')
                                        ->icon('heroicon-o-rocket-launch')
                                        ->visible(fn (?MarketplaceOrganizer $record): bool => $record !== null && self::marketplaceHasMicroservice('activities-module'))
                                        ->schema([
                                            Forms\Components\Placeholder::make('activities_stats')
                                                ->hiddenLabel()
                                                ->content(fn (?MarketplaceOrganizer $record) => self::renderActivitiesStats($record)),
                                        ]),

                                    Section::make('Listă activități')
                                        ->icon('heroicon-o-list-bullet')
                                        ->description('Toate activitățile organizatorului cu rezervări și venituri.')
                                        ->visible(fn (?MarketplaceOrganizer $record): bool => $record !== null && self::marketplaceHasMicroservice('activities-module'))
                                        ->schema([
                                            Forms\Components\Placeholder::make('activities_list')
                                                ->hiddenLabel()
                                                ->content(fn (?MarketplaceOrganizer $record) => self::renderActivitiesList($record)),
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

                            // Opt-in explicit pentru semantica "floor" a comisionului fix.
                            // Cand bifat: comisionul per bilet = max(rate% * pret, fixed).
                            // Cand debifat (default): comisionul e strict pur procentual
                            // (fixed_commission_default e ignorat in customer/admin/raport,
                            // dar POS on_top pastreaza invariantul istoric max()).
                            Forms\Components\Toggle::make('commission_use_floor')
                                ->label('Calculează cu floor (minim per bilet)')
                                ->hintIcon('heroicon-m-information-circle', tooltip: 'Când e bifat: comisionul per bilet nu poate fi mai mic decât „Fixed Commission" de mai sus. Aplicat în: POS, customer checkout, pagina de vânzări din admin, raport organizator.')
                                ->default(true)
                                ->inline(false),

                            // Opt-in pentru biletul auto-provizionat "Test POS"
                            // (10 lei, meta.is_test) folosit la smoke-test-ul
                            // aplicatiei mobile POS. Default OFF: fara bifa,
                            // evenimentele acestui organizator NU primesc biletul
                            // Test POS (exclus din interfata publica, carduri si
                            // rapoarte prin meta.is_test). Vezi Event::ensureTestTicketType().
                            Forms\Components\Toggle::make('test_pos_enabled')
                                ->label('Bilete Test POS')
                                ->hintIcon('heroicon-m-information-circle', tooltip: 'Când e bifat: fiecare eveniment (non-leisure) al acestui organizator primește automat un bilet „Test POS" de 10 lei pentru testarea aplicației mobile POS (vânzare + print + scanare). Când NU e bifat: biletul nu se mai creează, iar cele deja existente se pot șterge global cu „php artisan test-tickets:prune-disabled".')
                                ->default(true)
                                ->inline(false),

                            // Per-organizer override of marketplace.payment_fees.pass_to_customer.
                            // Visible only when the marketplace has the feature enabled (otherwise
                            // it's a config that goes nowhere). For ambilet/tics this section
                            // doesn't render until they opt in.
                            Forms\Components\Select::make('payment_fee_mode')
                                ->label('Taxă procesare card (Stripe / Netopia / RoPay)')
                                ->options([
                                    'pass_to_customer'       => 'Transferă clientului (linie separată în checkout)',
                                    'absorbed_by_commission' => 'Inclusă în comision (marketplace absoarbe taxa)',
                                ])
                                ->placeholder('Moștenește din marketplace')
                                ->helperText('Lasă gol pentru a moșteni setarea de la nivel de marketplace. Setează doar dacă vrei un deal special pentru acest organizator.')
                                ->native(false)
                                ->visible(fn () => is_array(static::getMarketplaceClient()?->payment_fees ?? null)),

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

    /**
     * Memoized + cached aggregate of SalesBreakdownService results across
     * every event of an organizer. Used by both renderFinancialStats and
     * renderEventsList — previously each helper recomputed independently,
     * so a 50-event organizer ran build() 100 times per page render.
     *
     * Cache layers (most-specific first):
     *  - per-request static cache: same request never recomputes
     *  - Laravel cache 5 min: page reloads inside that window get cached
     *    numbers without hitting the database at all
     *
     * Stored shape per event: just the numbers (total_revenue, total_net,
     * total_commission, tickets count). Full per_type / tt eloquent objects
     * are NOT cached (huge serialization cost, not needed at the org stats
     * level).
     *
     * Stale tolerance: stats can be up to 5 min behind the latest order —
     * acceptable for the admin overview page.
     */
    protected static array $organizerBreakdownsCache = [];

    protected static function getOrganizerBreakdowns(int $organizerId): array
    {
        if (isset(self::$organizerBreakdownsCache[$organizerId])) {
            return self::$organizerBreakdownsCache[$organizerId];
        }

        $cached = \Illuminate\Support\Facades\Cache::remember(
            "organizer:{$organizerId}:breakdowns:v1",
            now()->addMinutes(5),
            function () use ($organizerId) {
                $service = app(\App\Services\Marketplace\SalesBreakdownService::class);
                $events = Event::where('marketplace_organizer_id', $organizerId)
                    ->with(['ticketTypes', 'marketplaceOrganizer', 'marketplaceClient', 'tenant'])
                    ->get();

                $perEvent = [];
                $totalGross = 0.0;
                $totalNet = 0.0;
                $totalCommission = 0.0;
                foreach ($events as $event) {
                    $bd = $service->build($event);
                    $perEvent[$event->id] = [
                        'total_revenue' => (float) $bd['total_revenue'],
                        'total_net' => (float) $bd['total_net'],
                        'total_commission' => (float) $bd['total_commission'],
                        'tickets' => (int) collect($bd['per_type'])->sum('qty'),
                    ];
                    $totalGross += (float) $bd['total_revenue'];
                    $totalNet += (float) $bd['total_net'];
                    $totalCommission += (float) $bd['total_commission'];
                }

                return [
                    'per_event' => $perEvent,
                    'totals' => [
                        'gross' => $totalGross,
                        'net' => $totalNet,
                        'commission' => $totalCommission,
                    ],
                ];
            }
        );

        return self::$organizerBreakdownsCache[$organizerId] = $cached;
    }

    protected static function renderFinancialStats(?MarketplaceOrganizer $record): HtmlString
    {
        if (!$record) return new HtmlString('');

        $data = self::getOrganizerBreakdowns($record->id);
        $totalGross = $data['totals']['gross'];
        $totalNet = $data['totals']['net'];
        $totalCommission = $data['totals']['commission'];

        return new HtmlString("
            <div style='display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;'>
                <div style='text-align: center; padding: 8px;'>
                    <div style='font-size: 16px; font-weight: 700; color: white;'>" . number_format($totalGross, 2) . " RON</div>
                    <div style='font-size: 11px; color: #64748B;'>Total Revenue <span style='color:#475569;'>(live)</span></div>
                </div>
                <div style='text-align: center; padding: 8px;'>
                    <div style='font-size: 16px; font-weight: 700; color: #10B981;'>" . number_format($totalNet, 2) . " RON</div>
                    <div style='font-size: 11px; color: #64748B;'>Net Revenue <span style='color:#475569;'>(live)</span></div>
                </div>
                <div style='text-align: center; padding: 8px;'>
                    <div style='font-size: 16px; font-weight: 700; color: #f87171;'>" . number_format($totalCommission, 2) . " RON</div>
                    <div style='font-size: 11px; color: #64748B;'>Comisioane <span style='color:#475569;'>(live)</span></div>
                </div>
                <div style='text-align: center; padding: 8px;'>
                    <div style='font-size: 16px; font-weight: 700; color: white;'>" . number_format($record->total_paid_out, 2) . " RON</div>
                    <div style='font-size: 11px; color: #64748B;'>Total Paid Out</div>
                </div>
                <div style='text-align: center; padding: 8px;'>
                    <div style='font-size: 16px; font-weight: 700; color: #10B981;'>" . number_format($record->available_balance, 2) . " RON</div>
                    <div style='font-size: 11px; color: #64748B;'>Available Balance</div>
                </div>
                <div style='text-align: center; padding: 8px;'>
                    <div style='font-size: 16px; font-weight: 700; color: #F59E0B;'>" . number_format($record->pending_balance, 2) . " RON</div>
                    <div style='font-size: 11px; color: #64748B;'>Pending Balance</div>
                </div>
            </div>
        ");
    }

    protected static function renderEventsStats(?MarketplaceOrganizer $record): HtmlString
    {
        if (!$record) return new HtmlString('');

        // $record->events() returns MarketplaceEvent (a different model that
        // doesn't carry is_published/is_cancelled/event_date columns). Query
        // the real Event model directly so the filters land on actual data.
        $today = now()->toDateString();
        $eventsBase = Event::where('marketplace_organizer_id', $record->id);
        $totalEvents = (clone $eventsBase)->count();
        $activeEvents = (clone $eventsBase)
            ->where('is_published', true)
            ->where(function ($q) {
                $q->where('is_cancelled', false)->orWhereNull('is_cancelled');
            })
            ->count();
        $upcomingEvents = (clone $eventsBase)
            ->where('is_published', true)
            ->where(function ($q) {
                $q->where('is_cancelled', false)->orWhereNull('is_cancelled');
            })
            ->where(function ($q) use ($today) {
                $q->whereDate('event_date', '>=', $today)
                  ->orWhereDate('range_end_date', '>=', $today);
            })
            ->count();
        $completedEvents = (clone $eventsBase)
            ->where(function ($q) use ($today) {
                $q->whereDate('event_date', '<', $today)
                  ->orWhere(function ($q2) use ($today) {
                      $q2->whereNotNull('range_end_date')->whereDate('range_end_date', '<', $today);
                  });
            })
            ->count();

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

        // Pull per-event breakdowns from the shared memoized/cached helper
        // (same numbers as the per-event Sales tab, computed once per
        // request and reused by renderFinancialStats).
        $breakdowns = self::getOrganizerBreakdowns($record->id)['per_event'];

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

            $bd = $breakdowns[$event->id] ?? null;
            $netToOrganizer = $bd['total_net'] ?? 0.0;
            $commission     = $bd['total_commission'] ?? 0.0;
            $tickets        = $bd['tickets'] ?? 0;

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

    /**
     * Activities tile grid — total / published / draft / inactive variants.
     * Parallel to renderEventsStats; only fires when the activities-module
     * microservice is active for the marketplace (gated at the Section
     * `visible` level too).
     */
    protected static function renderActivitiesStats(?MarketplaceOrganizer $record): HtmlString
    {
        if (! $record) return new HtmlString('');

        $base = Activity::where('marketplace_organizer_id', $record->id);
        $total = (clone $base)->count();
        $published = (clone $base)->where('is_published', true)->count();
        $drafts = $total - $published;

        // Variants count = sum of active variants across all the organizer's activities
        $variantsCount = DB::table('activity_variants')
            ->join('activities', 'activities.id', '=', 'activity_variants.activity_id')
            ->where('activities.marketplace_organizer_id', $record->id)
            ->whereNull('activity_variants.deleted_at')
            ->where('activity_variants.is_active', true)
            ->count();

        // Revenue + booking count from activity_bookings (paid + confirmed + checked_in)
        $bookingStats = DB::table('activity_bookings')
            ->join('activities', 'activities.id', '=', 'activity_bookings.activity_id')
            ->where('activities.marketplace_organizer_id', $record->id)
            ->whereIn('activity_bookings.status', ['paid', 'confirmed', 'checked_in'])
            ->whereNull('activity_bookings.deleted_at')
            ->selectRaw('COUNT(*) AS bookings, SUM(total_cents) AS revenue, SUM(commission_cents) AS commission, SUM(participants_count) AS participants')
            ->first();

        $bookings     = (int) ($bookingStats->bookings ?? 0);
        $revenue      = number_format(((int) ($bookingStats->revenue ?? 0)) / 100, 2, ',', '.');
        $commission   = number_format(((int) ($bookingStats->commission ?? 0)) / 100, 2, ',', '.');
        $participants = (int) ($bookingStats->participants ?? 0);

        $tile = fn ($label, $value, $color = 'white') => "
            <div style='background: #0F172A; border-radius: 8px; padding: 12px; text-align: center;'>
                <div style='font-size: 18px; font-weight: 700; color: {$color};'>{$value}</div>
                <div style='font-size: 10px; color: #64748B; text-transform: uppercase;'>{$label}</div>
            </div>
        ";

        return new HtmlString("
            <div style='display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px;'>
                " . $tile('Total activități', $total) . "
                " . $tile('Publicate', $published, '#10B981') . "
                " . $tile('În draft', $drafts, '#F59E0B') . "
                " . $tile('Variante active', $variantsCount, '#A78BFA') . "
                " . $tile('Rezervări confirmate', $bookings, '#38BDF8') . "
                " . $tile('Participanți', $participants, '#38BDF8') . "
                " . $tile('Venit (lei)', $revenue, '#10B981') . "
                " . $tile('Comision (lei)', $commission, '#EF4444') . "
            </div>
        ");
    }

    /**
     * Per-activity table — count + revenue + commission for each activity the
     * organizer owns. Mirrors renderEventsList shape but aggregates from
     * activity_bookings instead of orders.
     */
    protected static function renderActivitiesList(?MarketplaceOrganizer $record): HtmlString
    {
        if (! $record) return new HtmlString('');

        $activities = Activity::where('marketplace_organizer_id', $record->id)
            ->with(['city:id,name,slug', 'category:id,name,slug'])
            ->orderByDesc('updated_at')
            ->get();

        if ($activities->isEmpty()) {
            return new HtmlString('<p style="color:#94A3B8;padding:1rem 0;">Niciun rezultat — adaugă activități pentru acest organizator.</p>');
        }

        // Single query to pull per-activity totals so we don't N+1.
        $stats = DB::table('activity_bookings')
            ->select('activity_id')
            ->selectRaw('COUNT(*) AS bookings')
            ->selectRaw("SUM(CASE WHEN status IN ('paid','confirmed','checked_in') THEN total_cents ELSE 0 END) AS revenue")
            ->selectRaw("SUM(CASE WHEN status IN ('paid','confirmed','checked_in') THEN commission_cents ELSE 0 END) AS commission")
            ->selectRaw("SUM(CASE WHEN status IN ('paid','confirmed','checked_in') THEN participants_count ELSE 0 END) AS participants")
            ->whereIn('activity_id', $activities->pluck('id'))
            ->whereNull('deleted_at')
            ->groupBy('activity_id')
            ->get()
            ->keyBy('activity_id');

        $rows = '';
        foreach ($activities as $a) {
            $title = is_array($a->title) ? ($a->title['ro'] ?? $a->title['en'] ?? $a->slug) : ($a->title ?? $a->slug);
            $cityName = $a->city ? (is_array($a->city->name) ? ($a->city->name['ro'] ?? $a->city->name['en'] ?? $a->city->slug) : $a->city->name) : '—';
            $catName = $a->category ? (is_array($a->category->name) ? ($a->category->name['ro'] ?? $a->category->name['en'] ?? $a->category->slug) : $a->category->name) : '—';
            $published = $a->is_published
                ? '<span style="background:#10B98133;color:#10B981;padding:2px 8px;border-radius:9999px;font-size:11px;">LIVE</span>'
                : '<span style="background:#F59E0B33;color:#F59E0B;padding:2px 8px;border-radius:9999px;font-size:11px;">DRAFT</span>';
            $s = $stats[$a->id] ?? null;
            $bookings = (int) ($s->bookings ?? 0);
            $revenue = number_format(((int) ($s->revenue ?? 0)) / 100, 2, ',', '.');
            $commission = number_format(((int) ($s->commission ?? 0)) / 100, 2, ',', '.');
            $participants = (int) ($s->participants ?? 0);

            $editUrl = e(ActivityResource::getUrl('edit', ['record' => $a->id]));

            $rows .= "<tr style='border-top:1px solid rgba(100,116,139,0.2);'>"
                . "<td style='padding:8px 12px;'><a href='{$editUrl}' style='color:#60A5FA;font-weight:600;' target='_blank'>" . e($title) . "</a><div style='font-size:11px;color:#94A3B8;'>" . e($cityName) . ' · ' . e($catName) . "</div></td>"
                . "<td style='padding:8px 12px;text-align:center;'>{$published}</td>"
                . "<td style='padding:8px 12px;text-align:right;'>{$bookings}</td>"
                . "<td style='padding:8px 12px;text-align:right;'>{$participants}</td>"
                . "<td style='padding:8px 12px;text-align:right;font-weight:600;color:#10B981;'>{$revenue} lei</td>"
                . "<td style='padding:8px 12px;text-align:right;color:#EF4444;'>{$commission} lei</td>"
                . "</tr>";
        }

        $html = '<div style="overflow-x:auto;">'
            . '<table style="width:100%;border-collapse:collapse;font-size:13px;color:#CBD5E1;">'
            . '<thead><tr style="text-align:left;">'
            . '<th style="padding:8px 12px;font-size:11px;color:#64748B;text-transform:uppercase;letter-spacing:.05em;">Activitate</th>'
            . '<th style="padding:8px 12px;font-size:11px;color:#64748B;text-transform:uppercase;letter-spacing:.05em;text-align:center;">Status</th>'
            . '<th style="padding:8px 12px;font-size:11px;color:#64748B;text-transform:uppercase;letter-spacing:.05em;text-align:right;">Rezervări</th>'
            . '<th style="padding:8px 12px;font-size:11px;color:#64748B;text-transform:uppercase;letter-spacing:.05em;text-align:right;">Participanți</th>'
            . '<th style="padding:8px 12px;font-size:11px;color:#64748B;text-transform:uppercase;letter-spacing:.05em;text-align:right;">Venit</th>'
            . '<th style="padding:8px 12px;font-size:11px;color:#64748B;text-transform:uppercase;letter-spacing:.05em;text-align:right;">Comision</th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
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

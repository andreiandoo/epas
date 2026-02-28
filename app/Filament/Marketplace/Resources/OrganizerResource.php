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
use Illuminate\Validation\Rules\Unique;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;

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
                                ->dehydrateStateUsing(fn ($state) => \Illuminate\Support\Facades\Hash::make($state))
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

                    Section::make('Bank Accounts')
                        ->icon('heroicon-o-credit-card')
                        ->description('Manage organizer bank accounts for payouts. The primary account will be used for payments.')
                        ->visible(fn (?MarketplaceOrganizer $record): bool => $record !== null)
                        ->schema([
                            Forms\Components\Placeholder::make('bank_accounts_list')
                                ->hiddenLabel()
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
                                ->maxItems(5)
                                ->collapsible()
                                ->collapsed()
                                ->itemLabel(fn (array $state): ?string => $state['bank_name'] ?? 'New Account'),
                        ]),

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
                        ]),
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

                    Section::make('Financial Summary')
                    ->icon('heroicon-o-currency-dollar')
                    ->compact()
                    ->visible(fn (?MarketplaceOrganizer $record): bool => $record !== null)
                    ->extraAttributes(['class' => 'bg-gradient-to-r from-emerald-500/10 to-emerald-600/5 border-emerald-500/30'])
                    ->schema([
                        Forms\Components\Placeholder::make('financial_stats')
                            ->hiddenLabel()
                            ->content(fn (?MarketplaceOrganizer $record) => self::renderFinancialStats($record)),
                    ]),

                    // Quick Actions (doar pe Edit/View)
                    Section::make('Quick Actions')
                        ->icon('heroicon-o-bolt')
                        ->compact()
                        ->visible(fn (?MarketplaceOrganizer $record): bool => $record !== null)
                        ->schema([
                            SC\Actions::make([
                                Action::make('view_events')
                                    ->label('View Events')
                                    ->icon('heroicon-o-calendar')
                                    ->color('gray')
                                    ->url(fn ($record) => EventResource::getUrl('index', ['organizer' => $record->id])),
                                Action::make('create_event')
                                    ->label('Create Event')
                                    ->icon('heroicon-o-plus')
                                    ->color('gray')
                                    ->url(fn ($record) => EventResource::getUrl('create', ['organizer' => $record->id])),
                                Action::make('view_contract')
                                    ->label('Vezi Contract')
                                    ->icon('heroicon-o-document-text')
                                    ->color('primary')
                                    ->visible(fn ($record) => \App\Models\OrganizerDocument::where('marketplace_organizer_id', $record->id)
                                        ->where('document_type', 'organizer_contract')
                                        ->exists())
                                    ->url(fn ($record) => \App\Models\OrganizerDocument::where('marketplace_organizer_id', $record->id)
                                        ->where('document_type', 'organizer_contract')
                                        ->latest('issued_at')
                                        ->first()?->download_url, shouldOpenInNewTab: true),
                                Action::make('view_balance')
                                    ->label('View Balance')
                                    ->icon('heroicon-o-wallet')
                                    ->color('warning')
                                    ->url(fn ($record) => url('/marketplace/organizers/' . $record->id . '/balance')),
                                Action::make('create_payout')
                                    ->label('Create Payout')
                                    ->icon('heroicon-o-banknotes')
                                    ->color('info')
                                    ->visible(fn ($record) => $record->available_balance > 0)
                                    ->url(fn ($record) => url('/marketplace/organizers/' . $record->id . '/balance')),
                                Action::make('suspend')
                                    ->label('Suspend Organizer')
                                    ->icon('heroicon-o-x-circle')
                                    ->color('danger')
                                    ->requiresConfirmation()
                                    ->visible(fn ($record) => $record->status === 'active')
                                    ->action(fn ($record) => $record->update(['status' => 'suspended'])),
                                Action::make('reactivate')
                                    ->label('Reactivate')
                                    ->icon('heroicon-o-arrow-path')
                                    ->color('success')
                                    ->visible(fn ($record) => $record->status === 'suspended')
                                    ->action(fn ($record) => $record->update(['status' => 'active'])),
                            ]),
                        ]),

                    // Events Stats (doar pe Edit/View)
                    Section::make('Events Stats')
                        ->icon('heroicon-o-chart-bar')
                        ->compact()
                        ->collapsible()
                        ->visible(fn (?MarketplaceOrganizer $record): bool => $record !== null)
                        ->schema([
                            Forms\Components\Placeholder::make('events_stats')
                                ->hiddenLabel()
                                ->content(fn (?MarketplaceOrganizer $record) => self::renderEventsStats($record)),
                        ]),

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
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&background=10b981&color=fff'),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'suspended' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-circle'),

                Tables\Columns\TextColumn::make('total_events')
                    ->label('Events')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_revenue')
                    ->label('Revenue')
                    ->money('RON')
                    ->sortable(),

                Tables\Columns\TextColumn::make('available_balance')
                    ->label('Balance')
                    ->money('RON')
                    ->sortable(),

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

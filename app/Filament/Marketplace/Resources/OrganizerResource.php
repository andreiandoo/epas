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
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;

class OrganizerResource extends Resource
{
    protected static ?string $model = MarketplaceOrganizer::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';

    protected static \UnitEnum|string|null $navigationGroup = 'Organizers';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        $marketplaceAdmin = Auth::guard('marketplace_admin')->user();
        if (!$marketplaceAdmin) return null;

        $count = static::getEloquentQuery()
            ->where('status', 'pending')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
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
                                ->maxLength(255),

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

                    Section::make('Company Information')
                        ->icon('heroicon-o-building-office')
                        ->schema([
                            Forms\Components\TextInput::make('company_name')
                                ->maxLength(255),

                            Forms\Components\TextInput::make('company_tax_id')
                                ->label('Tax ID / VAT')
                                ->maxLength(50),

                            Forms\Components\TextInput::make('company_registration')
                                ->label('Registration Number')
                                ->maxLength(100),

                            Forms\Components\Textarea::make('company_address')
                                ->rows(2)
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('bank_name')
                                ->label('Bank Name')
                                ->maxLength(255)
                                ->placeholder('e.g., ING Bank, BRD, BCR'),

                            Forms\Components\TextInput::make('iban')
                                ->label('IBAN')
                                ->maxLength(34)
                                ->placeholder('e.g., RO49AAAA1B31007593840000')
                                ->helperText('Used for payouts to this organizer'),
                        ])
                        ->columns(2),

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
                                Action::make('create_payout')
                                    ->label('Create Payout')
                                    ->icon('heroicon-o-banknotes')
                                    ->color('info')
                                    ->visible(fn ($record) => $record->available_balance > 0),
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
}

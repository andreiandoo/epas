<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\OrganizerPromoCodeResource\Pages;
use App\Models\MarketplaceOrganizerPromoCode;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceEvent;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\BulkAction;

class OrganizerPromoCodeResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceOrganizerPromoCode::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = 'Organizer Promo Codes';

    protected static ?string $navigationParentItem = 'Organizers';

    protected static \UnitEnum|string|null $navigationGroup = 'Organizers';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Promo Code';

    protected static ?string $pluralModelLabel = 'Organizer Promo Codes';

    protected static ?string $slug = 'organizer-promo-codes';

    public static function getEloquentQuery(): Builder
    {
        $marketplaceClientId = static::getMarketplaceClientId();

        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $marketplaceClientId)
            ->with(['organizer', 'event']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice('coupon-codes');
    }

    public static function getNavigationBadge(): ?string
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) {
            return null;
        }

        $count = MarketplaceOrganizerPromoCode::where('marketplace_client_id', $marketplace->id)
            ->where('status', 'active')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();

        return $schema
            ->schema([
                Forms\Components\Hidden::make('marketplace_client_id')
                    ->default($marketplace?->id),

                SC\Grid::make(3)
                    ->schema([
                        // Left column (2/3)
                        SC\Grid::make(1)
                            ->columnSpan(2)
                            ->schema([
                                SC\Section::make('Promo Code Details')
                                    ->schema([
                                        Forms\Components\Select::make('marketplace_organizer_id')
                                            ->label('Organizer')
                                            ->options(function () use ($marketplace) {
                                                return MarketplaceOrganizer::where('marketplace_client_id', $marketplace?->id)
                                                    ->where('status', 'active')
                                                    ->pluck('name', 'id');
                                            })
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(fn ($set) => $set('marketplace_event_id', null)),

                                        Forms\Components\TextInput::make('code')
                                            ->label('Promo Code')
                                            ->placeholder('Leave empty for auto-generated code')
                                            ->maxLength(50)
                                            ->helperText('Will be converted to uppercase'),

                                        Forms\Components\TextInput::make('name')
                                            ->label('Internal Name')
                                            ->required()
                                            ->maxLength(255),

                                        Forms\Components\Textarea::make('description')
                                            ->label('Description')
                                            ->rows(2)
                                            ->maxLength(500),
                                    ])->columns(2),

                                SC\Section::make('Discount Configuration')
                                    ->schema([
                                        Forms\Components\Select::make('type')
                                            ->label('Discount Type')
                                            ->options([
                                                'percentage' => 'Percentage (%)',
                                                'fixed' => 'Fixed Amount',
                                            ])
                                            ->default('percentage')
                                            ->required()
                                            ->live(),

                                        Forms\Components\TextInput::make('value')
                                            ->label('Discount Value')
                                            ->numeric()
                                            ->required()
                                            ->minValue(0)
                                            ->suffix(fn ($get) => $get('type') === 'percentage' ? '%' : 'RON'),

                                        Forms\Components\TextInput::make('max_discount_amount')
                                            ->label('Maximum Discount')
                                            ->numeric()
                                            ->minValue(0)
                                            ->suffix('RON')
                                            ->helperText('Cap for percentage discounts'),

                                        Forms\Components\TextInput::make('min_purchase_amount')
                                            ->label('Minimum Purchase')
                                            ->numeric()
                                            ->minValue(0)
                                            ->suffix('RON'),

                                        Forms\Components\TextInput::make('min_tickets')
                                            ->label('Minimum Tickets')
                                            ->numeric()
                                            ->minValue(1)
                                            ->integer(),
                                    ])->columns(3),

                                SC\Section::make('Applicability')
                                    ->description('Define what this promo code applies to')
                                    ->schema([
                                        Forms\Components\Select::make('applies_to')
                                            ->label('Applies To')
                                            ->options([
                                                'all_events' => 'All Organizer Events',
                                                'specific_event' => 'Specific Event',
                                                'ticket_type' => 'Specific Ticket Type',
                                            ])
                                            ->default('all_events')
                                            ->required()
                                            ->live(),

                                        Forms\Components\Select::make('marketplace_event_id')
                                            ->label('Event')
                                            ->options(function ($get) use ($marketplace) {
                                                $organizerId = $get('marketplace_organizer_id');
                                                if (!$organizerId) {
                                                    return [];
                                                }
                                                return MarketplaceEvent::where('marketplace_client_id', $marketplace?->id)
                                                    ->where('marketplace_organizer_id', $organizerId)
                                                    ->pluck('title', 'id');
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->visible(fn ($get) => in_array($get('applies_to'), ['specific_event', 'ticket_type']))
                                            ->requiredIf('applies_to', 'specific_event'),

                                        Forms\Components\Select::make('ticket_type_id')
                                            ->label('Ticket Type')
                                            ->options(function ($get) {
                                                $eventId = $get('marketplace_event_id');
                                                if (!$eventId) {
                                                    return [];
                                                }
                                                $event = MarketplaceEvent::find($eventId);
                                                if (!$event) {
                                                    return [];
                                                }
                                                return $event->ticketTypes()->pluck('name', 'id');
                                            })
                                            ->searchable()
                                            ->visible(fn ($get) => $get('applies_to') === 'ticket_type')
                                            ->requiredIf('applies_to', 'ticket_type'),
                                    ])->columns(3),
                            ]),

                        // Right column (1/3)
                        SC\Grid::make(1)
                            ->columnSpan(1)
                            ->schema([
                                SC\Section::make('Status & Validity')
                                    ->schema([
                                        Forms\Components\Select::make('status')
                                            ->options([
                                                'draft' => 'Draft',
                                                'active' => 'Active',
                                                'inactive' => 'Inactive',
                                                'expired' => 'Expired',
                                                'exhausted' => 'Exhausted',
                                            ])
                                            ->default('draft')
                                            ->required(),

                                        Forms\Components\Toggle::make('is_public')
                                            ->label('Public Code')
                                            ->helperText('Visible on event pages'),

                                        Forms\Components\DateTimePicker::make('starts_at')
                                            ->label('Starts At'),

                                        Forms\Components\DateTimePicker::make('expires_at')
                                            ->label('Expires At'),
                                    ]),

                                SC\Section::make('Usage Limits')
                                    ->schema([
                                        Forms\Components\TextInput::make('usage_limit')
                                            ->label('Total Usage Limit')
                                            ->numeric()
                                            ->minValue(1)
                                            ->integer()
                                            ->helperText('Leave empty for unlimited'),

                                        Forms\Components\TextInput::make('usage_limit_per_customer')
                                            ->label('Per Customer Limit')
                                            ->numeric()
                                            ->minValue(1)
                                            ->integer()
                                            ->default(1),

                                        Forms\Components\Placeholder::make('usage_count_display')
                                            ->label('Times Used')
                                            ->content(fn ($record) => $record?->usage_count ?? 0)
                                            ->visible(fn ($record) => $record !== null),
                                    ]),
                            ]),
                    ]),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('organizer.name')
                    ->label('Organizer')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'percentage' ? 'Percent' : 'Fixed'),

                Tables\Columns\TextColumn::make('value')
                    ->label('Value')
                    ->formatStateUsing(fn ($state, $record) =>
                        $record->type === 'percentage' ? "{$state}%" : number_format($state, 2) . ' RON'
                    ),

                Tables\Columns\TextColumn::make('applies_to')
                    ->label('Applies To')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'all_events' => 'success',
                        'specific_event' => 'warning',
                        'ticket_type' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'all_events' => 'All Events',
                        'specific_event' => 'Specific Event',
                        'ticket_type' => 'Ticket Type',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('usage_count')
                    ->label('Used')
                    ->formatStateUsing(fn ($state, $record) =>
                        $record->usage_limit ? "{$state}/{$record->usage_limit}" : $state
                    ),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'draft',
                        'success' => 'active',
                        'warning' => 'inactive',
                        'danger' => ['expired', 'exhausted'],
                    ]),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'expired' => 'Expired',
                        'exhausted' => 'Exhausted',
                    ]),

                Tables\Filters\SelectFilter::make('marketplace_organizer_id')
                    ->label('Organizer')
                    ->relationship('organizer', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('applies_to')
                    ->options([
                        'all_events' => 'All Events',
                        'specific_event' => 'Specific Event',
                        'ticket_type' => 'Ticket Type',
                    ]),

                Tables\Filters\TernaryFilter::make('is_public')
                    ->label('Public'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),

                Actions\Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status !== 'active')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->activate();
                        Notification::make()
                            ->success()
                            ->title('Promo code activated')
                            ->send();
                    }),

                Actions\Action::make('deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === 'active')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->deactivate();
                        Notification::make()
                            ->warning()
                            ->title('Promo code deactivated')
                            ->send();
                    }),

                Actions\Action::make('copy_code')
                    ->label('Copy')
                    ->icon('heroicon-o-clipboard')
                    ->action(function ($record, $livewire) {
                        $livewire->js("navigator.clipboard.writeText('{$record->code}')");
                        Notification::make()
                            ->success()
                            ->title('Code copied to clipboard')
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),

                    BulkAction::make('bulk_activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->activate()),

                    BulkAction::make('bulk_deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->deactivate()),
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
            'index' => Pages\ListOrganizerPromoCodes::route('/'),
            'create' => Pages\CreateOrganizerPromoCode::route('/create'),
            'view' => Pages\ViewOrganizerPromoCode::route('/{record}'),
            'edit' => Pages\EditOrganizerPromoCode::route('/{record}/edit'),
        ];
    }
}

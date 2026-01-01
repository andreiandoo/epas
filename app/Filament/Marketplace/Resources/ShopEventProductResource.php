<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\ShopEventProductResource\Pages;
use App\Models\Event;
use App\Models\Shop\ShopEventProduct;
use App\Models\Shop\ShopProduct;
use App\Models\TicketType;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class ShopEventProductResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = ShopEventProduct::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationLabel = 'Event Products';

    protected static ?string $modelLabel = 'Event Product';

    protected static ?string $pluralModelLabel = 'Event Products';

    protected static \UnitEnum|string|null $navigationGroup = 'Services';

    protected static ?string $navigationParentItem = 'Shop';

    protected static ?int $navigationSort = 70;

        public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice('shop');
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = static::getMarketplaceClient()?->id;

        return parent::getEloquentQuery()
            ->whereHas('event', fn($q) => $q->where('marketplace_client_id', $tenantId));
    }

    public static function form(Schema $schema): Schema
    {
        $tenantId = static::getMarketplaceClient()?->id;
        $marketplaceLanguage = static::getMarketplaceClient()?->language ?? 'en';

        return $schema
            ->schema([
                SC\Section::make('Association Details')
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->label('Event')
                            ->options(
                                Event::where('marketplace_client_id', $tenantId)
                                    ->where('is_cancelled', false)
                                    ->orderBy('event_date', 'desc')
                                    ->get()
                                    ->mapWithKeys(fn($e) => [$e->id => $e->getTranslation('title', $marketplaceLanguage)])
                            )
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn(\Filament\Schemas\Components\Utilities\Set $set) => $set('ticket_type_id', null)),

                        Forms\Components\Select::make('product_id')
                            ->label('Product')
                            ->options(function () use ($tenantId, $marketplaceLanguage) {
                                return ShopProduct::where('marketplace_client_id', $tenantId)
                                    ->where('status', 'active')
                                    ->get()
                                    ->mapWithKeys(fn($p) => [$p->id => $p->getTranslation('title', $marketplaceLanguage)]);
                            })
                            ->searchable()
                            ->required(),

                        Forms\Components\Select::make('association_type')
                            ->label('Association Type')
                            ->options([
                                'upsell' => 'Upsell - Show during checkout',
                                'bundle' => 'Bundle - Included with ticket',
                            ])
                            ->required()
                            ->live()
                            ->helperText(fn($state) => match($state) {
                                'upsell' => 'Product will be offered as an add-on during event checkout',
                                'bundle' => 'Product will be automatically included when purchasing the selected ticket type',
                                default => '',
                            }),

                        Forms\Components\Select::make('ticket_type_id')
                            ->label('Ticket Type')
                            ->options(function (\Filament\Schemas\Components\Utilities\Get $get) {
                                $eventId = $get('event_id');
                                if (!$eventId) return [];

                                return TicketType::where('event_id', $eventId)
                                    ->get()
                                    ->mapWithKeys(fn($t) => [$t->id => $t->name]);
                            })
                            ->searchable()
                            ->helperText(fn($state, \Filament\Schemas\Components\Utilities\Get $get) => $get('association_type') === 'bundle'
                                ? 'Required for bundles - product will be included with this ticket type'
                                : 'Optional for upsells - leave empty to show for all ticket types')
                            ->required(fn(\Filament\Schemas\Components\Utilities\Get $get) => $get('association_type') === 'bundle')
                            ->visible(fn(\Filament\Schemas\Components\Utilities\Get $get) => $get('event_id') !== null),

                        Forms\Components\TextInput::make('quantity_included')
                            ->label('Quantity Included')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->visible(fn(\Filament\Schemas\Components\Utilities\Get $get) => $get('association_type') === 'bundle')
                            ->helperText('Number of this product included per ticket'),
                    ])
                    ->columns(2),

                SC\Section::make('Display Settings')
                    ->schema([
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers appear first'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive products will not be shown or included'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        $marketplaceLanguage = static::getMarketplaceClient()?->language ?? 'en';

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event.title')
                    ->label('Event')
                    ->formatStateUsing(fn($record) => $record->event?->getTranslation('title', $marketplaceLanguage))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.title')
                    ->label('Product')
                    ->formatStateUsing(fn($record) => $record->product?->getTranslation('title', $marketplaceLanguage))
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('association_type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'upsell',
                        'success' => 'bundle',
                    ])
                    ->formatStateUsing(fn($state) => ucfirst($state)),

                Tables\Columns\TextColumn::make('ticketType.name')
                    ->label('Ticket Type')
                    ->formatStateUsing(fn($record) => $record->ticketType?->name ?? 'All')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('quantity_included')
                    ->label('Qty')
                    ->visible(fn($livewire) => $livewire->tableFilters['association_type']['value'] ?? null === 'bundle'),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('association_type')
                    ->options([
                        'upsell' => 'Upsells',
                        'bundle' => 'Bundles',
                    ]),

                Tables\Filters\SelectFilter::make('event_id')
                    ->label('Event')
                    ->options(function () {
                        $tenantId = static::getMarketplaceClient()?->id;
                        $marketplaceLanguage = static::getMarketplaceClient()?->language ?? 'en';

                        return Event::where('marketplace_client_id', $tenantId)
                            ->orderBy('event_date', 'desc')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn($e) => [$e->id => $e->getTranslation('title', $marketplaceLanguage)]);
                    })
                    ->searchable(),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                EditAction::make(),
                Action::make('toggle')
                    ->label(fn($record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn($record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn($record) => $record->is_active ? 'warning' : 'success')
                    ->action(fn($record) => $record->update(['is_active' => !$record->is_active])),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShopEventProducts::route('/'),
            'create' => Pages\CreateShopEventProduct::route('/create'),
            'edit' => Pages\EditShopEventProduct::route('/{record}/edit'),
        ];
    }
}

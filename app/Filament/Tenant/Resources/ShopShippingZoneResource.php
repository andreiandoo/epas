<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\ShopShippingZoneResource\Pages;
use App\Filament\Tenant\Resources\ShopShippingZoneResource\RelationManagers;
use App\Models\Shop\ShopShippingZone;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;

class ShopShippingZoneResource extends Resource
{
    protected static ?string $model = ShopShippingZone::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Shipping';

    protected static ?string $navigationParentItem = 'Shop';

    protected static \UnitEnum|string|null $navigationGroup = 'Services';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Shipping Zone';

    protected static ?string $pluralModelLabel = 'Shipping Zones';

    protected static ?string $slug = 'shop-shipping';

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()->where('tenant_id', $tenant?->id);
    }

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()->tenant;
        if (!$tenant) return false;

        return $tenant->microservices()
            ->where('slug', 'shop')
            ->wherePivot('is_active', true)
            ->exists();
    }

    public static function form(Schema $schema): Schema
    {
        $tenant = auth()->user()->tenant;
        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';

        return $schema
            ->schema([
                Forms\Components\Hidden::make('tenant_id')
                    ->default($tenant?->id),

                SC\Section::make('Zone Details')
                    ->schema([
                        Forms\Components\TextInput::make("name.{$tenantLanguage}")
                            ->label('Zone Name')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('e.g., Romania, Europe, Worldwide'),

                        Forms\Components\TagsInput::make('countries')
                            ->label('Countries')
                            ->placeholder('Add country codes (e.g., RO, DE, FR)')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Use ISO 3166-1 alpha-2 country codes. Leave empty for "Rest of World"'),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])->columns(2),

                SC\Section::make('Shipping Methods')
                    ->schema([
                        Forms\Components\Repeater::make('methods')
                            ->relationship('methods')
                            ->schema([
                                Forms\Components\TextInput::make("name.{$tenantLanguage}")
                                    ->label('Method Name')
                                    ->required()
                                    ->maxLength(100)
                                    ->placeholder('e.g., Standard Shipping, Express'),

                                Forms\Components\Select::make('type')
                                    ->label('Type')
                                    ->options([
                                        'flat_rate' => 'Flat Rate',
                                        'free' => 'Free Shipping',
                                        'weight_based' => 'Weight Based',
                                        'price_based' => 'Price Based',
                                    ])
                                    ->default('flat_rate')
                                    ->required()
                                    ->live(),

                                Forms\Components\TextInput::make('price_cents')
                                    ->label('Price (cents)')
                                    ->numeric()
                                    ->default(0)
                                    ->visible(fn ($get) => $get('type') === 'flat_rate')
                                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Enter price in cents'),

                                Forms\Components\TextInput::make('free_shipping_threshold_cents')
                                    ->label('Free Shipping Above (cents)')
                                    ->numeric()
                                    ->placeholder('No threshold')
                                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Free shipping when order exceeds this amount'),

                                Forms\Components\TextInput::make('min_order_cents')
                                    ->label('Min Order (cents)')
                                    ->numeric()
                                    ->placeholder('No minimum'),

                                Forms\Components\TextInput::make('max_order_cents')
                                    ->label('Max Order (cents)')
                                    ->numeric()
                                    ->placeholder('No maximum'),

                                Forms\Components\TextInput::make('estimated_days_min')
                                    ->label('Est. Days (min)')
                                    ->numeric()
                                    ->default(3),

                                Forms\Components\TextInput::make('estimated_days_max')
                                    ->label('Est. Days (max)')
                                    ->numeric()
                                    ->default(5),

                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),

                                Forms\Components\TextInput::make('sort_order')
                                    ->label('Order')
                                    ->numeric()
                                    ->default(0),
                            ])
                            ->columns(5)
                            ->defaultItems(1)
                            ->addActionLabel('Add Shipping Method')
                            ->collapsible()
                            ->reorderable()
                            ->orderColumn('sort_order')
                            ->itemLabel(fn (array $state) => $state['name'][$tenantLanguage] ?? 'New Method'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $tenant = auth()->user()->tenant;
        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';

        return $table
            ->columns([
                Tables\Columns\TextColumn::make("name.{$tenantLanguage}")
                    ->label('Zone Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('countries')
                    ->label('Countries')
                    ->formatStateUsing(function ($state) {
                        if (empty($state)) return 'Rest of World';
                        if (is_array($state)) {
                            return count($state) > 3
                                ? implode(', ', array_slice($state, 0, 3)) . '...'
                                : implode(', ', $state);
                        }
                        return $state;
                    }),

                Tables\Columns\TextColumn::make('methods_count')
                    ->label('Methods')
                    ->counts('methods')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                EditAction::make(),
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
            'index' => Pages\ListShopShippingZones::route('/'),
            'create' => Pages\CreateShopShippingZone::route('/create'),
            'edit' => Pages\EditShopShippingZone::route('/{record}/edit'),
        ];
    }
}

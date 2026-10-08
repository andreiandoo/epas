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

    public static function getNavigationLabel(): string
    {
        return __('Shipping');
    }

    protected static ?string $navigationParentItem = 'Shop';

    public static function getNavigationParentItem(): ?string
    {
        return __('Shop');
    }

    protected static \UnitEnum|string|null $navigationGroup = 'Services';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('Services');
    }

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Shipping Zone';

    public static function getModelLabel(): string
    {
        return __('Shipping Zone');
    }

    protected static ?string $pluralModelLabel = 'Shipping Zones';

    public static function getPluralModelLabel(): string
    {
        return __('Shipping Zones');
    }

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

                SC\Section::make(__('Zone Details'))
                    ->schema([
                        Forms\Components\TextInput::make("name.{$tenantLanguage}")
                            ->label(__('Zone Name'))
                            ->required()
                            ->maxLength(100)
                            ->placeholder(__('e.g., Romania, Europe, Worldwide')),

                        Forms\Components\TagsInput::make('countries')
                            ->label(__('Countries'))
                            ->placeholder(__('Add country codes (e.g., RO, DE, FR)'))
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Use ISO 3166-1 alpha-2 country codes. Leave empty for "Rest of World"'),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ]),

                SC\Section::make(__('Shipping Methods'))
                    ->schema([
                        Forms\Components\Repeater::make('methods')
                            ->relationship('methods')
                            ->schema([
                                SC\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make("name.{$tenantLanguage}")
                                            ->label(__('Method Name'))
                                            ->required()
                                            ->maxLength(100)
                                            ->placeholder(__('e.g., Standard Shipping, Express')),

                                        Forms\Components\Select::make('calculation_type')
                                            ->label(__('Type'))
                                            ->options([
                                                'flat' => __('Flat Rate'),
                                                'free' => 'Free Shipping',
                                                'weight_based' => __('Weight Based'),
                                                'price_based' => __('Price Based'),
                                            ])
                                            ->default('flat')
                                            ->required()
                                            ->live(),
                                    ]),

                                SC\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('cost')
                                            ->label(__('Price (RON)'))
                                            ->numeric()
                                            ->step(0.01)
                                            ->default(0)
                                            ->prefix('RON')
                                            ->visible(fn ($get) => in_array($get('calculation_type'), ['flat', 'weight_based', 'price_based'])),

                                        Forms\Components\TextInput::make('cost_per_kg')
                                            ->label(__('Cost per KG'))
                                            ->numeric()
                                            ->step(0.01)
                                            ->prefix('RON')
                                            ->placeholder('0.00')
                                            ->visible(fn ($get) => $get('calculation_type') === 'weight_based'),

                                        Forms\Components\TextInput::make('min_order')
                                            ->label(__('Min Order'))
                                            ->numeric()
                                            ->step(0.01)
                                            ->prefix('RON')
                                            ->placeholder(__('No minimum')),

                                        Forms\Components\TextInput::make('max_order')
                                            ->label(__('Free Above / Max Order'))
                                            ->numeric()
                                            ->step(0.01)
                                            ->prefix('RON')
                                            ->placeholder(__('No maximum'))
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Free shipping when order exceeds this amount'),
                                    ]),

                                SC\Grid::make(4)
                                    ->schema([
                                        Forms\Components\TextInput::make('estimated_days_min')
                                            ->label(__('Est. Days (min)'))
                                            ->numeric()
                                            ->default(3),

                                        Forms\Components\TextInput::make('estimated_days_max')
                                            ->label(__('Est. Days (max)'))
                                            ->numeric()
                                            ->default(5),

                                        Forms\Components\TextInput::make('sort_order')
                                            ->label('Order')
                                            ->numeric()
                                            ->default(0),

                                        Forms\Components\Toggle::make('is_active')
                                            ->label('Active')
                                            ->default(true)
                                            ->inline(false),
                                    ]),
                            ])
                            ->defaultItems(1)
                            ->addActionLabel(__('Add Shipping Method'))
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
                    ->label(__('Zone Name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('countries')
                    ->label(__('Countries'))
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
                    ->label(__('Methods'))
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

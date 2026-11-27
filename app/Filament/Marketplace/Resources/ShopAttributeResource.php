<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\ShopAttributeResource\Pages;
use App\Models\Shop\ShopAttribute;
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
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use Illuminate\Support\Str;

class ShopAttributeResource extends Resource
{
    use \App\Filament\Marketplace\Concerns\TranslatesResourceLabels;

    use HasMarketplaceContext;

    protected static ?string $model = ShopAttribute::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationLabel = 'Attributes';

    protected static ?string $navigationParentItem = 'Shop';

    protected static \UnitEnum|string|null $navigationGroup = 'Tools';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Attribute';

    protected static ?string $pluralModelLabel = 'Product Attributes';

    protected static ?string $slug = 'shop-attributes';

    public static function getEloquentQuery(): Builder
    {
        $marketplaceClientId = static::getMarketplaceClientId();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplaceClientId);
    }

        public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice('shop');
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();
        $marketplaceLanguage = $marketplace->language ?? $marketplace->locale ?? 'en';

        return $schema
            ->schema([
                Forms\Components\Hidden::make('marketplace_client_id')
                    ->default($marketplace?->id),

                SC\Section::make(__('Attribute Details'))
                    ->schema([
                        Forms\Components\TextInput::make("name.{$marketplaceLanguage}")
                            ->label(__('Attribute Name'))
                            ->required()
                            ->maxLength(100)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                                if ($state) $set('slug', Str::slug($state));
                            })
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'e.g., Size, Color, Material'),

                        Forms\Components\TextInput::make('slug')
                            ->label(__('Slug'))
                            ->required()
                            ->maxLength(100)
                            ->rule('alpha_dash'),

                        Forms\Components\Select::make('type')
                            ->options([
                                'select' => __('Dropdown Select'),
                                'color' => __('Color Picker'),
                                'text' => __('Text Input'),
                            ])
                            ->default('select')
                            ->required(),

                        Forms\Components\TextInput::make('sort_order')
                            ->label(__('Sort Order'))
                            ->numeric()
                            ->default(0),
                    ])->columns(2),

                SC\Section::make(__('Attribute Values'))
                    ->schema([
                        Forms\Components\Repeater::make('values')
                            ->relationship('values')
                            ->schema([
                                Forms\Components\TextInput::make("value.{$marketplaceLanguage}")
                                    ->label(__('Value'))
                                    ->required()
                                    ->maxLength(100)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                                        if ($state) $set('slug', Str::slug($state));
                                    }),

                                Forms\Components\TextInput::make('slug')
                                    ->label(__('Slug'))
                                    ->required()
                                    ->maxLength(100),

                                Forms\Components\ColorPicker::make('color_code')
                                    ->label(__('Color'))
                                    ->visible(fn ($get) => $get('../../type') === 'color'),

                                Forms\Components\TextInput::make('sort_order')
                                    ->label(__('Order'))
                                    ->numeric()
                                    ->default(0)
                                    ->columnSpan(1),
                            ])
                            ->columns(4)
                            ->defaultItems(0)
                            ->reorderable()
                            ->orderColumn('sort_order')
                            ->addActionLabel(__('Add Value'))
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string =>
                                $state['value'][$marketplaceLanguage] ?? $state['slug'] ?? null
                            ),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $marketplace = static::getMarketplaceClient();
        $marketplaceLanguage = $marketplace->language ?? $marketplace->locale ?? 'en';

        return $table
            ->columns([
                Tables\Columns\TextColumn::make("name.{$marketplaceLanguage}")
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->label(__('Slug'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\BadgeColumn::make('type')
                    ->label(__('Type'))
                    ->colors([
                        'primary' => 'select',
                        'success' => 'color',
                        'gray' => 'text',
                    ]),

                Tables\Columns\TextColumn::make('values_count')
                    ->label(__('Values'))
                    ->counts('values')
                    ->sortable(),

                Tables\Columns\TextColumn::make('products_count')
                    ->label(__('Products'))
                    ->counts('products')
                    ->sortable(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('Order'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'select' => __('Dropdown Select'),
                        'color' => __('Color Picker'),
                        'text' => __('Text Input'),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
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
            'index' => Pages\ListShopAttributes::route('/'),
            'create' => Pages\CreateShopAttribute::route('/create'),
            'edit' => Pages\EditShopAttribute::route('/{record}/edit'),
        ];
    }
}

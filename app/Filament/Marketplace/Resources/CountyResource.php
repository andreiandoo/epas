<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\CountyResource\Pages;
use App\Models\MarketplaceCounty;
use App\Models\MarketplaceRegion;
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

class CountyResource extends Resource
{
    use \App\Filament\Marketplace\Concerns\TranslatesResourceLabels;

    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceCounty::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'Județe';

    protected static ?string $navigationParentItem = 'Locații';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'County';

    protected static ?string $pluralModelLabel = 'Județe';

    protected static ?string $slug = 'counties';

    public static function getEloquentQuery(): Builder
    {
        $marketplaceClientId = static::getMarketplaceClientId();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplaceClientId);
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();
        $marketplaceLanguage = $marketplace->language ?? $marketplace->locale ?? 'ro';

        return $schema
            ->schema([
                Forms\Components\Hidden::make('marketplace_client_id')
                    ->default($marketplace?->id),

                SC\Section::make(__('County Details'))
                    ->schema([
                        SC\Tabs::make('Name Translations')
                            ->tabs([
                                SC\Tabs\Tab::make('Română')
                                    ->schema([
                                        Forms\Components\TextInput::make('name.ro')
                                            ->label(__('Nume județ (RO)'))
                                            ->required()
                                            ->maxLength(190)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get) {
                                                if ($state && !$get('slug')) {
                                                    $set('slug', Str::slug($state));
                                                }
                                            }),
                                    ]),
                                SC\Tabs\Tab::make('English')
                                    ->schema([
                                        Forms\Components\TextInput::make('name.en')
                                            ->label(__('County name (EN)'))
                                            ->maxLength(190),
                                    ]),
                            ])->columnSpanFull(),

                        Forms\Components\TextInput::make('slug')
                            ->label(__('Slug'))
                            ->required()
                            ->maxLength(190)
                            ->rule('alpha_dash'),

                        Forms\Components\TextInput::make('code')
                            ->label(__('County Code'))
                            ->required()
                            ->maxLength(2)
                            ->placeholder(__('CJ'))
                            ->helperText(__('2-letter county code (e.g., CJ for Cluj)')),

                        Forms\Components\Select::make('region_id')
                            ->label(__('Region'))
                            ->options(function () {
                                $marketplace = static::getMarketplaceClient();
                                $lang = $marketplace->language ?? $marketplace->locale ?? 'ro';
                                return MarketplaceRegion::where('marketplace_client_id', $marketplace?->id)
                                    ->orderBy('sort_order')
                                    ->get()
                                    ->mapWithKeys(fn ($r) => [$r->id => $r->name[$lang] ?? $r->name['ro'] ?? 'Unnamed']);
                            })
                            ->searchable()
                            ->required()
                            ->placeholder(__('Select a region')),

                        Forms\Components\TextInput::make('sort_order')
                            ->label(__('Sort Order'))
                            ->numeric()
                            ->default(0),
                    ])->columns(2),

                SC\Section::make(__('Appearance'))
                    ->collapsed()
                    ->schema([
                        Forms\Components\FileUpload::make('image_url')
                            ->label(__('Image'))
                            ->image()
                            ->disk('public')
                            ->directory('counties')
                            ->visibility('public'),

                        Forms\Components\TextInput::make('icon')
                            ->label(__('Icon'))
                            ->placeholder('heroicon-o-map')
                            ->maxLength(100),

                        Forms\Components\ColorPicker::make('color')
                            ->label(__('Color')),

                        Forms\Components\Toggle::make('is_visible')
                            ->label(__('Visible'))
                            ->default(true),

                        Forms\Components\Toggle::make('is_featured')
                            ->label(__('Featured')),
                    ])->columns(3),

                SC\Section::make(__('Description'))
                    ->collapsed()
                    ->schema([
                        SC\Tabs::make('Description Translations')
                            ->tabs([
                                SC\Tabs\Tab::make('Română')
                                    ->schema([
                                        Forms\Components\Textarea::make('description.ro')
                                            ->label(__('Descriere (RO)'))
                                            ->rows(3),
                                    ]),
                                SC\Tabs\Tab::make('English')
                                    ->schema([
                                        Forms\Components\Textarea::make('description.en')
                                            ->label(__('Description (EN)'))
                                            ->rows(3),
                                    ]),
                            ])->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $marketplace = static::getMarketplaceClient();
        $marketplaceLanguage = $marketplace->language ?? $marketplace->locale ?? 'ro';

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('Code'))
                    ->searchable()
                    ->sortable()
                    ->badge(),

                Tables\Columns\TextColumn::make("name.{$marketplaceLanguage}")
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('region.name')
                    ->label(__('Region'))
                    ->getStateUsing(function ($record) use ($marketplaceLanguage) {
                        $name = $record->region?->name;
                        if (is_array($name)) {
                            return $name[$marketplaceLanguage] ?? $name['ro'] ?? '-';
                        }
                        return $name ?? '-';
                    }),

                // Live count of cities currently linked via county_id —
                // the persisted column gets stale whenever cities are
                // added/removed outside the seed scripts.
                Tables\Columns\TextColumn::make('city_count')
                    ->label(__('Orașe'))
                    ->getStateUsing(fn ($record) => \App\Models\MarketplaceCity::query()
                        ->where('county_id', $record->id)
                        ->count())
                    ->sortable(),

                Tables\Columns\ColorColumn::make('color')
                    ->label(__('Color')),

                Tables\Columns\IconColumn::make('is_visible')
                    ->label(__('Visible'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label(__('Featured'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('Order'))
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('region_id')
                    ->label(__('Region'))
                    ->options(function () {
                        $marketplace = static::getMarketplaceClient();
                        $lang = $marketplace->language ?? $marketplace->locale ?? 'ro';
                        return MarketplaceRegion::where('marketplace_client_id', $marketplace?->id)
                            ->orderBy('sort_order')
                            ->get()
                            ->mapWithKeys(fn ($r) => [$r->id => $r->name[$lang] ?? $r->name['ro'] ?? 'Unnamed']);
                    }),
                Tables\Filters\TernaryFilter::make('is_visible')
                    ->label(__('Visible')),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label(__('Featured')),
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
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCounties::route('/'),
            'create' => Pages\CreateCounty::route('/create'),
            'edit' => Pages\EditCounty::route('/{record}/edit'),
        ];
    }
}

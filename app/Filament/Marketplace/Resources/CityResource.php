<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\CityResource\Pages;
use App\Models\MarketplaceCity;
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

class CityResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceCity::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationLabel = 'Cities';

    protected static ?string $navigationParentItem = 'Venues';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'City';

    protected static ?string $pluralModelLabel = 'Cities';

    protected static ?string $slug = 'cities';

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

                SC\Section::make('City Details')
                    ->schema([
                        SC\Tabs::make('Name Translations')
                            ->tabs([
                                SC\Tabs\Tab::make('Română')
                                    ->schema([
                                        Forms\Components\TextInput::make('name.ro')
                                            ->label('Nume oras (RO)')
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
                                            ->label('City name (EN)')
                                            ->maxLength(190),
                                    ]),
                            ])->columnSpanFull(),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(190)
                            ->rule('alpha_dash'),

                        Forms\Components\Select::make('county_id')
                            ->label('County (Județ)')
                            ->options(function () {
                                $marketplace = static::getMarketplaceClient();
                                $lang = $marketplace->language ?? $marketplace->locale ?? 'ro';
                                return MarketplaceCounty::where('marketplace_client_id', $marketplace?->id)
                                    ->with('region')
                                    ->orderBy('code')
                                    ->get()
                                    ->mapWithKeys(fn ($c) => [
                                        $c->id => $c->code . ' - ' . ($c->name[$lang] ?? $c->name['ro'] ?? 'Unnamed')
                                            . ($c->region ? ' (' . ($c->region->name[$lang] ?? $c->region->name['ro'] ?? '') . ')' : '')
                                    ]);
                            })
                            ->searchable()
                            ->required()
                            ->placeholder('Select a county')
                            ->live()
                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                                if ($state) {
                                    $county = MarketplaceCounty::find($state);
                                    if ($county) {
                                        $set('region_id', $county->region_id);
                                    }
                                }
                            }),

                        Forms\Components\Select::make('region_id')
                            ->label('Region')
                            ->options(function () {
                                $marketplace = static::getMarketplaceClient();
                                $lang = $marketplace->language ?? $marketplace->locale ?? 'ro';
                                return MarketplaceRegion::where('marketplace_client_id', $marketplace?->id)
                                    ->orderBy('sort_order')
                                    ->get()
                                    ->mapWithKeys(fn ($r) => [$r->id => $r->name[$lang] ?? $r->name['en'] ?? 'Unnamed']);
                            })
                            ->searchable()
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Auto-selected based on county'),

                        Forms\Components\Select::make('country')
                            ->label('Country')
                            ->options([
                                'RO' => 'Romania',
                                'MD' => 'Moldova',
                                'HU' => 'Hungary',
                                'BG' => 'Bulgaria',
                            ])
                            ->default('RO')
                            ->required(),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0),
                    ])->columns(2),

                SC\Section::make('Location')
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('latitude')
                            ->label('Latitude')
                            ->numeric()
                            ->step('0.0000001')
                            ->placeholder('44.4268'),

                        Forms\Components\TextInput::make('longitude')
                            ->label('Longitude')
                            ->numeric()
                            ->step('0.0000001')
                            ->placeholder('26.1025'),

                        Forms\Components\TextInput::make('timezone')
                            ->label('Timezone')
                            ->placeholder('Europe/Bucharest')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('population')
                            ->label('Population')
                            ->numeric()
                            ->minValue(0),
                    ])->columns(2),

                SC\Section::make('Appearance')
                    ->collapsed()
                    ->schema([
                        Forms\Components\FileUpload::make('image_url')
                            ->label('City Image')
                            ->image()
                            ->disk('public')
                            ->directory('cities')
                            ->visibility('public'),

                        Forms\Components\FileUpload::make('cover_image_url')
                            ->label('Cover Image')
                            ->image()
                            ->disk('public')
                            ->directory('cities/covers')
                            ->visibility('public'),

                        Forms\Components\TextInput::make('icon')
                            ->label('Icon')
                            ->placeholder('heroicon-o-building-library')
                            ->maxLength(100),

                        Forms\Components\Toggle::make('is_visible')
                            ->label('Visible')
                            ->default(true),

                        Forms\Components\Toggle::make('is_featured')
                            ->label('Featured'),

                        Forms\Components\Toggle::make('is_capital')
                            ->label('Capital of Region'),
                    ])->columns(3),

                SC\Section::make('Description')
                    ->collapsed()
                    ->schema([
                        SC\Tabs::make('Description Translations')
                            ->tabs([
                                SC\Tabs\Tab::make('Română')
                                    ->schema([
                                        Forms\Components\Textarea::make('description.ro')
                                            ->label('Descriere (RO)')
                                            ->rows(3),
                                    ]),
                                SC\Tabs\Tab::make('English')
                                    ->schema([
                                        Forms\Components\Textarea::make('description.en')
                                            ->label('Description (EN)')
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
                Tables\Columns\TextColumn::make("name.{$marketplaceLanguage}")
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('county.code')
                    ->label('County')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('region.name')
                    ->label('Region')
                    ->getStateUsing(function ($record) use ($marketplaceLanguage) {
                        $name = $record->region?->name;
                        if (is_array($name)) {
                            return $name[$marketplaceLanguage] ?? $name['ro'] ?? '-';
                        }
                        return $name ?? '-';
                    }),

                Tables\Columns\TextColumn::make('country')
                    ->label('Country')
                    ->badge(),

                Tables\Columns\TextColumn::make('event_count')
                    ->label('Events')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_capital')
                    ->label('Capital')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('region_id')
                    ->label('Region')
                    ->options(function () {
                        $marketplace = static::getMarketplaceClient();
                        $lang = $marketplace->language ?? $marketplace->locale ?? 'ro';
                        return MarketplaceRegion::where('marketplace_client_id', $marketplace?->id)
                            ->orderBy('sort_order')
                            ->get()
                            ->mapWithKeys(fn ($r) => [$r->id => $r->name[$lang] ?? $r->name['en'] ?? 'Unnamed']);
                    }),
                Tables\Filters\TernaryFilter::make('is_visible')
                    ->label('Visible'),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),
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
            'index' => Pages\ListCities::route('/'),
            'create' => Pages\CreateCity::route('/create'),
            'edit' => Pages\EditCity::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\RegionResource\Pages;
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

class RegionResource extends Resource
{
    use \App\Filament\Marketplace\Concerns\TranslatesResourceLabels;

    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceRegion::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'Regiuni';

    protected static ?string $navigationParentItem = 'Locații';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Region';

    protected static ?string $pluralModelLabel = 'Regiuni';

    protected static ?string $slug = 'regions';

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

                SC\Section::make(__('Region Details'))
                    ->schema([
                        SC\Tabs::make('Name Translations')
                            ->tabs([
                                SC\Tabs\Tab::make('Română')
                                    ->schema([
                                        Forms\Components\TextInput::make('name.ro')
                                            ->label(__('Nume regiune (RO)'))
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
                                            ->label(__('Region name (EN)'))
                                            ->maxLength(190),
                                    ]),
                            ])->columnSpanFull(),

                        Forms\Components\TextInput::make('slug')
                            ->label(__('Slug'))
                            ->required()
                            ->maxLength(190)
                            ->rule('alpha_dash'),

                        Forms\Components\TextInput::make('code')
                            ->label(__('Region Code'))
                            ->maxLength(10)
                            ->placeholder(__('e.g., B, CJ, TM')),

                        Forms\Components\Select::make('country')
                            ->label(__('Country'))
                            ->options([
                                'RO' => __('Romania'),
                                'MD' => __('Moldova'),
                                'HU' => __('Hungary'),
                                'BG' => __('Bulgaria'),
                            ])
                            ->default('RO')
                            ->required(),

                        Forms\Components\TextInput::make('sort_order')
                            ->label(__('Sort Order'))
                            ->numeric()
                            ->default(0),
                    ])->columns(2),

                SC\Section::make(__('Appearance'))
                    ->collapsed()
                    ->schema([
                        Forms\Components\FileUpload::make('image_url')
                            ->label(__('Region Image'))
                            ->image()
                            ->disk('public')
                            ->directory('regions')
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
                Tables\Columns\TextColumn::make("name.{$marketplaceLanguage}")
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label(__('Code'))
                    ->badge()
                    ->searchable(),

                Tables\Columns\TextColumn::make('country')
                    ->label(__('Country'))
                    ->badge(),

                // Live count of cities currently linked via region_id —
                // sidesteps the stale `city_count` column whose backfill
                // only runs when the seed scripts touch it. Cheap join
                // (~8 regions × one count query each).
                Tables\Columns\TextColumn::make('city_count')
                    ->label(__('Orașe'))
                    ->getStateUsing(fn ($record) => \App\Models\MarketplaceCity::query()
                        ->where('region_id', $record->id)
                        ->count())
                    ->sortable(),

                // Events / Activities — same logic as CityResource:
                // activities-module marketplaces (bilete.online) compute
                // live from the Activity table; events marketplaces use
                // the persisted counter.
                Tables\Columns\TextColumn::make('event_count')
                    ->label(fn () => static::marketplaceHasMicroservice('activities-module')
                        ? 'Activități' : 'Events')
                    ->getStateUsing(function ($record) {
                        if (static::marketplaceHasMicroservice('activities-module')) {
                            return \App\Models\Activity::query()
                                ->whereHas('city', fn ($q) => $q->where('region_id', $record->id))
                                ->count();
                        }
                        return $record->event_count;
                    })
                    ->sortable(),

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
            'index' => Pages\ListRegions::route('/'),
            'create' => Pages\CreateRegion::route('/create'),
            'edit' => Pages\EditRegion::route('/{record}/edit'),
        ];
    }
}

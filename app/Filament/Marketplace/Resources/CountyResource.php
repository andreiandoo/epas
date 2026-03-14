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
    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceCounty::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'Counties';

    protected static ?string $navigationParentItem = 'Venues';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'County';

    protected static ?string $pluralModelLabel = 'Counties';

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

                SC\Section::make('County Details')
                    ->schema([
                        SC\Tabs::make('Name Translations')
                            ->tabs([
                                SC\Tabs\Tab::make('Română')
                                    ->schema([
                                        Forms\Components\TextInput::make('name.ro')
                                            ->label('Nume județ (RO)')
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
                                            ->label('County name (EN)')
                                            ->maxLength(190),
                                    ]),
                            ])->columnSpanFull(),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(190)
                            ->rule('alpha_dash'),

                        Forms\Components\TextInput::make('code')
                            ->label('County Code')
                            ->required()
                            ->maxLength(2)
                            ->placeholder('CJ')
                            ->helperText('2-letter county code (e.g., CJ for Cluj)'),

                        Forms\Components\Select::make('region_id')
                            ->label('Region')
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
                            ->placeholder('Select a region'),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0),
                    ])->columns(2),

                SC\Section::make('Appearance')
                    ->collapsed()
                    ->schema([
                        Forms\Components\FileUpload::make('image_url')
                            ->label('Image')
                            ->image()
                            ->disk('public')
                            ->directory('counties')
                            ->visibility('public'),

                        Forms\Components\TextInput::make('icon')
                            ->label('Icon')
                            ->placeholder('heroicon-o-map')
                            ->maxLength(100),

                        Forms\Components\ColorPicker::make('color')
                            ->label('Color'),

                        Forms\Components\Toggle::make('is_visible')
                            ->label('Visible')
                            ->default(true),

                        Forms\Components\Toggle::make('is_featured')
                            ->label('Featured'),
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
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->badge(),

                Tables\Columns\TextColumn::make("name.{$marketplaceLanguage}")
                    ->label('Name')
                    ->searchable()
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

                Tables\Columns\TextColumn::make('city_count')
                    ->label('Cities')
                    ->sortable(),

                Tables\Columns\ColorColumn::make('color')
                    ->label('Color'),

                Tables\Columns\IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
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
                            ->mapWithKeys(fn ($r) => [$r->id => $r->name[$lang] ?? $r->name['ro'] ?? 'Unnamed']);
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
            'index' => Pages\ListCounties::route('/'),
            'create' => Pages\CreateCounty::route('/create'),
            'edit' => Pages\EditCounty::route('/{record}/edit'),
        ];
    }
}

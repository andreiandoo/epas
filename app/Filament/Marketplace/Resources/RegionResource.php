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
    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceRegion::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'Regions';

    protected static ?string $navigationParentItem = 'Venues';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Region';

    protected static ?string $pluralModelLabel = 'Regions';

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

                SC\Section::make('Region Details')
                    ->schema([
                        SC\Tabs::make('Name Translations')
                            ->tabs([
                                SC\Tabs\Tab::make('Română')
                                    ->schema([
                                        Forms\Components\TextInput::make('name.ro')
                                            ->label('Nume regiune (RO)')
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
                                            ->label('Region name (EN)')
                                            ->maxLength(190),
                                    ]),
                            ])->columnSpanFull(),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(190)
                            ->rule('alpha_dash'),

                        Forms\Components\TextInput::make('code')
                            ->label('Region Code')
                            ->maxLength(10)
                            ->placeholder('e.g., B, CJ, TM'),

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

                SC\Section::make('Appearance')
                    ->collapsed()
                    ->schema([
                        Forms\Components\FileUpload::make('image_url')
                            ->label('Region Image')
                            ->image()
                            ->disk('public')
                            ->directory('regions')
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
                Tables\Columns\TextColumn::make("name.{$marketplaceLanguage}")
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->searchable(),

                Tables\Columns\TextColumn::make('country')
                    ->label('Country')
                    ->badge(),

                Tables\Columns\TextColumn::make('city_count')
                    ->label('Cities')
                    ->sortable(),

                Tables\Columns\TextColumn::make('event_count')
                    ->label('Events')
                    ->sortable(),

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
            'index' => Pages\ListRegions::route('/'),
            'create' => Pages\CreateRegion::route('/create'),
            'edit' => Pages\EditRegion::route('/{record}/edit'),
        ];
    }
}

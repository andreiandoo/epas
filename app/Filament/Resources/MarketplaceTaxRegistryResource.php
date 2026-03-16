<?php

namespace App\Filament\Resources;

use App\Models\MarketplaceTaxRegistry;
use App\Services\LocationService;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use BackedEnum;
use UnitEnum;

class MarketplaceTaxRegistryResource extends Resource
{
    protected static ?string $model = MarketplaceTaxRegistry::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-library';
    protected static ?string $navigationLabel = 'Tax Registries';
    protected static UnitEnum|string|null $navigationGroup = 'Marketplace';
    protected static ?int $navigationSort = 50;
    protected static ?string $modelLabel = 'Tax Registry';
    protected static ?string $pluralModelLabel = 'Tax Registries';

    public static function form(Schema $schema): Schema
    {
        $locationService = app(LocationService::class);
        $countries = $locationService->getCountries();

        return $schema
            ->components([
                Section::make('Marketplace')
                    ->schema([
                        Forms\Components\Select::make('marketplace_client_id')
                            ->label('Marketplace')
                            ->relationship('marketplaceClient', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ]),

                Section::make('Location')
                    ->schema([
                        Forms\Components\Select::make('country')
                            ->label('Country')
                            ->options($countries)
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                $set('county', null);
                                $set('city', null);
                            }),

                        Forms\Components\Select::make('county')
                            ->label('County/State')
                            ->options(function (Forms\Get $get) use ($locationService) {
                                $country = $get('country');
                                if (!$country) {
                                    return [];
                                }
                                return $locationService->getStates($country);
                            })
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                $set('city', null);
                            }),

                        Forms\Components\Select::make('city')
                            ->label('City')
                            ->options(function (Forms\Get $get) use ($locationService) {
                                $country = $get('country');
                                $county = $get('county');
                                if (!$country || !$county) {
                                    return [];
                                }
                                return $locationService->getCities($country, $county);
                            })
                            ->searchable(),

                        Forms\Components\TextInput::make('commune')
                            ->label('Comună')
                            ->maxLength(255),
                    ])
                    ->columns(4),

                Section::make('Registry Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('subname')
                            ->label('Subname')
                            ->maxLength(255)
                            ->helperText('Optional secondary name or department'),

                        Forms\Components\Textarea::make('address')
                            ->label('Address')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('directions')
                            ->label('Indicații')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('phone')
                            ->label('Phone')
                            ->tel()
                            ->maxLength(50),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email2')
                            ->label('Email 2')
                            ->email()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('website_url')
                            ->label('Website URL')
                            ->url()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('cif')
                            ->label('CIF / Tax ID')
                            ->maxLength(50)
                            ->helperText('Tax identification number'),

                        Forms\Components\TextInput::make('iban')
                            ->label('IBAN')
                            ->maxLength(50),

                        Forms\Components\TextInput::make('siruta_code')
                            ->label('Cod SIRUTA')
                            ->maxLength(50),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('marketplaceClient.name')
                    ->label('Marketplace')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('country')
                    ->label('Country')
                    ->sortable(),

                Tables\Columns\TextColumn::make('county')
                    ->label('County')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('city')
                    ->label('City')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('cif')
                    ->label('CIF')
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('marketplace_client_id')
                    ->label('Marketplace')
                    ->relationship('marketplaceClient', 'name'),

                Tables\Filters\SelectFilter::make('country')
                    ->options(fn () => app(LocationService::class)->getCountries()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => MarketplaceTaxRegistryResource\Pages\ListMarketplaceTaxRegistries::route('/'),
            'create' => MarketplaceTaxRegistryResource\Pages\CreateMarketplaceTaxRegistry::route('/create'),
            'view' => MarketplaceTaxRegistryResource\Pages\ViewMarketplaceTaxRegistry::route('/{record}'),
            'edit' => MarketplaceTaxRegistryResource\Pages\EditMarketplaceTaxRegistry::route('/{record}/edit'),
        ];
    }
}

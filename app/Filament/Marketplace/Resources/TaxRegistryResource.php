<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\TaxRegistryResource\Pages;
use App\Models\MarketplaceTaxRegistry;
use App\Services\LocationService;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get as SGet;
use Filament\Schemas\Components\Utilities\Set as SSet;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;

class TaxRegistryResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceTaxRegistry::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-library';

    protected static \UnitEnum|string|null $navigationGroup = 'Tax & Invoicing';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Tax Registry';

    protected static ?string $modelLabel = 'Tax Registry Entry';

    protected static ?string $pluralModelLabel = 'Tax Registry';

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplace?->id);
    }

    public static function form(Schema $schema): Schema
    {
        $locationService = app(LocationService::class);
        $countries = $locationService->getCountries();

        return $schema
            ->components([
                Section::make('Location')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        Forms\Components\Select::make('country')
                            ->label('Country')
                            ->options($countries)
                            ->default('Romania')
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (SSet $set) {
                                $set('county', null);
                                $set('city', null);
                            }),

                        Forms\Components\Select::make('county')
                            ->label('County')
                            ->options(function (SGet $get) use ($locationService) {
                                $country = $get('country');
                                if (!$country) return [];

                                $countryCode = $locationService->getCountryCode($country);
                                if (!$countryCode) return [];

                                return $locationService->getStates($countryCode);
                            })
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (SSet $set) {
                                $set('city', null);
                            }),

                        Forms\Components\Select::make('city')
                            ->label('City')
                            ->options(function (SGet $get) use ($locationService) {
                                $country = $get('country');
                                $county = $get('county');
                                if (!$country || !$county) return [];

                                $countryCode = $locationService->getCountryCode($country);
                                if (!$countryCode) return [];

                                return $locationService->getCities($countryCode, $county);
                            })
                            ->searchable(),
                    ])
                    ->columns(3),

                Section::make('Registry Information')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Company or Person Name'),

                        Forms\Components\TextInput::make('subname')
                            ->label('Subname / Department')
                            ->maxLength(255)
                            ->placeholder('Optional subdivision'),

                        Forms\Components\Textarea::make('address')
                            ->label('Address')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('phone')
                            ->label('Phone')
                            ->tel()
                            ->maxLength(50),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('cif')
                            ->label('CIF / Tax ID')
                            ->maxLength(50)
                            ->placeholder('e.g., RO12345678'),

                        Forms\Components\TextInput::make('iban')
                            ->label('IBAN')
                            ->maxLength(50)
                            ->placeholder('e.g., RO49AAAA1B31007593840000'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->subname),

                Tables\Columns\TextColumn::make('cif')
                    ->label('CIF')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('county')
                    ->label('County')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('city')
                    ->label('City')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Modified')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),

                Tables\Filters\SelectFilter::make('county')
                    ->label('County')
                    ->options(function () {
                        $locationService = app(LocationService::class);
                        return $locationService->getStates('ro');
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTaxRegistries::route('/'),
            'create' => Pages\CreateTaxRegistry::route('/create'),
            'edit' => Pages\EditTaxRegistry::route('/{record}/edit'),
        ];
    }
}

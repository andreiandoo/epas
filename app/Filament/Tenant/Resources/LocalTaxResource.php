<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\LocalTaxResource\Pages;
use App\Models\Tax\LocalTax;
use App\Models\EventType;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;

class LocalTaxResource extends Resource
{
    protected static ?string $model = LocalTax::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'Local Taxes';

    protected static ?string $navigationParentItem = 'Taxes';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Local Tax';

    protected static ?string $pluralModelLabel = 'Local Taxes';

    protected static ?string $slug = 'local-taxes';

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()->where('tenant_id', $tenant?->id);
    }

    protected static function getCountryOptions(): array
    {
        $countries = require resource_path('data/countries.php');
        return array_combine($countries, $countries);
    }

    protected static function getCountyOptions(string $country): array
    {
        // Romania counties
        $romaniaCounties = [
            'Alba', 'Arad', 'Arges', 'Bacau', 'Bihor', 'Bistrita-Nasaud', 'Botosani', 'Braila',
            'Brasov', 'Bucuresti', 'Buzau', 'Calarasi', 'Caras-Severin', 'Cluj', 'Constanta',
            'Covasna', 'Dambovita', 'Dolj', 'Galati', 'Giurgiu', 'Gorj', 'Harghita', 'Hunedoara',
            'Ialomita', 'Iasi', 'Ilfov', 'Maramures', 'Mehedinti', 'Mures', 'Neamt', 'Olt',
            'Prahova', 'Salaj', 'Satu Mare', 'Sibiu', 'Suceava', 'Teleorman', 'Timis', 'Tulcea',
            'Valcea', 'Vaslui', 'Vrancea'
        ];

        // US States
        $usStates = [
            'Alabama', 'Alaska', 'Arizona', 'Arkansas', 'California', 'Colorado', 'Connecticut',
            'Delaware', 'Florida', 'Georgia', 'Hawaii', 'Idaho', 'Illinois', 'Indiana', 'Iowa',
            'Kansas', 'Kentucky', 'Louisiana', 'Maine', 'Maryland', 'Massachusetts', 'Michigan',
            'Minnesota', 'Mississippi', 'Missouri', 'Montana', 'Nebraska', 'Nevada', 'New Hampshire',
            'New Jersey', 'New Mexico', 'New York', 'North Carolina', 'North Dakota', 'Ohio',
            'Oklahoma', 'Oregon', 'Pennsylvania', 'Rhode Island', 'South Carolina', 'South Dakota',
            'Tennessee', 'Texas', 'Utah', 'Vermont', 'Virginia', 'Washington', 'West Virginia',
            'Wisconsin', 'Wyoming', 'District of Columbia'
        ];

        // UK Counties
        $ukCounties = [
            'Bedfordshire', 'Berkshire', 'Bristol', 'Buckinghamshire', 'Cambridgeshire', 'Cheshire',
            'City of London', 'Cornwall', 'Cumbria', 'Derbyshire', 'Devon', 'Dorset', 'Durham',
            'East Riding of Yorkshire', 'East Sussex', 'Essex', 'Gloucestershire', 'Greater London',
            'Greater Manchester', 'Hampshire', 'Herefordshire', 'Hertfordshire', 'Isle of Wight',
            'Kent', 'Lancashire', 'Leicestershire', 'Lincolnshire', 'Merseyside', 'Norfolk',
            'North Yorkshire', 'Northamptonshire', 'Northumberland', 'Nottinghamshire', 'Oxfordshire',
            'Rutland', 'Shropshire', 'Somerset', 'South Yorkshire', 'Staffordshire', 'Suffolk',
            'Surrey', 'Tyne and Wear', 'Warwickshire', 'West Midlands', 'West Sussex', 'West Yorkshire',
            'Wiltshire', 'Worcestershire'
        ];

        // German States
        $germanStates = [
            'Baden-Württemberg', 'Bayern', 'Berlin', 'Brandenburg', 'Bremen', 'Hamburg', 'Hessen',
            'Mecklenburg-Vorpommern', 'Niedersachsen', 'Nordrhein-Westfalen', 'Rheinland-Pfalz',
            'Saarland', 'Sachsen', 'Sachsen-Anhalt', 'Schleswig-Holstein', 'Thüringen'
        ];

        return match ($country) {
            'Romania' => array_combine($romaniaCounties, $romaniaCounties),
            'United States' => array_combine($usStates, $usStates),
            'United Kingdom' => array_combine($ukCounties, $ukCounties),
            'Germany' => array_combine($germanStates, $germanStates),
            default => [],
        };
    }

    public static function form(Schema $schema): Schema
    {
        $tenant = auth()->user()->tenant;
        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';

        return $schema
            ->schema([
                Forms\Components\Hidden::make('tenant_id')
                    ->default($tenant?->id),

                SC\Section::make('Location')
                    ->description('Define the geographic area where this tax applies')
                    ->schema([
                        Forms\Components\Select::make('country')
                            ->label('Country')
                            ->options(static::getCountryOptions())
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('county', null);
                                $set('city', null);
                            }),

                        Forms\Components\Select::make('county')
                            ->label('County / State / Region')
                            ->options(function (Get $get) {
                                $country = $get('country');
                                if (!$country) {
                                    return [];
                                }
                                return static::getCountyOptions($country);
                            })
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('city', null))
                            ->placeholder('Select county (optional)')
                            ->helperText('Leave empty to apply to entire country'),

                        Forms\Components\TextInput::make('city')
                            ->label('City')
                            ->maxLength(100)
                            ->placeholder('Enter city name (optional)')
                            ->helperText('Enter a city name or leave empty to apply to entire county/country')
                            ->datalist(function (Get $get) use ($tenant) {
                                $country = $get('country');
                                $county = $get('county');
                                if (!$country) {
                                    return [];
                                }
                                return LocalTax::getCitiesForLocation($tenant?->id, $country, $county);
                            }),
                    ])->columns(3),

                SC\Section::make('Tax Details')
                    ->schema([
                        Forms\Components\TextInput::make('value')
                            ->label('Tax Rate (%)')
                            ->required()
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->placeholder('0.00')
                            ->helperText('Enter the tax rate as a percentage'),

                        Forms\Components\Select::make('event_types')
                            ->label('Event Types')
                            ->relationship('eventTypes', 'name')
                            ->options(function () use ($tenantLanguage) {
                                return EventType::all()
                                    ->mapWithKeys(fn ($type) => [
                                        $type->id => $type->name[$tenantLanguage] ?? $type->name['en'] ?? $type->slug
                                    ]);
                            })
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->placeholder('Select event types (leave empty for all)')
                            ->helperText('Select multiple event types this tax applies to, or leave empty for all types'),

                        Forms\Components\Textarea::make('explanation')
                            ->label('Explanation')
                            ->rows(3)
                            ->placeholder('Describe what this tax is for and why it applies to this location...')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('source_url')
                            ->label('Source URL')
                            ->url()
                            ->maxLength(500)
                            ->placeholder('https://...')
                            ->helperText('Link to official documentation or source for this tax rate')
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive taxes will not be applied to orders.'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        $tenant = auth()->user()->tenant;
        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('country')
                    ->label('Country')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('county')
                    ->label('County/State')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('city')
                    ->label('City')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('value')
                    ->label('Rate')
                    ->formatStateUsing(fn ($state) => number_format($state, 2) . '%')
                    ->sortable()
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('eventTypes.name')
                    ->label('Event Types')
                    ->formatStateUsing(function ($state, $record) use ($tenantLanguage) {
                        $types = $record->eventTypes;
                        if ($types->isEmpty()) {
                            return 'All Types';
                        }
                        return $types->map(function ($type) use ($tenantLanguage) {
                            $name = $type->name;
                            return is_array($name) ? ($name[$tenantLanguage] ?? $name['en'] ?? 'Unknown') : $name;
                        })->join(', ');
                    })
                    ->wrap()
                    ->limit(50),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('source_url')
                    ->label('Source')
                    ->formatStateUsing(fn ($state) => $state ? 'View' : '-')
                    ->url(fn ($record) => $record->source_url, shouldOpenInNewTab: true)
                    ->color('primary')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
                Tables\Filters\SelectFilter::make('country')
                    ->label('Country')
                    ->options(static::getCountryOptions()),
                Tables\Filters\SelectFilter::make('event_type')
                    ->label('Event Type')
                    ->relationship('eventTypes', 'name')
                    ->options(function () use ($tenantLanguage) {
                        return EventType::all()
                            ->mapWithKeys(fn ($type) => [
                                $type->id => $type->name[$tenantLanguage] ?? $type->name['en'] ?? $type->slug
                            ]);
                    }),
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
            ->defaultSort('country');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocalTaxes::route('/'),
            'create' => Pages\CreateLocalTax::route('/create'),
            'edit' => Pages\EditLocalTax::route('/{record}/edit'),
        ];
    }
}

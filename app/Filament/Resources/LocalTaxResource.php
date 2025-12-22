<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LocalTaxResource\Pages;
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
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

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
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
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
        return $schema
            ->schema([
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
                            ->helperText('Enter a city name or leave empty to apply to entire county/country'),
                    ])->columns(3),

                SC\Section::make('Tax Details')
                    ->schema([
                        Forms\Components\Grid::make(2)
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

                                Forms\Components\TextInput::make('priority')
                                    ->label('Priority')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Higher priority taxes are applied first'),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Toggle::make('is_compound')
                                    ->label('Compound Tax')
                                    ->default(false)
                                    ->live()
                                    ->helperText('Compound taxes are calculated on the subtotal plus other non-compound taxes'),

                                Forms\Components\TextInput::make('compound_order')
                                    ->label('Compound Order')
                                    ->numeric()
                                    ->default(0)
                                    ->visible(fn (Get $get) => $get('is_compound'))
                                    ->helperText('Order in which compound taxes are applied (lower first)'),
                            ]),

                        Forms\Components\Select::make('event_types')
                            ->label('Event Types')
                            ->relationship('eventTypes', 'name')
                            ->options(function () {
                                return EventType::all()
                                    ->mapWithKeys(fn ($type) => [
                                        $type->id => $type->name['en'] ?? $type->slug
                                    ]);
                            })
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->placeholder('Select event types (leave empty for all)')
                            ->helperText('Select multiple event types this tax applies to, or leave empty for all types')
                            ->columnSpanFull(),

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
                    ]),

                SC\Section::make('Validity Period')
                    ->description('Optionally set when this tax is active. Leave empty for always active.')
                    ->collapsed()
                    ->schema([
                        Forms\Components\DatePicker::make('valid_from')
                            ->label('Valid From')
                            ->placeholder('Always')
                            ->helperText('Tax becomes active on this date'),

                        Forms\Components\DatePicker::make('valid_until')
                            ->label('Valid Until')
                            ->placeholder('Forever')
                            ->afterOrEqual('valid_from')
                            ->helperText('Tax expires after this date'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive taxes will not be applied to orders.')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
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

                Tables\Columns\TextColumn::make('priority')
                    ->label('Priority')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('eventTypes.name')
                    ->label('Event Types')
                    ->formatStateUsing(function ($state, $record) {
                        $types = $record->eventTypes;
                        if ($types->isEmpty()) {
                            return 'All Types';
                        }
                        return $types->map(function ($type) {
                            $name = $type->name;
                            return is_array($name) ? ($name['en'] ?? 'Unknown') : $name;
                        })->join(', ');
                    })
                    ->wrap()
                    ->limit(50),

                Tables\Columns\TextColumn::make('validity')
                    ->label('Validity')
                    ->state(function ($record) {
                        return $record->getValidityStatus();
                    })
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'gray' => 'inactive',
                        'info' => 'scheduled',
                        'danger' => 'expired',
                    ])
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                Tables\Columns\TextColumn::make('valid_from')
                    ->label('From')
                    ->date()
                    ->placeholder('Always')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('valid_until')
                    ->label('Until')
                    ->date()
                    ->placeholder('Forever')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('source_url')
                    ->label('Source')
                    ->formatStateUsing(fn ($state) => $state ? 'View' : '-')
                    ->url(fn ($record) => $record->source_url, shouldOpenInNewTab: true)
                    ->color('primary')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('Deleted')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
                Tables\Filters\SelectFilter::make('country')
                    ->label('Country')
                    ->options(static::getCountryOptions()),
                Tables\Filters\SelectFilter::make('event_type')
                    ->label('Event Type')
                    ->relationship('eventTypes', 'name')
                    ->options(function () {
                        return EventType::all()
                            ->mapWithKeys(fn ($type) => [
                                $type->id => $type->name['en'] ?? $type->slug
                            ]);
                    }),
                Tables\Filters\Filter::make('validity')
                    ->form([
                        Forms\Components\Select::make('validity_status')
                            ->options([
                                'active' => 'Currently Active',
                                'scheduled' => 'Scheduled (Future)',
                                'expired' => 'Expired',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['validity_status'], function ($query, $status) {
                            $today = now()->toDateString();
                            return match ($status) {
                                'active' => $query->where('is_active', true)
                                    ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today))
                                    ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', $today)),
                                'scheduled' => $query->where('valid_from', '>', $today),
                                'expired' => $query->where('valid_until', '<', $today),
                                default => $query,
                            };
                        });
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
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

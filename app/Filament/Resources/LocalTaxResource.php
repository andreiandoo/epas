<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LocalTaxResource\Pages;
use App\Models\Tax\LocalTax;
use App\Models\EventType;
use BackedEnum;
use UnitEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LocalTaxResource extends Resource
{
    protected static ?string $model = LocalTax::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'Local Taxes';

    protected static ?string $navigationParentItem = 'Taxes';

    protected static UnitEnum|string|null $navigationGroup = 'Settings';

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
        $romaniaCounties = [
            'Alba', 'Arad', 'Arges', 'Bacau', 'Bihor', 'Bistrita-Nasaud', 'Botosani', 'Braila',
            'Brasov', 'Bucuresti', 'Buzau', 'Calarasi', 'Caras-Severin', 'Cluj', 'Constanta',
            'Covasna', 'Dambovita', 'Dolj', 'Galati', 'Giurgiu', 'Gorj', 'Harghita', 'Hunedoara',
            'Ialomita', 'Iasi', 'Ilfov', 'Maramures', 'Mehedinti', 'Mures', 'Neamt', 'Olt',
            'Prahova', 'Salaj', 'Satu Mare', 'Sibiu', 'Suceava', 'Teleorman', 'Timis', 'Tulcea',
            'Valcea', 'Vaslui', 'Vrancea'
        ];

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
                            ->helperText('Leave empty to apply to entire county/country'),
                    ])->columns(3),

                SC\Section::make('Tax Details')
                    ->schema([
                        SC\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('value')
                                    ->label('Tax Rate (%)')
                                    ->required()
                                    ->numeric()
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->placeholder('0.00'),

                                Forms\Components\TextInput::make('max_rate')
                                    ->label('Max Rate (by law)')
                                    ->numeric()
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->placeholder('e.g., 2% or 5%')
                                    ->helperText('Maximum rate allowed by HCL'),

                                Forms\Components\TextInput::make('priority')
                                    ->label('Priority')
                                    ->numeric()
                                    ->default(0),
                            ]),

                        SC\Grid::make(2)
                            ->schema([
                                Forms\Components\Toggle::make('is_compound')
                                    ->label('Compound Tax')
                                    ->default(false)
                                    ->live()
                                    ->helperText('Calculated on subtotal + other taxes'),

                                Forms\Components\TextInput::make('compound_order')
                                    ->label('Compound Order')
                                    ->numeric()
                                    ->default(0)
                                    ->visible(fn (Get $get) => $get('is_compound')),
                            ]),

                        Forms\Components\Select::make('applied_to_base')
                            ->label('Applied To')
                            ->options([
                                'gross_with_vat' => 'Gross (with VAT)',
                                'gross_excl_vat' => 'Gross (excluding VAT)',
                                'ticket_price' => 'Ticket Price',
                            ])
                            ->default('gross_excl_vat')
                            ->helperText('What is the tax calculated on?'),

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
                            ->placeholder('All event types')
                            ->columnSpanFull(),

                        Forms\Components\RichEditor::make('explanation')
                            ->label('Explanation / Notes')
                            ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList'])
                            ->placeholder('Describe what this tax is for...')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('source_url')
                            ->label('Source URL')
                            ->url()
                            ->maxLength(500)
                            ->placeholder('https://...')
                            ->helperText('Link to official documentation')
                            ->columnSpanFull(),
                    ]),

                SC\Section::make('Payment Information')
                    ->description('Where and to whom is this tax paid?')
                    ->collapsed()
                    ->schema([
                        SC\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('beneficiary')
                                    ->label('Beneficiary')
                                    ->maxLength(255)
                                    ->placeholder('e.g., DITL, Buget Local'),

                                Forms\Components\TextInput::make('iban')
                                    ->label('IBAN')
                                    ->maxLength(34)
                                    ->placeholder('RO49AAAA1B31007593840000'),
                            ]),

                        Forms\Components\Textarea::make('beneficiary_address')
                            ->label('Beneficiary Address')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\RichEditor::make('where_to_pay')
                            ->label('Where / How to Pay')
                            ->toolbarButtons(['bold', 'italic', 'link', 'bulletList'])
                            ->placeholder('Payment instructions...')
                            ->columnSpanFull(),
                    ]),

                SC\Section::make('Payment Terms')
                    ->description('When must this tax be paid?')
                    ->collapsed()
                    ->schema([
                        SC\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('payment_term_type')
                                    ->label('Payment Term Type')
                                    ->options([
                                        'day_of_month' => 'Day of Following Month',
                                        'days_after_event' => 'Days After Event',
                                        'quarterly' => 'Quarterly',
                                        'custom' => 'Custom',
                                    ])
                                    ->live(),

                                Forms\Components\TextInput::make('payment_term_day')
                                    ->label('Day of Month')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(31)
                                    ->placeholder('e.g., 10')
                                    ->visible(fn (Get $get) => in_array($get('payment_term_type'), ['day_of_month', 'quarterly'])),
                            ]),

                        Forms\Components\TextInput::make('payment_term')
                            ->label('Payment Term Description')
                            ->maxLength(255)
                            ->placeholder('e.g., "10 a lunii următoare"')
                            ->columnSpanFull(),
                    ]),

                SC\Section::make('Legal & Documentation')
                    ->description('Legal basis and required declarations')
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('legal_basis')
                            ->label('Legal Basis')
                            ->maxLength(255)
                            ->placeholder('e.g., Art. 481 Cod Fiscal, HCL nr. X')
                            ->columnSpanFull(),

                        Forms\Components\RichEditor::make('declaration')
                            ->label('Declaration Template / Requirements')
                            ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList'])
                            ->placeholder('What declarations need to be filed?')
                            ->columnSpanFull(),
                    ]),

                SC\Section::make('Instructions')
                    ->description('What needs to be done before and after the event?')
                    ->collapsed()
                    ->schema([
                        Forms\Components\RichEditor::make('before_event_instructions')
                            ->label('Before Event')
                            ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList'])
                            ->placeholder('Steps to complete before the event...')
                            ->columnSpanFull(),

                        Forms\Components\RichEditor::make('after_event_instructions')
                            ->label('After Event')
                            ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList'])
                            ->placeholder('Steps to complete after the event...')
                            ->columnSpanFull(),
                    ]),

                SC\Section::make('Validity Period')
                    ->description('When is this tax active?')
                    ->collapsed()
                    ->schema([
                        SC\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('valid_from')
                                    ->label('Valid From')
                                    ->placeholder('Always'),

                                Forms\Components\DatePicker::make('valid_until')
                                    ->label('Valid Until')
                                    ->placeholder('Forever')
                                    ->afterOrEqual('valid_from'),
                            ]),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive taxes will not be applied.')
                            ->columnSpanFull(),
                    ]),
            ]) ->columns(1);
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

                Tables\Columns\TextColumn::make('max_rate')
                    ->label('Max')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state, 2) . '%' : '-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('beneficiary')
                    ->label('Beneficiary')
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('eventTypes.name')
                    ->label('Event Types')
                    ->formatStateUsing(function ($state, $record) {
                        $types = $record->eventTypes;
                        if ($types->isEmpty()) {
                            return 'All Types';
                        }
                        return $types->map(fn ($type) => is_array($type->name) ? ($type->name['en'] ?? 'Unknown') : $type->name)->join(', ');
                    })
                    ->wrap()
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('validity')
                    ->label('Status')
                    ->state(fn ($record) => $record->getValidityStatus())
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'gray' => 'inactive',
                        'info' => 'scheduled',
                        'danger' => 'expired',
                    ])
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

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

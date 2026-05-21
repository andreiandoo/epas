<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\TicketTypeResource\Pages;
use App\Models\TicketType;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TicketTypeResource extends Resource
{
    protected static ?string $model = TicketType::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-ticket';
    protected static \UnitEnum|string|null $navigationGroup = 'Leisure';
    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        $type = auth()->user()?->tenant?->tenant_type;
        $value = $type instanceof \App\Enums\TenantType ? $type->value : (string) $type;
        return $value === 'leisure' ? 'Produse & Bilete' : 'Ticket Types';
    }

    /**
     * Show in sidebar only for leisure tenants — for other tenant types
     * ticket types are managed through their parent Event edit page.
     */
    public static function shouldRegisterNavigation(): bool
    {
        $type = auth()->user()?->tenant?->tenant_type;
        $value = $type instanceof \App\Enums\TenantType ? $type->value : (string) $type;
        return $value === 'leisure';
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()
            ->whereHas('event', function ($query) use ($tenant) {
                $query->where('tenant_id', $tenant?->id);
            });
    }

    public static function form(Schema $schema): Schema
    {
        $isLeisureTenant = fn () => (auth()->user()?->tenant?->tenant_type instanceof \App\Enums\TenantType
            ? auth()->user()->tenant->tenant_type->value
            : (string) auth()->user()?->tenant?->tenant_type) === 'leisure';

        return $schema->components([
            SC\Grid::make(4)->schema([
                // ========== COLOANA STÂNGĂ (3/4) — conținut principal ==========
                SC\Group::make()
                    ->columnSpan(3)
                    ->schema([
                SC\Section::make('Date produs')
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->relationship('event', modifyQueryUsing: function (Builder $query) {
                                $tenant = auth()->user()->tenant;
                                return $query->where('tenant_id', $tenant?->id);
                            })
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('title', app()->getLocale()))
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),

                // ──────────────────────────────────────────────────────────────
                // E1: Leisure tenant — extra fields. Visible only when the
                // logged-in user belongs to a tenant_type=leisure tenant.
                // ──────────────────────────────────────────────────────────────
                SC\Section::make('Leisure: Categorie & Durată')
                    ->description('Configurări specifice pentru locații de agrement.')
                    ->icon('heroicon-o-sun')
                    ->visible($isLeisureTenant)
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('service_category')
                            ->label('Categorie serviciu')
                            ->options([
                                'access' => 'Bilet acces (principal)',
                                'parking' => 'Parcare',
                                'rental' => 'Rental (echipament)',
                                'activity' => 'Activitate / Ghid',
                                'food' => 'Mâncare / Băutură',
                                'extra' => 'Extra',
                            ])
                            ->live()
                            ->placeholder('access (implicit)'),
                        Forms\Components\TextInput::make('service_duration_minutes')
                            ->label('Durată implicită (minute)')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('min')
                            ->helperText('Pentru rental/parking: durata pentru care e valabil biletul de bază.')
                            ->visible(fn (callable $get) => in_array($get('service_category'), ['rental', 'parking', 'activity'], true)),
                        Forms\Components\Toggle::make('is_subscription')
                            ->label('Abonament sezonal')
                            ->helperText('Bilet valabil pe o perioadă (nu single-day).'),
                        Forms\Components\Toggle::make('is_entry_ticket')
                            ->label('Bilet de acces principal')
                            ->helperText('Activează dacă acesta e biletul de intrare (folosit pentru requires_access_ticket pe alte produse).'),
                        Forms\Components\DatePicker::make('valid_date')
                            ->label('Valabil doar în data')
                            ->helperText('Lasă gol pentru bilete valabile în orice zi (sau pe interval).'),
                        Forms\Components\Toggle::make('requires_access_ticket')
                            ->label('Necesită bilet de acces')
                            ->helperText('Serviciul poate fi cumpărat doar împreună cu un bilet acces valid pentru aceeași zi.'),
                    ]),

                SC\Section::make('Leisure: Disponibilitate generală')
                    ->description('Stoc maximal pe zi și programul săptămânal. Excepțiile (zile închise / capacități modificate punctual) se setează separat la "Excepții capacități".')
                    ->icon('heroicon-o-calendar-days')
                    ->visible($isLeisureTenant)
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('leisure_default_daily_capacity')
                            ->label('Capacitate / zi')
                            ->numeric()
                            ->minValue(0)
                            ->placeholder('ex: 500')
                            ->helperText('Stocul implicit pentru fiecare zi din program. Gol = nelimitat.'),
                        Forms\Components\CheckboxList::make('leisure_schedule_days')
                            ->label('Zile de funcționare')
                            ->options([
                                1 => 'Luni', 2 => 'Marți', 3 => 'Miercuri', 4 => 'Joi',
                                5 => 'Vineri', 6 => 'Sâmbătă', 7 => 'Duminică',
                            ])
                            ->columns(7)
                            ->helperText('Gol = toate zilele.')
                            ->columnSpanFull(),
                        Forms\Components\TimePicker::make('leisure_schedule_open_time')
                            ->label('Ora deschidere')
                            ->seconds(false)
                            ->placeholder('09:00'),
                        Forms\Components\TimePicker::make('leisure_schedule_close_time')
                            ->label('Ora închidere')
                            ->seconds(false)
                            ->placeholder('20:00'),
                        Forms\Components\TextInput::make('leisure_slot_duration_minutes')
                            ->label('Durată slot (minute)')
                            ->numeric()
                            ->placeholder('ex: 60 pentru sloturi orare')
                            ->helperText('Pentru rental pe timp: cât durează un slot. Gol = capacitate zilnică simplă.')
                            ->columnSpanFull(),
                    ]),

                SC\Section::make('Leisure: Variante durată (rentals)')
                    ->description('Pentru rentals cu durate diferite (ex: 30min/1h/2h). Lasă gol dacă durata e fixă.')
                    ->icon('heroicon-o-clock')
                    ->visible(fn (callable $get) => $isLeisureTenant() && in_array($get('service_category'), ['rental', 'activity'], true))
                    ->schema([
                        Forms\Components\Repeater::make('leisure_duration_variants')
                            ->label('')
                            ->schema([
                                Forms\Components\TextInput::make('duration_minutes')
                                    ->label('Durată')
                                    ->numeric()
                                    ->suffix('min')
                                    ->required(),
                                Forms\Components\TextInput::make('label')
                                    ->label('Etichetă afișată')
                                    ->placeholder('ex: 30 min, 1h, 2h')
                                    ->required(),
                                Forms\Components\TextInput::make('price_multiplier')
                                    ->label('Multiplicator preț')
                                    ->numeric()
                                    ->step(0.01)
                                    ->default(1.0)
                                    ->helperText('1.0 = prețul de bază. 1.5 = +50%.')
                                    ->required(),
                            ])
                            ->columns(3)
                            ->collapsible()
                            ->itemLabel(fn (array $state) => ($state['label'] ?? 'Variantă') . ' (' . ($state['duration_minutes'] ?? '?') . ' min)')
                            ->reorderableWithButtons()
                            ->defaultItems(0),

                        Forms\Components\Toggle::make('leisure_is_overtime_chargeable')
                            ->label('Aplică surcharge la depășire durată')
                            ->live(),

                        SC\Grid::make(2)
                            ->visible(fn (callable $get) => $get('leisure_is_overtime_chargeable'))
                            ->schema([
                                Forms\Components\TextInput::make('leisure_overtime_surcharge_cents')
                                    ->label('Surcharge (cenți / interval)')
                                    ->numeric()
                                    ->suffix('cents'),
                                Forms\Components\TextInput::make('leisure_overtime_interval_minutes')
                                    ->label('Interval (min)')
                                    ->numeric()
                                    ->suffix('min')
                                    ->helperText('Ex: 30 min = surcharge aplicat la fiecare 30 min de depășire.'),
                            ]),
                    ]),

                SC\Section::make('Leisure: Reguli preț per zi')
                    ->description('Modificatori automatici aplicați în funcție de ziua săptămânii (ex: weekend +25%).')
                    ->icon('heroicon-o-calendar-days')
                    ->visible($isLeisureTenant)
                    ->collapsed(fn ($record) => empty($record?->leisure_pricing_rules))
                    ->schema([
                        Forms\Components\Repeater::make('leisure_pricing_rules')
                            ->label('')
                            ->schema([
                                Forms\Components\TextInput::make('label')
                                    ->label('Etichetă internă')
                                    ->placeholder('ex: Weekend +25%')
                                    ->required(),
                                Forms\Components\CheckboxList::make('days')
                                    ->label('Zile aplicabile')
                                    ->options([
                                        1 => 'Luni',
                                        2 => 'Marți',
                                        3 => 'Miercuri',
                                        4 => 'Joi',
                                        5 => 'Vineri',
                                        6 => 'Sâmbătă',
                                        7 => 'Duminică',
                                    ])
                                    ->columns(7)
                                    ->required(),
                                Forms\Components\Select::make('type')
                                    ->label('Tip')
                                    ->options([
                                        'percent' => '% Modificare',
                                        'fixed' => 'Sumă fixă (RON)',
                                    ])
                                    ->required(),
                                Forms\Components\TextInput::make('value')
                                    ->label('Valoare')
                                    ->numeric()
                                    ->step(0.01)
                                    ->helperText('Pozitiv pentru creștere, negativ pentru reducere. Ex: 25 (% / RON) sau -10.')
                                    ->required(),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->itemLabel(fn (array $state) => $state['label'] ?? 'Regulă')
                            ->defaultItems(0),
                    ]),

                SC\Section::make('Leisure: Sezoane')
                    ->description('Intervale calendaristice cu pricing diferit (ex: vară 1 iulie – 31 august).')
                    ->icon('heroicon-o-sun')
                    ->visible($isLeisureTenant)
                    ->collapsed(fn ($record) => empty($record?->leisure_seasons))
                    ->schema([
                        Forms\Components\Repeater::make('leisure_seasons')
                            ->label('')
                            ->schema([
                                Forms\Components\TextInput::make('label')
                                    ->label('Nume sezon')
                                    ->placeholder('ex: Vară, Sărbători')
                                    ->required(),
                                Forms\Components\DatePicker::make('start_date')
                                    ->label('Început')
                                    ->required(),
                                Forms\Components\DatePicker::make('end_date')
                                    ->label('Sfârșit')
                                    ->required(),
                                Forms\Components\Select::make('type')
                                    ->label('Tip modificator')
                                    ->options([
                                        'percent' => '% Modificare',
                                        'fixed' => 'Sumă fixă (RON)',
                                    ])
                                    ->nullable()
                                    ->helperText('Lasă gol dacă vrei doar să marchezi sezonul fără să modifici prețul.'),
                                Forms\Components\TextInput::make('value')
                                    ->label('Valoare')
                                    ->numeric()
                                    ->step(0.01)
                                    ->nullable(),
                                Forms\Components\TextInput::make('last_entry')
                                    ->label('Ultima intrare (HH:MM)')
                                    ->placeholder('ex: 17:00')
                                    ->nullable(),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->itemLabel(fn (array $state) => $state['label'] ?? 'Sezon')
                            ->defaultItems(0),
                    ]),

                SC\Section::make('Descriere produs & Termeni')
                    ->icon('heroicon-o-document-text')
                    ->visible($isLeisureTenant)
                    ->collapsed()
                    ->schema([
                        Forms\Components\RichEditor::make('product_description')
                            ->label('Descriere produs')
                            ->helperText('Apare pe pagina publică a produsului.'),
                        Forms\Components\RichEditor::make('usage_terms')
                            ->label('Termeni de utilizare')
                            ->helperText('Apar la confirmarea biletului. Reguli de utilizare, restricții etc.'),
                    ]),
                    ]),

                // ========== COLOANA DREAPTĂ (1/4) — sidebar ==========
                SC\Group::make()
                    ->columnSpan(1)
                    ->schema([
                        SC\Section::make('Status')
                            ->schema([
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Activ')
                                    ->default(true)
                                    ->inline(false),
                            ]),

                        SC\Section::make('Preț de bază')
                            ->schema([
                                Forms\Components\TextInput::make('price')
                                    ->numeric()
                                    ->required()
                                    ->prefix(fn ($get) => $get('currency') ?? 'RON'),
                                Forms\Components\Select::make('currency')
                                    ->options([
                                        'RON' => 'RON',
                                        'EUR' => 'EUR',
                                        'USD' => 'USD',
                                    ])
                                    ->default('RON'),
                            ]),

                        SC\Section::make('Limite comandă')
                            ->schema([
                                Forms\Components\TextInput::make('available_quantity')
                                    ->label('Cantitate disponibilă')
                                    ->numeric()
                                    ->required(),
                                Forms\Components\TextInput::make('max_per_order')
                                    ->label('Max / comandă')
                                    ->numeric()
                                    ->default(10),
                            ]),

                        SC\Section::make('Prețuri per canal')
                            ->description('Cenți. Gol = preț de bază.')
                            ->visible(fn () => $isLeisureTenant() && (auth()->user()?->tenant?->features['leisure']['channel_pricing']['enabled'] ?? false))
                            ->collapsed()
                            ->schema([
                                Forms\Components\TextInput::make('channel_pricing.online')
                                    ->label('Online')
                                    ->numeric()
                                    ->suffix('cents'),
                                Forms\Components\TextInput::make('channel_pricing.pos_fixed')
                                    ->label('POS fix')
                                    ->numeric()
                                    ->suffix('cents'),
                                Forms\Components\TextInput::make('channel_pricing.pos_mobile')
                                    ->label('POS mobil')
                                    ->numeric()
                                    ->suffix('cents'),
                                Forms\Components\TextInput::make('channel_pricing.embed')
                                    ->label('Embed')
                                    ->numeric()
                                    ->suffix('cents'),
                                Forms\Components\TextInput::make('channel_pricing.partner_app')
                                    ->label('App parteneri')
                                    ->numeric()
                                    ->suffix('cents'),
                            ]),

                        SC\Section::make('Societate emitentă')
                            ->visible(fn () => $isLeisureTenant() && (auth()->user()?->tenant?->features['leisure']['multi_society']['enabled'] ?? false))
                            ->collapsed()
                            ->schema([
                                Forms\Components\Select::make('tenant_tax_registry_id')
                                    ->label('CIF')
                                    ->options(function () {
                                        $tenantId = auth()->user()?->tenant?->id;
                                        return \App\Models\Leisure\TenantTaxRegistry::query()
                                            ->where('tenant_id', $tenantId)
                                            ->where('is_active', true)
                                            ->get()
                                            ->mapWithKeys(fn ($r) => [$r->id => "{$r->company_name} ({$r->cui})"]);
                                    })
                                    ->placeholder('Implicită')
                                    ->helperText('Gol = societatea default.'),
                            ]),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event.title')
                    ->label('Event')
                    ->formatStateUsing(fn ($record) => $record->event?->getTranslation('title', app()->getLocale()) ?? '-')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('available_quantity')
                    ->label('Available')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTicketTypes::route('/'),
            'create' => Pages\CreateTicketType::route('/create'),
            'edit' => Pages\EditTicketType::route('/{record}/edit'),
        ];
    }
}

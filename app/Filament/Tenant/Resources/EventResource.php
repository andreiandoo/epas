<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\EventResource\Pages;
use App\Models\Event;
use App\Models\EventGenre;
use App\Models\EventType;
use App\Models\Venue;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Utilities\Get as SGet;
use Filament\Schemas\Components\Utilities\Set as SSet;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-calendar';
    protected static \UnitEnum|string|null $navigationGroup = 'Content';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()->where('tenant_id', $tenant?->id);
    }

    public static function form(Schema $schema): Schema
    {
        $today = Carbon::today();
        $tenant = auth()->user()->tenant;

        return $schema->schema([
            // Hidden tenant_id field
            Forms\Components\Hidden::make('tenant_id')
                ->default($tenant?->id),

            // BASICS - EN/RO
            SC\Section::make('Event Details')
                ->schema([
                    SC\Tabs::make('Title Translations')
                        ->tabs([
                            SC\Tabs\Tab::make('English')
                                ->schema([
                                    Forms\Components\TextInput::make('title.en')
                                        ->label('Event title (EN)')
                                        ->required()
                                        ->maxLength(190)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function ($state, SSet $set) {
                                            if ($state) $set('slug.en', Str::slug($state));
                                        }),
                                    Forms\Components\TextInput::make('slug.en')
                                        ->label('Slug (EN)')
                                        ->maxLength(190)
                                        ->rule('alpha_dash'),
                                ])->columns(2),
                            SC\Tabs\Tab::make('Română')
                                ->schema([
                                    Forms\Components\TextInput::make('title.ro')
                                        ->label('Titlu eveniment (RO)')
                                        ->maxLength(190)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function ($state, SSet $set) {
                                            if ($state) $set('slug.ro', Str::slug($state));
                                        }),
                                    Forms\Components\TextInput::make('slug.ro')
                                        ->label('Slug (RO)')
                                        ->maxLength(190)
                                        ->rule('alpha_dash'),
                                ])->columns(2),
                        ])->columnSpanFull(),
                ]),

            // FLAGS
            SC\Section::make('Status Flags')
                ->schema([
                    SC\Grid::make(5)->schema([
                        Forms\Components\Toggle::make('is_sold_out')
                            ->label('Sold out')
                            ->live(),
                        Forms\Components\Toggle::make('door_sales_only')
                            ->label('Door sales only'),
                        Forms\Components\Toggle::make('is_cancelled')
                            ->label('Cancelled')
                            ->live(),
                        Forms\Components\Toggle::make('is_postponed')
                            ->label('Postponed')
                            ->live(),
                        Forms\Components\Toggle::make('is_promoted')
                            ->label('Promoted')
                            ->live(),
                    ]),

                    Forms\Components\Textarea::make('cancel_reason')
                        ->label('Cancellation reason')
                        ->rows(2)
                        ->visible(fn (SGet $get) => (bool) $get('is_cancelled')),

                    SC\Grid::make(4)->schema([
                        Forms\Components\DatePicker::make('postponed_date')
                            ->label('New date')
                            ->minDate($today)
                            ->native(false),
                        Forms\Components\TimePicker::make('postponed_start_time')
                            ->label('Start time')
                            ->seconds(false)
                            ->native(true),
                        Forms\Components\TimePicker::make('postponed_door_time')
                            ->label('Door time')
                            ->seconds(false)
                            ->native(true),
                        Forms\Components\TimePicker::make('postponed_end_time')
                            ->label('End time')
                            ->seconds(false)
                            ->native(true),
                    ])->visible(fn (SGet $get) => (bool) $get('is_postponed')),

                    Forms\Components\Textarea::make('postponed_reason')
                        ->label('Postponement reason')
                        ->rows(2)
                        ->visible(fn (SGet $get) => (bool) $get('is_postponed')),

                    Forms\Components\DatePicker::make('promoted_until')
                        ->label('Promoted until')
                        ->minDate($today)
                        ->native(false)
                        ->visible(fn (SGet $get) => (bool) $get('is_promoted')),
                ])->columns(1),

            // SCHEDULE
            SC\Section::make('Schedule')
                ->schema([
                    Forms\Components\Radio::make('duration_mode')
                        ->label('Duration')
                        ->options([
                            'single_day' => 'Single day',
                            'range' => 'Range',
                            'multi_day' => 'Multiple days',
                            'recurring' => 'Recurring',
                        ])
                        ->inline()
                        ->default('single_day')
                        ->required()
                        ->live(),

                    // Single day
                    SC\Grid::make(4)->schema([
                        Forms\Components\DatePicker::make('event_date')
                            ->label('Date')
                            ->minDate($today)
                            ->native(false),
                        Forms\Components\TimePicker::make('start_time')
                            ->label('Start time')
                            ->seconds(false)
                            ->native(true)
                            ->required(fn (SGet $get) => $get('duration_mode') === 'single_day'),
                        Forms\Components\TimePicker::make('door_time')
                            ->label('Door time')
                            ->seconds(false)
                            ->native(true),
                        Forms\Components\TimePicker::make('end_time')
                            ->label('End time')
                            ->seconds(false)
                            ->native(true),
                    ])->visible(fn (SGet $get) => $get('duration_mode') === 'single_day'),

                    // Range
                    SC\Grid::make(4)->schema([
                        Forms\Components\DatePicker::make('range_start_date')
                            ->label('Start date')
                            ->minDate($today)
                            ->native(false),
                        Forms\Components\DatePicker::make('range_end_date')
                            ->label('End date')
                            ->native(false),
                        Forms\Components\TimePicker::make('range_start_time')
                            ->label('Start time')
                            ->seconds(false)
                            ->native(true),
                        Forms\Components\TimePicker::make('range_end_time')
                            ->label('End time')
                            ->seconds(false)
                            ->native(true),
                    ])->visible(fn (SGet $get) => $get('duration_mode') === 'range'),

                    // Multi day
                    Forms\Components\Repeater::make('multi_slots')
                        ->label('Days & times')
                        ->schema([
                            Forms\Components\DatePicker::make('date')
                                ->label('Date')
                                ->minDate($today)
                                ->native(false)
                                ->required(),
                            Forms\Components\TimePicker::make('start_time')
                                ->label('Start')
                                ->seconds(false)
                                ->native(true),
                            Forms\Components\TimePicker::make('door_time')
                                ->label('Door')
                                ->seconds(false)
                                ->native(true),
                            Forms\Components\TimePicker::make('end_time')
                                ->label('End')
                                ->seconds(false)
                                ->native(true),
                        ])
                        ->addActionLabel('Add another date')
                        ->default([])
                        ->visible(fn (SGet $get) => $get('duration_mode') === 'multi_day')
                        ->columns(4),

                    // Recurring
                    SC\Group::make()
                        ->visible(fn (SGet $get) => $get('duration_mode') === 'recurring')
                        ->schema([
                            SC\Grid::make(4)->schema([
                                Forms\Components\DatePicker::make('recurring_start_date')
                                    ->label('Initial date')
                                    ->minDate(now()->startOfDay())
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function ($state, SSet $set) {
                                        if (!$state) { $set('recurring_weekday', null); return; }
                                        $w = Carbon::parse($state)->dayOfWeekIso;
                                        $set('recurring_weekday', $w);
                                    }),
                                Forms\Components\TextInput::make('recurring_weekday')
                                    ->label('Weekday')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->formatStateUsing(function (SGet $get) {
                                        $map = [1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'];
                                        return $map[$get('recurring_weekday')] ?? '';
                                    }),
                                Forms\Components\Select::make('recurring_frequency')
                                    ->label('Recurrence')
                                    ->options([
                                        'weekly' => 'Weekly',
                                        'monthly_nth' => 'Monthly (Nth weekday)',
                                    ])
                                    ->required()
                                    ->live(),
                                Forms\Components\TextInput::make('recurring_count')
                                    ->label('Occurrences')
                                    ->numeric()
                                    ->minValue(1),
                            ]),
                            SC\Grid::make(2)
                                ->visible(fn (SGet $get) => $get('recurring_frequency') === 'monthly_nth')
                                ->schema([
                                    Forms\Components\Select::make('recurring_week_of_month')
                                        ->label('Week of month')
                                        ->options([
                                            1 => 'First', 2 => 'Second', 3 => 'Third', 4 => 'Fourth', -1 => 'Last',
                                        ])
                                        ->required(),
                                ]),
                            SC\Grid::make(3)->schema([
                                Forms\Components\TimePicker::make('recurring_start_time')
                                    ->label('Start time')
                                    ->seconds(false)->native(true)
                                    ->required(),
                                Forms\Components\TimePicker::make('recurring_door_time')
                                    ->label('Door time')
                                    ->seconds(false)->native(true),
                                Forms\Components\TimePicker::make('recurring_end_time')
                                    ->label('End time')
                                    ->seconds(false)->native(true),
                            ]),
                        ]),
                ])->columns(1),

            // LOCATION & LINKS
            SC\Section::make('Location & Links')
                ->schema([
                    Forms\Components\Select::make('venue_id')
                        ->label('Venue')
                        ->searchable()
                        ->preload()
                        ->live()
                        ->options(function () use ($tenant) {
                            return Venue::query()
                                ->where(fn($q) => $q
                                    ->whereNull('tenant_id')
                                    ->orWhere('tenant_id', $tenant?->id))
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn ($venue) => [
                                    $venue->id => $venue->getTranslation('name', app()->getLocale())
                                ]);
                        })
                        ->afterStateUpdated(function ($state, SSet $set) {
                            if ($state) {
                                $venue = Venue::find($state);
                                if ($venue) {
                                    $set('address', $venue->address ?? $venue->full_address ?? '');
                                    $set('website_url', $venue->website_url ?? '');
                                }
                            }
                        })
                        ->nullable(),
                    Forms\Components\TextInput::make('address')
                        ->label('Address')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('website_url')
                        ->label('Website')
                        ->url()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('facebook_url')
                        ->label('Facebook Event')
                        ->url()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('event_website_url')
                        ->label('Event Website')
                        ->url()
                        ->maxLength(255),
                ])->columns(2),

            // MEDIA
            SC\Section::make('Media')
                ->schema([
                    Forms\Components\FileUpload::make('poster_url')
                        ->label('Poster (vertical)')
                        ->image()
                        ->directory('events/posters')
                        ->disk('public'),
                    Forms\Components\FileUpload::make('hero_image_url')
                        ->label('Hero image (horizontal)')
                        ->image()
                        ->directory('events/hero')
                        ->disk('public'),
                ])->columns(2),

            // CONTENT - EN/RO
            SC\Section::make('Content')
                ->schema([
                    SC\Tabs::make('Content Translations')
                        ->tabs([
                            SC\Tabs\Tab::make('English')
                                ->schema([
                                    Forms\Components\Textarea::make('short_description.en')
                                        ->label('Short description (EN)')
                                        ->rows(3),
                                    Forms\Components\RichEditor::make('description.en')
                                        ->label('Description (EN)')
                                        ->columnSpanFull(),
                                    Forms\Components\RichEditor::make('ticket_terms.en')
                                        ->label('Ticket terms (EN)')
                                        ->columnSpanFull()
                                        ->afterStateHydrated(function ($component, SGet $get) use ($tenant) {
                                            // Auto-fill from tenant if empty
                                            if (!$component->getState() && $tenant?->ticket_terms) {
                                                $component->state($tenant->ticket_terms);
                                            }
                                        }),
                                ]),
                            SC\Tabs\Tab::make('Română')
                                ->schema([
                                    Forms\Components\Textarea::make('short_description.ro')
                                        ->label('Descriere scurtă (RO)')
                                        ->rows(3),
                                    Forms\Components\RichEditor::make('description.ro')
                                        ->label('Descriere (RO)')
                                        ->columnSpanFull(),
                                    Forms\Components\RichEditor::make('ticket_terms.ro')
                                        ->label('Termeni bilete (RO)')
                                        ->columnSpanFull()
                                        ->afterStateHydrated(function ($component, SGet $get) use ($tenant) {
                                            // Auto-fill from tenant if empty
                                            if (!$component->getState() && $tenant?->ticket_terms) {
                                                $component->state($tenant->ticket_terms);
                                            }
                                        }),
                                ]),
                        ])->columnSpanFull(),
                ])->columns(1),

            // TAXONOMIES
            SC\Section::make('Taxonomies & Relations')
                ->schema([
                    Forms\Components\Select::make('eventTypes')
                        ->label('Event types')
                        ->relationship(
                            name: 'eventTypes',
                            modifyQueryUsing: fn (Builder $query) => $query->whereNotNull('parent_id')->orderBy('name')
                        )
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('name', app()->getLocale()))
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->maxItems(2)
                        ->live()
                        ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                            $typeIds = (array) ($get('eventTypes') ?? []);
                            if (!$typeIds) {
                                $set('eventGenres', []);
                                return;
                            }
                            $allowed = EventGenre::query()
                                ->whereExists(function ($sub) use ($typeIds) {
                                    $sub->selectRaw('1')
                                        ->from('event_type_event_genre as eteg')
                                        ->whereColumn('eteg.event_genre_id', 'event_genres.id')
                                        ->whereIn('eteg.event_type_id', $typeIds);
                                })
                                ->pluck('id')
                                ->all();
                            $current = (array) ($get('eventGenres') ?? []);
                            $filtered = array_values(array_intersect($current, $allowed));
                            if (count($filtered) !== count($current)) {
                                $set('eventGenres', $filtered);
                            }
                        }),

                    Forms\Components\Select::make('eventGenres')
                        ->label('Event genres')
                        ->relationship(
                            name: 'eventGenres',
                            modifyQueryUsing: function (Builder $query, SGet $get) {
                                $typeIds = (array) ($get('eventTypes') ?? []);
                                if (!$typeIds) {
                                    $query->whereRaw('1=0');
                                    return;
                                }
                                $query->whereExists(function ($sub) use ($typeIds) {
                                    $sub->selectRaw('1')
                                        ->from('event_type_event_genre as eteg')
                                        ->whereColumn('eteg.event_genre_id', 'event_genres.id')
                                        ->whereIn('eteg.event_type_id', $typeIds);
                                })->orderBy('name');
                            }
                        )
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('name', app()->getLocale()))
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->disabled(fn (SGet $get) => empty($get('eventTypes')))
                        ->maxItems(5),

                    Forms\Components\Select::make('artists')
                        ->label('Artists')
                        ->relationship('artists', 'name')
                        ->multiple()
                        ->preload()
                        ->searchable(),

                    Forms\Components\Select::make('tags')
                        ->label('Event tags')
                        ->relationship('tags', 'name')
                        ->multiple()
                        ->preload()
                        ->searchable(),
                ])->columns(2),

            // TICKETS
            SC\Section::make('Tickets')
                ->schema([
                    Forms\Components\Repeater::make('ticketTypes')
                        ->relationship()
                        ->label('Ticket types')
                        ->collapsed()
                        ->addActionLabel('Add ticket type')
                        ->itemLabel(fn (array $state) => $state['name'] ?? 'Ticket')
                        ->columns(12)
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Name')
                                ->placeholder('e.g. Early Bird, Standard, VIP')
                                ->datalist(['Early Bird','Standard','VIP','Backstage','Student','Senior','Child'])
                                ->required()
                                ->columnSpan(6)
                                ->live(debounce: 400)
                                ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                    if ($get('sku')) return;
                                    $set('sku', Str::upper(Str::slug($state, '-')));
                                }),
                            Forms\Components\TextInput::make('sku')
                                ->label('SKU')
                                ->placeholder('AUTO-GEN if left empty')
                                ->columnSpan(6),

                            SC\Grid::make(4)->schema([
                                Forms\Components\TextInput::make('currency')
                                    ->label('Currency')
                                    ->default($tenant?->currency ?? 'RON')
                                    ->disabled()
                                    ->dehydrated(true),
                                Forms\Components\TextInput::make('price_max')
                                    ->label('Price')
                                    ->placeholder('e.g. 120.00')
                                    ->numeric()
                                    ->minValue(0)
                                    ->required()
                                    ->live(debounce: 300)
                                    ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                        $price = (float) $state;
                                        $sale = $get('price');
                                        $disc = $get('discount_percent');
                                        if ($price > 0 && !$sale && is_numeric($disc)) {
                                            $disc = max(0, min(100, (float)$disc));
                                            $set('price', round($price * (1 - $disc/100), 2));
                                        }
                                        if ($price > 0 && $sale) {
                                            $d = round((1 - ((float)$sale / $price)) * 100, 2);
                                            $set('discount_percent', max(0, min(100, $d)));
                                        }
                                    }),
                                Forms\Components\TextInput::make('price')
                                    ->label('Sale price')
                                    ->placeholder('leave empty if no sale')
                                    ->numeric()
                                    ->minValue(0)
                                    ->live(debounce: 300)
                                    ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                        $price = (float) ($get('price_max') ?: 0);
                                        $sale = $state !== null && $state !== '' ? (float)$state : null;
                                        if ($price > 0 && $sale) {
                                            $d = round((1 - ($sale / $price)) * 100, 2);
                                            $set('discount_percent', max(0, min(100, $d)));
                                        } else {
                                            $set('discount_percent', null);
                                        }
                                    }),
                                Forms\Components\TextInput::make('discount_percent')
                                    ->label('Discount %')
                                    ->placeholder('e.g. 20')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->live(debounce: 300)
                                    ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                        $price = (float) ($get('price_max') ?: 0);
                                        if ($price <= 0) return;
                                        if ($state === null || $state === '') {
                                            $set('price', null);
                                            return;
                                        }
                                        $disc = max(0, min(100, (float)$state));
                                        $set('price', round($price * (1 - $disc/100), 2));
                                    }),
                            ])->columnSpan(12),

                            SC\Grid::make(3)->schema([
                                Forms\Components\TextInput::make('capacity')
                                    ->label('Capacity')
                                    ->placeholder('e.g. 250')
                                    ->numeric()
                                    ->minValue(0)
                                    ->required(),
                                Forms\Components\DateTimePicker::make('sale_starts_at')
                                    ->label('Sale starts')
                                    ->native(false)
                                    ->minDate(now()),
                                Forms\Components\DateTimePicker::make('sale_ends_at')
                                    ->label('Sale ends')
                                    ->native(false),
                            ])->columnSpan(12),

                            // Bulk discounts
                            Forms\Components\Repeater::make('bulk_discounts')
                                ->label('Bulk discounts')
                                ->collapsed()
                                ->addActionLabel('Add bulk rule')
                                ->itemLabel(fn (array $state) => $state['rule_type'] ?? 'Rule')
                                ->columns(12)
                                ->schema([
                                    Forms\Components\Select::make('rule_type')
                                        ->label('Rule type')
                                        ->options([
                                            'buy_x_get_y' => 'Buy X get Y free',
                                            'buy_x_percent_off' => 'Buy X tickets → % off',
                                            'amount_off_per_ticket' => 'Amount off per ticket (min qty)',
                                            'bundle_price' => 'Bundle price (X tickets for total)',
                                        ])
                                        ->required()
                                        ->columnSpan(3)
                                        ->live(),
                                    Forms\Components\TextInput::make('buy_qty')
                                        ->label('Buy X')
                                        ->numeric()->minValue(1)
                                        ->visible(fn ($get) => $get('rule_type') === 'buy_x_get_y')
                                        ->columnSpan(3),
                                    Forms\Components\TextInput::make('get_qty')
                                        ->label('Get Y free')
                                        ->numeric()->minValue(1)
                                        ->visible(fn ($get) => $get('rule_type') === 'buy_x_get_y')
                                        ->columnSpan(3),
                                    Forms\Components\TextInput::make('min_qty')
                                        ->label('Min qty')
                                        ->numeric()->minValue(1)
                                        ->visible(fn ($get) => in_array($get('rule_type'), ['buy_x_percent_off','amount_off_per_ticket','bundle_price']))
                                        ->columnSpan(3),
                                    Forms\Components\TextInput::make('percent_off')
                                        ->label('% off')
                                        ->numeric()->minValue(1)->maxValue(100)
                                        ->visible(fn ($get) => $get('rule_type') === 'buy_x_percent_off')
                                        ->columnSpan(3),
                                    Forms\Components\TextInput::make('amount_off')
                                        ->label('Amount off')
                                        ->numeric()->minValue(0.01)
                                        ->visible(fn ($get) => $get('rule_type') === 'amount_off_per_ticket')
                                        ->columnSpan(3),
                                    Forms\Components\TextInput::make('bundle_total_price')
                                        ->label('Bundle total')
                                        ->numeric()->minValue(0.01)
                                        ->visible(fn ($get) => $get('rule_type') === 'bundle_price')
                                        ->columnSpan(3),
                                ])
                                ->columnSpan(12),

                            Forms\Components\Toggle::make('is_active')
                                ->label('Active?')
                                ->default(true)
                                ->columnSpan(12),
                        ]),
                ])->collapsible(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('venue.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('event_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_cancelled')
                    ->boolean()
                    ->label('Cancelled'),
                Tables\Columns\IconColumn::make('is_sold_out')
                    ->boolean()
                    ->label('Sold Out'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_cancelled'),
                Tables\Filters\TernaryFilter::make('is_sold_out'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvents::route('/'),
            'create' => Pages\CreateEvent::route('/create'),
            'edit' => Pages\EditEvent::route('/{record}/edit'),
        ];
    }
}

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

        // Get tenant's language (check both 'language' and 'locale' columns)
        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';

        return $schema->schema([
            // Hidden tenant_id field
            Forms\Components\Hidden::make('tenant_id')
                ->default($tenant?->id),

            // BASICS - Single Language based on Tenant setting
            SC\Section::make('Event Details')
                ->schema([
                    SC\Group::make()
                        ->schema([
                            Forms\Components\TextInput::make("title.{$tenantLanguage}")
                                ->label($tenantLanguage === 'ro' ? 'Titlu eveniment' : 'Event title')
                                ->required()
                                ->maxLength(190)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function ($state, SSet $set) {
                                    // Slug is NOT translatable - it's a plain string field
                                    if ($state) $set('slug', Str::slug($state));
                                }),
                            Forms\Components\TextInput::make('slug')
                                ->label('Slug')
                                ->maxLength(190)
                                ->rule('alpha_dash'),
                        ])->columns(2)->columnSpanFull(),
                ]),

            // FLAGS
            SC\Section::make('Status Flags')
                ->schema([
                    SC\Grid::make(5)->schema([
                        Forms\Components\Toggle::make('is_sold_out')
                            ->label('Sold out')
                            ->onIcon('heroicon-m-lock-closed')
                            ->offIcon('heroicon-m-lock-open')
                            ->live()
                            ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                if ($state) {
                                    if ($get('is_cancelled')) $set('is_cancelled', false);
                                }
                            })
                            ->disabled(fn (SGet $get) => (bool) $get('is_cancelled')),
                        Forms\Components\Toggle::make('door_sales_only')
                            ->label('Door sales only')
                            ->onIcon('heroicon-m-key')
                            ->offIcon('heroicon-m-key')
                            ->disabled(fn (SGet $get) => (bool) $get('is_cancelled')),
                        Forms\Components\Toggle::make('is_cancelled')
                            ->label('Cancelled')
                            ->onIcon('heroicon-m-x-circle')
                            ->offIcon('heroicon-m-x-circle')
                            ->live()
                            ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                if ($state) {
                                    if ($get('is_postponed')) $set('is_postponed', false);
                                    if ($get('is_sold_out'))  $set('is_sold_out', false);
                                    if ($get('is_promoted'))  $set('is_promoted', false);
                                    $set('promoted_until', null);
                                }
                            }),
                        Forms\Components\Toggle::make('is_postponed')
                            ->label('Postponed')
                            ->onIcon('heroicon-m-clock')
                            ->offIcon('heroicon-m-clock')
                            ->live()
                            ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                if ($state) {
                                    if ($get('is_cancelled')) $set('is_cancelled', false);
                                } else {
                                    $set('postponed_date', null);
                                    $set('postponed_start_time', null);
                                    $set('postponed_door_time', null);
                                    $set('postponed_end_time', null);
                                    $set('postponed_reason', null);
                                }
                            })
                            ->disabled(fn (SGet $get) => (bool) $get('is_cancelled')),
                        Forms\Components\Toggle::make('is_promoted')
                            ->label('Promoted')
                            ->onIcon('heroicon-m-sparkles')
                            ->offIcon('heroicon-m-sparkles')
                            ->live()
                            ->afterStateUpdated(function ($state, SSet $set) {
                                if (!$state) $set('promoted_until', null);
                            })
                            ->disabled(fn (SGet $get) => (bool) $get('is_cancelled')),
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

            // CONTENT - Single Language
            SC\Section::make('Content')
                ->schema([
                    Forms\Components\Textarea::make("short_description.{$tenantLanguage}")
                        ->label($tenantLanguage === 'ro' ? 'Descriere scurtă' : 'Short description')
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\RichEditor::make("description.{$tenantLanguage}")
                        ->label($tenantLanguage === 'ro' ? 'Descriere' : 'Description')
                        ->columnSpanFull(),
                    Forms\Components\RichEditor::make("ticket_terms.{$tenantLanguage}")
                        ->label($tenantLanguage === 'ro' ? 'Termeni bilete' : 'Ticket terms')
                        ->columnSpanFull()
                        ->default($tenant?->ticket_terms ?? null),
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
                    // Commission Mode for event
                    SC\Grid::make(2)->schema([
                        Forms\Components\Select::make('commission_mode')
                            ->label('Commission Mode')
                            ->options([
                                'included' => 'Include commission in ticket price (you receive less)',
                                'added_on_top' => 'Add commission on top (customer pays more)',
                            ])
                            ->placeholder('Use default from contract')
                            ->helperText(function () use ($tenant) {
                                $mode = $tenant->commission_mode ?? 'included';
                                $rate = $tenant->commission_rate ?? 5.00;
                                $modeText = $mode === 'included'
                                    ? 'included in price'
                                    : 'added on top';
                                return "Your default: {$rate}% {$modeText}. Leave empty to use this default.";
                            })
                            ->live()
                            ->nullable(),

                        Forms\Components\Placeholder::make('commission_example')
                            ->label('Example (100 RON ticket)')
                            ->live()
                            ->content(function (SGet $get) use ($tenant) {
                                $eventMode = $get('commission_mode');
                                $mode = $eventMode ?: ($tenant->commission_mode ?? 'included');
                                $rate = $tenant->commission_rate ?? 5.00;
                                $ticketPrice = 100;
                                $commission = round($ticketPrice * ($rate / 100), 2);

                                if ($mode === 'included') {
                                    $revenue = $ticketPrice - $commission;
                                    return "Customer pays: **{$ticketPrice} RON** → You receive: **{$revenue} RON** (commission: {$commission} RON)";
                                } else {
                                    $total = $ticketPrice + $commission;
                                    return "Customer pays: **{$total} RON** → You receive: **{$ticketPrice} RON** (commission: {$commission} RON)";
                                }
                            }),
                    ]),

                    Forms\Components\Repeater::make('ticketTypes')
                        ->relationship()
                        ->label('Ticket types')
                        ->collapsed()
                        ->addActionLabel('Add ticket type')
                        ->itemLabel(fn (array $state) => ($state['is_active'] ?? true)
                            ? '✓ ' . ($state['name'] ?? 'Ticket')
                            : '○ ' . ($state['name'] ?? 'Ticket'))
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

                            Forms\Components\Textarea::make('description')
                                ->label('Description')
                                ->placeholder('Optional ticket type description (e.g. "Includes backstage access and meet & greet")')
                                ->rows(2)
                                ->columnSpan(12),

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

                            // Commission calculation for this ticket
                            Forms\Components\Placeholder::make('ticket_commission_calc')
                                ->label('💰 Price with Commission')
                                ->live()
                                ->content(function (SGet $get) use ($tenant) {
                                    $price = (float) ($get('price') ?: $get('price_max') ?: 0);
                                    if ($price <= 0) {
                                        return 'Enter a price to see commission calculation.';
                                    }

                                    $eventMode = $get('../../commission_mode');
                                    $mode = $eventMode ?: ($tenant->commission_mode ?? 'included');
                                    $rate = $tenant->commission_rate ?? 5.00;
                                    $commission = round($price * ($rate / 100), 2);
                                    $currency = $get('currency') ?: 'RON';

                                    if ($mode === 'included') {
                                        $revenue = round($price - $commission, 2);
                                        return "Customer pays: **{$price} {$currency}** → You receive: **{$revenue} {$currency}** (Tixello commission: {$commission} {$currency})";
                                    } else {
                                        $total = round($price + $commission, 2);
                                        return "Customer pays: **{$total} {$currency}** → You receive: **{$price} {$currency}** (Tixello commission: {$commission} {$currency})";
                                    }
                                })
                                ->columnSpan(12),

                            SC\Grid::make(3)->schema([
                                Forms\Components\TextInput::make('capacity')
                                    ->label('Capacity')
                                    ->placeholder('e.g. 250')
                                    ->numeric()
                                    ->minValue(0)
                                    ->required(),
                                Forms\Components\DateTimePicker::make('sales_start_at')
                                    ->label('Sale starts')
                                    ->native(false)
                                    ->seconds(false)
                                    ->displayFormat('Y-m-d H:i')
                                    ->minDate(now()),
                                Forms\Components\DateTimePicker::make('sales_end_at')
                                    ->label('Sale ends')
                                    ->native(false)
                                    ->seconds(false)
                                    ->displayFormat('Y-m-d H:i'),
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
                                ->live()
                                ->columnSpan(12),
                        ]),
                ])->collapsible(),

            // SEO Section
            SC\Section::make('SEO')
                ->collapsed()
                ->schema([
                    Forms\Components\Select::make('seo_presets')
                        ->label('Add SEO keys from template')
                        ->multiple()
                        ->dehydrated(false)
                        ->options([
                            'core'        => 'Core (title/description/canonical/robots)',
                            'intl'        => 'International (hreflang, og:locale)',
                            'open_graph'  => 'Open Graph (og:*)',
                            'article'     => 'OG Article extras',
                            'product'     => 'OG Product extras',
                            'twitter'     => 'Twitter Cards',
                            'jsonld'      => 'Structured Data (JSON-LD)',
                            'robots_adv'  => 'Robots advanced',
                            'verify'      => 'Verification (Google/Bing/etc.)',
                            'feeds'       => 'Feeds (RSS/Atom/oEmbed)',
                        ])
                        ->helperText('Select templates to add keys. Values will be pre-filled from event data where available.')
                        ->live()
                        ->afterStateUpdated(function ($state, SSet $set, SGet $get) use ($tenantLanguage, $tenant) {
                            $seo = (array) ($get('seo') ?? []);

                            // Get event data for auto-fill
                            $title = $get("title.{$tenantLanguage}") ?? '';
                            $slug = $get('slug') ?? '';
                            $description = $get("short_description.{$tenantLanguage}") ?? $get("description.{$tenantLanguage}") ?? '';
                            $shortDesc = strip_tags($description);
                            if (strlen($shortDesc) > 160) {
                                $shortDesc = substr($shortDesc, 0, 157) . '...';
                            }
                            $posterUrl = $get('poster_url') ?? '';
                            $heroUrl = $get('hero_image_url') ?? '';
                            $imageUrl = $posterUrl ?: $heroUrl;
                            $eventDate = $get('event_date') ?? '';
                            $startTime = $get('start_time') ?? '';
                            $endTime = $get('end_time') ?? '';
                            $venueName = '';
                            $venueAddress = '';

                            // Try to get venue info
                            $venueId = $get('venue_id');
                            if ($venueId) {
                                $venue = \App\Models\Venue::find($venueId);
                                if ($venue) {
                                    $venueName = $venue->getTranslation('name', $tenantLanguage) ?? $venue->name ?? '';
                                    $venueAddress = $venue->address ?? '';
                                }
                            }

                            // Get tenant's primary domain for absolute URLs
                            $primaryDomain = $tenant?->domains()
                                ->where('is_primary', true)
                                ->where('is_active', true)
                                ->first();
                            $baseUrl = $primaryDomain
                                ? 'https://' . $primaryDomain->domain
                                : ($tenant?->website ?? '');

                            // Build absolute event URL
                            $eventUrl = $baseUrl && $slug ? "{$baseUrl}/event/{$slug}" : '';

                            // Build absolute image URL
                            $absoluteImageUrl = '';
                            if ($imageUrl) {
                                // If it's already an absolute URL, use as-is
                                if (str_starts_with($imageUrl, 'http://') || str_starts_with($imageUrl, 'https://')) {
                                    $absoluteImageUrl = $imageUrl;
                                } else {
                                    // Build absolute URL using storage
                                    $absoluteImageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($imageUrl);
                                }
                            }

                            // Current timestamp for article times
                            $now = now()->toIso8601String();

                            $templates = [
                                'core' => [
                                    'meta_title'       => $title,
                                    'meta_description' => $shortDesc,
                                    'canonical_url'    => $eventUrl,
                                    'robots'           => 'index,follow',
                                    'viewport'         => 'width=device-width, initial-scale=1',
                                    'referrer'         => 'no-referrer-when-downgrade',
                                ],
                                'intl' => [
                                    'og:locale'        => $tenantLanguage === 'ro' ? 'ro_RO' : 'en_US',
                                    'hreflang_map'     => '[]',
                                ],
                                'open_graph' => [
                                    'og:title'         => $title,
                                    'og:description'   => $shortDesc,
                                    'og:type'          => 'event',
                                    'og:url'           => $eventUrl,
                                    'og:image'         => $absoluteImageUrl,
                                    'og:image:alt'     => $title,
                                    'og:image:width'   => '1200',
                                    'og:image:height'  => '630',
                                    'og:site_name'     => $tenant?->public_name ?? $tenant?->name ?? '',
                                ],
                                'article' => [
                                    'article:author'         => $tenant?->public_name ?? '',
                                    'article:section'        => 'Events',
                                    'article:tag'            => '',
                                    'article:published_time' => $now,
                                    'article:modified_time'  => $now,
                                ],
                                'product' => [
                                    'product:price:amount'   => '',
                                    'product:price:currency' => $tenant?->currency ?? 'RON',
                                    'product:availability'   => 'instock',
                                ],
                                'twitter' => [
                                    'twitter:card'        => 'summary_large_image',
                                    'twitter:title'       => $title,
                                    'twitter:description' => $shortDesc,
                                    'twitter:image'       => $absoluteImageUrl,
                                    'twitter:site'        => '',
                                    'twitter:creator'     => '',
                                    'twitter:player'        => '',
                                    'twitter:player:width'  => '',
                                    'twitter:player:height' => '',
                                ],
                                'jsonld' => [
                                    'structured_data' => json_encode([
                                        '@context' => 'https://schema.org',
                                        '@type'    => 'Event',
                                        'name'     => $title,
                                        'description' => $shortDesc,
                                        'image'    => $absoluteImageUrl,
                                        'startDate'=> $eventDate && $startTime ? "{$eventDate}T{$startTime}" : $eventDate,
                                        'endDate'  => $eventDate && $endTime ? "{$eventDate}T{$endTime}" : '',
                                        'location' => [
                                            '@type'   => 'Place',
                                            'name'    => $venueName,
                                            'address' => $venueAddress,
                                        ],
                                        'organizer' => [
                                            '@type' => 'Organization',
                                            'name'  => $tenant?->public_name ?? $tenant?->name ?? '',
                                            'url'   => $baseUrl,
                                        ],
                                        'url'     => $eventUrl,
                                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                                ],
                                'robots_adv' => [
                                    'max-snippet'       => '-1',
                                    'max-image-preview' => 'large',
                                    'max-video-preview' => '-1',
                                    'noarchive'         => '',
                                    'nosnippet'         => '',
                                    'noimageindex'      => '',
                                    'indexifembedded'   => '',
                                    'googlebot'         => '',
                                    'bingbot'           => '',
                                ],
                                'verify' => [
                                    'google-site-verification'     => '',
                                    'msvalidate.01'                 => '',
                                    'p:domain_verify'               => '',
                                    'yandex-verification'           => '',
                                    'ahrefs-site-verification'      => '',
                                    'facebook-domain-verification'  => '',
                                ],
                                'feeds' => [
                                    'rss_url'         => $baseUrl ? "{$baseUrl}/feed/rss" : '',
                                    'atom_url'        => $baseUrl ? "{$baseUrl}/feed/atom" : '',
                                    'oembed_json'     => $eventUrl ? "{$eventUrl}/oembed.json" : '',
                                    'oembed_xml'      => $eventUrl ? "{$eventUrl}/oembed.xml" : '',
                                ],
                            ];

                            foreach ((array) $state as $group) {
                                foreach (($templates[$group] ?? []) as $k => $v) {
                                    if (! array_key_exists($k, $seo)) {
                                        $seo[$k] = $v;
                                    }
                                }
                            }

                            $set('seo', $seo);
                        }),

                    Forms\Components\KeyValue::make('seo')
                        ->keyLabel('Meta key')
                        ->valueLabel('Meta value')
                        ->addable()
                        ->deletable()
                        ->reorderable()
                        ->columnSpanFull()
                        ->default([
                            'meta_title'       => '',
                            'meta_description' => '',
                            'canonical_url'    => '',
                            'robots'           => 'index,follow',
                        ])
                        ->helperText('Add custom SEO meta tags. Use templates above to quickly add common sets.'),
                ]),
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

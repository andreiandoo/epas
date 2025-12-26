<?php

namespace App\Filament\Resources\Events;

use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Pages\ListEvents;
use App\Models\Event;
use App\Models\EventGenre;
use App\Models\EventType;
use App\Models\Tax\GeneralTax;
use BackedEnum;
use Illuminate\Support\HtmlString;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Utilities\Get as SGet;
use Filament\Schemas\Components\Utilities\Set as SSet;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationGroup = 'Catalog';
    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        $today = Carbon::today();

        return $schema->schema([
            SC\Group::make()
                ->extraAttributes(['id' => 'basics','data-ep-section'=>'','data-ep-id'=>'basics','data-ep-label'=>'Basics','data-ep-icon'=>'document-text'])
                ->schema([
                    Forms\Components\TextInput::make('title.en')
                        ->label('Event title (EN)')
                        ->required()
                        ->maxLength(190)
                        ->placeholder('Type the event title…')
                        ->extraAttributes(['class' => 'ep-title'])
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (string $state, SSet $set) {
                            if ($state) $set('slug.en', \Illuminate\Support\Str::slug($state));
                        }),
                    SC\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('slug.en')
                            ->label('Slug (EN)')
                            ->helperText('Editable URL slug for EN locale.')
                            ->maxLength(190)
                            ->rule('alpha_dash')
                            ->placeholder('auto-from-title')
                            ->prefixIcon('heroicon-m-link'),
                        Forms\Components\Select::make('tenant_id')
                            ->label('Tenant')
                            ->relationship('tenant', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                $set('venue_id', null);

                                // Auto-fill ticket_terms from tenant
                                if ($state) {
                                    $tenant = \App\Models\Tenant::find($state);
                                    if ($tenant && $tenant->ticket_terms) {
                                        // Only set if current ticket_terms is empty
                                        if (!$get('ticket_terms.en')) {
                                            $set('ticket_terms.en', $tenant->ticket_terms);
                                        }
                                        if (!$get('ticket_terms.ro')) {
                                            $set('ticket_terms.ro', $tenant->ticket_terms);
                                        }
                                    }
                                }
                            })
                            ->prefixIcon('heroicon-m-building-office-2'),
                    ]),
                ]),

            // FLAGS
            SC\Section::make('Flags')
                ->extraAttributes(['id'=>'flags','data-ep-section'=>'','data-ep-id'=>'flags','data-ep-label'=>'Flags','data-ep-icon'=>'flag'])
                ->schema([
                    // 5 columns with iconized toggles
                    SC\Grid::make(5)
                        ->schema([
                            Forms\Components\Toggle::make('is_sold_out')
                                ->label('Sold out')
                                ->onIcon('heroicon-m-lock-closed')
                                ->offIcon('heroicon-m-lock-open')
                                ->live()
                                ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                    if ($state) {
                                        // Sold out poate coexista cu Postponed, dar nu cu Cancelled
                                        if ($get('is_cancelled')) $set('is_cancelled', false);
                                    }
                                })
                                ->disabled(fn (SGet $get) => (bool) $get('is_cancelled')),

                            Forms\Components\Toggle::make('door_sales_only')
                                ->label('Door sales only')
                                ->onIcon('heroicon-m-key')
                                ->offIcon('heroicon-m-key')
                                ->live()
                                ->disabled(fn (SGet $get) => (bool) $get('is_cancelled')),

                            Forms\Components\Toggle::make('is_cancelled')
                                ->label('Cancelled')
                                ->onIcon('heroicon-m-x-circle')
                                ->offIcon('heroicon-m-x-circle')
                                ->live()
                                ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                    if ($state) {
                                        // Cancelled e exclusiv: scoatem Postponed, Promoted și Sold Out
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
                                        // Postponed nu coexista cu Cancelled; POATE coexista cu Sold Out
                                        if ($get('is_cancelled')) $set('is_cancelled', false);
                                    } else {
                                        // clear postponed fields
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
                                ->disabled(fn (SGet $get) => (bool) $get('is_cancelled')),
                        ]),

                    // Conditional blocks BELOW the toggles, single-column
                    SC\Grid::make(1)
                        ->schema([
                            Forms\Components\Textarea::make('cancel_reason')
                                ->label('Cancellation reason')
                                ->rows(2)
                                ->placeholder('Explain why the event is cancelled…')
                                ->visible(fn (SGet $get) => (bool) $get('is_cancelled')),

                            SC\Grid::make(4)
                                ->schema([
                                    Forms\Components\DatePicker::make('postponed_date')
                                        ->label('New date')
                                        ->placeholder('Select date')
                                        ->minDate($today)
                                        ->native(false)
                                        ->suffixIcon('heroicon-m-calendar'),

                                    Forms\Components\TimePicker::make('postponed_start_time')
                                        ->label('Start time')
                                        ->placeholder('Select time')
                                        ->seconds(false)
                                        ->native(true)
                                        ->suffixIcon('heroicon-m-clock')
                                        ->live()
                                        ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                            $end = $get('postponed_end_time');
                                            if ($state && $end && $end < $state) {
                                                $set('postponed_end_time', $state);
                                            }
                                            $door = $get('postponed_door_time');
                                            if ($state && $door && $door > $state) {
                                                $set('postponed_door_time', $state);
                                            }
                                        }),

                                    Forms\Components\TimePicker::make('postponed_door_time')
                                        ->label('Door time')
                                        ->placeholder('Select time')
                                        ->seconds(false)
                                        ->native(true)
                                        ->suffixIcon('heroicon-m-clock')
                                        ->rule('before_or_equal:postponed_start_time'),

                                    Forms\Components\TimePicker::make('postponed_end_time')
                                        ->label('End time')
                                        ->placeholder('Select time')
                                        ->seconds(false)
                                        ->native(true)
                                        ->suffixIcon('heroicon-m-clock')
                                        ->rule('after_or_equal:postponed_start_time'),
                                ])
                                ->visible(fn (SGet $get) => (bool) $get('is_postponed')),

                            Forms\Components\Textarea::make('postponed_reason')
                                ->label('Postponement reason')
                                ->rows(2)
                                ->placeholder('Explain why the event is postponed…')
                                ->visible(fn (SGet $get) => (bool) $get('is_postponed')),

                            Forms\Components\DatePicker::make('promoted_until')
                                ->label('Promoted until')
                                ->placeholder('Select date')
                                ->minDate($today)
                                ->native(false)
                                ->suffixIcon('heroicon-m-calendar')
                                ->visible(fn (SGet $get) => (bool) $get('is_promoted')),
                        ]),
                ])
                ->columns(1), // everything in one column under the section title

            // PRICING & COMMISSION
            SC\Section::make('Pricing & Commission')
                ->extraAttributes(['id'=>'pricing','data-ep-section'=>'','data-ep-id'=>'pricing','data-ep-label'=>'Pricing','data-ep-icon'=>'banknotes'])
                ->schema([
                    Forms\Components\Placeholder::make('commission_inheritance_info')
                        ->label('Commission Settings')
                        ->live()
                        ->content(function (SGet $get) {
                            $tenantId = $get('tenant_id');
                            if (!$tenantId) {
                                return 'Select a tenant first to see default commission settings.';
                            }

                            $tenant = \App\Models\Tenant::find($tenantId);
                            if (!$tenant) {
                                return 'Tenant not found.';
                            }

                            $mode = $tenant->commission_mode ?? 'included';
                            $rate = $tenant->commission_rate ?? 5.00;

                            $modeText = $mode === 'included'
                                ? 'Included in ticket price'
                                : 'Added on top of ticket price';

                            return "**Default from tenant:** {$modeText} at **{$rate}%**. You can override these settings below for this event.";
                        })
                        ->columnSpanFull(),

                    SC\Grid::make(2)->schema([
                        Forms\Components\Select::make('commission_mode')
                            ->label('Commission Mode')
                            ->options([
                                'included' => 'Include commission in ticket price',
                                'added_on_top' => 'Add commission on top of ticket price',
                            ])
                            ->placeholder('Use tenant default')
                            ->helperText('Choose how commission is applied to ticket prices. Leave empty to use tenant\'s default setting.')
                            ->live()
                            ->nullable(),

                        Forms\Components\TextInput::make('commission_rate')
                            ->label('Commission Rate (%)')
                            ->placeholder('e.g. 5.00')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%')
                            ->helperText('Override tenant\'s commission rate for this event. Leave empty to use tenant\'s default.')
                            ->live(debounce: 500)
                            ->nullable(),
                    ]),

                    Forms\Components\Placeholder::make('commission_example')
                        ->label('Example Calculation')
                        ->live()
                        ->content(function (SGet $get) {
                            $tenantId = $get('tenant_id');
                            $eventMode = $get('commission_mode');
                            $eventRate = $get('commission_rate');

                            if (!$tenantId) {
                                return 'Select a tenant first.';
                            }

                            $tenant = \App\Models\Tenant::find($tenantId);
                            if (!$tenant) {
                                return 'Tenant not found.';
                            }

                            $mode = $eventMode ?: ($tenant->commission_mode ?? 'included');
                            $rate = $eventRate ?: ($tenant->commission_rate ?? 5.00);

                            $ticketPrice = 100;
                            $commission = round($ticketPrice * ($rate / 100), 2);

                            if ($mode === 'included') {
                                $revenue = $ticketPrice - $commission;
                                return "**Example:** For a {$ticketPrice} RON ticket, commission of {$commission} RON is deducted, leaving {$revenue} RON revenue.";
                            } else {
                                $total = $ticketPrice + $commission;
                                return "**Example:** For a {$ticketPrice} RON ticket, commission of {$commission} RON is added on top, customer pays {$total} RON.";
                            }
                        })
                        ->columnSpanFull(),
                ])
                ->columns(1),

            // SCHEDULE
            SC\Section::make('Schedule')
                ->extraAttributes(['id'=>'schedule','data-ep-section'=>'','data-ep-id'=>'schedule','data-ep-label'=>'Schedule','data-ep-icon'=>'calendar-days'])
                ->schema([
                    Forms\Components\Radio::make('duration_mode')
                        ->label('Duration')
                        ->options([
                            'single_day' => 'Single day',
                            'range'      => 'Range',
                            'multi_day'  => 'Multiple days',
                            'recurring'  => 'Recurring',
                        ])
                        ->inline()
                        ->default('single_day')
                        ->required()
                        ->live(),

                    // single day
                    SC\Grid::make(4)
                        ->schema([
                            Forms\Components\DatePicker::make('event_date')
                                ->label('Date')
                                ->placeholder('Select date')
                                ->minDate($today)
                                ->native(false)
                                ->suffixIcon('heroicon-m-calendar'),

                            Forms\Components\TimePicker::make('start_time')
                                ->label('Start time')
                                ->placeholder('Select time')
                                ->seconds(false)
                                ->native(true)
                                ->suffixIcon('heroicon-m-clock')
                                ->required(fn (SGet $get) => $get('duration_mode') === 'single_day')
                                ->live()
                                ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                    $end = $get('end_time');
                                    if ($state && $end && $end < $state) {
                                        $set('end_time', $state);
                                    }
                                    $door = $get('door_time');
                                    if ($state && $door && $door > $state) {
                                        $set('door_time', $state);
                                    }
                                }),

                            Forms\Components\TimePicker::make('door_time')
                                ->label('Door time')
                                ->placeholder('Select time')
                                ->seconds(false)
                                ->native(true)
                                ->suffixIcon('heroicon-m-clock')
                                ->rule('before_or_equal:start_time'),

                            Forms\Components\TimePicker::make('end_time')
                                ->label('End time')
                                ->placeholder('Select time')
                                ->seconds(false)
                                ->native(true)
                                ->suffixIcon('heroicon-m-clock')
                                ->rule('after_or_equal:start_time'),
                        ])
                        ->visible(fn (SGet $get) => $get('duration_mode') === 'single_day'),

                    // range
                    SC\Grid::make(4)
                        ->schema([
                            Forms\Components\DatePicker::make('range_start_date')
                                ->label('Start date')
                                ->placeholder('Select date')
                                ->minDate($today)
                                ->native(false)
                                ->suffixIcon('heroicon-m-calendar')
                                ->live()
                                ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                    if ($state) {
                                        $minEnd = \Illuminate\Support\Carbon::parse($state)->addDay()->format('Y-m-d');
                                        $end = $get('range_end_date');
                                        if (! $end || \Illuminate\Support\Carbon::parse($end)->lt(\Illuminate\Support\Carbon::parse($minEnd))) {
                                            $set('range_end_date', $minEnd);
                                        }
                                    }
                                }),

                            Forms\Components\DatePicker::make('range_end_date')
                                ->label('End date')
                                ->placeholder('Select date')
                                ->minDate(fn (SGet $get) =>
                                    ($get('range_start_date')
                                        ? \Illuminate\Support\Carbon::parse($get('range_start_date'))->addDay()
                                        : \Illuminate\Support\Carbon::today()->addDay())
                                )
                                ->rule(fn (SGet $get) => $get('range_start_date') ? 'after:range_start_date' : null)
                                ->native(false)
                                ->suffixIcon('heroicon-m-calendar')
                                ->live()
                                ->helperText('End date must be at least 1 day after start date.'),

                            Forms\Components\TimePicker::make('range_start_time')
                                ->label('Start time (start day)')
                                ->placeholder('Select time')
                                ->seconds(false)
                                ->native(true)
                                ->suffixIcon('heroicon-m-clock')
                                ->required(fn (SGet $get) => $get('duration_mode') === 'range')
                                ->live()
                                ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                    $end = $get('range_end_time');
                                    if ($state && $end && $end < $state) {
                                        $set('range_end_time', $state);
                                    }
                                }),

                            Forms\Components\TimePicker::make('range_end_time')
                                ->label('End time (end day)')
                                ->placeholder('Select time')
                                ->seconds(false)
                                ->native(true)
                                ->suffixIcon('heroicon-m-clock')
                                ->rule('after_or_equal:range_start_time'),
                        ])
                        ->visible(fn (SGet $get) => $get('duration_mode') === 'range'),

                    // multi day (stored in JSON)
                    Forms\Components\Repeater::make('multi_slots')
                        ->label('Days & times')
                        ->schema([
                            Forms\Components\DatePicker::make('date')
                                ->label('Date')
                                ->placeholder('Select date')
                                ->minDate($today)
                                ->native(false)
                                ->suffixIcon('heroicon-m-calendar')
                                ->required(),

                            Forms\Components\TimePicker::make('start_time')
                                ->label('Start')
                                ->placeholder('Select time')
                                ->seconds(false)
                                ->native(true)
                                ->suffixIcon('heroicon-m-clock')
                                ->nullable()
                                ->live()
                                ->afterStateUpdated(function($state, SSet $set, SGet $get){ $end=$get('end_time'); if($state && $end && $end < $state){ $set('end_time',$state);} $door=$get('door_time'); if($state && $door && $door > $state){ $set('door_time',$state);} }),

                            Forms\Components\TimePicker::make('door_time')
                                ->label('Door')
                                ->placeholder('Select time')
                                ->seconds(false)
                                ->native(true)
                                ->suffixIcon('heroicon-m-clock')
                                ->nullable()
                                ->rule('before_or_equal:start_time'),

                            Forms\Components\TimePicker::make('end_time')
                                ->label('End')
                                ->placeholder('Select time')
                                ->seconds(false)
                                ->native(true)
                                ->suffixIcon('heroicon-m-clock')
                                ->nullable()
                                ->rule('after_or_equal:start_time'),
                        ])
                        ->addActionLabel('Add another date')
                        ->default([])
                        ->visible(fn (SGet $get) => $get('duration_mode') === 'multi_day')
                        ->columns(4),

                    SC\Group::make()
                        ->visible(fn (SGet $get) => $get('duration_mode') === 'recurring')
                        ->schema([
                            SC\Grid::make(4)->schema([
                                Forms\Components\DatePicker::make('recurring_start_date')
                                    ->label('Initial date')
                                    ->placeholder('Select date')
                                    ->minDate(now()->startOfDay())
                                    ->native(false)
                                    ->suffixIcon('heroicon-m-calendar')
                                    ->live()
                                    ->afterStateUpdated(function ($state, SSet $set) {
                                        if (! $state) { $set('recurring_weekday', null); return; }
                                        $w = \Illuminate\Support\Carbon::parse($state)->dayOfWeekIso; // 1..7
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
                                        'weekly'      => 'Weekly',
                                        'monthly_nth' => 'Monthly (Nth weekday)',
                                    ])
                                    ->required()
                                    ->live()
                                    ->helperText(fn (SGet $get) => $get('recurring_weekday')
                                        ? 'Weekday: ' . (['','Mon','Tue','Wed','Thu','Fri','Sat','Sun'][$get('recurring_weekday')])
                                        : 'Pick an initial date to detect weekday.'
                                    ),

                                Forms\Components\TextInput::make('recurring_count')
                                    ->label('Occurrences (optional)')
                                    ->numeric()
                                    ->minValue(1),
                            ]),

                            // monthly_nth specifics
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

                            // times
                            SC\Grid::make(4)->schema([
                                Forms\Components\TimePicker::make('recurring_start_time')
                                    ->label('Start time')
                                    ->seconds(false)->native(true)->suffixIcon('heroicon-m-clock')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                        $end = $get('recurring_end_time');
                                        if ($state && $end && $end < $state) {
                                            $set('recurring_end_time', $state);
                                        }
                                        $door = $get('recurring_door_time');
                                        if ($state && $door && $door > $state) {
                                            $set('recurring_door_time', $state);
                                        }
                                    }),

                                Forms\Components\TimePicker::make('recurring_door_time')
                                    ->label('Door time')
                                    ->seconds(false)->native(true)->suffixIcon('heroicon-m-clock')
                                    ->rule('before_or_equal:recurring_start_time'),

                                Forms\Components\TimePicker::make('recurring_end_time')
                                    ->label('End time')
                                    ->seconds(false)->native(true)->suffixIcon('heroicon-m-clock')
                                    ->rule('after_or_equal:recurring_start_time'),
                            ]),
                        ]),
                ])
                ->columns(1),

            
            

            // LOCATION & LINKS
            SC\Section::make('Location & Links')
                ->extraAttributes(['id'=>'location','data-ep-section'=>'','data-ep-id'=>'location','data-ep-label'=>'Location & Links','data-ep-icon'=>'map-pin'])
                ->schema([
                    Forms\Components\Select::make('venue_id')
                        ->label('Venue')
                        ->searchable()
                        ->preload()
                        ->relationship(
                            name: 'venue',
                            modifyQueryUsing: function (Builder $query, SGet $get) {
                                $tenantId = $get('tenant_id');
                                return $query->when($tenantId, fn($q) => $q->where(fn($qq) => $qq
                                    ->whereNull('tenant_id')
                                    ->orWhere('tenant_id', $tenantId)))
                                    ->orderBy('name');
                            }
                        )
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('name', 'en'))
                        ->helperText('Afișează doar venue-uri fără owner sau ale Tenantului acestui eveniment.')
                        ->live()
                        ->nullable()
                        ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                            if (!$state) return;
                            $tenantId = $get('tenant_id');
                            $venue = Venue::whereKey($state)
                                ->where(fn($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))
                                ->first();

                            if (!$venue) {
                                $set('venue_id', null);
                                return;
                            }

                            // Auto-fill address and website from venue
                            if ($venue->address && !$get('address')) {
                                $set('address', $venue->address);
                            }
                            if ($venue->website_url && !$get('website_url')) {
                                $set('website_url', $venue->website_url);
                            }
                        })
                        ->rules(function (SGet $get) {
                            return [
                                function (string $attribute, $value, \Closure $fail) use ($get) {
                                    if (!$value) return;

                                    $tenantId = $get('tenant_id');
                                    $exists = Venue::whereKey($value)
                                        ->where(fn($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))
                                        ->exists();

                                    if (!$exists) {
                                        $fail('Venue-ul selectat nu este permis pentru acest Tenant.');
                                    }
                                },
                            ];
                        }),

                    Forms\Components\Select::make('event_seating_layout_id')
                        ->label('Seating Layout')
                        ->searchable()
                        ->preload()
                        ->options(function (SGet $get) {
                            $venueId = $get('venue_id');
                            if (!$venueId) return [];

                            return \App\Models\Seating\SeatingLayout::query()
                                ->where('venue_id', $venueId)
                                ->where('status', 'published')
                                ->orderBy('name')
                                ->pluck('name', 'id');
                        })
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Select a published seating layout for this event (if available)')
                        ->nullable()
                        ->visible(fn (SGet $get) => $get('venue_id') !== null)
                        ->reactive(),

                    Forms\Components\TextInput::make('address')->label('Address')->maxLength(255)->placeholder('Street, city, country…'),

                    Forms\Components\TextInput::make('website_url')
                        ->label('Website')->url()->rule('url')
                        ->prefixIcon('heroicon-m-globe-alt')->maxLength(255)
                        ->placeholder('https://…'),

                    Forms\Components\TextInput::make('facebook_url')
                        ->label('Facebook Event')->url()->rule('url')
                        ->prefixIcon('heroicon-m-link')->maxLength(255)
                        ->placeholder('https://facebook.com/events/...'),

                    Forms\Components\TextInput::make('event_website_url')
                        ->label('Event Website')->url()->rule('url')
                        ->prefixIcon('heroicon-m-link')->maxLength(255)
                        ->placeholder('https://…'),
                ])
                ->columns(2),

            // MEDIA
            SC\Section::make('Media')
                ->extraAttributes(['id'=>'media','data-ep-section'=>'','data-ep-id'=>'media','data-ep-label'=>'Media','data-ep-icon'=>'photo'])
                ->schema([
                    Forms\Components\FileUpload::make('poster_url')
                        ->label('Poster (vertical)')
                        ->image()
                        ->directory('events/posters')
                        ->disk('public')
                        ->visibility('public'),

                    Forms\Components\FileUpload::make('hero_image_url')
                        ->label('Hero image (horizontal)')
                        ->image()
                        ->directory('events/hero')
                        ->disk('public')
                        ->visibility('public'),
                ])
                ->columns(2),

            // CONTENT
            SC\Section::make('Content')
                ->extraAttributes(['id'=>'content','data-ep-section'=>'','data-ep-id'=>'content','data-ep-label'=>'Content','data-ep-icon'=>'pencil-square'])
                ->schema([
                    Forms\Components\Textarea::make('short_description.en')
                        ->label('Short description (EN)')
                        ->rows(3)
                        ->placeholder('1–2 lines summary…'),

                    Forms\Components\RichEditor::make('description.en')
                        ->label('Description (EN)')
                        ->columnSpanFull(),

                    Forms\Components\RichEditor::make('ticket_terms.en')
                        ->label('Ticket terms (EN)')
                        ->columnSpanFull(),
                ])
                ->columns(1),

            // TAXONOMIES & RELATIONS
            SC\Section::make('Taxonomies & Relations')
                ->extraAttributes(['id'=>'taxonomies','data-ep-section'=>'','data-ep-id'=>'taxonomies','data-ep-label'=>'Taxonomies','data-ep-icon'=>'tag'])
                ->schema([
                    // EVENT TYPES: children only, PG-safe ordering, max 2, custom search (no ambiguous column)
                    Forms\Components\Select::make('eventTypes')
                        ->label('Event types')
                        ->relationship(
                            name: 'eventTypes',
                            modifyQueryUsing: function (Builder $query) {
                                $query
                                    ->leftJoin('event_types as p', 'p.id', '=', 'event_types.parent_id')
                                    ->whereNotNull('event_types.parent_id') // NU permitem părinții
                                    ->select('event_types.*')
                                    ->addSelect('p.name as __parent_name')
                                    ->addSelect(DB::raw("CASE WHEN event_types.parent_id IS NULL THEN 0 ELSE 1 END AS __parent_rank"))
                                    ->orderBy('__parent_rank')
                                    ->orderBy('__parent_name')
                                    ->orderBy('event_types.name');
                            }
                        )
                        ->getSearchResultsUsing(function (string $search) {
                            return EventType::query()
                                ->leftJoin('event_types as p', 'p.id', '=', 'event_types.parent_id')
                                ->whereNotNull('event_types.parent_id') // doar copii
                                ->where(function ($q) use ($search) {
                                    $q->where('event_types.name', 'like', "%{$search}%")
                                      ->orWhere('p.name', 'like', "%{$search}%");
                                })
                                ->select('event_types.id', 'event_types.name', 'p.name as __parent_name')
                                ->addSelect(DB::raw("CASE WHEN event_types.parent_id IS NULL THEN 0 ELSE 1 END AS __parent_rank"))
                                ->orderBy('__parent_rank')
                                ->orderBy('__parent_name')
                                ->orderBy('event_types.name')
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(function ($r) {
                                    // Extract translated name for child
                                    $childName = is_array($r->name)
                                        ? ($r->name['en'] ?? $r->name['ro'] ?? reset($r->name))
                                        : $r->name;
                                    // Extract translated name for parent
                                    $parentName = $r->__parent_name;
                                    if (is_array($parentName)) {
                                        $parentName = $parentName['en'] ?? $parentName['ro'] ?? reset($parentName);
                                    } elseif (is_string($parentName) && str_starts_with($parentName, '{')) {
                                        $decoded = json_decode($parentName, true);
                                        $parentName = $decoded['en'] ?? $decoded['ro'] ?? reset($decoded) ?? $parentName;
                                    }
                                    return [
                                        $r->id => $parentName ? "{$parentName} ▸ {$childName}" : $childName,
                                    ];
                                })
                                ->toArray();
                        })
                        ->getOptionLabelFromRecordUsing(function ($record) {
                            // Get translated child name
                            $childName = $record->getTranslation('name', 'en') ?: $record->getTranslation('name', 'ro') ?: $record->name;
                            if (is_array($childName)) {
                                $childName = $childName['en'] ?? $childName['ro'] ?? reset($childName);
                            }

                            // Get parent name if exists
                            $parentName = null;
                            if ($record->parent_id) {
                                $parent = EventType::find($record->parent_id);
                                if ($parent) {
                                    $parentName = $parent->getTranslation('name', 'en') ?: $parent->getTranslation('name', 'ro') ?: $parent->name;
                                    if (is_array($parentName)) {
                                        $parentName = $parentName['en'] ?? $parentName['ro'] ?? reset($parentName);
                                    }
                                }
                            }

                            return $parentName ? "{$parentName} ▸ {$childName}" : $childName;
                        })
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->reactive()
                        ->maxItems(2)
                        ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                            // curățăm genurile nepermise când se schimbă types
                            $typeIds = (array) ($get('eventTypes') ?? []);
                            if (! $typeIds) {
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

                    // EVENT GENRES: dezactivat până selectezi type; filtrat de pivot; max 5
                    Forms\Components\Select::make('eventGenres')
                        ->label('Event genres')
                        ->relationship(
                            name: 'eventGenres',
                            modifyQueryUsing: function (Builder $query, SGet $get) {
                                $typeIds = (array) ($get('eventTypes') ?? []);
                                if (! $typeIds) {
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
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('name', 'en'))
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->disabled(fn (SGet $get) => empty($get('eventTypes')))
                        ->reactive()
                        ->minItems(0)
                        ->maxItems(5),

                    // Artists (many-to-many)
                    Forms\Components\Select::make('artists')
                        ->label('Artists')
                        ->relationship('artists', 'name')
                        ->multiple()
                        ->preload()
                        ->searchable(),

                    // Tags (flat)
                    Forms\Components\Select::make('tags')
                        ->label('Event tags')
                        ->relationship('tags', 'name')
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')->required()->maxLength(190),
                            Forms\Components\TextInput::make('slug')
                                ->helperText('Leave empty to auto-generate.')
                                ->maxLength(190),
                            Forms\Components\Textarea::make('description')->rows(2),
                        ])
                        ->createOptionUsing(function (array $data) {
                            $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
                            return \App\Models\EventTag::create($data);
                        }),

                    // Dynamic tax display based on selected event types
                    Forms\Components\Placeholder::make('applicable_taxes')
                        ->label('Taxe aplicabile pentru tipul de eveniment')
                        ->columnSpanFull()
                        ->visible(fn (SGet $get) => !empty($get('eventTypes')))
                        ->content(function (SGet $get, $record) {
                            $eventTypeIds = (array) ($get('eventTypes') ?? []);
                            if (empty($eventTypeIds)) {
                                return '';
                            }

                            // Get tenant from record if editing, otherwise show general info
                            $tenant = $record?->tenant;
                            $isVatPayer = $tenant?->vat_payer ?? null;
                            $taxDisplayMode = $tenant?->tax_display_mode ?? 'included';

                            // Get applicable taxes using the new forEventTypes scope
                            $allTaxes = GeneralTax::query()
                                ->whereNull('tenant_id') // Global taxes only
                                ->active()
                                ->validOn(Carbon::today())
                                ->forEventTypes($eventTypeIds)
                                ->orderByDesc('priority')
                                ->get()
                                ->unique('id');

                            if ($allTaxes->isEmpty()) {
                                return new HtmlString('<div class="text-sm text-gray-500 italic">Nu există taxe configurate pentru tipul de eveniment selectat.</div>');
                            }

                            $html = '<div class="space-y-2">';

                            // VAT payer status and tax display mode if tenant is known
                            if ($isVatPayer !== null) {
                                $vatBadge = $isVatPayer
                                    ? '<span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Tenant: Plătitor TVA</span>'
                                    : '<span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">Tenant: Neplătitor TVA</span>';

                                $modeBadge = $taxDisplayMode === 'added'
                                    ? '<span class="ml-2 inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">Taxe adăugate la preț</span>'
                                    : '<span class="ml-2 inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Taxe incluse în preț</span>';

                                $html .= '<div class="mb-3 flex flex-wrap items-center gap-2">' . $vatBadge . $modeBadge . '</div>';
                            } else {
                                $html .= '<div class="mb-3 text-xs text-gray-500 italic">Aplicarea TVA depinde de statusul de plătitor TVA al tenant-ului.</div>';
                            }

                            $html .= '<div class="grid grid-cols-1 md:grid-cols-2 gap-2">';

                            foreach ($allTaxes as $tax) {
                                $isVatTax = str_contains(strtolower($tax->name ?? ''), 'tva') ||
                                            str_contains(strtolower($tax->name ?? ''), 'vat');

                                // Skip VAT if tenant is known and not a VAT payer
                                if ($isVatTax && $isVatPayer === false) {
                                    continue;
                                }

                                $rateDisplay = $tax->value_type === 'percent'
                                    ? number_format($tax->value, 2) . '%'
                                    : number_format($tax->value, 2) . ' ' . ($tax->currency ?? 'RON');

                                $includedBadge = $tax->is_added_to_price
                                    ? '<span class="text-xs text-amber-600 dark:text-amber-400">(se adaugă la preț)</span>'
                                    : '<span class="text-xs text-gray-500">(inclus în preț)</span>';

                                $vatBadgeSmall = $isVatTax
                                    ? '<span class="ml-1 px-1.5 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">TVA</span>'
                                    : '';

                                // Custom SVG icon
                                $iconHtml = $tax->icon_svg ? '<span class="inline-flex items-center mr-1">' . $tax->icon_svg . '</span>' : '';

                                $html .= '<div class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">';
                                $html .= '<div class="flex items-center gap-2">';
                                $html .= $iconHtml;
                                $html .= '<span class="font-medium text-sm text-gray-900 dark:text-white">' . e($tax->name) . '</span>';
                                $html .= $vatBadgeSmall;
                                $html .= '</div>';
                                $html .= '<div class="text-right">';
                                $html .= '<span class="font-semibold text-primary">' . $rateDisplay . '</span>';
                                $html .= '<br>' . $includedBadge;
                                $html .= '</div>';
                                $html .= '</div>';
                            }

                            $html .= '</div></div>';

                            return new HtmlString($html);
                        }),
                ])
                ->columns(2),

            // SEO
            SC\Section::make('SEO')
                ->extraAttributes(['id'=>'seo','data-ep-section'=>'','data-ep-id'=>'seo','data-ep-label'=>'SEO','data-ep-icon'=>'cog-6-tooth'])
                ->schema([
                    // Quick add from templates (not saved, only mutates seo state)
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
                            'jsonld'      => 'Structured Data (JSON-LD shell)',
                            'robots_adv'  => 'Robots advanced (max-* / indexifembedded)',
                            'verify'      => 'Verification (Google/Bing/etc.)',
                            'feeds'       => 'Feeds (RSS/Atom/oEmbed)',
                        ])
                        ->helperText('Select one or more sets — keys missing in the map below will be added automatically.')
                        ->live()
                        ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                            $seo = (array) ($get('seo') ?? []);

                            $templates = [
                                'core' => [
                                    'meta_title'       => '',
                                    'meta_description' => '',
                                    'canonical_url'    => '',
                                    'robots'           => 'index,follow',
                                    'viewport'         => 'width=device-width, initial-scale=1',
                                    'referrer'         => 'no-referrer-when-downgrade',
                                ],
                                'intl' => [
                                    // Setăm aici, iar concretele hreflang le poți pune tu în tenant/site
                                    'og:locale'        => 'en_US', // schimbabil din tenant
                                    // pentru alternate hreflang, folosim un JSON în string
                                    'hreflang_map'     => '[]', // e.g. [{"lang":"en","url":"..."},{"lang":"ro","url":"..."}]
                                ],
                                'open_graph' => [
                                    'og:title'         => '',
                                    'og:description'   => '',
                                    'og:type'          => 'website',
                                    'og:url'           => '',
                                    'og:image'         => '',
                                    'og:image:alt'     => '',
                                    'og:image:width'   => '',
                                    'og:image:height'  => '',
                                    'og:site_name'     => '',
                                ],
                                'article' => [
                                    'article:author'         => '',
                                    'article:section'        => '',
                                    'article:tag'            => '',
                                    'article:published_time' => '',
                                    'article:modified_time'  => '',
                                ],
                                'product' => [
                                    'product:price:amount'   => '',
                                    'product:price:currency' => '',
                                    'product:availability'   => '',
                                ],
                                'twitter' => [
                                    'twitter:card'        => 'summary_large_image',
                                    'twitter:title'       => '',
                                    'twitter:description' => '',
                                    'twitter:image'       => '',
                                    'twitter:site'        => '',
                                    'twitter:creator'     => '',
                                    // player keys dacă ai video
                                    'twitter:player'        => '',
                                    'twitter:player:width'  => '',
                                    'twitter:player:height' => '',
                                ],
                                'jsonld' => [
                                    // punem un JSON „shell” ca string (poți lipi JSON-ul tău complet)
                                    'structured_data' =>
                                        json_encode([
                                            '@context' => 'https://schema.org',
                                            '@type'    => 'Event',
                                            'name'     => '',
                                            'description' => '',
                                            'image'    => '',
                                            'startDate'=> '',
                                            'endDate'  => '',
                                            'location' => [
                                                '@type'   => 'Place',
                                                'name'    => '',
                                                'address' => '',
                                            ],
                                            'organizer' => [
                                                '@type' => 'Organization',
                                                'name'  => '',
                                                'url'   => '',
                                            ],
                                            'url'     => '',
                                        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                                ],
                                'robots_adv' => [
                                    'max-snippet'       => '',
                                    'max-image-preview' => '',
                                    'max-video-preview' => '',
                                    'noarchive'         => '',
                                    'nosnippet'         => '',
                                    'noimageindex'      => '',
                                    'indexifembedded'   => '',
                                    // per-bot overrides
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
                                    'rss_url'         => '',
                                    'atom_url'        => '',
                                    'oembed_json'     => '',
                                    'oembed_xml'      => '',
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
                            // Core
                            'meta_title'       => '',
                            'meta_description' => '',
                            'canonical_url'    => '',
                            'robots'           => 'index,follow',
                            'viewport'         => 'width=device-width, initial-scale=1',
                            'referrer'         => 'no-referrer-when-downgrade',

                            // Internationalization
                            'og:locale'        => 'en_US',
                            'hreflang_map'     => '[]',

                            // Open Graph
                            'og:title'         => '',
                            'og:description'   => '',
                            'og:type'          => 'website',
                            'og:url'           => '',
                            'og:image'         => '',
                            'og:image:alt'     => '',
                            'og:image:width'   => '',
                            'og:image:height'  => '',
                            'og:site_name'     => '',

                            // Article extras
                            'article:author'         => '',
                            'article:section'        => '',
                            'article:tag'            => '',
                            'article:published_time' => '',
                            'article:modified_time'  => '',

                            // Product extras
                            'product:price:amount'   => '',
                            'product:price:currency' => '',
                            'product:availability'   => '',

                            // Twitter
                            'twitter:card'        => 'summary_large_image',
                            'twitter:title'       => '',
                            'twitter:description' => '',
                            'twitter:image'       => '',
                            'twitter:site'        => '',
                            'twitter:creator'     => '',
                            'twitter:player'        => '',
                            'twitter:player:width'  => '',
                            'twitter:player:height' => '',

                            // Structured data (JSON-LD string)
                            'structured_data'   => '',

                            // Robots advanced
                            'max-snippet'       => '',
                            'max-image-preview' => '',
                            'max-video-preview' => '',
                            'noarchive'         => '',
                            'nosnippet'         => '',
                            'noimageindex'      => '',
                            'indexifembedded'   => '',
                            'googlebot'         => '',
                            'bingbot'           => '',

                            // Verification & discovery
                            'google-site-verification'    => '',
                            'msvalidate.01'                => '',
                            'p:domain_verify'              => '',
                            'yandex-verification'          => '',
                            'ahrefs-site-verification'     => '',
                            'facebook-domain-verification' => '',
                            'rss_url'                      => '',
                            'atom_url'                     => '',
                            'oembed_json'                  => '',
                            'oembed_xml'                   => '',
                        ])
                        ->helperText('Auto-fill on save applies only to empty keys (no overwrite). You can add/remove keys as needed.'),
                ]),

            SC\Section::make('Tickets')
                ->extraAttributes(['id'=>'tickets','data-ep-section'=>'','data-ep-id'=>'tickets','data-ep-label'=>'Tickets','data-ep-icon'=>'ticket'])
                ->schema([
                    // Ticket Template selector
                    Forms\Components\Select::make('ticket_template_id')
                        ->label('Ticket Template')
                        ->relationship(
                            name: 'ticketTemplate',
                            modifyQueryUsing: fn (Builder $query, SGet $get) => $query
                                ->where('tenant_id', $get('tenant_id'))
                                ->where('status', 'active')
                                ->orderBy('name')
                        )
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->name . ($record->is_default ? ' (Default)' : ''))
                        ->placeholder('Use default template')
                        ->helperText('Select a template for tickets generated for this event. Leave empty to use the default template.')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->live()
                        ->disabled(fn (SGet $get) => !$get('tenant_id')),

                    Forms\Components\Repeater::make('ticketTypes')
                        ->relationship()
                        ->label('Ticket types')
                        ->collapsed()
                        ->addActionLabel('Add ticket type')
                        ->itemLabel(fn (array $state) => ($state['is_active'] ?? true)
                            ? '✓ ' . ($state['name'] ?? 'Ticket')
                            : '○ ' . ($state['name'] ?? 'Ticket'))
                        ->columns(12) // tot item-ul pe un grid de 12 col pentru control fin
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Name')
                                ->placeholder('e.g. Early Bird, Standard, VIP')
                                ->datalist(['Early Bird','Standard','VIP','Backstage','Student','Senior','Child','Crew'])
                                ->required()
                                ->columnSpan(6)
                                ->live(debounce: 400)
                                ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                    if ($get('sku')) return;
                                    $set('sku', \Illuminate\Support\Str::upper(\Illuminate\Support\Str::slug($state, '-')));
                                }),

                            Forms\Components\TextInput::make('sku')
                                ->label('SKU')
                                ->placeholder('AUTO-GEN if left empty')
                                ->helperText('Leave empty to auto-generate from event + name.')
                                ->columnSpan(6)
                                ->afterStateHydrated(function ($component, SGet $get) {
                                    if ($component->getState()) return;
                                    $title = (string) ($get('../../title.en') ?? 'EVT');
                                    $slug  = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::slug($title, '-'));
                                    $date  = (string) ($get('../../event_date') ?? $get('../../range_start_date') ?? now()->toDateString());
                                    $ymd   = str_replace('-', '', $date);
                                    $name  = (string) ($get('name') ?? 'TKT');
                                    $code  = \Illuminate\Support\Str::upper(preg_replace('/[^A-Z0-9]+/i', '-', $name));
                                    $component->state($slug . '-' . $ymd . '-' . $code);
                                }),

                            Forms\Components\Textarea::make('description')
                                ->label('Description')
                                ->placeholder('Optional ticket type description (e.g. "Includes backstage access and meet & greet")')
                                ->rows(2)
                                ->columnSpan(12),

                            // ROW: Currency | Price | Sale price | Discount %
                            SC\Grid::make(4)->schema([
                                Forms\Components\TextInput::make('currency')
                                    ->label('Currency')
                                    ->placeholder('Auto from tenant')
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->afterStateHydrated(function ($component, SGet $get) {
                                        if ($component->getState()) return;
                                        $tenantId = $get('../../tenant_id');
                                        $cur = optional(\App\Models\Tenant::find($tenantId))->currency ?? 'RON';
                                        $component->state($cur);
                                    }),

                                Forms\Components\TextInput::make('price_max')
                                    ->label('Price')
                                    ->placeholder('e.g. 120.00')
                                    ->numeric()
                                    ->minValue(0)
                                    ->required()
                                    ->live(debounce: 300)
                                    ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                        $price = (float) $state;
                                        $sale  = $get('price');
                                        $disc  = $get('discount_percent');

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
                                        $sale  = $state !== null && $state !== '' ? (float)$state : null;
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

                            // Commission information (inherited from event/tenant)
                            Forms\Components\Placeholder::make('commission_info')
                                ->label('Commission Settings')
                                ->live()
                                ->content(function (SGet $get) {
                                    $eventId = $get('../../id');
                                    $tenantId = $get('../../tenant_id');
                                    $eventMode = $get('../../commission_mode');
                                    $eventRate = $get('../../commission_rate');

                                    if (!$eventId) {
                                        // For new events, show preview based on form data
                                        if (!$tenantId) {
                                            return 'Select a tenant first to see commission settings.';
                                        }

                                        $tenant = \App\Models\Tenant::find($tenantId);
                                        if (!$tenant) {
                                            return 'Tenant not found.';
                                        }

                                        $mode = $eventMode ?: ($tenant->commission_mode ?? 'included');
                                        $rate = $eventRate ?: ($tenant->commission_rate ?? 5.00);
                                        $source = $eventMode ? 'event override' : 'tenant default';
                                    } else {
                                        $event = \App\Models\Event::with('tenant')->find($eventId);
                                        if (!$event) {
                                            return 'Event not found.';
                                        }

                                        $mode = $event->getEffectiveCommissionMode();
                                        $rate = $event->getEffectiveCommissionRate();
                                        $source = $event->commission_mode ? 'event override' : 'tenant default';
                                    }

                                    $modeText = $mode === 'included'
                                        ? 'Included in ticket price'
                                        : 'Added on top of ticket price';

                                    return "**{$modeText}** at **{$rate}%** (from {$source})";
                                })
                                ->helperText('Commission settings are inherited from the event or tenant configuration. To change these, edit the event\'s commission settings in the "Pricing & Commission" section above.')
                                ->columnSpan(12),

                            // ROW: Capacity | Sale window (date-times with icons/placeholders)
                            SC\Grid::make(3)->schema([
                                Forms\Components\TextInput::make('capacity')
                                    ->label('Capacity')
                                    ->placeholder('e.g. 250')
                                    ->numeric()
                                    ->minValue(0)
                                    ->required(),

                                Forms\Components\DateTimePicker::make('sale_starts_at')
                                    ->label('Sale starts')
                                    ->placeholder('Select date & time')
                                    ->native(false)
                                    ->suffixIcon('heroicon-m-calendar')
                                    ->minDate(now())
                                    ->live(),

                                Forms\Components\DateTimePicker::make('sale_ends_at')
                                    ->label('Sale ends')
                                    ->placeholder('Select date & time')
                                    ->native(false)
                                    ->suffixIcon('heroicon-m-calendar')
                                    ->minDate(fn (SGet $get) =>
                                        $get('sale_starts_at')
                                            ? \Illuminate\Support\Carbon::parse($get('sale_starts_at'))->addDay()
                                            : now()->addDay()
                                    )
                                    ->rule('after:sale_starts_at')
                                    ->live()
                                    ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                        $start = $get('sale_starts_at');
                                        if (!$start) return;
                                        $minEnd = \Illuminate\Support\Carbon::parse($start)->addDay();
                                        if ($state && \Illuminate\Support\Carbon::parse($state)->lt($minEnd)) {
                                            $set('sale_ends_at', $minEnd->format('Y-m-d H:i:s'));
                                        }
                                    }),
                            ])->columnSpan(12),

                            // BULK DISCOUNTS — pe un singur rând
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
                                            'buy_x_get_y'           => 'Buy X get Y free',
                                            'buy_x_percent_off'     => 'Buy X tickets → % off',
                                            'amount_off_per_ticket' => 'Amount off per ticket (min qty)',
                                            'bundle_price'          => 'Bundle price (X tickets for total)',
                                        ])
                                        ->required()
                                        ->columnSpan(3)
                                        ->live(),

                                    // buy X get Y free
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

                                    // buy X tickets -> % off
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

                                    // amount off per ticket
                                    Forms\Components\TextInput::make('amount_off')
                                        ->label('Amount off')
                                        ->numeric()->minValue(0.01)
                                        ->visible(fn ($get) => $get('rule_type') === 'amount_off_per_ticket')
                                        ->columnSpan(3),

                                    // bundle price (X for total)
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
                ])
                ->collapsible(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->getStateUsing(fn (Event $record) => $record->getTranslation('title', 'en') ?: $record->getTranslation('title', 'ro') ?: collect($record->title)->first())
                    ->searchable(query: fn (Builder $query, string $search) => $query->where('title', 'like', "%{$search}%"))
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(title, '$.en')) {$direction}"))
                    ->url(fn (Event $record) => static::getUrl('edit', ['record' => $record])),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Event Date')
                    ->date('d M Y')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderByRaw("COALESCE(event_date, range_start_date, starts_at) {$direction}"))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('venue.name')
                    ->label('Venue')
                    ->getStateUsing(fn (Event $record) => $record->venue?->getTranslation('name', 'en') ?? $record->venue?->getTranslation('name', 'ro') ?? '-')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('venue.city')
                    ->label('City')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('venue.country')
                    ->label('Country')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft'     => 'Draft',
                        'published' => 'Published',
                        'archived'  => 'Archived',
                    ]),
                Tables\Filters\Filter::make('upcoming')
                    ->label('Upcoming only')
                    ->query(fn ($q) => $q->where(function ($query) {
                        $now = now()->toDateString();
                        $query->where('event_date', '>=', $now)
                            ->orWhere('range_start_date', '>=', $now)
                            ->orWhere('range_end_date', '>=', $now);
                    })),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            // future relation managers
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListEvents::route('/'),
            'create' => CreateEvent::route('/create'),
            'edit'   => EditEvent::route('/{record}/edit'),
        ];
    }
}

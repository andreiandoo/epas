<?php

namespace App\Filament\Resources\Events;

use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Pages\ImportEvents;
use App\Filament\Resources\Events\Pages\ListEvents;
use App\Models\Event;
use App\Models\EventGenre;
use App\Models\EventType;
use App\Models\Tax\GeneralTax;
use App\Models\Tour;
use BackedEnum;
use UnitEnum;
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

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-calendar';
    protected static UnitEnum|string|null $navigationGroup = 'Catalog';
    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        $today = Carbon::today();

        return $schema->columns(1)->schema([
            SC\Grid::make(4)->schema([
                // ========== LEFT COLUMN (3/4) ==========
                SC\Group::make()
                    ->columnSpan(3)
                    ->schema([
                        SC\Tabs::make('EventTabs')
                            ->persistTabInQueryString()
                            ->tabs([

                                // ========== TAB 1: DETALII ==========
                                SC\Tabs\Tab::make('Detalii')
                                    ->key('detalii')
                                    ->icon('heroicon-o-document-text')
                                    ->schema([
                        SC\Section::make('Event Details')
                            ->schema([
                                Forms\Components\TextInput::make('title.en')
                                    ->label('Event title')
                                    ->required()
                                    ->maxLength(190)
                                    ->placeholder('Type the event title...')
                                    ->extraAttributes(['class' => 'ep-title'])
                                    ->live(onBlur: true)
                                    ->afterStateHydrated(function ($component, $state, ?Event $record) {
                                        if (empty($state) && $record) {
                                            $val = $record->getTranslation('title', 'en');
                                            if ($val) $component->state($val);
                                        }
                                    })
                                    ->afterStateUpdated(function (string $state, SSet $set, SGet $get, ?Event $record) {
                                        // Slug is auto-generated ONLY on initial create.
                                        // On edit, never overwrite an existing slug when the title changes.
                                        if (!$state) return;
                                        if ($record && $record->exists) {
                                            if (!$get('slug.en')) {
                                                $set('slug.en', Str::slug($state));
                                            }
                                            return;
                                        }
                                        $set('slug.en', Str::slug($state));
                                    }),
                                SC\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('slug.en')
                                        ->label('Slug')
                                        ->helperText('URL slug')
                                        ->maxLength(190)
                                        ->rule('alpha_dash')
                                        ->placeholder('auto-from-title')
                                        ->prefixIcon('heroicon-m-link')
                                        ->afterStateHydrated(function ($component, $state, ?Event $record) {
                                            if (empty($state) && $record && $record->slug) {
                                                $component->state(is_array($record->slug) ? (collect($record->slug)->first() ?? '') : $record->slug);
                                            }
                                        }),
                                    Forms\Components\Select::make('tenant_id')
                                        ->label('Tenant')
                                        ->relationship('tenant', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->live()
                                        ->hintIcon('heroicon-m-information-circle')
                                        ->hint(function (?Event $record) {
                                            if ($record && !$record->tenant_id && $record->marketplaceClient) {
                                                return 'Marketplace: ' . $record->marketplaceClient->name;
                                            }
                                            return null;
                                        })
                                        ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                            $set('venue_id', null);
                                            if ($state) {
                                                $tenant = \App\Models\Tenant::find($state);
                                                if ($tenant && $tenant->ticket_terms) {
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
                                        ->live()
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
                                        ->disabled(fn (SGet $get) => (bool) $get('is_cancelled')),
                                ]),
                                SC\Grid::make(1)->schema([
                                    Forms\Components\Textarea::make('cancel_reason')
                                        ->label('Cancellation reason')->rows(2)
                                        ->placeholder('Explain why the event is cancelled...')
                                        ->visible(fn (SGet $get) => (bool) $get('is_cancelled')),
                                    SC\Grid::make(4)->schema([
                                        Forms\Components\DatePicker::make('postponed_date')
                                            ->label('New date')->placeholder('Select date')
                                            ->minDate($today)->native(false)->suffixIcon('heroicon-m-calendar'),
                                        Forms\Components\TimePicker::make('postponed_start_time')
                                            ->label('Start time')->placeholder('Select time')
                                            ->seconds(false)->native(true)->suffixIcon('heroicon-m-clock')
                                            ->live()
                                            ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                                $end = $get('postponed_end_time');
                                                if ($state && $end && $end < $state) $set('postponed_end_time', $state);
                                                $door = $get('postponed_door_time');
                                                if ($state && $door && $door > $state) $set('postponed_door_time', $state);
                                            }),
                                        Forms\Components\TimePicker::make('postponed_door_time')
                                            ->label('Door time')->placeholder('Select time')
                                            ->seconds(false)->native(true)->suffixIcon('heroicon-m-clock')
                                            ->rule('before_or_equal:postponed_start_time'),
                                        Forms\Components\TimePicker::make('postponed_end_time')
                                            ->label('End time')->placeholder('Select time')
                                            ->seconds(false)->native(true)->suffixIcon('heroicon-m-clock')
                                            ->rule('after_or_equal:postponed_start_time'),
                                    ])->visible(fn (SGet $get) => (bool) $get('is_postponed')),
                                    Forms\Components\Textarea::make('postponed_reason')
                                        ->label('Postponement reason')->rows(2)
                                        ->placeholder('Explain why the event is postponed...')
                                        ->visible(fn (SGet $get) => (bool) $get('is_postponed')),
                                    Forms\Components\DatePicker::make('promoted_until')
                                        ->label('Promoted until')->placeholder('Select date')
                                        ->minDate($today)->native(false)->suffixIcon('heroicon-m-calendar')
                                        ->visible(fn (SGet $get) => (bool) $get('is_promoted')),
                                ]),
                            ])->columns(1),
                                    ]), // End Tab 1: Detalii

                                // ========== TAB 2: PROGRAM ==========
                                SC\Tabs\Tab::make('Program')
                                    ->key('program')
                                    ->icon('heroicon-o-calendar')
                                    ->lazy()
                                    ->schema([
                        SC\Section::make('Schedule')
                            ->schema([
                                Forms\Components\Radio::make('duration_mode')
                                    ->label('Duration')
                                    ->options(['single_day' => 'Single day', 'range' => 'Range', 'multi_day' => 'Multiple days', 'recurring' => 'Recurring'])
                                    ->inline()->default('single_day')->required()->live(),
                                SC\Grid::make(4)->schema([
                                    Forms\Components\DatePicker::make('event_date')->label('Date')->placeholder('Select date')->minDate($today)->native(false)->suffixIcon('heroicon-m-calendar'),
                                    Forms\Components\TimePicker::make('start_time')->label('Start time')->placeholder('Select time')->seconds(false)->native(true)->suffixIcon('heroicon-m-clock')
                                        ->required(fn (SGet $get) => $get('duration_mode') === 'single_day')->live()
                                        ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                            $end = $get('end_time'); if ($state && $end && $end < $state) $set('end_time', $state);
                                            $door = $get('door_time'); if ($state && $door && $door > $state) $set('door_time', $state);
                                        }),
                                    Forms\Components\TimePicker::make('door_time')->label('Door time')->placeholder('Select time')->seconds(false)->native(true)->suffixIcon('heroicon-m-clock')->rule('before_or_equal:start_time'),
                                    Forms\Components\TimePicker::make('end_time')->label('End time')->placeholder('Select time')->seconds(false)->native(true)->suffixIcon('heroicon-m-clock')->rule('after_or_equal:start_time'),
                                ])->visible(fn (SGet $get) => $get('duration_mode') === 'single_day'),
                                SC\Grid::make(4)->schema([
                                    Forms\Components\DatePicker::make('range_start_date')->label('Start date')->placeholder('Select date')->minDate($today)->native(false)->suffixIcon('heroicon-m-calendar')->live()
                                        ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                            if ($state) { $minEnd = Carbon::parse($state)->addDay()->format('Y-m-d'); $end = $get('range_end_date'); if (! $end || Carbon::parse($end)->lt(Carbon::parse($minEnd))) $set('range_end_date', $minEnd); }
                                        }),
                                    Forms\Components\DatePicker::make('range_end_date')->label('End date')->placeholder('Select date')
                                        ->minDate(fn (SGet $get) => ($get('range_start_date') ? Carbon::parse($get('range_start_date'))->addDay() : Carbon::today()->addDay()))
                                        ->rule(fn (SGet $get) => $get('range_start_date') ? 'after:range_start_date' : null)
                                        ->native(false)->suffixIcon('heroicon-m-calendar')->live()->helperText('End date must be at least 1 day after start date.'),
                                    Forms\Components\TimePicker::make('range_start_time')->label('Start time (start day)')->placeholder('Select time')->seconds(false)->native(true)->suffixIcon('heroicon-m-clock')
                                        ->required(fn (SGet $get) => $get('duration_mode') === 'range')->live()
                                        ->afterStateUpdated(function ($state, SSet $set, SGet $get) { $end = $get('range_end_time'); if ($state && $end && $end < $state) $set('range_end_time', $state); }),
                                    Forms\Components\TimePicker::make('range_end_time')->label('End time (end day)')->placeholder('Select time')->seconds(false)->native(true)->suffixIcon('heroicon-m-clock')->rule('after_or_equal:range_start_time'),
                                ])->visible(fn (SGet $get) => $get('duration_mode') === 'range'),
                                Forms\Components\Repeater::make('multi_slots')->label('Days & times')
                                    ->schema([
                                        Forms\Components\DatePicker::make('date')->label('Date')->placeholder('Select date')->minDate($today)->native(false)->suffixIcon('heroicon-m-calendar')->required(),
                                        Forms\Components\TimePicker::make('start_time')->label('Start')->placeholder('Select time')->seconds(false)->native(true)->suffixIcon('heroicon-m-clock')->nullable()->live()
                                            ->afterStateUpdated(function($state, SSet $set, SGet $get){ $end=$get('end_time'); if($state && $end && $end < $state){ $set('end_time',$state);} $door=$get('door_time'); if($state && $door && $door > $state){ $set('door_time',$state);} }),
                                        Forms\Components\TimePicker::make('door_time')->label('Door')->placeholder('Select time')->seconds(false)->native(true)->suffixIcon('heroicon-m-clock')->nullable()->rule('before_or_equal:start_time'),
                                        Forms\Components\TimePicker::make('end_time')->label('End')->placeholder('Select time')->seconds(false)->native(true)->suffixIcon('heroicon-m-clock')->nullable()->rule('after_or_equal:start_time'),
                                    ])->addActionLabel('Add another date')->default([])->visible(fn (SGet $get) => $get('duration_mode') === 'multi_day')->columns(4),
                                SC\Group::make()->visible(fn (SGet $get) => $get('duration_mode') === 'recurring')->schema([
                                    SC\Grid::make(4)->schema([
                                        Forms\Components\DatePicker::make('recurring_start_date')->label('Initial date')->placeholder('Select date')->minDate(now()->startOfDay())->native(false)->suffixIcon('heroicon-m-calendar')->live()
                                            ->afterStateUpdated(function ($state, SSet $set) { if (! $state) { $set('recurring_weekday', null); return; } $set('recurring_weekday', Carbon::parse($state)->dayOfWeekIso); }),
                                        Forms\Components\TextInput::make('recurring_weekday')->label('Weekday')->disabled()->dehydrated(false)
                                            ->formatStateUsing(function (SGet $get) { $map = [1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun']; return $map[$get('recurring_weekday')] ?? ''; }),
                                        Forms\Components\Select::make('recurring_frequency')->label('Recurrence')->options(['weekly' => 'Weekly', 'monthly_nth' => 'Monthly (Nth weekday)'])->required()->live()
                                            ->helperText(fn (SGet $get) => $get('recurring_weekday') ? 'Weekday: ' . (['','Mon','Tue','Wed','Thu','Fri','Sat','Sun'][$get('recurring_weekday')]) : 'Pick an initial date to detect weekday.'),
                                        Forms\Components\TextInput::make('recurring_count')->label('Occurrences (optional)')->numeric()->minValue(1),
                                    ]),
                                    SC\Grid::make(2)->visible(fn (SGet $get) => $get('recurring_frequency') === 'monthly_nth')->schema([
                                        Forms\Components\Select::make('recurring_week_of_month')->label('Week of month')->options([1 => 'First', 2 => 'Second', 3 => 'Third', 4 => 'Fourth', -1 => 'Last'])->required(),
                                    ]),
                                    SC\Grid::make(4)->schema([
                                        Forms\Components\TimePicker::make('recurring_start_time')->label('Start time')->seconds(false)->native(true)->suffixIcon('heroicon-m-clock')->required()->live()
                                            ->afterStateUpdated(function ($state, SSet $set, SGet $get) { $end = $get('recurring_end_time'); if ($state && $end && $end < $state) $set('recurring_end_time', $state); $door = $get('recurring_door_time'); if ($state && $door && $door > $state) $set('recurring_door_time', $state); }),
                                        Forms\Components\TimePicker::make('recurring_door_time')->label('Door time')->seconds(false)->native(true)->suffixIcon('heroicon-m-clock')->rule('before_or_equal:recurring_start_time'),
                                        Forms\Components\TimePicker::make('recurring_end_time')->label('End time')->seconds(false)->native(true)->suffixIcon('heroicon-m-clock')->rule('after_or_equal:recurring_start_time'),
                                    ]),
                                ]),
                            ])->columns(1),
                        SC\Section::make('Location & Links')
                            ->schema([
                                Forms\Components\Select::make('venue_id')->label('Venue')->searchable()->preload()
                                    ->relationship(name: 'venue', titleAttribute: 'name', modifyQueryUsing: function (Builder $query, SGet $get) {
                                        $tenantId = $get('tenant_id');
                                        return $query->when($tenantId, fn($q) => $q->where(fn($qq) => $qq->whereNull('tenant_id')->orWhere('tenant_id', $tenantId)));
                                    })
                                    ->getOptionLabelFromRecordUsing(fn ($record) => ($record->getTranslation('name', 'en') ?: $record->getTranslation('name', 'ro') ?: $record->name) . ($record->city ? " ({$record->city})" : ''))
                                    ->helperText('Shows only venues without an owner or belonging to this event\'s Tenant.')
                                    ->live()->nullable()
                                    ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                        if (!$state) return;
                                        $tenantId = $get('tenant_id');
                                        $venue = Venue::whereKey($state)->where(fn($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))->first();
                                        if (!$venue) { $set('venue_id', null); return; }
                                        if ($venue->address && !$get('address')) $set('address', $venue->address);
                                        if ($venue->website_url && !$get('website_url')) $set('website_url', $venue->website_url);
                                    })
                                    ->rules(function (SGet $get) {
                                        return [function (string $attribute, $value, \Closure $fail) use ($get) {
                                            if (!$value) return;
                                            $tenantId = $get('tenant_id');
                                            if (!Venue::whereKey($value)->where(fn($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))->exists())
                                                $fail('Venue-ul selectat nu este permis pentru acest Tenant.');
                                        }];
                                    }),
                                Forms\Components\Select::make('event_seating_layout_id')->label('Seating Layout')->searchable()->preload()
                                    ->options(function (SGet $get) {
                                        $venueId = $get('venue_id'); if (!$venueId) return [];
                                        return \App\Models\Seating\SeatingLayout::query()->where('venue_id', $venueId)->where('status', 'published')->orderBy('name')->pluck('name', 'id');
                                    })
                                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Select a published seating layout for this event (if available)')
                                    ->nullable()->visible(fn (SGet $get) => $get('venue_id') !== null)->reactive(),
                                Forms\Components\TextInput::make('address')->label('Address')->maxLength(255)->placeholder('Street, city, country...'),
                                Forms\Components\TextInput::make('website_url')->label('Website')->url()->rule('url')->prefixIcon('heroicon-m-globe-alt')->maxLength(255)->placeholder('https://...'),
                                Forms\Components\TextInput::make('facebook_url')->label('Facebook Event')->url()->rule('url')->prefixIcon('heroicon-m-link')->maxLength(255)->placeholder('https://facebook.com/events/...'),
                                Forms\Components\TextInput::make('event_website_url')->label('Event Website')->url()->rule('url')->prefixIcon('heroicon-m-link')->maxLength(255)->placeholder('https://...'),
                            ])->columns(2),
                                    ]), // End Tab 2: Program

                                // ========== TAB 3: CONTINUT ==========
                                SC\Tabs\Tab::make('Continut')
                                    ->key('continut')
                                    ->icon('heroicon-o-pencil-square')
                                    ->lazy()
                                    ->schema([
                        SC\Section::make('Media')->schema([
                            Forms\Components\FileUpload::make('poster_url')->label('Poster (vertical)')->image()->directory('events/posters')->disk('public')->visibility('public'),
                            Forms\Components\FileUpload::make('hero_image_url')->label('Hero image (horizontal)')->image()->directory('events/hero')->disk('public')->visibility('public'),
                        ])->columns(2),
                        SC\Section::make('Content')->schema([
                            Forms\Components\Textarea::make('short_description.en')->label('Short description')->rows(3)->placeholder('1-2 lines summary...')
                                ->afterStateHydrated(function ($component, $state, ?Event $record) { if (empty($state) && $record) { $val = $record->getTranslation('short_description', 'en'); if ($val) $component->state($val); } }),
                            Forms\Components\RichEditor::make('description.en')->label('Description')->columnSpanFull()
                                ->afterStateHydrated(function ($component, $state, ?Event $record) { if (empty($state) && $record) { $val = $record->getTranslation('description', 'en'); if ($val) $component->state($val); } }),
                            Forms\Components\RichEditor::make('ticket_terms.en')->label('Ticket terms')->columnSpanFull()
                                ->afterStateHydrated(function ($component, $state, ?Event $record) { if (empty($state) && $record) { $val = $record->getTranslation('ticket_terms', 'en'); if ($val) $component->state($val); } }),
                        ])->columns(1),
                        SC\Section::make('Taxonomies & Relations')->schema([
                            Forms\Components\Select::make('eventTypes')->label('Event types')
                                ->relationship(name: 'eventTypes', modifyQueryUsing: function (Builder $query) {
                                    $query->leftJoin('event_types as p', 'p.id', '=', 'event_types.parent_id')
                                        ->whereNotNull('event_types.parent_id')->select('event_types.*')
                                        ->addSelect('p.name as __parent_name')
                                        ->addSelect(DB::raw("CASE WHEN event_types.parent_id IS NULL THEN 0 ELSE 1 END AS __parent_rank"))
                                        ->orderBy('__parent_rank')->orderBy('__parent_name')->orderBy('event_types.name');
                                })
                                ->getSearchResultsUsing(function (string $search) {
                                    return EventType::query()->leftJoin('event_types as p', 'p.id', '=', 'event_types.parent_id')
                                        ->whereNotNull('event_types.parent_id')
                                        ->where(function ($q) use ($search) { $q->where('event_types.name', 'like', "%{$search}%")->orWhere('p.name', 'like', "%{$search}%"); })
                                        ->select('event_types.id', 'event_types.name', 'p.name as __parent_name')
                                        ->addSelect(DB::raw("CASE WHEN event_types.parent_id IS NULL THEN 0 ELSE 1 END AS __parent_rank"))
                                        ->orderBy('__parent_rank')->orderBy('__parent_name')->orderBy('event_types.name')->limit(50)->get()
                                        ->mapWithKeys(function ($r) {
                                            $childName = is_array($r->name) ? ($r->name['en'] ?? $r->name['ro'] ?? reset($r->name)) : $r->name;
                                            $parentName = $r->__parent_name;
                                            if (is_array($parentName)) { $parentName = $parentName['en'] ?? $parentName['ro'] ?? reset($parentName); }
                                            elseif (is_string($parentName) && str_starts_with($parentName, '{')) { $decoded = json_decode($parentName, true); $parentName = $decoded['en'] ?? $decoded['ro'] ?? reset($decoded) ?? $parentName; }
                                            return [$r->id => $parentName ? "{$parentName} > {$childName}" : $childName];
                                        })->toArray();
                                })
                                ->getOptionLabelFromRecordUsing(function ($record) {
                                    $childName = $record->getTranslation('name', 'en') ?: $record->getTranslation('name', 'ro') ?: $record->name;
                                    if (is_array($childName)) $childName = $childName['en'] ?? $childName['ro'] ?? reset($childName);
                                    $parentName = null;
                                    if ($record->parent_id) { $parent = EventType::find($record->parent_id); if ($parent) { $parentName = $parent->getTranslation('name', 'en') ?: $parent->getTranslation('name', 'ro') ?: $parent->name; if (is_array($parentName)) $parentName = $parentName['en'] ?? $parentName['ro'] ?? reset($parentName); } }
                                    return $parentName ? "{$parentName} > {$childName}" : $childName;
                                })
                                ->multiple()->preload()->searchable()->reactive()->maxItems(2)
                                ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                    $typeIds = (array) ($get('eventTypes') ?? []); if (! $typeIds) { $set('eventGenres', []); return; }
                                    $allowed = EventGenre::query()->whereExists(function ($sub) use ($typeIds) { $sub->selectRaw('1')->from('event_type_event_genre as eteg')->whereColumn('eteg.event_genre_id', 'event_genres.id')->whereIn('eteg.event_type_id', $typeIds); })->pluck('id')->all();
                                    $current = (array) ($get('eventGenres') ?? []); $filtered = array_values(array_intersect($current, $allowed));
                                    if (count($filtered) !== count($current)) $set('eventGenres', $filtered);
                                }),
                            Forms\Components\Select::make('eventGenres')->label('Event genres')
                                ->relationship(name: 'eventGenres', modifyQueryUsing: function (Builder $query, SGet $get) {
                                    $typeIds = (array) ($get('eventTypes') ?? []); if (! $typeIds) { $query->whereRaw('1=0'); return; }
                                    $query->whereExists(function ($sub) use ($typeIds) { $sub->selectRaw('1')->from('event_type_event_genre as eteg')->whereColumn('eteg.event_genre_id', 'event_genres.id')->whereIn('eteg.event_type_id', $typeIds); });
                                })
                                ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('name', 'en'))
                                ->multiple()->preload()->searchable()->disabled(fn (SGet $get) => empty($get('eventTypes')))->reactive()->minItems(0)->maxItems(5),
                            Forms\Components\Select::make('artists')->label('Artists')->relationship('artists', 'name')->multiple()->preload()->searchable(),
                            Forms\Components\Select::make('tags')->label('Event tags')->relationship('tags', 'name')->multiple()->preload()->searchable()
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('name')->required()->maxLength(190),
                                    Forms\Components\TextInput::make('slug')->helperText('Leave empty to auto-generate.')->maxLength(190),
                                    Forms\Components\Textarea::make('description')->rows(2),
                                ])
                                ->createOptionUsing(function (array $data) { $data['slug'] = $data['slug'] ?: Str::slug($data['name']); return \App\Models\EventTag::create($data); }),
                            Forms\Components\Placeholder::make('applicable_taxes')
                                ->label('Taxe aplicabile pentru tipul de eveniment')->columnSpanFull()
                                ->visible(fn (SGet $get) => !empty($get('eventTypes')) || !empty($get('venue_id')))
                                ->content(function (SGet $get, $record) {
                                    $eventTypeIds = (array) ($get('eventTypes') ?? []); $venueId = $get('venue_id');
                                    $tenant = $record?->tenant; $isVatPayer = $tenant?->vat_payer ?? null; $taxDisplayMode = $tenant?->tax_display_mode ?? 'included';
                                    $venueHasMonumentTax = false; if ($venueId) { $venue = Venue::find($venueId); $venueHasMonumentTax = $venue?->has_historical_monument_tax ?? false; }
                                    $allTaxes = GeneralTax::query()->whereNull('tenant_id')->active()->validOn(Carbon::today())
                                        ->when(!empty($eventTypeIds), fn($q) => $q->forEventTypes($eventTypeIds))
                                        ->when(empty($eventTypeIds), fn($q) => $q->whereDoesntHave('eventTypes'))
                                        ->orderByDesc('priority')->get()->unique('id');
                                    if ($venueHasMonumentTax) { $monumentTax = GeneralTax::where('name', 'Taxa de Monument Istoric')->active()->first(); if ($monumentTax && !$allTaxes->contains('id', $monumentTax->id)) $allTaxes = $allTaxes->push($monumentTax)->sortByDesc('priority'); }
                                    if ($allTaxes->isEmpty()) return new HtmlString('<div class="text-sm text-gray-500 italic">Nu exista taxe configurate.</div>');
                                    $html = '<div class="space-y-2">';
                                    if ($venueHasMonumentTax && $venueId) { $venue = $venue ?? Venue::find($venueId); $venueName = $venue?->getTranslation('name', 'en') ?? 'Venue selectat'; $html .= '<div class="mb-3 p-2 bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-700 rounded-lg"><div class="flex items-center gap-2 text-sm text-purple-800 dark:text-purple-200"><span><strong>' . e($venueName) . '</strong> este monument istoric - Taxa de Monument Istoric (2%)</span></div></div>'; }
                                    if ($isVatPayer !== null) { $vatBadge = $isVatPayer ? '<span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">Platitor TVA</span>' : '<span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-600">Neplatitor TVA</span>'; $html .= '<div class="mb-3 flex flex-wrap items-center gap-2">' . $vatBadge . '</div>'; }
                                    $html .= '<div class="grid grid-cols-1 md:grid-cols-2 gap-2">';
                                    foreach ($allTaxes as $tax) { $isVatTax = str_contains(strtolower($tax->name ?? ''), 'tva') || str_contains(strtolower($tax->name ?? ''), 'vat'); $isMonumentTax = str_contains(strtolower($tax->name ?? ''), 'monument'); if ($isVatTax && $isVatPayer === false) continue; $rateDisplay = $tax->value_type === 'percent' ? number_format($tax->value, 2) . '%' : number_format($tax->value, 2) . ' ' . ($tax->currency ?? 'RON'); $includedBadge = $tax->is_added_to_price ? '<span class="text-xs text-amber-600">(se adauga)</span>' : '<span class="text-xs text-gray-500">(inclus)</span>'; $iconHtml = $tax->icon_svg ? '<span class="inline-flex items-center mr-1">' . $tax->icon_svg . '</span>' : ''; $borderClass = $isMonumentTax ? 'border-purple-300 bg-purple-50 dark:bg-purple-900/20' : 'border-gray-200 bg-gray-50 dark:bg-gray-800'; $html .= '<div class="flex items-center justify-between p-2 rounded-lg border ' . $borderClass . '"><div class="flex items-center gap-2">' . $iconHtml . '<span class="font-medium text-sm">' . e($tax->name) . '</span></div><div class="text-right"><span class="font-semibold text-primary">' . $rateDisplay . '</span><br>' . $includedBadge . '</div></div>'; }
                                    $html .= '</div></div>'; return new HtmlString($html);
                                }),
                        ])->columns(2),
                                    ]), // End Tab 3: Continut

                                // ========== TAB 4: BILETE ==========
                                SC\Tabs\Tab::make('Bilete')
                                    ->key('bilete')
                                    ->icon('heroicon-o-ticket')
                                    ->lazy()
                                    ->schema([
                        SC\Section::make('Pricing & Commission')->schema([
                            Forms\Components\Placeholder::make('commission_inheritance_info')->label('Commission Settings')->live()
                                ->content(function (SGet $get) {
                                    $tenantId = $get('tenant_id'); if (!$tenantId) return 'Select a tenant first to see default commission settings.';
                                    $tenant = \App\Models\Tenant::find($tenantId); if (!$tenant) return 'Tenant not found.';
                                    $mode = $tenant->commission_mode ?? 'included'; $rate = $tenant->commission_rate ?? 5.00;
                                    $modeText = $mode === 'included' ? 'Included in ticket price' : 'Added on top of ticket price';
                                    return "**Default from tenant:** {$modeText} at **{$rate}%**. You can override below.";
                                })->columnSpanFull(),
                            SC\Grid::make(2)->schema([
                                Forms\Components\Select::make('commission_mode')->label('Commission Mode')
                                    ->options(['included' => 'Include commission in ticket price', 'added_on_top' => 'Add commission on top of ticket price'])
                                    ->placeholder('Use tenant default')->helperText('Choose how commission is applied. Leave empty to use tenant\'s default.')->live()->nullable(),
                                Forms\Components\TextInput::make('commission_rate')->label('Commission Rate (%)')->placeholder('e.g. 5.00')->numeric()->minValue(0)->maxValue(100)->step(0.01)->suffix('%')
                                    ->helperText('Override tenant\'s commission rate. Leave empty for default.')->live(debounce: 500)->nullable(),
                            ]),
                            Forms\Components\Placeholder::make('commission_example')->label('Example Calculation')->live()
                                ->content(function (SGet $get) {
                                    $tenantId = $get('tenant_id'); $eventMode = $get('commission_mode'); $eventRate = $get('commission_rate');
                                    if (!$tenantId) return 'Select a tenant first.';
                                    $tenant = \App\Models\Tenant::find($tenantId); if (!$tenant) return 'Tenant not found.';
                                    $mode = $eventMode ?: ($tenant->commission_mode ?? 'included'); $rate = $eventRate ?: ($tenant->commission_rate ?? 5.00);
                                    $ticketPrice = 100; $commission = round($ticketPrice * ($rate / 100), 2);
                                    if ($mode === 'included') { $revenue = $ticketPrice - $commission; return "**Example:** For a {$ticketPrice} RON ticket, commission of {$commission} RON is deducted, leaving {$revenue} RON revenue."; }
                                    else { $total = $ticketPrice + $commission; return "**Example:** For a {$ticketPrice} RON ticket, commission of {$commission} RON is added, customer pays {$total} RON."; }
                                })->columnSpanFull(),
                        ])->columns(1),
                        SC\Section::make('Tickets')->schema([
                            Forms\Components\Select::make('ticket_template_id')->label('Ticket Template')
                                ->relationship(name: 'ticketTemplate', modifyQueryUsing: fn (Builder $query, SGet $get) => $query->where('tenant_id', $get('tenant_id'))->where('status', 'active'))
                                ->getOptionLabelFromRecordUsing(fn ($record) => $record->name . ($record->is_default ? ' (Default)' : ''))
                                ->placeholder('Use default template')->helperText('Select a template for tickets. Leave empty for default.')
                                ->searchable()->preload()->nullable()->live()->disabled(fn (SGet $get) => !$get('tenant_id')),
                            Forms\Components\Repeater::make('ticketTypes')->relationship()->label('Ticket types')->collapsed()
                                ->addActionLabel('Add ticket type')
                                ->itemLabel(fn (array $state) => ($state['is_active'] ?? true) ? '> ' . ($state['name'] ?? 'Ticket') : 'o ' . ($state['name'] ?? 'Ticket'))
                                ->columns(12)->schema([
                                    Forms\Components\TextInput::make('name')->label('Name')->placeholder('e.g. Early Bird, Standard, VIP')
                                        ->datalist(['Early Bird','Standard','VIP','Backstage','Student','Senior','Child','Crew'])->required()->columnSpan(6)->live(debounce: 400)
                                        ->afterStateUpdated(function ($state, SSet $set, SGet $get) { if ($get('sku')) return; $set('sku', Str::upper(Str::slug($state, '-'))); }),
                                    Forms\Components\TextInput::make('sku')->label('SKU')->placeholder('AUTO-GEN if left empty')->helperText('Leave empty to auto-generate.')->columnSpan(6)
                                        ->afterStateHydrated(function ($component, SGet $get) {
                                            if ($component->getState()) return;
                                            $title = (string) ($get('../../title.en') ?? 'EVT'); $slug = Str::upper(Str::slug($title, '-'));
                                            $date = (string) ($get('../../event_date') ?? $get('../../range_start_date') ?? now()->toDateString()); $ymd = str_replace('-', '', $date);
                                            $name = (string) ($get('name') ?? 'TKT'); $code = Str::upper(preg_replace('/[^A-Z0-9]+/i', '-', $name));
                                            $component->state($slug . '-' . $ymd . '-' . $code);
                                        }),
                                    Forms\Components\Textarea::make('description')->label('Description')->placeholder('Optional ticket type description')->rows(2)->columnSpan(12),
                                    SC\Grid::make(4)->schema([
                                        Forms\Components\TextInput::make('currency')->label('Currency')->placeholder('Auto from tenant')->disabled()->dehydrated(true)
                                            ->afterStateHydrated(function ($component, SGet $get) { if ($component->getState()) return; $component->state(optional(\App\Models\Tenant::find($get('../../tenant_id')))->currency ?? 'RON'); }),
                                        Forms\Components\TextInput::make('price_max')->label('Price')->placeholder('e.g. 120.00')->numeric()->minValue(0)->required()->live(debounce: 300)
                                            ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                                $price = (float) $state; $sale = $get('price'); $disc = $get('discount_percent');
                                                if ($price > 0 && !$sale && is_numeric($disc)) { $set('price', round($price * (1 - max(0, min(100, (float)$disc))/100), 2)); }
                                                if ($price > 0 && $sale) { $set('discount_percent', max(0, min(100, round((1 - ((float)$sale / $price)) * 100, 2)))); }
                                            }),
                                        Forms\Components\TextInput::make('price')->label('Sale price')->placeholder('leave empty if no sale')->numeric()->minValue(0)->live(debounce: 300)
                                            ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                                $price = (float) ($get('price_max') ?: 0); $sale = $state !== null && $state !== '' ? (float)$state : null;
                                                if ($price > 0 && $sale) { $set('discount_percent', max(0, min(100, round((1 - ($sale / $price)) * 100, 2)))); } else { $set('discount_percent', null); }
                                            }),
                                        Forms\Components\TextInput::make('discount_percent')->label('Discount %')->placeholder('e.g. 20')->numeric()->minValue(0)->maxValue(100)->live(debounce: 300)
                                            ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                                $price = (float) ($get('price_max') ?: 0); if ($price <= 0) return;
                                                if ($state === null || $state === '') { $set('price', null); return; }
                                                $set('price', round($price * (1 - max(0, min(100, (float)$state))/100), 2));
                                            }),
                                    ])->columnSpan(12),
                                    Forms\Components\Placeholder::make('commission_info')->label('Commission Settings')->live()
                                        ->content(function (SGet $get) {
                                            $eventId = $get('../../id'); $tenantId = $get('../../tenant_id'); $eventMode = $get('../../commission_mode'); $eventRate = $get('../../commission_rate');
                                            if (!$eventId) { if (!$tenantId) return 'Select a tenant first.'; $tenant = \App\Models\Tenant::find($tenantId); if (!$tenant) return 'Tenant not found.'; $mode = $eventMode ?: ($tenant->commission_mode ?? 'included'); $rate = $eventRate ?: ($tenant->commission_rate ?? 5.00); $source = $eventMode ? 'event override' : 'tenant default'; }
                                            else { $event = \App\Models\Event::with('tenant')->find($eventId); if (!$event) return 'Event not found.'; $mode = $event->getEffectiveCommissionMode(); $rate = $event->getEffectiveCommissionRate(); $source = $event->commission_mode ? 'event override' : 'tenant default'; }
                                            return "**" . ($mode === 'included' ? 'Included in ticket price' : 'Added on top') . "** at **{$rate}%** (from {$source})";
                                        })->helperText('Commission settings inherited from event or tenant.')->columnSpan(12),
                                    SC\Grid::make(3)->schema([
                                        Forms\Components\TextInput::make('capacity')->label('Capacity')->placeholder('e.g. 250')->numeric()->minValue(0)->required(),
                                        Forms\Components\DateTimePicker::make('sale_starts_at')->label('Sale starts')->placeholder('Select date & time')->native(false)->suffixIcon('heroicon-m-calendar')->minDate(now())->live(),
                                        Forms\Components\DateTimePicker::make('sale_ends_at')->label('Sale ends')->placeholder('Select date & time')->native(false)->suffixIcon('heroicon-m-calendar')
                                            ->minDate(fn (SGet $get) => $get('sale_starts_at') ? Carbon::parse($get('sale_starts_at'))->addDay() : now()->addDay())
                                            ->rule('after:sale_starts_at')->live()
                                            ->afterStateUpdated(function ($state, SSet $set, SGet $get) { $start = $get('sale_starts_at'); if (!$start) return; $minEnd = Carbon::parse($start)->addDay(); if ($state && Carbon::parse($state)->lt($minEnd)) $set('sale_ends_at', $minEnd->format('Y-m-d H:i:s')); }),
                                    ])->columnSpan(12),
                                    Forms\Components\Repeater::make('bulk_discounts')->label('Bulk discounts')->collapsed()->addActionLabel('Add bulk rule')
                                        ->itemLabel(fn (array $state) => $state['rule_type'] ?? 'Rule')->columns(12)->schema([
                                            Forms\Components\Select::make('rule_type')->label('Rule type')->options(['buy_x_get_y' => 'Buy X get Y free', 'buy_x_percent_off' => 'Buy X tickets % off', 'amount_off_per_ticket' => 'Amount off per ticket', 'bundle_price' => 'Bundle price'])->required()->columnSpan(3)->live(),
                                            Forms\Components\TextInput::make('buy_qty')->label('Buy X')->numeric()->minValue(1)->visible(fn ($get) => $get('rule_type') === 'buy_x_get_y')->columnSpan(3),
                                            Forms\Components\TextInput::make('get_qty')->label('Get Y free')->numeric()->minValue(1)->visible(fn ($get) => $get('rule_type') === 'buy_x_get_y')->columnSpan(3),
                                            Forms\Components\TextInput::make('min_qty')->label('Min qty')->numeric()->minValue(1)->visible(fn ($get) => in_array($get('rule_type'), ['buy_x_percent_off','amount_off_per_ticket','bundle_price']))->columnSpan(3),
                                            Forms\Components\TextInput::make('percent_off')->label('% off')->numeric()->minValue(1)->maxValue(100)->visible(fn ($get) => $get('rule_type') === 'buy_x_percent_off')->columnSpan(3),
                                            Forms\Components\TextInput::make('amount_off')->label('Amount off')->numeric()->minValue(0.01)->visible(fn ($get) => $get('rule_type') === 'amount_off_per_ticket')->columnSpan(3),
                                            Forms\Components\TextInput::make('bundle_total_price')->label('Bundle total')->numeric()->minValue(0.01)->visible(fn ($get) => $get('rule_type') === 'bundle_price')->columnSpan(3),
                                        ])->columnSpan(12),
                                    Forms\Components\Toggle::make('is_active')->label('Active?')->default(true)->live()->columnSpan(4),
                                    Forms\Components\DateTimePicker::make('scheduled_at')->label('Schedule Activation')->helperText('When this ticket type should automatically become active')->native(false)->seconds(false)->displayFormat('Y-m-d H:i')->minDate(now())->visible(fn (SGet $get) => !$get('is_active'))->columnSpan(4),
                                    Forms\Components\Toggle::make('autostart_when_previous_sold_out')->label('Autostart when previous sold out')->helperText('Activate automatically when previous ticket types reach 0 capacity')->visible(fn (SGet $get) => !$get('is_active'))->columnSpan(4),
                                ]),
                        ])->collapsible(),
                                    ]), // End Tab 4: Bilete

                                // ========== TAB 5: SEO ==========
                                SC\Tabs\Tab::make('SEO')
                                    ->key('seo')
                                    ->icon('heroicon-o-globe-alt')
                                    ->lazy()
                                    ->schema([
                        SC\Section::make('SEO')->schema([
                            Forms\Components\Select::make('seo_presets')->label('Add SEO keys from template')->multiple()->dehydrated(false)
                                ->options(['core' => 'Core (title/description/canonical/robots)', 'intl' => 'International (hreflang, og:locale)', 'open_graph' => 'Open Graph (og:*)', 'article' => 'OG Article extras', 'product' => 'OG Product extras', 'twitter' => 'Twitter Cards', 'jsonld' => 'Structured Data (JSON-LD shell)', 'robots_adv' => 'Robots advanced', 'verify' => 'Verification (Google/Bing/etc.)', 'feeds' => 'Feeds (RSS/Atom/oEmbed)'])
                                ->helperText('Select one or more sets - keys missing below will be added automatically.')->live()
                                ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                    $seo = (array) ($get('seo') ?? []);
                                    $templates = [
                                        'core' => ['meta_title' => '', 'meta_description' => '', 'canonical_url' => '', 'robots' => 'index,follow', 'viewport' => 'width=device-width, initial-scale=1', 'referrer' => 'no-referrer-when-downgrade'],
                                        'intl' => ['og:locale' => 'en_US', 'hreflang_map' => '[]'],
                                        'open_graph' => ['og:title' => '', 'og:description' => '', 'og:type' => 'website', 'og:url' => '', 'og:image' => '', 'og:image:alt' => '', 'og:image:width' => '', 'og:image:height' => '', 'og:site_name' => ''],
                                        'article' => ['article:author' => '', 'article:section' => '', 'article:tag' => '', 'article:published_time' => '', 'article:modified_time' => ''],
                                        'product' => ['product:price:amount' => '', 'product:price:currency' => '', 'product:availability' => ''],
                                        'twitter' => ['twitter:card' => 'summary_large_image', 'twitter:title' => '', 'twitter:description' => '', 'twitter:image' => '', 'twitter:site' => '', 'twitter:creator' => '', 'twitter:player' => '', 'twitter:player:width' => '', 'twitter:player:height' => ''],
                                        'jsonld' => ['structured_data' => json_encode(['@context' => 'https://schema.org', '@type' => 'Event', 'name' => '', 'description' => '', 'image' => '', 'startDate' => '', 'endDate' => '', 'location' => ['@type' => 'Place', 'name' => '', 'address' => ''], 'organizer' => ['@type' => 'Organization', 'name' => '', 'url' => ''], 'url' => ''], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)],
                                        'robots_adv' => ['max-snippet' => '', 'max-image-preview' => '', 'max-video-preview' => '', 'noarchive' => '', 'nosnippet' => '', 'noimageindex' => '', 'indexifembedded' => '', 'googlebot' => '', 'bingbot' => ''],
                                        'verify' => ['google-site-verification' => '', 'msvalidate.01' => '', 'p:domain_verify' => '', 'yandex-verification' => '', 'ahrefs-site-verification' => '', 'facebook-domain-verification' => ''],
                                        'feeds' => ['rss_url' => '', 'atom_url' => '', 'oembed_json' => '', 'oembed_xml' => ''],
                                    ];
                                    foreach ((array) $state as $group) { foreach (($templates[$group] ?? []) as $k => $v) { if (! array_key_exists($k, $seo)) $seo[$k] = $v; } }
                                    $set('seo', $seo);
                                }),
                            Forms\Components\KeyValue::make('seo')->keyLabel('Meta key')->valueLabel('Meta value')->addable()->deletable()->reorderable()->columnSpanFull()
                                ->default(['meta_title' => '', 'meta_description' => '', 'canonical_url' => '', 'robots' => 'index,follow', 'viewport' => 'width=device-width, initial-scale=1', 'referrer' => 'no-referrer-when-downgrade', 'og:locale' => 'en_US', 'hreflang_map' => '[]', 'og:title' => '', 'og:description' => '', 'og:type' => 'website', 'og:url' => '', 'og:image' => '', 'og:image:alt' => '', 'og:image:width' => '', 'og:image:height' => '', 'og:site_name' => '', 'article:author' => '', 'article:section' => '', 'article:tag' => '', 'article:published_time' => '', 'article:modified_time' => '', 'product:price:amount' => '', 'product:price:currency' => '', 'product:availability' => '', 'twitter:card' => 'summary_large_image', 'twitter:title' => '', 'twitter:description' => '', 'twitter:image' => '', 'twitter:site' => '', 'twitter:creator' => '', 'twitter:player' => '', 'twitter:player:width' => '', 'twitter:player:height' => '', 'structured_data' => '', 'max-snippet' => '', 'max-image-preview' => '', 'max-video-preview' => '', 'noarchive' => '', 'nosnippet' => '', 'noimageindex' => '', 'indexifembedded' => '', 'googlebot' => '', 'bingbot' => '', 'google-site-verification' => '', 'msvalidate.01' => '', 'p:domain_verify' => '', 'yandex-verification' => '', 'ahrefs-site-verification' => '', 'facebook-domain-verification' => '', 'rss_url' => '', 'atom_url' => '', 'oembed_json' => '', 'oembed_xml' => ''])
                                ->helperText('Auto-fill on save applies only to empty keys. You can add/remove keys as needed.'),
                        ]),
                                    ]), // End Tab 5: SEO

                                // ========== TAB 6: HARTA LOCURI ==========
                                SC\Tabs\Tab::make('Harta Locuri')
                                    ->key('harta')
                                    ->icon('heroicon-o-map')
                                    ->visible(fn (SGet $get) => (bool) $get('event_seating_layout_id'))
                                    ->lazy()
                                    ->schema([
                        Forms\Components\Placeholder::make('seating_map_editor')->hiddenLabel()
                            ->content(function (?Event $record) {
                                if (!$record || !$record->event_seating_layout_id) return new HtmlString('<div class="p-6 text-center text-gray-500">Save the event with a seating layout to see the visualization.</div>');
                                return new HtmlString(view('filament.forms.components.seating-map-editor', ['record' => $record])->render());
                            })->columnSpanFull(),
                                    ]), // End Tab 6: Harta Locuri

                                // ========== TAB 7: GRUPARE ==========
                                SC\Tabs\Tab::make('Grupare')
                                    ->key('turneu')
                                    ->icon('heroicon-o-squares-2x2')
                                    ->lazy()
                                    ->schema([
                        SC\Section::make('Grouping Settings')->schema([
                            Forms\Components\Toggle::make('is_in_tour')->label('Part of a Grouping')->helperText('Check if this event is part of a grouping (series or tour)')->dehydrated(false)->live(),
                            Forms\Components\Radio::make('grouping_type')->label('Grouping type')->options(['serie_evenimente' => 'Event Series', 'turneu' => 'Tour'])->default('serie_evenimente')->dehydrated(false)->live()->visible(fn (SGet $get) => (bool) $get('is_in_tour')),
                            Forms\Components\Radio::make('tour_mode')->label('Grouping mode')->options(['new' => 'New grouping', 'existing' => 'Existing grouping'])->default('new')->dehydrated(false)->live()->visible(fn (SGet $get) => (bool) $get('is_in_tour')),
                            Forms\Components\TextInput::make('tour_name')->label('Grouping name')->helperText('Enter a name for the grouping')->dehydrated(false)->maxLength(255)->visible(fn (SGet $get) => (bool) $get('is_in_tour') && $get('tour_mode') === 'new'),
                            Forms\Components\Select::make('existing_tour_id')->label('Select grouping')->helperText('Choose an existing grouping filtered by this event\'s artists.')->searchable()->dehydrated(false)
                                ->options(function (?Event $record) {
                                    $query = Tour::query();
                                    if ($record?->tenant_id) $query->where('tenant_id', $record->tenant_id);
                                    $artistIds = $record?->artists?->pluck('id')->toArray() ?? [];
                                    if (!empty($artistIds)) {
                                        $tourIds = Event::whereHas('artists', fn ($q) => $q->whereIn('artists.id', $artistIds))->whereNotNull('tour_id')->pluck('tour_id')->unique()->toArray();
                                        if (!empty($tourIds)) { if ($record?->tour_id) $tourIds[] = $record->tour_id; $query->whereIn('id', array_unique($tourIds)); }
                                    }
                                    return $query->orderBy('name')->get()->mapWithKeys(fn ($tour) => [$tour->id => $tour->name ?: ('Grouping #' . $tour->id)]);
                                })
                                ->visible(fn (SGet $get) => (bool) $get('is_in_tour') && $get('tour_mode') === 'existing'),
                        ]),
                                    ]), // End Tab 7: Grupare

                                // ========== TAB 8: OBSERVATII ==========
                                SC\Tabs\Tab::make('Observatii')
                                    ->key('observatii')
                                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                                    ->schema([
                        SC\Section::make('Internal Event Notes')->schema([
                            Forms\Components\Textarea::make('admin_notes')->label('Notes')->placeholder('Add internal notes about this event...')->rows(5)->columnSpanFull(),
                        ]),
                        SC\Section::make('Ticket Type Notes')->schema([
                            Forms\Components\Placeholder::make('ticket_notes_list')->hiddenLabel()
                                ->content(function (?Event $record) {
                                    if (!$record || !$record->exists) return 'Save the event to see ticket type notes.';
                                    $ticketTypes = $record->ticketTypes()->get();
                                    $hasNotes = $ticketTypes->filter(fn ($tt) => !empty($tt->admin_notes));
                                    if ($hasNotes->isEmpty()) return new HtmlString('<p class="text-sm text-gray-500">No notes on ticket types.</p>');
                                    $html = '<div class="space-y-3">';
                                    foreach ($hasNotes as $tt) { $name = e($tt->name); $notes = nl2br(e($tt->admin_notes)); $html .= '<div class="p-3 border border-gray-200 rounded-lg bg-gray-50 dark:bg-gray-800 dark:border-gray-700"><div class="mb-1 text-xs font-semibold text-primary-600 dark:text-primary-400">' . $name . '</div><div class="text-sm text-gray-700 dark:text-gray-300">' . $notes . '</div></div>'; }
                                    $html .= '</div>'; return new HtmlString($html);
                                }),
                        ]),
                                    ]), // End Tab 8: Observatii

                            ]), // End Tabs component
                    ]),

                // ========== RIGHT COLUMN - SIDEBAR (1/4) ==========
                SC\Group::make()
                    ->columnSpan(1)
                    ->schema([
                        Forms\Components\Toggle::make('is_published')->label('Published')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'When enabled, the event will be visible publicly.')
                            ->onIcon('heroicon-m-eye')->offIcon('heroicon-m-eye-slash')->default(false)->live(),
                        Forms\Components\Placeholder::make('event_status_badge')->hiddenLabel()
                            ->visible(fn (?Event $record) => $record && $record->exists)
                            ->content(function (?Event $record) {
                                if (!$record || !$record->exists) return null;
                                if ($record->is_cancelled) return new HtmlString('<span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold text-red-400 rounded-full bg-red-500/20 ring-1 ring-inset ring-red-500/30">CANCELLED</span>');
                                if ($record->is_postponed) return new HtmlString('<span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold rounded-full bg-amber-500/20 text-amber-400 ring-1 ring-inset ring-amber-500/30">POSTPONED</span>');
                                $eventEndDateTime = null;
                                if ($record->duration_mode === 'single_day' && $record->event_date) { $eventEndDateTime = Carbon::parse($record->event_date->format('Y-m-d') . ' ' . ($record->end_time ?? '23:59')); }
                                elseif ($record->duration_mode === 'range' && $record->range_end_date) { $eventEndDateTime = Carbon::parse($record->range_end_date->format('Y-m-d') . ' ' . ($record->range_end_time ?? '23:59')); }
                                elseif ($record->duration_mode === 'multi_day' && !empty($record->multi_slots)) { $lastSlot = collect($record->multi_slots)->sortByDesc('date')->first(); if ($lastSlot) $eventEndDateTime = Carbon::parse($lastSlot['date'] . ' ' . ($lastSlot['end_time'] ?? '23:59')); }
                                if ($eventEndDateTime && $eventEndDateTime->isPast()) return new HtmlString('<span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold text-gray-400 rounded-full bg-gray-500/20 ring-1 ring-inset ring-gray-500/30">ENDED</span>');
                                return null;
                            }),
                        Forms\Components\Placeholder::make('tenant_info')->hiddenLabel()
                            ->visible(fn (?Event $record) => $record && $record->exists && $record->tenant_id)
                            ->content(function (?Event $record) {
                                if (!$record || !$record->tenant) return null;
                                $tenant = $record->tenant; $name = e($tenant->public_name ?? $tenant->name);
                                $commission = $record->commission_rate ?? $tenant->commission_rate ?? 'N/A';
                                $mode = $record->commission_mode ?? $tenant->commission_mode ?? 'included';
                                $modeLabel = $mode === 'included' ? 'Included' : 'Added on top';
                                return new HtmlString('<div class="p-3 border border-gray-200 rounded-lg bg-gray-50 dark:bg-gray-800 dark:border-gray-700"><div class="text-xs font-semibold text-gray-500 mb-1">Tenant</div><div class="text-sm font-medium text-gray-900 dark:text-white">' . $name . '</div><div class="mt-2 text-xs text-gray-500">Commission: ' . $commission . '% (' . $modeLabel . ')</div></div>');
                            }),

                        // Sales Stats
                        SC\Section::make(fn () => new HtmlString('Sales <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-500/20 text-green-400 ring-1 ring-inset ring-green-500/30">LIVE</span>'))
                            ->icon('heroicon-o-chart-bar')
                            ->compact()
                            ->visible(fn (?Event $record) => $record && $record->exists)
                            ->schema([
                                Forms\Components\Placeholder::make('stats_overview')
                                    ->hiddenLabel()
                                    ->content(function (?Event $record) {
                                        if (!$record || !$record->exists) {
                                            return new HtmlString('<div class="text-sm text-gray-500">Save the event to see statistics.</div>');
                                        }

                                        $ticketsSold = $record->ticketTypes->sum('quota_sold') ?? 0;
                                        $calculatedRevenue = $record->ticketTypes->sum(fn ($tt) => ($tt->quota_sold ?? 0) * ($tt->display_price ?? $tt->price ?? 0));
                                        $totalRevenue = $calculatedRevenue ?? 0;
                                        $totalCapacity = $record->ticketTypes->sum(fn ($tt) => $tt->capacity ?? $tt->quota_total ?? 0) ?? 0;
                                        $percentSold = $totalCapacity > 0 ? round(($ticketsSold / $totalCapacity) * 100) : 0;

                                        $revenueFormatted = $totalRevenue >= 1000
                                            ? number_format($totalRevenue / 100000, 1) . 'K'
                                            : number_format($totalRevenue / 100, 0);

                                        $eventId = $record->id;
                                        $ticketCount = \App\Models\Ticket::where(fn ($q) => $q->where('event_id', $eventId)->orWhere('marketplace_event_id', $eventId))->count();
                                        $orderCount = \App\Models\Order::where(fn ($q) => $q
                                            ->where('event_id', $eventId)
                                            ->orWhereHas('tickets', fn ($tq) => $tq->where('event_id', $eventId)->orWhere('marketplace_event_id', $eventId))
                                        )->count();

                                        $btnClass = 'inline-flex items-center justify-center gap-1.5 px-3 py-1.5 text-sm font-semibold rounded-lg transition-colors no-underline';

                                        return new HtmlString("
                                            <div class='grid grid-cols-2 gap-3'>
                                                <div class='p-3 text-center bg-gray-800 rounded-lg'>
                                                    <div class='text-2xl font-bold text-white'>" . number_format($ticketsSold) . "</div>
                                                    <div class='text-xs text-gray-400'>Tickets</div>
                                                </div>
                                                <div class='p-3 text-center bg-gray-800 rounded-lg'>
                                                    <div class='text-2xl font-bold text-emerald-400'>{$revenueFormatted}</div>
                                                    <div class='text-xs text-gray-400'>Revenue (RON)</div>
                                                </div>
                                            </div>
                                            <div class='mt-3'>
                                                <div class='flex justify-between mb-1 text-xs text-gray-400'>
                                                    <span>Total capacity</span>
                                                    <span>" . number_format($ticketsSold) . " / " . number_format($totalCapacity) . " ({$percentSold}%)</span>
                                                </div>
                                                <div class='h-2 overflow-hidden bg-gray-700 rounded-full'>
                                                    <div class='h-full transition-all rounded-full bg-gradient-to-r from-primary-500 to-primary-400' style='width: {$percentSold}%'></div>
                                                </div>
                                            </div>
                                            <div class='grid grid-cols-2 gap-2 pt-3 mt-4 border-t border-gray-700'>
                                                <a href='" . route('filament.admin.resources.tickets.index', ['tableFilters[event_id][value]' => $eventId]) . "' class='{$btnClass} text-gray-200 bg-gray-700 hover:bg-gray-600'>
                                                    <svg class='w-3.5 h-3.5' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z'/></svg>
                                                    Tickets" . ($ticketCount > 0 ? " ({$ticketCount})" : '') . "
                                                </a>
                                                <a href='" . route('filament.admin.resources.orders.index', ['tableFilters[event_id][value]' => $eventId]) . "' class='{$btnClass} text-gray-200 bg-gray-700 hover:bg-gray-600'>
                                                    <svg class='w-3.5 h-3.5' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z'/></svg>
                                                    Orders" . ($orderCount > 0 ? " ({$orderCount})" : '') . "
                                                </a>
                                            </div>
                                        ");
                                    }),
                            ]),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->getStateUsing(fn (Event $record) => $record->getTranslation('title', 'en') ?: $record->getTranslation('title', 'ro') ?: collect($record->title)->first())
                    ->searchable(query: fn (Builder $query, string $search) => \App\Support\SearchHelper::searchTranslatable($query, 'title', $search))
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderByRaw(
                        DB::getDriverName() === 'pgsql'
                            ? "title->>'en' {$direction}"
                            : "JSON_UNQUOTE(JSON_EXTRACT(title, '$.en')) {$direction}"
                    ))
                    ->limit(40)
                    ->url(fn (Event $record) => static::getUrl('edit', ['record' => $record])),

                Tables\Columns\TextColumn::make('tenant_display')
                    ->label('Tenant / Marketplace')
                    ->getStateUsing(function (Event $record) {
                        return $record->tenant?->public_name
                            ?? $record->tenant?->name
                            ?? $record->marketplaceClient?->name
                            ?? '-';
                    })
                    ->description(function (Event $record) {
                        if ($record->marketplace_client_id) return 'Marketplace';
                        if ($record->tenant_id) return 'Tenant';
                        return null;
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('event_date')
                    ->label('Event Date')
                    ->getStateUsing(function (Event $record) {
                        if ($record->duration_mode === 'range') {
                            $start = $record->range_start_date;
                            $end = $record->range_end_date;
                            if ($start && $end) {
                                if ($start->format('m Y') === $end->format('m Y')) {
                                    return $start->format('d') . '-' . $end->format('d M Y');
                                }
                                return $start->format('d M') . ' - ' . $end->format('d M Y');
                            }
                            return $start?->format('d M Y') ?? '-';
                        }
                        if ($record->duration_mode === 'multi_day' && !empty($record->multi_slots)) {
                            $slots = collect($record->multi_slots)->pluck('date')->filter()->sort();
                            if ($slots->count() > 1) {
                                $first = Carbon::parse($slots->first());
                                $last = Carbon::parse($slots->last());
                                return $first->format('d M') . ' - ' . $last->format('d M Y');
                            }
                            return $slots->count() ? Carbon::parse($slots->first())->format('d M Y') : '-';
                        }
                        return $record->event_date?->format('d M Y')
                            ?? $record->range_start_date?->format('d M Y')
                            ?? '-';
                    })
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderByRaw("COALESCE(event_date, range_start_date) {$direction}"))
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

                Tables\Columns\IconColumn::make('is_published')
                    ->boolean()
                    ->label('Published')
                    ->trueIcon('heroicon-o-eye')
                    ->falseIcon('heroicon-o-eye-slash')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_cancelled')
                    ->boolean()
                    ->label('Cancelled')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_sold_out')
                    ->boolean()
                    ->label('Sold Out')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\BadgeColumn::make('status_display')
                    ->label('Status')
                    ->getStateUsing(function (Event $record) {
                        $endDate = match ($record->duration_mode) {
                            'range' => $record->range_end_date ?? $record->range_start_date,
                            'multi_day' => !empty($record->multi_slots)
                                ? (($last = collect($record->multi_slots)->pluck('date')->filter()->sort()->last()) ? Carbon::parse($last) : null)
                                : null,
                            default => $record->event_date,
                        };
                        if ($record->is_cancelled) return 'Cancelled';
                        if (!$endDate) return 'No Date';
                        return $endDate->isPast() ? 'Ended' : 'Active';
                    })
                    ->colors([
                        'success' => 'Active',
                        'gray' => 'Ended',
                        'danger' => 'Cancelled',
                        'warning' => 'No Date',
                    ])
                    ->sortable(false),

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
                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('Published')
                    ->placeholder('All')
                    ->trueLabel('Published')
                    ->falseLabel('Unpublished'),
                Tables\Filters\TernaryFilter::make('is_cancelled')
                    ->label('Cancelled'),
                Tables\Filters\TernaryFilter::make('is_sold_out')
                    ->label('Sold Out'),
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload(),
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
            'import' => ImportEvents::route('/import'),
        ];
    }
}

<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\EventResource\Pages;
use App\Filament\Marketplace\Resources\ArtistResource;
use App\Filament\Marketplace\Resources\VenueResource;
use App\Models\Event;
use App\Models\EventGenre;
use App\Models\EventType;
use App\Models\MarketplaceCity;
use App\Models\MarketplaceEventCategory;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceRegion;
use App\Models\Tax\GeneralTax;
use App\Models\Venue;
use Filament\Forms;
use Illuminate\Support\HtmlString;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Utilities\Get as SGet;
use Filament\Schemas\Components\Utilities\Set as SSet;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class EventResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = Event::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-calendar';
    protected static \UnitEnum|string|null $navigationGroup = null;
    protected static ?int $navigationSort = 2;

    /**
     * Navigation badge showing pending events count
     */
    public static function getNavigationBadge(): ?string
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) {
            return null;
        }

        $pendingCount = static::getEloquentQuery()
            ->where('status', 'pending')
            ->count();

        return $pendingCount > 0 ? (string) $pendingCount : null;
    }

    /**
     * Navigation badge color
     */
    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * Navigation badge tooltip
     */
    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pending events awaiting approval';
    }

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();

        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $marketplace?->id);
    }

    public static function form(Schema $schema): Schema
    {
        $today = Carbon::today();
        $marketplace = static::getMarketplaceClient();

        // Get tenant's language (check both 'language' and 'locale' columns)
        $marketplaceLanguage = $marketplace->language ?? $marketplace->locale ?? 'en';

        return $schema->schema([
            // Hidden marketplace_client_id field
            Forms\Components\Hidden::make('marketplace_client_id')
                ->default($marketplace?->id),

            // Organizer selector - marketplace selects which organizer owns this event
            SC\Section::make('Organizer')
                ->description('Select the organizer who will own this event')
                ->schema([
                    Forms\Components\Select::make('marketplace_organizer_id')
                        ->label('Organizer')
                        ->options(function () use ($marketplace) {
                            return MarketplaceOrganizer::query()
                                ->where('marketplace_client_id', $marketplace?->id)
                                ->where('status', 'active')
                                ->orderBy('name')
                                ->pluck('name', 'id');
                        })
                        ->required()
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function ($state, SSet $set) use ($marketplace, $marketplaceLanguage) {
                            // When organizer changes, update commission info and ticket terms
                            if ($state) {
                                $organizer = MarketplaceOrganizer::find($state);
                                if ($organizer) {
                                    // Pre-fill custom commission with organizer's rate if set
                                    $rate = $organizer->commission_rate ?? $marketplace?->commission_rate;
                                    $set('commission_rate', $rate);

                                    // Pre-fill ticket terms from organizer if available
                                    if ($organizer->ticket_terms) {
                                        $set("ticket_terms.{$marketplaceLanguage}", $organizer->ticket_terms);
                                    }
                                }
                            }
                        })
                        ->helperText('The selected organizer will receive payouts for this event')
                        ->prefixIcon('heroicon-m-building-office-2'),

                    Forms\Components\Placeholder::make('organizer_info')
                        ->label('Organizer Details')
                        ->visible(fn (SGet $get) => (bool) $get('marketplace_organizer_id'))
                        ->content(function (SGet $get) use ($marketplace) {
                            $organizerId = $get('marketplace_organizer_id');
                            if (!$organizerId) return '';

                            $organizer = MarketplaceOrganizer::find($organizerId);
                            if (!$organizer) return '';

                            $status = match($organizer->status) {
                                'active' => '<span class="text-green-600">Active</span>',
                                'pending' => '<span class="text-yellow-600">Pending</span>',
                                'suspended' => '<span class="text-red-600">Suspended</span>',
                                default => $organizer->status,
                            };

                            $verified = $organizer->verified_at
                                ? '<span class="text-green-600">✓ Verified</span>'
                                : '<span class="text-gray-500">Not verified</span>';

                            $commissionRate = $organizer->commission_rate ?? $marketplace?->commission_rate ?? 5;

                            return new HtmlString("
                                <div class='space-y-1 text-sm'>
                                    <div><strong>Email:</strong> {$organizer->email}</div>
                                    <div><strong>Status:</strong> {$status} | {$verified}</div>
                                    <div><strong>Default Commission:</strong> {$commissionRate}%</div>
                                    <div><strong>Events:</strong> {$organizer->total_events} | <strong>Revenue:</strong> " . number_format($organizer->total_revenue, 2) . " RON</div>
                                </div>
                            ");
                        }),
                ])->columns(2),

            // BASICS - Single Language based on Tenant setting
            SC\Section::make('Event Details')
                ->schema([
                    SC\Group::make()
                        ->schema([
                            Forms\Components\TextInput::make("title.{$marketplaceLanguage}")
                                ->label($marketplaceLanguage === 'ro' ? 'Titlu eveniment' : 'Event title')
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

            // FEATURED SETTINGS (Marketplace only)
            SC\Section::make('Featured Settings')
                ->description('Control where this event appears as featured on the marketplace website')
                ->schema([
                    SC\Grid::make(3)->schema([
                        Forms\Components\Toggle::make('is_homepage_featured')
                            ->label('Homepage Featured')
                            ->helperText('Show on homepage hero/featured section')
                            ->onIcon('heroicon-m-home')
                            ->offIcon('heroicon-m-home'),
                        Forms\Components\Toggle::make('is_general_featured')
                            ->label('General Featured')
                            ->helperText('Show in general featured events lists')
                            ->onIcon('heroicon-m-star')
                            ->offIcon('heroicon-m-star'),
                        Forms\Components\Toggle::make('is_category_featured')
                            ->label('Category Featured')
                            ->helperText('Show as featured in its category page')
                            ->onIcon('heroicon-m-tag')
                            ->offIcon('heroicon-m-tag'),
                    ]),
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
                        ->options(function () use ($marketplace) {
                            return Venue::query()
                                ->where(fn($q) => $q
                                    ->whereNull('marketplace_client_id')
                                    ->orWhere('marketplace_client_id', $marketplace?->id))
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn ($venue) => [
                                    $venue->id => $venue->getTranslation('name', app()->getLocale())
                                        . ($venue->city ? ' (' . $venue->city . ')' : '')
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
                        ->suffixAction(
                            Action::make('create_venue')
                                ->icon('heroicon-o-plus-circle')
                                ->tooltip('Adaugă locație nouă')
                                ->url(fn () => VenueResource::getUrl('create'))
                                ->openUrlInNewTab()
                        )
                        ->nullable(),
                    Forms\Components\Select::make('marketplace_city_id')
                        ->label('City')
                        ->options(function () use ($marketplace, $marketplaceLanguage) {
                            return MarketplaceCity::query()
                                ->where('marketplace_client_id', $marketplace?->id)
                                ->where('is_visible', true)
                                ->with('region')
                                ->orderBy('sort_order')
                                ->get()
                                ->mapWithKeys(fn ($city) => [
                                    $city->id => ($city->region ? ($city->region->name[$marketplaceLanguage] ?? $city->region->name['ro'] ?? '') . ' > ' : '')
                                        . ($city->name[$marketplaceLanguage] ?? $city->name['ro'] ?? 'Unnamed')
                                ]);
                        })
                        ->searchable()
                        ->preload()
                        ->placeholder('Select a city')
                        ->helperText('Filter events by city on the website')
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
                    Forms\Components\Textarea::make("short_description.{$marketplaceLanguage}")
                        ->label($marketplaceLanguage === 'ro' ? 'Descriere scurtă' : 'Short description')
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\RichEditor::make("description.{$marketplaceLanguage}")
                        ->label($marketplaceLanguage === 'ro' ? 'Descriere' : 'Description')
                        ->columnSpanFull()
                        ->fileAttachmentsDisk('public')
                        ->fileAttachmentsDirectory('event-descriptions')
                        ->fileAttachmentsVisibility('public'),
                    Forms\Components\RichEditor::make("ticket_terms.{$marketplaceLanguage}")
                        ->label($marketplaceLanguage === 'ro' ? 'Termeni bilete' : 'Ticket terms')
                        ->columnSpanFull()
                        ->default($marketplace?->ticket_terms ?? null),
                ])->columns(1),

            // TAXONOMIES
            SC\Section::make('Taxonomies & Relations')
                ->schema([
                    Forms\Components\Select::make('marketplace_event_category_id')
                        ->label('Event Category')
                        ->options(function () use ($marketplace, $marketplaceLanguage) {
                            return MarketplaceEventCategory::query()
                                ->where('marketplace_client_id', $marketplace?->id)
                                ->where('is_visible', true)
                                ->with('parent')
                                ->orderBy('sort_order')
                                ->get()
                                ->mapWithKeys(fn ($cat) => [
                                    $cat->id => ($cat->icon_emoji ? $cat->icon_emoji . ' ' : '')
                                        . ($cat->parent ? ($cat->parent->name[$marketplaceLanguage] ?? $cat->parent->name['ro'] ?? '') . ' > ' : '')
                                        . ($cat->name[$marketplaceLanguage] ?? $cat->name['ro'] ?? 'Unnamed')
                                ]);
                        })
                        ->searchable()
                        ->preload()
                        ->placeholder('Select a category')
                        ->helperText('Custom marketplace event category')
                        ->nullable(),

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
                        ->searchable()
                        ->suffixAction(
                            Action::make('create_artist')
                                ->icon('heroicon-o-plus-circle')
                                ->tooltip('Adaugă artist nou')
                                ->url(fn () => ArtistResource::getUrl('create'))
                                ->openUrlInNewTab()
                        ),

                    Forms\Components\Select::make('tags')
                        ->label('Event tags')
                        ->relationship('tags', 'name')
                        ->multiple()
                        ->preload()
                        ->searchable(),

                    // Dynamic tax display based on selected event types
                    Forms\Components\Placeholder::make('applicable_taxes')
                        ->label('Taxe aplicabile')
                        ->columnSpanFull()
                        ->visible(fn (SGet $get) => !empty($get('eventTypes')))
                        ->content(function (SGet $get) use ($marketplace) {
                            $eventTypeIds = (array) ($get('eventTypes') ?? []);
                            if (empty($eventTypeIds)) {
                                return '';
                            }

                            $isVatPayer = $marketplace?->vat_payer ?? false;
                            $taxDisplayMode = $marketplace?->tax_display_mode ?? 'included';

                            // Get applicable taxes using the new forEventTypes scope
                            $allTaxes = GeneralTax::query()
                                ->whereNull('tenant_id') // Global taxes only (not tenant-specific)
                                ->active()
                                ->validOn(\Carbon\Carbon::today())
                                ->forEventTypes($eventTypeIds)
                                ->orderByDesc('priority')
                                ->get()
                                ->unique('id');

                            if ($allTaxes->isEmpty()) {
                                return new HtmlString('<div class="text-sm italic text-gray-500">Nu există taxe configurate pentru tipul de eveniment selectat.</div>');
                            }

                            $html = '<div class="space-y-2">';

                            // VAT payer status and tax display mode
                            $vatBadge = $isVatPayer
                                ? '<span class="inline-flex items-center px-2 py-1 text-xs font-medium text-green-800 bg-green-100 rounded-full dark:bg-green-900 dark:text-green-200">Plătitor TVA</span>'
                                : '<span class="inline-flex items-center px-2 py-1 text-xs font-medium text-gray-600 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-300">Neplătitor TVA</span>';

                            $modeBadge = $taxDisplayMode === 'added'
                                ? '<span class="inline-flex items-center px-2 py-1 ml-2 text-xs font-medium rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">Taxe adăugate la preț</span>'
                                : '<span class="inline-flex items-center px-2 py-1 ml-2 text-xs font-medium text-blue-800 bg-blue-100 rounded-full dark:bg-blue-900 dark:text-blue-200">Taxe incluse în preț</span>';

                            $html .= '<div class="flex flex-wrap items-center gap-2 mb-3">' . $vatBadge . $modeBadge . '</div>';

                            $html .= '<div class="grid grid-cols-1 gap-2 md:grid-cols-2">';

                            foreach ($allTaxes as $tax) {
                                $isVatTax = str_contains(strtolower($tax->name ?? ''), 'tva') ||
                                            str_contains(strtolower($tax->name ?? ''), 'vat');

                                // Skip VAT if tenant is not a VAT payer
                                if ($isVatTax && !$isVatPayer) {
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

                                $html .= '<div class="flex items-center justify-between p-2 border border-gray-200 rounded-lg bg-gray-50 dark:bg-gray-800 dark:border-gray-700">';
                                $html .= '<div class="flex items-center gap-2">';
                                $html .= $iconHtml;
                                $html .= '<span class="text-sm font-medium text-gray-900 dark:text-white">' . e($tax->name) . '</span>';
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
                ])->columns(2),

            // TARGET PRICE (Marketplace Admin only)
            SC\Section::make('Target Price')
                ->description('Set a target price reference for this event (internal use only)')
                ->schema([
                    Forms\Components\TextInput::make('target_price')
                        ->label('Target Price')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->suffix($marketplace?->currency ?? 'RON')
                        ->placeholder('e.g. 100.00')
                        ->helperText('Reference price for planning and negotiations. Not displayed publicly.')
                        ->columnSpan(1),
                ])->columns(2),

            // TICKETS
            SC\Section::make('Tickets')
                ->schema([
                    // Ticket Template selector
                    Forms\Components\Select::make('ticket_template_id')
                        ->label('Ticket Template')
                        ->relationship(
                            name: 'ticketTemplate',
                            modifyQueryUsing: fn (Builder $query) => $query
                                ->where('marketplace_client_id', static::getMarketplaceClient()?->id)
                                ->where('status', 'active')
                                ->orderBy('name')
                        )
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->name . ($record->is_default ? ' (Default)' : ''))
                        ->placeholder('Use default template')
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Select a template for tickets generated for this event. Leave empty to use the default template.')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->visible(fn () => static::getMarketplaceClient()?->microservices()
                            ->where('slug', 'ticket-customizer')
                            ->wherePivot('is_active', true)
                            ->exists() ?? false),

                    // Commission Mode and Rate for event
                    SC\Grid::make(3)->schema([
                        Forms\Components\Select::make('commission_mode')
                            ->label('Commission Mode')
                            ->options([
                                'included' => 'Include in price (organizer receives less)',
                                'added_on_top' => 'Add on top (customer pays more)',
                            ])
                            ->placeholder('Use default from contract')
                            ->helperText(function () use ($marketplace) {
                                $mode = $marketplace->commission_mode ?? 'included';
                                $modeText = $mode === 'included'
                                    ? 'included in price'
                                    : 'added on top';
                                return "Default: {$modeText}";
                            })
                            ->live()
                            ->nullable(),

                        Forms\Components\TextInput::make('commission_rate')
                            ->label('Custom Commission Rate (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(50)
                            ->step(0.5)
                            ->suffix('%')
                            ->placeholder(function (SGet $get) use ($marketplace) {
                                $organizerId = $get('marketplace_organizer_id');
                                if ($organizerId) {
                                    $organizer = MarketplaceOrganizer::find($organizerId);
                                    if ($organizer && $organizer->commission_rate !== null) {
                                        return $organizer->commission_rate . '% (organizer default)';
                                    }
                                }
                                return ($marketplace->commission_rate ?? 5) . '% (marketplace default)';
                            })
                            ->helperText('Leave empty to use organizer\'s or marketplace default rate')
                            ->live()
                            ->nullable(),

                        Forms\Components\Placeholder::make('commission_example')
                            ->label('Example (100 RON ticket)')
                            ->live()
                            ->content(function (SGet $get) use ($marketplace) {
                                $eventMode = $get('commission_mode');
                                $mode = $eventMode ?: ($marketplace->commission_mode ?? 'included');

                                // Get effective commission rate: event custom > organizer > marketplace
                                $eventRate = $get('commission_rate');
                                if ($eventRate !== null && $eventRate !== '') {
                                    $rate = (float) $eventRate;
                                } else {
                                    $organizerId = $get('marketplace_organizer_id');
                                    if ($organizerId) {
                                        $organizer = MarketplaceOrganizer::find($organizerId);
                                        $rate = $organizer?->commission_rate ?? $marketplace->commission_rate ?? 5.00;
                                    } else {
                                        $rate = $marketplace->commission_rate ?? 5.00;
                                    }
                                }

                                $ticketPrice = 100;
                                $commission = round($ticketPrice * ($rate / 100), 2);

                                if ($mode === 'included') {
                                    $revenue = $ticketPrice - $commission;
                                    return "Customer pays: **{$ticketPrice} RON** → Organizer receives: **{$revenue} RON** (commission: {$commission} RON @ {$rate}%)";
                                } else {
                                    $total = $ticketPrice + $commission;
                                    return "Customer pays: **{$total} RON** → Organizer receives: **{$ticketPrice} RON** (commission: {$commission} RON @ {$rate}%)";
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
                                    ->default($marketplace?->currency ?? 'RON')
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
                                    ->formatStateUsing(function ($state, SGet $get) {
                                        // Calculate discount % on form load based on price_max and price
                                        if ($state !== null && $state !== '') {
                                            return $state;
                                        }
                                        $priceMax = (float) ($get('price_max') ?: 0);
                                        $salePrice = $get('price');
                                        if ($priceMax > 0 && $salePrice !== null && $salePrice !== '') {
                                            $sale = (float) $salePrice;
                                            return round((1 - ($sale / $priceMax)) * 100, 2);
                                        }
                                        return null;
                                    })
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
                                ->content(function (SGet $get) use ($marketplace) {
                                    $price = (float) ($get('price') ?: $get('price_max') ?: 0);
                                    if ($price <= 0) {
                                        return 'Enter a price to see commission calculation.';
                                    }

                                    $eventMode = $get('../../commission_mode');
                                    $mode = $eventMode ?: ($marketplace->commission_mode ?? 'included');

                                    // Get effective commission rate: event custom > organizer > marketplace
                                    $eventRate = $get('../../commission_rate');
                                    if ($eventRate !== null && $eventRate !== '') {
                                        $rate = (float) $eventRate;
                                    } else {
                                        $organizerId = $get('../../marketplace_organizer_id');
                                        if ($organizerId) {
                                            $organizer = MarketplaceOrganizer::find($organizerId);
                                            $rate = $organizer?->commission_rate ?? $marketplace->commission_rate ?? 5.00;
                                        } else {
                                            $rate = $marketplace->commission_rate ?? 5.00;
                                        }
                                    }

                                    $commission = round($price * ($rate / 100), 2);
                                    $currency = $get('currency') ?: 'RON';
                                    $marketplaceName = $marketplace->name ?? 'Marketplace';

                                    if ($mode === 'included') {
                                        $revenue = round($price - $commission, 2);
                                        return "Customer pays: **{$price} {$currency}** → Organizer receives: **{$revenue} {$currency}** → {$marketplaceName} receives: **{$commission} {$currency}** @ {$rate}%";
                                    } else {
                                        $total = round($price + $commission, 2);
                                        return "Customer pays: **{$total} {$currency}** → Organizer receives: **{$price} {$currency}** → {$marketplaceName} receives: **{$commission} {$currency}** @ {$rate}%";
                                    }
                                })
                                ->columnSpan(12),

                            SC\Grid::make(3)->schema([
                                Forms\Components\TextInput::make('capacity')
                                    ->label('Capacity')
                                    ->placeholder('Leave empty for unlimited')
                                    ->numeric()
                                    ->minValue(0)
                                    ->nullable(),
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
                                ->default([])
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
                                ->columnSpan(4),

                            // Scheduling fields - shown when ticket is NOT active
                            Forms\Components\DateTimePicker::make('scheduled_at')
                                ->label('Schedule Activation')
                                ->helperText('When this ticket type should automatically become active')
                                ->native(false)
                                ->seconds(false)
                                ->displayFormat('Y-m-d H:i')
                                ->minDate(now())
                                ->visible(fn (SGet $get) => !$get('is_active'))
                                ->columnSpan(4),

                            Forms\Components\Toggle::make('autostart_when_previous_sold_out')
                                ->label('Autostart when previous sold out')
                                ->helperText('Activate automatically when previous ticket types reach 0 capacity')
                                ->visible(fn (SGet $get) => !$get('is_active'))
                                ->columnSpan(4),
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
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Select templates to add keys. Values will be pre-filled from event data where available.')
                        ->live()
                        ->afterStateUpdated(function ($state, SSet $set, SGet $get) use ($marketplaceLanguage, $marketplace) {
                            $seo = (array) ($get('seo') ?? []);

                            // Get event data for auto-fill
                            $title = $get("title.{$marketplaceLanguage}") ?? '';
                            $slug = $get('slug') ?? '';
                            $description = $get("short_description.{$marketplaceLanguage}") ?? $get("description.{$marketplaceLanguage}") ?? '';
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
                                    $venueName = $venue->getTranslation('name', $marketplaceLanguage) ?? $venue->name ?? '';
                                    $venueAddress = $venue->address ?? '';
                                }
                            }

                            // Get marketplace's website URL (MarketplaceClient doesn't have domains like Tenant)
                            $baseUrl = $marketplace?->website ?? '';
                            // Ensure it has https:// prefix
                            if ($baseUrl && ! str_starts_with($baseUrl, 'http://') && ! str_starts_with($baseUrl, 'https://')) {
                                $baseUrl = 'https://' . $baseUrl;
                            }

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
                                    'og:locale'        => $marketplaceLanguage === 'ro' ? 'ro_RO' : 'en_US',
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
                                    'og:site_name'     => $marketplace?->public_name ?? $marketplace?->name ?? '',
                                ],
                                'article' => [
                                    'article:author'         => $marketplace?->public_name ?? '',
                                    'article:section'        => 'Events',
                                    'article:tag'            => '',
                                    'article:published_time' => $now,
                                    'article:modified_time'  => $now,
                                ],
                                'product' => [
                                    'product:price:amount'   => '',
                                    'product:price:currency' => $marketplace?->currency ?? 'RON',
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
                                            'name'  => $marketplace?->public_name ?? $marketplace?->name ?? '',
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
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Add custom SEO meta tags. Use templates above to quickly add common sets.'),
                ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        $marketplace = static::getMarketplaceClient();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('marketplaceOrganizer.name')
                    ->label('Organizer')
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
                Tables\Filters\SelectFilter::make('marketplace_organizer_id')
                    ->label('Organizer')
                    ->relationship('marketplaceOrganizer', 'name'),
            ])
            ->actions([
                Action::make('statistics')
                    ->label('Statistics')
                    ->icon('heroicon-o-chart-bar')
                    ->color('info')
                    ->url(fn (Event $record) => static::getUrl('statistics', ['record' => $record])),
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil')
                    ->url(fn (Event $record) => static::getUrl('edit', ['record' => $record])),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->recordUrl(fn (Event $record) => static::getUrl('edit', ['record' => $record]))
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
            'statistics' => Pages\EventStatistics::route('/{record}/statistics'),
            'view-guest' => Pages\ViewGuestEvent::route('/{record}/view'),
        ];
    }
}

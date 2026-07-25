<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\EventResource\Pages;
use App\Models\Event;
use App\Models\EventGenre;
use App\Models\EventType;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationLabel = 'Evenimentele mele';
    protected static \UnitEnum|string|null $navigationGroup = null;
    protected static ?int $navigationSort = 2;

    /**
     * Hide "My Events" from sidebar for leisure tenants — they don't run
     * events, they run a physical location. The Event row still exists in
     * the DB (we'll surface it through a Location-style multi-tab page in a
     * follow-up), so this only affects the sidebar.
     */
    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()?->tenant;
        $type = $tenant?->tenant_type instanceof \App\Enums\TenantType
            ? $tenant->tenant_type->value
            : (string) $tenant?->tenant_type;
        return $type !== 'leisure';
    }

    /**
     * Navigation badge showing hosted events count
     */
    public static function getNavigationBadge(): ?string
    {
        $tenant = auth()->user()?->tenant;
        if (!$tenant || !$tenant->ownsVenues()) {
            return null;
        }

        $hostedCount = $tenant->hostedEvents()->count();
        return $hostedCount > 0 ? (string) $hostedCount : null;
    }

    /**
     * Navigation badge color
     */
    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    /**
     * Navigation badge tooltip
     */
    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Hosted events at your venues';
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        $tenantId = $tenant?->id;

        // Get IDs of venues owned by this tenant
        $ownedVenueIds = \App\Models\Venue::where('tenant_id', $tenantId)->pluck('id')->toArray();

        return parent::getEloquentQuery()
            ->where(function ($query) use ($tenantId, $ownedVenueIds) {
                // Own events
                $query->where('tenant_id', $tenantId)
                    // OR events happening at owned venues (guest events)
                    ->orWhereIn('venue_id', $ownedVenueIds);
            });
    }

    /**
     * Check if an event is a guest event (not owned by current tenant but at their venue)
     */
    public static function isGuestEvent(Event $event): bool
    {
        $tenant = auth()->user()->tenant;
        return $event->tenant_id !== $tenant?->id;
    }

    protected static array $eventTicketCountsCache = [];
    protected static array $eventActiveNonIndepCache = [];

    protected static function ticketTypeCounts(mixed $ticketTypeId, mixed $eventId): array
    {
        $eventId = is_numeric($eventId) ? (int) $eventId : null;
        $ticketTypeId = is_numeric($ticketTypeId) ? (int) $ticketTypeId : null;
        $empty = ['active' => 0, 'cancelled' => 0];
        if (! $eventId || ! $ticketTypeId) {
            return $empty;
        }
        if (! array_key_exists($eventId, static::$eventTicketCountsCache)) {
            $typeIds = \App\Models\TicketType::where('event_id', $eventId)->pluck('id');
            $map = [];
            if ($typeIds->isNotEmpty()) {
                $rows = \App\Models\Ticket::whereIn('ticket_type_id', $typeIds)
                    ->selectRaw('ticket_type_id, status, count(*) as c')
                    ->groupBy('ticket_type_id', 'status')
                    ->get();
                foreach ($rows as $row) {
                    $tid = (int) $row->ticket_type_id;
                    if (! isset($map[$tid])) {
                        $map[$tid] = ['active' => 0, 'cancelled' => 0];
                    }
                    if (in_array($row->status, ['valid', 'used'], true)) {
                        $map[$tid]['active'] += (int) $row->c;
                    } elseif (in_array($row->status, ['cancelled', 'refunded'], true)) {
                        $map[$tid]['cancelled'] += (int) $row->c;
                    }
                }
            }
            static::$eventTicketCountsCache[$eventId] = $map;
        }
        return static::$eventTicketCountsCache[$eventId][$ticketTypeId] ?? $empty;
    }

    protected static function activeNonIndepCount(mixed $eventId): int
    {
        $eventId = is_numeric($eventId) ? (int) $eventId : null;
        if (! $eventId) {
            return 0;
        }
        if (! array_key_exists($eventId, static::$eventActiveNonIndepCache)) {
            $nonIndepIds = \App\Models\TicketType::where('event_id', $eventId)
                ->where('is_independent_stock', false)
                ->pluck('id');
            static::$eventActiveNonIndepCache[$eventId] = $nonIndepIds->isEmpty() ? 0
                : \App\Models\Ticket::whereIn('ticket_type_id', $nonIndepIds)
                    ->whereIn('status', ['valid', 'used'])
                    ->count();
        }
        return static::$eventActiveNonIndepCache[$eventId];
    }

    public static function form(Schema $schema): Schema
    {
        $today = Carbon::today();
        $tenant = auth()->user()->tenant;
        $il = false; // inline labels off (matches marketplace ticket repeater)

        // Get tenant's language (check both 'language' and 'locale' columns)
        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';

        // Theater vertical: artist names for autocomplete + whether to show theater fields
        $isTheater = (bool) ($tenant?->isTheater());
        $artistNames = $isTheater && $tenant
            ? \App\Models\TenantArtist::where('tenant_id', $tenant->id)->orderBy('name')->pluck('name')->filter()->values()->all()
            : [];

        // Public base URL of the tenant (for preview / test links in the sidebar)
        $publicBase = null;
        if ($tenant) {
            $td = optional($tenant->domains()->where('is_active', true)->orderByDesc('is_primary')->first())->domain
                ?? $tenant->domain ?? null;
            if ($td) {
                $publicBase = rtrim(str_starts_with($td, 'http') ? $td : 'https://' . $td, '/');
            }
        }

        return $schema->schema([
            // Hidden tenant_id field
            Forms\Components\Hidden::make('tenant_id')
                ->default($tenant?->id),

            SC\Grid::make(4)->schema([
            SC\Group::make()->columnSpan(3)->schema([

            SC\Tabs::make('EventTabs')
                ->persistTabInQueryString()
                ->columnSpanFull()
                ->tabs([

            SC\Tabs\Tab::make('Detalii')->icon('heroicon-o-information-circle')->schema([

            // BASICS - Single Language based on Tenant setting
            SC\Section::make('Detalii eveniment')
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

                    // Subtitlu — doar pentru tenant de tip teatru
                    Forms\Components\TextInput::make("subtitle.{$tenantLanguage}")
                        ->label('Subtitlu')
                        ->placeholder('ex: dupa William Shakespeare')
                        ->maxLength(190)
                        ->visible($isTheater)
                        ->columnSpanFull(),
                ]),

            // FLAGS
            SC\Section::make('Stări')
                ->schema([
                    SC\Grid::make(5)->schema([
                        Forms\Components\Toggle::make('is_sold_out')
                            ->label('Epuizat')
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
                            ->label('Doar vânzare la ușă')
                            ->onIcon('heroicon-m-key')
                            ->offIcon('heroicon-m-key')
                            ->disabled(fn (SGet $get) => (bool) $get('is_cancelled')),
                        Forms\Components\Toggle::make('is_cancelled')
                            ->label('Anulat')
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
                            ->label('Amânat')
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
                            ->label('Promovat')
                            ->onIcon('heroicon-m-sparkles')
                            ->offIcon('heroicon-m-sparkles')
                            ->live()
                            ->afterStateUpdated(function ($state, SSet $set) {
                                if (!$state) $set('promoted_until', null);
                            })
                            ->disabled(fn (SGet $get) => (bool) $get('is_cancelled')),
                    ]),

                    Forms\Components\Textarea::make('cancel_reason')
                        ->label('Motiv anulare')
                        ->rows(2)
                        ->visible(fn (SGet $get) => (bool) $get('is_cancelled')),

                    SC\Grid::make(4)->schema([
                        Forms\Components\DatePicker::make('postponed_date')
                            ->label('Dată nouă')
                            ->minDate($today)
                            ->native(false),
                        Forms\Components\TimePicker::make('postponed_start_time')
                            ->label('Ora de început')
                            ->seconds(false)
                            ->native(true),
                        Forms\Components\TimePicker::make('postponed_door_time')
                            ->label('Ora deschiderii')
                            ->seconds(false)
                            ->native(true),
                        Forms\Components\TimePicker::make('postponed_end_time')
                            ->label('Ora de sfârșit')
                            ->seconds(false)
                            ->native(true),
                    ])->visible(fn (SGet $get) => (bool) $get('is_postponed')),

                    Forms\Components\Textarea::make('postponed_reason')
                        ->label('Motiv amânare')
                        ->rows(2)
                        ->visible(fn (SGet $get) => (bool) $get('is_postponed')),

                    Forms\Components\DatePicker::make('promoted_until')
                        ->label('Promovat până la')
                        ->minDate($today)
                        ->native(false)
                        ->visible(fn (SGet $get) => (bool) $get('is_promoted')),
                ])->columns(1),

            ]),

            SC\Tabs\Tab::make('Program')->icon('heroicon-o-calendar-days')->schema([

            // SCHEDULE
            SC\Section::make('Program')
                ->schema([
                    Forms\Components\Radio::make('duration_mode')
                        ->label('Durată')
                        ->options([
                            'single_day' => 'O singură zi',
                            'range' => 'Interval',
                            'multi_day' => 'Mai multe zile',
                            'recurring' => 'Recurent',
                        ])
                        ->inline()
                        ->default('single_day')
                        ->required()
                        ->live(),

                    // Single day
                    SC\Grid::make(4)->schema([
                        Forms\Components\DatePicker::make('event_date')
                            ->label('Dată')
                            ->minDate($today)
                            ->native(false),
                        Forms\Components\TimePicker::make('start_time')
                            ->label('Ora de început')
                            ->seconds(false)
                            ->native(true)
                            ->required(fn (SGet $get) => $get('duration_mode') === 'single_day'),
                        Forms\Components\TimePicker::make('door_time')
                            ->label('Ora deschiderii')
                            ->seconds(false)
                            ->native(true),
                        Forms\Components\TimePicker::make('end_time')
                            ->label('Ora de sfârșit')
                            ->seconds(false)
                            ->native(true),
                    ])->visible(fn (SGet $get) => $get('duration_mode') === 'single_day'),

                    // Range
                    SC\Grid::make(4)->schema([
                        Forms\Components\DatePicker::make('range_start_date')
                            ->label('Data de început')
                            ->minDate($today)
                            ->native(false),
                        Forms\Components\DatePicker::make('range_end_date')
                            ->label('Data de sfârșit')
                            ->native(false),
                        Forms\Components\TimePicker::make('range_start_time')
                            ->label('Ora de început')
                            ->seconds(false)
                            ->native(true),
                        Forms\Components\TimePicker::make('range_end_time')
                            ->label('Ora de sfârșit')
                            ->seconds(false)
                            ->native(true),
                    ])->visible(fn (SGet $get) => $get('duration_mode') === 'range'),

                    // Multi day
                    Forms\Components\Repeater::make('multi_slots')
                        ->label('Zile & ore')
                        ->schema([
                            Forms\Components\DatePicker::make('date')
                                ->label('Dată')
                                ->minDate($today)
                                ->native(false)
                                ->required(),
                            Forms\Components\TimePicker::make('start_time')
                                ->label('Început')
                                ->seconds(false)
                                ->native(true),
                            Forms\Components\TimePicker::make('door_time')
                                ->label('Uși')
                                ->seconds(false)
                                ->native(true),
                            Forms\Components\TimePicker::make('end_time')
                                ->label('Sfârșit')
                                ->seconds(false)
                                ->native(true),
                        ])
                        ->addActionLabel('Adaugă altă dată')
                        ->default([])
                        ->visible(fn (SGet $get) => $get('duration_mode') === 'multi_day')
                        ->columns(4),

                    // Recurring
                    SC\Group::make()
                        ->visible(fn (SGet $get) => $get('duration_mode') === 'recurring')
                        ->schema([
                            SC\Grid::make(4)->schema([
                                Forms\Components\DatePicker::make('recurring_start_date')
                                    ->label('Data inițială')
                                    ->minDate(now()->startOfDay())
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function ($state, SSet $set) {
                                        if (!$state) { $set('recurring_weekday', null); return; }
                                        $w = Carbon::parse($state)->dayOfWeekIso;
                                        $set('recurring_weekday', $w);
                                    }),
                                Forms\Components\TextInput::make('recurring_weekday')
                                    ->label('Zi a săptămânii')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->formatStateUsing(function (SGet $get) {
                                        $map = [1=>'Lun',2=>'Mar',3=>'Mie',4=>'Joi',5=>'Vin',6=>'Sâm',7=>'Dum'];
                                        return $map[$get('recurring_weekday')] ?? '';
                                    }),
                                Forms\Components\Select::make('recurring_frequency')
                                    ->label('Recurență')
                                    ->options([
                                        'weekly' => 'Săptămânal',
                                        'monthly_nth' => 'Lunar (a N-a zi din săptămână)',
                                    ])
                                    ->required()
                                    ->live(),
                                Forms\Components\TextInput::make('recurring_count')
                                    ->label('Repetări')
                                    ->numeric()
                                    ->minValue(1),
                            ]),
                            SC\Grid::make(2)
                                ->visible(fn (SGet $get) => $get('recurring_frequency') === 'monthly_nth')
                                ->schema([
                                    Forms\Components\Select::make('recurring_week_of_month')
                                        ->label('Săptămâna din lună')
                                        ->options([
                                            1 => 'Prima', 2 => 'A doua', 3 => 'A treia', 4 => 'A patra', -1 => 'Ultima',
                                        ])
                                        ->required(),
                                ]),
                            SC\Grid::make(3)->schema([
                                Forms\Components\TimePicker::make('recurring_start_time')
                                    ->label('Ora de început')
                                    ->seconds(false)->native(true)
                                    ->required(),
                                Forms\Components\TimePicker::make('recurring_door_time')
                                    ->label('Ora deschiderii')
                                    ->seconds(false)->native(true),
                                Forms\Components\TimePicker::make('recurring_end_time')
                                    ->label('Ora de sfârșit')
                                    ->seconds(false)->native(true),
                            ]),
                        ]),
                ])->columns(1),

            // LOCATION & LINKS
            SC\Section::make('Locație & Linkuri')
                ->schema([
                    Forms\Components\Select::make('venue_id')
                        ->label('Locație')
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
                        ->label('Adresă')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('website_url')
                        ->label('Site web')
                        ->url()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('facebook_url')
                        ->label('Eveniment Facebook')
                        ->url()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('event_website_url')
                        ->label('Site web eveniment')
                        ->url()
                        ->maxLength(255),
                ])->columns(2),

            ]),

            SC\Tabs\Tab::make('Conținut')->icon('heroicon-o-document-text')->schema([

            // MEDIA
            SC\Section::make('Media')
                ->schema([
                    Forms\Components\FileUpload::make('poster_url')
                        ->label('Poster (vertical)')
                        ->image()
                        ->directory('events/posters')
                        ->disk('public'),
                    Forms\Components\FileUpload::make('hero_image_url')
                        ->label('Imagine hero (orizontală)')
                        ->image()
                        ->directory('events/hero')
                        ->disk('public'),
                ])->columns(2),

            // CONTENT - Single Language
            SC\Section::make('Conținut')
                ->schema([
                    Forms\Components\Textarea::make("short_description.{$tenantLanguage}")
                        ->label($tenantLanguage === 'ro' ? 'Descriere scurtă' : 'Short description')
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\RichEditor::make("description.{$tenantLanguage}")
                        ->label($tenantLanguage === 'ro' ? 'Descriere' : 'Description')
                        ->columnSpanFull()
                        ->fileAttachmentsDisk('public')
                        ->fileAttachmentsDirectory('event-descriptions')
                        ->fileAttachmentsVisibility('public'),
                    Forms\Components\RichEditor::make("ticket_terms.{$tenantLanguage}")
                        ->label($tenantLanguage === 'ro' ? 'Termeni bilete' : 'Ticket terms')
                        ->columnSpanFull()
                        ->default($tenant?->ticket_terms ?? null),
                    Forms\Components\RichEditor::make("thank_you_message.{$tenantLanguage}")
                        ->label($tenantLanguage === 'ro' ? 'Mesaj post-achiziție (thank-you)' : 'Post-purchase message')
                        ->helperText($tenantLanguage === 'ro'
                            ? 'Afișat clientului pe pagina de confirmare a comenzii, imediat după plată.'
                            : 'Shown to the customer on the order confirmation page right after payment.')
                        ->columnSpanFull()
                        ->fileAttachmentsDisk('public')
                        ->fileAttachmentsDirectory('event-thank-you')
                        ->fileAttachmentsVisibility('public'),
                    Forms\Components\TextInput::make('video_url')
                        ->label($tenantLanguage === 'ro' ? 'Videoclip / Trailer' : 'Video / Trailer')
                        ->url()
                        ->maxLength(500)
                        ->placeholder('https://www.youtube.com/watch?v=...')
                        ->helperText($tenantLanguage === 'ro'
                            ? 'Link YouTube — va fi afișat ca videoclip pe pagina publică a spectacolului.'
                            : 'YouTube link — shown as an embedded video on the public event page.')
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('gallery')
                        ->label($tenantLanguage === 'ro' ? 'Galerie foto' : 'Photo gallery')
                        ->helperText($tenantLanguage === 'ro' ? 'Imaginile apar pe pagina publică a spectacolului. Trage pentru a reordona.' : 'Shown on the public event page. Drag to reorder.')
                        ->multiple()
                        ->image()
                        ->reorderable()
                        ->appendFiles()
                        ->disk('public')
                        ->directory('event-gallery')
                        ->visibility('public')
                        ->imageEditor()
                        ->panelLayout('grid')
                        ->imagePreviewHeight('120')
                        ->columnSpanFull(),
                ])->columns(1),

            ]),

            SC\Tabs\Tab::make('Taxonomii & Relații')->icon('heroicon-o-tag')->schema([

            // TAXONOMIES
            SC\Section::make('Taxonomii & Relații')
                ->schema([
                    Forms\Components\Select::make('tenantEventCategories')
                        ->label('Categoriile tale')
                        ->relationship(
                            name: 'tenantEventCategories',
                            titleAttribute: 'slug',
                            modifyQueryUsing: fn (Builder $query) => $query->where('tenant_id', $tenant?->id)->where('is_active', true)->orderBy('sort_order')
                        )
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('name', 'ro'))
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->helperText('Categorii proprii, gestionate în „Categorii evenimente". Apar pe site-ul tău.'),

                    Forms\Components\Select::make('eventTypes')
                        ->label('Tipuri de eveniment')
                        ->relationship(
                            name: 'eventTypes',
                            modifyQueryUsing: fn (Builder $query) => $query->whereNotNull('parent_id')
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
                        ->label('Genuri')
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
                                });
                            }
                        )
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('name', app()->getLocale()))
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->disabled(fn (SGet $get) => empty($get('eventTypes')))
                        ->maxItems(5),

                    Forms\Components\Select::make('artists')
                        ->label('Artiști')
                        ->relationship('artists', 'name')
                        ->multiple()
                        ->preload()
                        ->searchable()
                        // La teatru distribuția se gestionează în tab-ul Distribuție
                        ->visible(fn () => !$isTheater),

                    Forms\Components\Select::make('tags')
                        ->label('Etichete')
                        ->relationship('tags', 'name')
                        ->multiple()
                        ->preload()
                        ->searchable(),

                    // Dynamic tax display based on selected event types
                    Forms\Components\Placeholder::make('applicable_taxes')
                        ->label('Taxe aplicabile')
                        ->columnSpanFull()
                        ->visible(fn (SGet $get) => !empty($get('eventTypes')))
                        ->content(function (SGet $get) use ($tenant) {
                            $eventTypeIds = (array) ($get('eventTypes') ?? []);
                            if (empty($eventTypeIds)) {
                                return '';
                            }

                            $isVatPayer = $tenant?->vat_payer ?? false;
                            $taxDisplayMode = $tenant?->tax_display_mode ?? 'included';

                            // Get applicable taxes using the new forEventTypes scope
                            $allTaxes = GeneralTax::query()
                                ->whereNull('tenant_id') // Global taxes only
                                ->active()
                                ->validOn(\Carbon\Carbon::today())
                                ->forEventTypes($eventTypeIds)
                                ->orderByDesc('priority')
                                ->get()
                                ->unique('id');

                            if ($allTaxes->isEmpty()) {
                                return new HtmlString('<div class="text-sm text-gray-500 italic">Nu există taxe configurate pentru tipul de eveniment selectat.</div>');
                            }

                            $html = '<div class="space-y-2">';

                            // VAT payer status and tax display mode
                            $vatBadge = $isVatPayer
                                ? '<span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Plătitor TVA</span>'
                                : '<span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">Neplătitor TVA</span>';

                            $modeBadge = $taxDisplayMode === 'added'
                                ? '<span class="ml-2 inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">Taxe adăugate la preț</span>'
                                : '<span class="ml-2 inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Taxe incluse în preț</span>';

                            $html .= '<div class="mb-3 flex flex-wrap items-center gap-2">' . $vatBadge . $modeBadge . '</div>';

                            $html .= '<div class="grid grid-cols-1 md:grid-cols-2 gap-2">';

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
                ])->columns(2),

            ]),

            SC\Tabs\Tab::make('Distribuție')
                ->icon('heroicon-o-user-group')
                ->visible(fn () => $isTheater)
                ->schema([
                    SC\Section::make('Detalii spectacol')
                        ->description('Se afișează pe pagina publică a spectacolului (vertical Teatru).')
                        ->schema([
                            SC\Grid::make(3)->schema([
                                Forms\Components\TextInput::make('theater_director')->label('Regia')->maxLength(190)->datalist($artistNames),
                                Forms\Components\TextInput::make('theater_lead')->label('În rolul principal')->maxLength(190)->datalist($artistNames),
                                Forms\Components\TextInput::make('theater_duration')->label('Durata')->placeholder('2h 45min')->maxLength(60),
                            ]),
                        ]),
                    SC\Section::make('Distribuție')
                        ->schema([
                            Forms\Components\Repeater::make('theater_cast')
                                ->label('')
                                ->extraAttributes(['class' => 'ep-repeater-padded'])
                                ->schema([
                                    SC\Grid::make(2)->schema([
                                        Forms\Components\Select::make('name')->label('Nume')
                                            ->options(fn () => \App\Models\TenantArtist::where('tenant_id', $tenant?->id)->orderBy('name')->pluck('name', 'name'))
                                            ->searchable()
                                            ->createOptionForm([
                                                Forms\Components\TextInput::make('name')->label('Nume')->required(),
                                                Forms\Components\TextInput::make('role')->label('Rol / funcție implicită'),
                                            ])
                                            ->createOptionUsing(function (array $data) use ($tenant) {
                                                $ta = \App\Models\TenantArtist::firstOrCreate(
                                                    ['tenant_id' => $tenant?->id, 'name' => $data['name']],
                                                    ['slug' => \Illuminate\Support\Str::slug($data['name']) . '-' . substr(md5($data['name']), 0, 6), 'role' => $data['role'] ?? null, 'status' => 'active']
                                                );
                                                return $ta->name;
                                            }),
                                        Forms\Components\TextInput::make('role')->label('Rol / Personaj'),
                                    ]),
                                ])
                                ->reorderable()
                                ->collapsible()
                                ->itemLabel(fn (array $state) => trim(($state['name'] ?? '') . (!empty($state['role']) ? ' — ' . $state['role'] : '')) ?: 'Interpret')
                                ->addActionLabel('Adaugă în distribuție')
                                ->defaultItems(0),
                        ]),
                    SC\Section::make('Echipa creativă')
                        ->schema([
                            Forms\Components\Repeater::make('theater_creative')
                                ->label('')
                                ->extraAttributes(['class' => 'ep-repeater-padded'])
                                ->schema([
                                    SC\Grid::make(2)->schema([
                                        Forms\Components\TextInput::make('role')->label('Funcție')->placeholder('Scenografie, Costume, Muzica...'),
                                        Forms\Components\Select::make('name')->label('Nume')
                                            ->options(fn () => \App\Models\TenantArtist::where('tenant_id', $tenant?->id)->orderBy('name')->pluck('name', 'name'))
                                            ->searchable()
                                            ->createOptionForm([
                                                Forms\Components\TextInput::make('name')->label('Nume')->required(),
                                                Forms\Components\TextInput::make('role')->label('Rol / funcție implicită'),
                                            ])
                                            ->createOptionUsing(function (array $data) use ($tenant) {
                                                $ta = \App\Models\TenantArtist::firstOrCreate(
                                                    ['tenant_id' => $tenant?->id, 'name' => $data['name']],
                                                    ['slug' => \Illuminate\Support\Str::slug($data['name']) . '-' . substr(md5($data['name']), 0, 6), 'role' => $data['role'] ?? null, 'status' => 'active']
                                                );
                                                return $ta->name;
                                            }),
                                    ]),
                                ])
                                ->reorderable()
                                ->collapsible()
                                ->itemLabel(fn (array $state) => trim(($state['role'] ?? '') . (!empty($state['name']) ? ': ' . $state['name'] : '')) ?: 'Rol creativ')
                                ->addActionLabel('Adaugă în echipă')
                                ->defaultItems(0),
                        ]),
                ]),

            SC\Tabs\Tab::make('Bilete')->icon('heroicon-o-ticket')->schema([

            // TICKETS
            SC\Section::make('Bilete')
                ->schema([
                    // Ticket Template selector
                    Forms\Components\Select::make('ticket_template_id')
                        ->label('Șablon bilet')
                        ->relationship(
                            name: 'ticketTemplate',
                            modifyQueryUsing: fn (Builder $query) => $query
                                ->where('tenant_id', auth()->user()->tenant?->id)
                                ->where('status', 'active')
                        )
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->name . ($record->is_default ? ' (Default)' : ''))
                        ->placeholder('Use default template')
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Select a template for tickets generated for this event. Leave empty to use the default template.')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->visible(fn () => auth()->user()->tenant?->microservices()
                            ->where('slug', 'ticket-customizer')
                            ->wherePivot('is_active', true)
                            ->exists() ?? false),

                    // Event-level capacity & reference price (commission mode is set globally in Settings)
                    SC\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('general_quota')
                            ->label('Capacitate generală')
                            ->numeric()->minValue(1)->nullable()
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Numărul maxim total de bilete (pool partajat între tipurile fără stoc independent). Gol = fără limită.')
                            ->placeholder('gol = fără limită'),

                        Forms\Components\TextInput::make('target_price')
                            ->label('Preț la intrare (referință)')
                            ->numeric()->minValue(0)->step(0.01)
                            ->suffix($tenant?->currency ?? 'RON')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Preț de referință pentru planificare. Nu este afișat public.'),
                    ]),

                    Forms\Components\Select::make('seating_layout_id')
                        ->label('Hartă de locuri')
                        ->searchable()->preload()->live(onBlur: true)
                        ->visible(function (SGet $get) {
                            $venueId = $get('venue_id');
                            if (!$venueId) return false;
                            return \App\Models\Seating\SeatingLayout::where('venue_id', $venueId)->where('status', 'published')->exists();
                        })
                        ->options(function (SGet $get) {
                            $venueId = $get('venue_id');
                            if (!$venueId) return [];
                            return \App\Models\Seating\SeatingLayout::query()->where('venue_id', $venueId)->where('status', 'published')->orderBy('name')->get()
                                ->mapWithKeys(fn ($layout) => [$layout->id => $layout->name . ' (' . $layout->sections()->count() . ' secțiuni)']);
                        })
                        ->helperText('Selectează o hartă pentru locuri numerotate. Lasă gol pentru acces general.')
                        ->nullable()
                        ->columnSpanFull(),

                    Forms\Components\Repeater::make('ticketTypes')
                        ->relationship()
                        ->label('Tipuri de bilete')
                        ->collapsible()
                        ->collapsed()
                        ->reorderable()
                        ->reorderableWithDragAndDrop()
                        ->orderColumn('sort_order')
                        ->addActionLabel('Adaugă tip bilet')
                        ->itemLabel(function (array $state) {
                            $name = e($state['name'] ?? 'Bilet');
                            $isActive = $state['is_active'] ?? true;
                            $isEntryTicket = $state['is_entry_ticket'] ?? false;
                            $isDeclarable = $state['is_declarable'] ?? true;
                            $isRefundable = $state['is_refundable'] ?? false;
                            $isSubscription = $state['is_subscription'] ?? false;
                            $isSoldOut = $state['is_sold_out'] ?? false;

                            $badges = '';
                            if ($isSoldOut) {
                                $badges .= '<span style="font-size:10px;font-weight:700;color:#dc2626;background:#fef2f2;padding:1px 6px;border-radius:4px;margin-left:6px;">SOLD OUT</span>';
                            }
                            $badges .= $isEntryTicket
                                ? '<span style="font-size:10px;font-weight:600;color:#7c3aed;background:#f5f3ff;padding:1px 6px;border-radius:4px;margin-left:6px;">Offline</span>'
                                : '<span style="font-size:10px;font-weight:600;color:#0891b2;background:#ecfeff;padding:1px 6px;border-radius:4px;margin-left:6px;">Online</span>';
                            if ($isEntryTicket) {
                                $badges .= '<span style="font-size:10px;font-weight:600;color:#7c3aed;background:#f5f3ff;padding:1px 6px;border-radius:4px;margin-left:4px;">App</span>';
                            }
                            if ($isDeclarable) {
                                $badges .= '<span style="font-size:10px;font-weight:600;color:#0e7490;background:#ecfeff;padding:1px 6px;border-radius:4px;margin-left:4px;">Declarabil</span>';
                            }
                            if ($isRefundable) {
                                $badges .= '<span style="font-size:10px;font-weight:600;color:#059669;background:#ecfdf5;padding:1px 6px;border-radius:4px;margin-left:4px;">Returnabil</span>';
                            }
                            if ($isSubscription) {
                                $badges .= '<span style="font-size:10px;font-weight:600;color:#a16207;background:#fefce8;padding:1px 6px;border-radius:4px;margin-left:4px;display:inline-flex;align-items:center;gap:3px;"><svg style="width:11px;height:11px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Abonament</span>';
                            }

                            if ($isActive) {
                                return new \Illuminate\Support\HtmlString('✓ ' . $name . $badges);
                            }

                            $activeUntil = $state['active_until'] ?? null;
                            if ($activeUntil && \Carbon\Carbon::parse($activeUntil, 'Europe/Bucharest')->isPast()) {
                                return new \Illuminate\Support\HtmlString('○ ' . $name . $badges . ' <span style="font-size:11px;font-weight:600;color:#dc2626;background:#fef2f2;padding:1px 6px;border-radius:4px;margin-left:6px;">Expirat</span>');
                            }

                            $scheduledAt = $state['scheduled_at'] ?? null;
                            if ($scheduledAt) {
                                try {
                                    $scheduledDate = \Carbon\Carbon::parse($scheduledAt, 'Europe/Bucharest');
                                    if ($scheduledDate->isFuture()) {
                                        return new \Illuminate\Support\HtmlString('○ ' . $name . $badges . ' <span style="font-size:11px;font-weight:600;color:#7c3aed;background:#f5f3ff;padding:1px 6px;border-radius:4px;margin-left:6px;">Programat ' . $scheduledDate->format('d.m.Y H:i') . '</span>');
                                    }
                                } catch (\Exception $e) {}
                            }

                            if ($state['autostart_when_previous_sold_out'] ?? false) {
                                return new \Illuminate\Support\HtmlString('○ ' . $name . $badges . ' <span style="font-size:11px;font-weight:600;color:#2563eb;background:#eff6ff;padding:1px 6px;border-radius:4px;margin-left:6px;">Autostart</span>');
                            }

                            return new \Illuminate\Support\HtmlString('○ ' . $name . $badges . ' <span style="font-size:11px;font-weight:600;color:#d97706;background:#fffbeb;padding:1px 6px;border-radius:4px;margin-left:6px;">Dezactivat</span>');
                        })
                        ->extraItemActions([
                            Action::make('toggleApp')->iconButton()
                                ->icon(fn (array $arguments, Forms\Components\Repeater $component): string => ($component->getState()[$arguments['item']]['is_entry_ticket'] ?? false) ? 'heroicon-s-device-phone-mobile' : 'heroicon-o-device-phone-mobile')
                                ->color(fn (array $arguments, Forms\Components\Repeater $component): string => ($component->getState()[$arguments['item']]['is_entry_ticket'] ?? false) ? 'success' : 'gray')
                                ->tooltip(fn (array $arguments, Forms\Components\Repeater $component) => ($component->getState()[$arguments['item']]['is_entry_ticket'] ?? false) ? 'App: ON (click to disable)' : 'App: OFF (click to enable)')
                                ->action(function (array $arguments, Forms\Components\Repeater $component) {
                                    $state = $component->getState(); $state[$arguments['item']]['is_entry_ticket'] = !($state[$arguments['item']]['is_entry_ticket'] ?? false); $component->state($state);
                                }),
                            Action::make('toggleDeclarabil')->iconButton()
                                ->icon(fn (array $arguments, Forms\Components\Repeater $component): string => ($component->getState()[$arguments['item']]['is_declarable'] ?? true) ? 'heroicon-s-document-check' : 'heroicon-o-document-check')
                                ->color(fn (array $arguments, Forms\Components\Repeater $component): string => ($component->getState()[$arguments['item']]['is_declarable'] ?? true) ? 'info' : 'gray')
                                ->tooltip(fn (array $arguments, Forms\Components\Repeater $component) => ($component->getState()[$arguments['item']]['is_declarable'] ?? true) ? 'Declarabil: ON (click to disable)' : 'Declarabil: OFF (click to enable)')
                                ->action(function (array $arguments, Forms\Components\Repeater $component) {
                                    $state = $component->getState(); $state[$arguments['item']]['is_declarable'] = !($state[$arguments['item']]['is_declarable'] ?? true); $component->state($state);
                                }),
                            Action::make('toggleReturnabil')->iconButton()
                                ->icon(fn (array $arguments, Forms\Components\Repeater $component): string => ($component->getState()[$arguments['item']]['is_refundable'] ?? false) ? 'heroicon-s-arrow-uturn-left' : 'heroicon-o-arrow-uturn-left')
                                ->color(fn (array $arguments, Forms\Components\Repeater $component): string => ($component->getState()[$arguments['item']]['is_refundable'] ?? false) ? 'warning' : 'gray')
                                ->tooltip(fn (array $arguments, Forms\Components\Repeater $component) => ($component->getState()[$arguments['item']]['is_refundable'] ?? false) ? 'Returnabil: ON (click to disable)' : 'Returnabil: OFF (click to enable)')
                                ->action(function (array $arguments, Forms\Components\Repeater $component) {
                                    $state = $component->getState(); $state[$arguments['item']]['is_refundable'] = !($state[$arguments['item']]['is_refundable'] ?? false); $component->state($state);
                                }),
                            Action::make('toggleAbonament')->iconButton()
                                ->icon(fn (array $arguments, Forms\Components\Repeater $component): string => ($component->getState()[$arguments['item']]['is_subscription'] ?? false) ? 'heroicon-s-clock' : 'heroicon-o-clock')
                                ->color(fn (array $arguments, Forms\Components\Repeater $component): string => ($component->getState()[$arguments['item']]['is_subscription'] ?? false) ? 'warning' : 'gray')
                                ->tooltip(fn (array $arguments, Forms\Components\Repeater $component) => ($component->getState()[$arguments['item']]['is_subscription'] ?? false) ? 'Abonament: ON (click to disable)' : 'Abonament: OFF (click to enable)')
                                ->action(function (array $arguments, Forms\Components\Repeater $component) {
                                    $state = $component->getState(); $state[$arguments['item']]['is_subscription'] = !($state[$arguments['item']]['is_subscription'] ?? false); $component->state($state);
                                }),
                            Action::make('toggleSoldOut')->iconButton()
                                ->icon(fn (array $arguments, Forms\Components\Repeater $component): string => ($component->getState()[$arguments['item']]['is_sold_out'] ?? false) ? 'heroicon-s-no-symbol' : 'heroicon-o-no-symbol')
                                ->color(fn (array $arguments, Forms\Components\Repeater $component): string => ($component->getState()[$arguments['item']]['is_sold_out'] ?? false) ? 'danger' : 'gray')
                                ->tooltip(fn (array $arguments, Forms\Components\Repeater $component) => ($component->getState()[$arguments['item']]['is_sold_out'] ?? false) ? 'Sold Out: ON (click pentru a repune în vânzare)' : 'Sold Out: OFF (click pentru a marca sold out)')
                                ->action(function (array $arguments, Forms\Components\Repeater $component) {
                                    $state = $component->getState(); $state[$arguments['item']]['is_sold_out'] = !($state[$arguments['item']]['is_sold_out'] ?? false); $component->state($state);
                                }),
                            Action::make('duplicateTicketType')->icon('heroicon-m-document-duplicate')->color('gray')
                                ->tooltip('Duplică tipul de bilet')
                                ->action(function (array $arguments, Forms\Components\Repeater $component) {
                                    $state = $component->getState(); $itemData = $state[$arguments['item']] ?? null; if (!$itemData) return;
                                    $newData = $itemData; $newData['name'] = '[DUP] ' . ($newData['name'] ?? ''); $newData['id'] = null; $newData['sku'] = ''; $newData['quota_sold'] = 0; $newData['series_start'] = null; $newData['series_end'] = null;
                                    $state[(string) Str::uuid()] = $newData; $component->state($state);
                                }),
                        ])
                        ->columns(12)
                        ->schema([
                            Forms\Components\Hidden::make('id'),

                            SC\Section::make('Identificare')
                                ->extraAttributes(['class' => 'ep-tt-section'])
                                ->schema([
                                    SC\Grid::make(4)->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Nume')->placeholder('ex: Early Bird, Standard, VIP')
                                            ->datalist(['Early Bird','Standard','VIP','Backstage','Student','Senior','Child'])
                                            ->required()->inlineLabel($il)->live(onBlur: true)->skipRenderAfterStateUpdated()
                                            ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                                if ($get('sku')) return;
                                                $set('sku', Str::upper(Str::slug($state, '-')));
                                            }),
                                        Forms\Components\TextInput::make('sku')->label('SKU')->inlineLabel($il)->placeholder('Se generează automat dacă lași gol'),
                                        Forms\Components\TextInput::make('price_max')
                                            ->label('Preț')->inlineLabel($il)->placeholder('ex: 120.00')->numeric()->minValue(0)->required()
                                            ->suffix($tenant?->currency ?? 'RON')->live(onBlur: true)->partiallyRenderAfterStateUpdated()
                                            ->hint(function (SGet $get) {
                                                $targetPrice = (float) ($get('../../target_price') ?: 0);
                                                $price = (float) ($get('price_max') ?: 0);
                                                if ($targetPrice > 0 && $price > $targetPrice) {
                                                    return new \Illuminate\Support\HtmlString('<span style="color:#dc2626;font-weight:600;">⚠ Depășește prețul la intrare (' . number_format($targetPrice, 2) . ')</span>');
                                                }
                                                return null;
                                            }),
                                        Forms\Components\TextInput::make('capacity')
                                            ->label('Stoc')->inlineLabel($il)->placeholder('obligatoriu')->numeric()->minValue(-1)->required()
                                            ->hintIcon('heroicon-o-information-circle', tooltip: '-1 = nelimitat')->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                                $eventSeries = $get('../../event_series'); $ticketTypeIdentifier = $get('id') ?: null;
                                                if (!$eventSeries || !$ticketTypeIdentifier) return;
                                                $capacity = (int) ($state ?: 0); if ($capacity === -1) $capacity = 1000; if ($capacity <= 0) return;
                                                $set('series_end', $eventSeries . '-' . $ticketTypeIdentifier . '-' . str_pad($capacity, 5, '0', STR_PAD_LEFT));
                                                if (!$get('series_start')) { $set('series_start', $eventSeries . '-' . $ticketTypeIdentifier . '-00001'); }
                                            })
                                            ->afterStateHydrated(function ($component, $state, SGet $get, $record) {
                                                if ($state === null || $state === '') {
                                                    $generalQuota = (int) ($get('../../general_quota') ?: 0);
                                                    if ($generalQuota > 0) {
                                                        $eventRecord = ($record instanceof \App\Models\TicketType) ? $record->event : (($record instanceof Event) ? $record : null);
                                                        if (!$eventRecord) { $eventId = $get('../../id'); $eventRecord = $eventId ? Event::find($eventId) : null; }
                                                        if ($eventRecord) {
                                                            $activeCount = static::activeNonIndepCount($eventRecord->id);
                                                            $component->state(max(0, $generalQuota - $activeCount));
                                                        } else { $component->state($generalQuota); }
                                                    }
                                                }
                                            })
                                            ->suffixAction(
                                                Action::make('toggle_independent')
                                                    ->icon(fn (SGet $get) => $get('is_independent_stock') ? 'heroicon-s-lock-open' : 'heroicon-s-lock-closed')
                                                    ->color(fn (SGet $get) => $get('is_independent_stock') ? 'success' : 'gray')
                                                    ->tooltip(fn (SGet $get) => $get('is_independent_stock') ? 'Stoc independent (nu consumă din capacitatea generală). Click pentru a dezactiva.' : 'Stoc partajat (consumă din capacitatea generală). Click pentru a face independent.')
                                                    ->action(function (SGet $get, SSet $set) { $set('is_independent_stock', !$get('is_independent_stock')); })
                                            )
                                            ->hint(function ($record, SGet $get) {
                                                $hints = [];
                                                if ($record) {
                                                    $__counts = static::ticketTypeCounts($record->id, $record->event_id ?? $get('../../id'));
                                                    $activeCount = $__counts['active']; $cancelledCount = $__counts['cancelled'];
                                                    if ($activeCount > 0 || $cancelledCount > 0) {
                                                        $capacity = $record->quota_total ?? $record->capacity ?? null;
                                                        $soldText = 'Active' . ": {$activeCount}";
                                                        if ($cancelledCount > 0) { $soldText .= ' · <span style="color:#dc2626;">' . 'Anulate' . ": {$cancelledCount}</span>"; }
                                                        if ($capacity !== null && (int) $capacity > 0) { $soldText .= " / {$capacity}"; }
                                                        $hints[] = '<span class="text-xs">' . $soldText . '</span>';
                                                    }
                                                }
                                                $generalQuota = (int) ($get('../../general_quota') ?: 0); $isIndependent = (bool) $get('is_independent_stock'); $capacity = (int) ($get('capacity') ?: 0);
                                                if ($generalQuota > 0 && !$isIndependent && $capacity > $generalQuota) { $hints[] = '<span style="color:#dc2626;font-weight:600;">⚠ Depășește capacitatea generală (' . $generalQuota . ')</span>'; }
                                                if ($isIndependent) { $hints[] = '<span class="text-xs" style="color:#059669;">🔓 Independent</span>'; }
                                                return !empty($hints) ? new \Illuminate\Support\HtmlString(implode(' · ', $hints)) : null;
                                            }),
                                        Forms\Components\Hidden::make('is_independent_stock')->default(false)->dehydrated(true),
                                        Forms\Components\Hidden::make('currency')->default($tenant?->currency ?? 'RON')->dehydrated(true),
                                    ])->columnSpan(12),

                                    SC\Grid::make(4)->schema([
                                        Forms\Components\Select::make('ticket_group')
                                            ->label('Grup')->placeholder('Selectează sau creează un grup...')
                                            ->options(function (SGet $get) {
                                                $allTicketTypes = $get('../../ticketTypes') ?? []; $groups = [];
                                                foreach ($allTicketTypes as $tt) { $g = $tt['ticket_group'] ?? null; if ($g && !isset($groups[$g])) { $groups[$g] = $g; } }
                                                foreach (['Bilete Acces', 'Camping', 'Parcări', 'VIP', 'Add-ons'] as $suggestion) { if (!isset($groups[$suggestion])) { $groups[$suggestion] = $suggestion; } }
                                                ksort($groups); return $groups;
                                            })
                                            ->searchable()
                                            ->createOptionForm([ Forms\Components\TextInput::make('group_name')->label('Nume grup nou')->required() ])
                                            ->createOptionUsing(fn (array $data) => $data['group_name'])
                                            ->visible(fn (SGet $get) => (bool) $get('../../enable_ticket_groups')),
                                        Forms\Components\TextInput::make('min_per_order')->label('Min bilete/comandă')->inlineLabel($il)->numeric()->minValue(1)->default(1)->live(onBlur: true)
                                            ->afterStateUpdated(fn ($state, SSet $set, SGet $get) => ($get('max_per_order') && (int) $state > (int) $get('max_per_order')) ? $set('max_per_order', $state) : null)
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Numărul minim de bilete care pot fi cumpărate într-o comandă'),
                                        Forms\Components\TextInput::make('max_per_order')->label('Max bilete/comandă')->inlineLabel($il)->numeric()->minValue(1)->default(10)->live(onBlur: true)
                                            ->rules([ fn (SGet $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                                $min = (int) ($get('min_per_order') ?: 1);
                                                if ($value && (int) $value < $min) { $fail("Max bilete/comandă ({$value}) nu poate fi mai mic decât Min bilete/comandă ({$min})."); }
                                            } ])
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Numărul maxim de bilete care pot fi cumpărate într-o comandă'),
                                        Forms\Components\TextInput::make('multiplier')->label('Multiplicator')->inlineLabel($il)->numeric()->minValue(1)->default(1)
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Pasul de incrementare la +/- pe frontend. Ex: 2 = se adaugă câte 2 bilete per click.'),
                                    ])->columnSpan(12),

                                    SC\Grid::make(2)->schema([
                                        Forms\Components\Textarea::make('description')->label('Descriere')->placeholder('Descriere opțională tip bilet')->rows(2)
                                            ->afterStateHydrated(function ($state, SSet $set, SGet $get) {
                                                if (!$state && $get('sales_end_at') && $get('price')) { $date = Carbon::parse($get('sales_end_at'))->format('d.m.Y'); $set('description', "Reducere până la {$date}"); }
                                            }),
                                        Forms\Components\Textarea::make('admin_notes')->label('Note interne')->placeholder('Vizibil doar în admin...')->rows(2),
                                    ])->columnSpan(12),

                                    Forms\Components\ColorPicker::make('color')->label('Culoare pe hartă')->hexColor()
                                        ->visible(fn (SGet $get) => (bool) $get('../../seating_layout_id'))->columnSpan(3),

                                    Forms\Components\Hidden::make('is_entry_ticket')->default(false),
                                    Forms\Components\Hidden::make('is_declarable')->default(true),
                                    Forms\Components\Hidden::make('is_refundable')->default(false),
                                    Forms\Components\Hidden::make('is_subscription')->default(false),
                                    Forms\Components\Hidden::make('is_sold_out')->default(false),

                                    Forms\Components\DatePicker::make('valid_date')->label('Bilet de 1 zi — valabil în data')->inlineLabel($il)->native(true)
                                        ->extraInputAttributes(['lang' => 'ro-RO'])
                                        ->minDate(fn (SGet $get) => $get('../../range_start_date') ? \Carbon\Carbon::parse($get('../../range_start_date'))->format('Y-m-d') : null)
                                        ->maxDate(fn (SGet $get) => $get('../../range_end_date') ? \Carbon\Carbon::parse($get('../../range_end_date'))->format('Y-m-d') : null)
                                        ->placeholder('Completează doar pentru bilete valabile o singură zi')
                                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Lasă gol dacă biletul e valabil pe toată durata evenimentului. Completează o dată specifică pentru bilete de o zi.')
                                        ->visible(fn (SGet $get) => $get('../../duration_mode') === 'range')->columnSpan(12),
                                ])
                                ->columns(12)->columnSpan(12),

                            SC\Section::make('Prețuri per reprezentație')
                                ->visible(fn (SGet $get) => $get('../../duration_mode') === 'multi_day' && $get('../../has_per_performance_pricing'))
                                ->schema([
                                    Forms\Components\Placeholder::make('perf_prices_hint')->hiddenLabel()
                                        ->content(function (\Livewire\Component $livewire) {
                                            $eventId = $livewire->record?->id ?? null;
                                            if (!$eventId) { return new HtmlString('<p class="text-sm text-amber-600 dark:text-amber-400">Salvează evenimentul mai întâi pentru a configura prețurile per reprezentație.</p>'); }
                                            $count = \App\Models\Performance::where('event_id', $eventId)->count();
                                            if ($count === 0) { return new HtmlString('<p class="text-sm text-amber-600 dark:text-amber-400">Salvează evenimentul pentru a genera reprezentațiile din tab-ul Program, apoi revino aici.</p>'); }
                                            return '';
                                        }),
                                    Forms\Components\Repeater::make('meta.performance_prices')->label('Prețuri per reprezentare')
                                        ->visible(fn (\Livewire\Component $livewire) => $livewire->record && \App\Models\Performance::where('event_id', $livewire->record->id)->exists())
                                        ->schema([
                                            Forms\Components\Select::make('perf_id')->hiddenLabel()->placeholder('Alege reprezentarea...')
                                                ->options(function (SGet $get, \Livewire\Component $livewire) {
                                                    $eventId = $livewire->record?->id ?? null;
                                                    if ($eventId) {
                                                        $performances = \App\Models\Performance::where('event_id', $eventId)->where(fn ($q) => $q->where('status', 'active')->orWhereNull('status'))->orderBy('starts_at')->get();
                                                        if ($performances->isNotEmpty()) { return $performances->mapWithKeys(fn ($p) => [$p->id => $p->starts_at->format('D, d M Y · H:i')])->toArray(); }
                                                    }
                                                    $multiSlots = $get('../../multi_slots') ?? [];
                                                    if (!empty($multiSlots)) {
                                                        $options = [];
                                                        foreach ($multiSlots as $idx => $slot) { $date = $slot['date'] ?? null; if (!$date) continue; $startTime = $slot['start_time'] ?? '00:00'; $options["slot_{$idx}"] = \Carbon\Carbon::parse("{$date} {$startTime}")->format('D, d M Y · H:i'); }
                                                        return $options;
                                                    }
                                                    return [];
                                                })
                                                ->required()->searchable()->live()->columnSpan(3),
                                            Forms\Components\TextInput::make('price')->hiddenLabel()->numeric()->step(0.01)->placeholder('Preț')->columnSpan(1),
                                            Forms\Components\TextInput::make('stock')->hiddenLabel()->numeric()->minValue(0)->placeholder('Stoc (gol = stoc tip bilet)')->columnSpan(1),
                                            Forms\Components\TextInput::make('series_start')->hiddenLabel()->placeholder('Serie start')->disabled()->dehydrated(true)->extraAttributes(['style' => 'font-family:monospace;font-size:9px;'])->columnSpan(2),
                                            Forms\Components\TextInput::make('series_end')->hiddenLabel()->placeholder('Serie end')->disabled()->dehydrated(true)->extraAttributes(['style' => 'font-family:monospace;font-size:9px;'])->columnSpan(2),
                                        ])
                                        ->columns(9)->grid(1)->itemLabel(fn () => null)->addActionLabel('+ Adaugă preț')->defaultItems(0)->reorderable(false)->columnSpan(12),
                                ])
                                ->collapsible()->collapsed()->persistCollapsed()->compact()->columns(12)->columnSpan(12),

                            SC\Section::make('Condiții & Beneficii')
                                ->visible(fn (SGet $get) => (bool) $get('../../enable_ticket_perks'))
                                ->schema([
                                    Forms\Components\Repeater::make('perks')->label('Condiții / Beneficii')
                                        ->simple(Forms\Components\TextInput::make('text')->placeholder('ex: Include acces la zona VIP')->required())
                                        ->defaultItems(0)->addActionLabel('Adaugă condiție / beneficiu')->reorderable()->columnSpan(12),
                                ])
                                ->collapsible()->collapsed()->persistCollapsed()->compact()->columns(12)->columnSpan(12),

                            SC\Section::make('Disponibilitate')
                                ->schema([
                                    Forms\Components\Toggle::make('is_active')->label('Activ')->default(true)->columnSpan(2),
                                    Forms\Components\DateTimePicker::make('scheduled_at')->label('Programează activare')->inlineLabel($il)
                                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Când acest tip de bilet ar trebui să devină automat activ')
                                        ->native(true)->seconds(false)->extraInputAttributes(['lang' => 'ro-RO'])->columnSpan(3),
                                    Forms\Components\DateTimePicker::make('active_until')->label('Activ până la')->inlineLabel($il)->native(true)->seconds(false)->extraInputAttributes(['lang' => 'ro-RO'])
                                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Când se atinge această dată, tipul de bilet va fi marcat ca sold out, chiar dacă mai sunt bilete în stoc.')->columnSpan(3),
                                    Forms\Components\Toggle::make('autostart_when_previous_sold_out')->label('Autostart când precedentul e sold out')
                                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Activează automat când tipurile de bilete anterioare ajung la capacitate 0')->columnSpan(4),
                                ])
                                ->collapsible()->collapsed()->persistCollapsed()->compact()->columns(12)->columnSpan(12),

                            SC\Section::make('Reducere')
                                ->schema([
                                    Forms\Components\Toggle::make('has_sale')->label('Activează reducere')->live()->default(false)->dehydrated(false)
                                        ->afterStateHydrated(function ($state, SSet $set, SGet $get) {
                                            $hasSaleData = $get('price') || $get('discount_percent') || $get('sales_start_at') || $get('sales_end_at') || $get('sale_stock');
                                            if ($hasSaleData) { $set('has_sale', true); }
                                        })
                                        ->afterStateUpdated(function ($state, SSet $set) {
                                            if (!$state) { $set('price', null); $set('sale_price_cents', null); $set('discount_percent', null); $set('sales_start_at', null); $set('sales_end_at', null); $set('sale_stock', null); }
                                        })->columnSpan(12),
                                    Forms\Components\TextInput::make('price')->label('Preț promoțional')->inlineLabel($il)->placeholder('lasă gol dacă nu e reducere')->numeric()->minValue(0)->suffix($tenant?->currency ?? 'RON')->live(onBlur: true)->skipRenderAfterStateUpdated()
                                        ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                            $price = (float) ($get('price_max') ?: 0); $sale = $state !== null && $state !== '' ? (float)$state : null;
                                            if ($price > 0 && $sale) { $set('discount_percent', max(0, min(100, round((1 - ($sale / $price)) * 100, 2)))); } else { $set('discount_percent', null); }
                                        })->visible(fn (SGet $get) => $get('has_sale'))->columnSpan(3),
                                    Forms\Components\TextInput::make('discount_percent')->label('Reducere %')->inlineLabel($il)->placeholder('ex: 20')->numeric()->minValue(0)->maxValue(100)->live(onBlur: true)->skipRenderAfterStateUpdated()
                                        ->formatStateUsing(function ($state, SGet $get) {
                                            if ($state !== null && $state !== '') { return $state; }
                                            $priceMax = (float) ($get('price_max') ?: 0); $salePrice = $get('price');
                                            if ($priceMax > 0 && $salePrice !== null && $salePrice !== '') { return round((1 - ((float) $salePrice / $priceMax)) * 100, 2); }
                                            return null;
                                        })
                                        ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                            $price = (float) ($get('price_max') ?: 0); if ($price <= 0) return;
                                            if ($state === null || $state === '') { $set('price', null); return; }
                                            $disc = max(0, min(100, (float)$state)); $set('price', round($price * (1 - $disc/100), 2));
                                        })->visible(fn (SGet $get) => $get('has_sale'))->columnSpan(3),
                                    Forms\Components\DateTimePicker::make('sales_start_at')->label('Început reducere')->inlineLabel($il)->native(true)->seconds(false)->extraInputAttributes(['lang' => 'ro-RO'])->live(onBlur: true)->skipRenderAfterStateUpdated()
                                        ->afterStateUpdated(function ($state, SSet $set) {
                                            if (!$state) return; $selectedDate = Carbon::parse($state); $now = Carbon::now();
                                            if ($selectedDate->isToday() && $selectedDate->format('H:i') === '00:00') { $set('sales_start_at', $now->copy()->addMinutes(5 - ($now->minute % 5))->second(0)->format('Y-m-d H:i')); }
                                            elseif ($selectedDate->lt($now)) { $set('sales_start_at', $now->copy()->addMinutes(5 - ($now->minute % 5))->second(0)->format('Y-m-d H:i')); }
                                        })->visible(fn (SGet $get) => $get('has_sale'))->columnSpan(3),
                                    Forms\Components\DateTimePicker::make('sales_end_at')->label('Sfârșit reducere')->inlineLabel($il)->native(true)->seconds(false)->extraInputAttributes(['lang' => 'ro-RO'])->live(onBlur: true)->skipRenderAfterStateUpdated()
                                        ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                            if ($state && !$get('description')) { $set('description', "Reducere până la " . Carbon::parse($state)->format('d.m.Y')); }
                                        })->visible(fn (SGet $get) => $get('has_sale'))->columnSpan(3),
                                    Forms\Components\TextInput::make('sale_stock')->label('Stoc reducere')->inlineLabel($il)->placeholder('Nelimitat')->numeric()->minValue(0)->nullable()
                                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Numărul de bilete disponibile la preț redus. Când se consumă stocul, oferta se închide automat.')
                                        ->visible(fn (SGet $get) => $get('has_sale'))->columnSpan(6),
                                ])
                                ->collapsible()->collapsed()->persistCollapsed()->compact()->columns(12)->columnSpan(12),

                            SC\Section::make('Reduceri la cantitate')
                                ->schema([
                                    Forms\Components\Repeater::make('bulk_discounts')->label('')->hiddenLabel()->default([])->addActionLabel('+ Adaugă reducere')
                                        ->itemLabel(fn (array $state) => match($state['rule_type'] ?? null) {
                                            'buy_x_get_y' => 'Cumperi ' . ($state['buy_qty'] ?? '?') . ' → primești ' . ($state['get_qty'] ?? '?') . ' gratis',
                                            'buy_x_percent_off' => 'Min ' . ($state['min_qty'] ?? '?') . ' → ' . ($state['percent_off'] ?? '?') . '% off',
                                            'amount_off_per_ticket' => 'Min ' . ($state['min_qty'] ?? '?') . ' → -' . ($state['amount_off'] ?? '?') . '/bilet',
                                            'bundle_price' => ($state['min_qty'] ?? '?') . ' bilete = ' . ($state['bundle_total_price'] ?? '?'),
                                            default => 'Regulă nouă',
                                        })
                                        ->collapsible()->collapsed()->persistCollapsed()->columns(12)->columnSpan(12)
                                        ->schema([
                                            Forms\Components\Select::make('rule_type')->label('Tip regulă')
                                                ->options([ 'buy_x_get_y' => 'Cumperi X primești Y gratis', 'buy_x_percent_off' => 'Cumperi X bilete → % reducere', 'amount_off_per_ticket' => 'Reducere pe bilet (min cantitate)', 'bundle_price' => 'Preț pachet (X bilete la preț total)' ])
                                                ->required()->columnSpan(4)->live()->partiallyRenderAfterStateUpdated(),
                                            Forms\Components\TextInput::make('buy_qty')->label('Cumperi')->numeric()->minValue(1)->visible(fn ($get) => $get('rule_type') === 'buy_x_get_y')->columnSpan(4),
                                            Forms\Components\TextInput::make('get_qty')->label('Primești gratis')->numeric()->minValue(1)->visible(fn ($get) => $get('rule_type') === 'buy_x_get_y')->columnSpan(4),
                                            Forms\Components\TextInput::make('min_qty')->label('Cantitate min')->numeric()->minValue(1)->visible(fn ($get) => in_array($get('rule_type'), ['buy_x_percent_off','amount_off_per_ticket','bundle_price']))->columnSpan(4),
                                            Forms\Components\TextInput::make('percent_off')->label('% reducere')->numeric()->minValue(1)->maxValue(100)->visible(fn ($get) => $get('rule_type') === 'buy_x_percent_off')->columnSpan(4),
                                            Forms\Components\TextInput::make('amount_off')->label('Reducere/bilet')->numeric()->minValue(0.01)->visible(fn ($get) => $get('rule_type') === 'amount_off_per_ticket')->columnSpan(4),
                                            Forms\Components\TextInput::make('bundle_total_price')->label('Preț total pachet')->numeric()->minValue(0.01)->visible(fn ($get) => $get('rule_type') === 'bundle_price')->columnSpan(4),
                                        ]),
                                ])
                                ->collapsible()->collapsed()->persistCollapsed()->compact()->columns(12)->columnSpan(12),

                            SC\Section::make('Serie bilete')
                                ->schema([
                                    Forms\Components\TextInput::make('series_start')->label('Serie start')->inlineLabel($il)->placeholder('Ex: AMB-5-00001')->maxLength(50)
                                        ->afterStateHydrated(function ($state, SSet $set, SGet $get) {
                                            if (!$state) {
                                                $eventSeries = $get('../../event_series'); $capacity = $get('capacity'); $ticketTypeIdentifier = $get('id') ?: null;
                                                if ($eventSeries && $capacity && (int)$capacity > 0 && $ticketTypeIdentifier) { $set('series_start', $eventSeries . '-' . $ticketTypeIdentifier . '-00001'); }
                                            }
                                        })->columnSpan(6),
                                    Forms\Components\TextInput::make('series_end')->label('Serie end')->inlineLabel($il)->placeholder('Ex: AMB-5-00500')->maxLength(50)
                                        ->afterStateHydrated(function ($state, SSet $set, SGet $get) {
                                            if (!$state) {
                                                $eventSeries = $get('../../event_series'); $capacity = (int) ($get('capacity') ?: 0); $ticketTypeIdentifier = $get('id') ?: null; if ($capacity === -1) $capacity = 1000;
                                                if ($eventSeries && $capacity > 0 && $ticketTypeIdentifier) { $set('series_end', $eventSeries . '-' . $ticketTypeIdentifier . '-' . str_pad($capacity, 5, '0', STR_PAD_LEFT)); }
                                            }
                                        })->columnSpan(6),
                                ])
                                ->collapsible()->collapsed()->persistCollapsed()->compact()->columns(12)->columnSpan(12),

                        ]),
                ])->collapsible(),

            ]),

            SC\Tabs\Tab::make('Harta Locuri')
                ->key('harta')
                ->icon('heroicon-o-map')
                ->visible(fn (SGet $get) => (bool) $get('seating_layout_id'))
                ->schema([
                    Forms\Components\Placeholder::make('seating_map_editor')
                        ->hiddenLabel()
                        ->content(function (?Event $record) {
                            if (!$record || !$record->seating_layout_id) {
                                return new \Illuminate\Support\HtmlString('<div class="p-6 text-center text-gray-500">Salvați evenimentul cu o hartă de locuri selectată pentru a vedea vizualizarea și a aloca tipurile de bilete pe secțiuni/rânduri.</div>');
                            }
                            return new \Illuminate\Support\HtmlString(
                                view('filament.forms.components.seating-map-editor', ['record' => $record])->render()
                            );
                        })
                        ->columnSpanFull(),
                ]),

            SC\Tabs\Tab::make('SEO')->icon('heroicon-o-magnifying-glass')->schema([

            // SEO Section
            SC\Section::make('SEO')
                ->collapsible()
                ->schema([
                    Forms\Components\Select::make('seo_presets')
                        ->label('Adaugă chei SEO din șablon')
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
                        ->keyLabel('Cheie meta')
                        ->valueLabel('Valoare meta')
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
            ]),

                ]),
                ]),

                // ========== SIDEBAR (colspan 1) ==========
                SC\Group::make()->columnSpan(1)->schema([

                    SC\Section::make($tenantLanguage === 'ro' ? 'Publicare' : 'Publish')
                        ->schema([
                            Forms\Components\Toggle::make('is_published')
                                ->label($tenantLanguage === 'ro' ? 'Publicat' : 'Published')
                                ->onIcon('heroicon-m-eye')->offIcon('heroicon-m-eye-slash')
                                ->default(false)
                                ->columnSpanFull(),
                            Forms\Components\Placeholder::make('preview_link')
                                ->hiddenLabel()
                                ->content(function (?Event $record) use ($publicBase, $tenantLanguage) {
                                    if (!$record || !$record->exists) {
                                        return new \Illuminate\Support\HtmlString('<span class="text-sm text-gray-500">' . ($tenantLanguage === 'ro' ? 'Salvează evenimentul pentru link' : 'Save the event to get the link') . '</span>');
                                    }
                                    if (!$publicBase) { return null; }
                                    $url = $publicBase . '/spectacol/' . $record->slug;
                                    return new \Illuminate\Support\HtmlString('<a href="' . e($url) . '" target="_blank" class="inline-flex items-center justify-center gap-2 w-full px-4 py-2.5 text-sm font-semibold text-white rounded-lg bg-primary-600 hover:bg-primary-500 transition-colors">' . ($tenantLanguage === 'ro' ? 'Previzualizare' : 'Preview') . '</a>');
                                }),
                            Forms\Components\Placeholder::make('test_link')
                                ->hiddenLabel()
                                ->visible(fn (?Event $record) => $record && $record->exists && $publicBase)
                                ->content(function (?Event $record) use ($publicBase, $tenantLanguage) {
                                    if (!$record || !$record->exists || !$publicBase) { return null; }
                                    $url = $publicBase . '/spectacol/' . $record->slug . '?preview=1';
                                    $label = $tenantLanguage === 'ro' ? 'Link test' : 'Test link';
                                    $copied = $tenantLanguage === 'ro' ? 'Copiat!' : 'Copied!';
                                    return new \Illuminate\Support\HtmlString('<button type="button" onclick="navigator.clipboard.writeText(\'' . e($url) . '\'); this.querySelector(\'span\').textContent=\'' . $copied . '\'; setTimeout(() => this.querySelector(\'span\').textContent=\'' . $label . '\', 2000);" class="inline-flex items-center justify-center w-full gap-2 px-4 py-2 text-sm font-semibold rounded-lg cursor-pointer text-amber-200 bg-amber-600/30 hover:bg-amber-600/50"><span>' . $label . '</span></button>');
                                }),
                            Forms\Components\TextInput::make('access_password')
                                ->label($tenantLanguage === 'ro' ? 'Parolă acces eveniment' : 'Event access password')
                                ->placeholder($tenantLanguage === 'ro' ? 'Lasă gol pentru acces liber' : 'Leave empty for open access')
                                ->prefixIcon('heroicon-o-lock-closed')
                                ->columnSpanFull(),
                            Forms\Components\TextInput::make('redirect_url')
                                ->label('Redirecționare')
                                ->url()->maxLength(500)->placeholder('https://...')
                                ->prefixIcon('heroicon-o-arrow-top-right-on-square')
                                ->hintIcon('heroicon-o-information-circle', tooltip: $tenantLanguage === 'ro' ? 'Dacă setezi un URL, evenimentul apare în listări dar link-ul duce către acest URL.' : 'If set, the event appears in listings but the link goes to this URL.')
                                ->columnSpanFull(),
                        ]),

                    SC\Section::make($tenantLanguage === 'ro' ? 'Activitate recentă' : 'Recent activity')
                        ->icon('heroicon-o-clock')->compact()->collapsed()
                        ->visible(fn (?Event $record) => $record && $record->exists)
                        ->schema([
                            Forms\Components\Placeholder::make('recent_activity')
                                ->hiddenLabel()
                                ->content(function (?Event $record) use ($tenantLanguage) {
                                    if (!$record) { return ''; }
                                    $html = '<div class="space-y-2 text-sm">';
                                    if ($record->updated_at) { $html .= '<div>' . ($tenantLanguage === 'ro' ? 'Modificat' : 'Updated') . ': ' . e($record->updated_at->diffForHumans()) . '</div>'; }
                                    if ($record->created_at) { $html .= '<div class="text-gray-400">' . ($tenantLanguage === 'ro' ? 'Creat' : 'Created') . ': ' . e($record->created_at->diffForHumans()) . '</div>'; }
                                    $html .= '</div>';
                                    return new \Illuminate\Support\HtmlString($html);
                                }),
                        ]),

                    SC\Section::make($tenantLanguage === 'ro' ? 'Checklist publicare' : 'Publish checklist')
                        ->icon('heroicon-o-clipboard-document-check')->compact()
                        ->schema([
                            Forms\Components\Placeholder::make('publish_checklist')
                                ->hiddenLabel()
                                ->live(onBlur: true)
                                ->content(function (SGet $get, ?Event $record) use ($tenantLanguage) {
                                    $has = fn ($v) => !empty($v);
                                    $hasTT = false;
                                    foreach ((array) ($get('ticketTypes') ?? []) as $tt) { if (!empty($tt['name'])) { $hasTT = true; break; } }
                                    if (!$hasTT && $record && $record->exists) { $hasTT = $record->ticketTypes()->count() > 0; }
                                    $hasImg = $record && (!empty($record->poster_url) || !empty($record->hero_image_url));
                                    $checks = [
                                        [$has($get("title.{$tenantLanguage}")), $tenantLanguage === 'ro' ? 'Titlu' : 'Title'],
                                        [$hasImg, $tenantLanguage === 'ro' ? 'Imagini' : 'Images'],
                                        [$has($get('venue_id')) || $has($get('venue_name')), $tenantLanguage === 'ro' ? 'Locație' : 'Venue'],
                                        [$has($get('event_date')) || $has($get('range_start_date')), $tenantLanguage === 'ro' ? 'Date' : 'Dates'],
                                        [$hasTT, $tenantLanguage === 'ro' ? 'Tipuri de bilete' : 'Ticket types'],
                                    ];
                                    $done = count(array_filter($checks, fn ($c) => $c[0]));
                                    $html = '<div class="space-y-2 text-sm">';
                                    foreach ($checks as $c) {
                                        $html .= '<div class="flex items-center gap-2"><span class="' . ($c[0] ? 'text-success-500' : 'text-gray-400') . '">' . ($c[0] ? '✓' : '○') . '</span><span>' . e($c[1]) . '</span></div>';
                                    }
                                    $html .= '</div><div class="mt-3 text-xs font-medium text-gray-400">' . $done . '/' . count($checks) . ' ' . ($tenantLanguage === 'ro' ? 'complet' : 'complete') . '</div>';
                                    return new \Illuminate\Support\HtmlString($html);
                                }),
                        ]),

                ]),
            ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        $tenant = auth()->user()->tenant;

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->getStateUsing(fn (Event $record) => $record->getTranslation('title', $tenant?->locale ?: 'ro'))
                    ->searchable()
                    ->sortable()
                    ->description(function (Event $record) use ($tenant) {
                        if ($record->tenant_id !== $tenant?->id) {
                            return 'Hosted event by ' . ($record->tenant?->public_name ?? $record->tenant?->name ?? 'Unknown');
                        }
                        return null;
                    }),
                Tables\Columns\TextColumn::make('ownership')
                    ->label('Tip')
                    ->badge()
                    ->getStateUsing(function (Event $record) use ($tenant) {
                        return $record->tenant_id === $tenant?->id ? 'Evenimentul tău' : 'Găzduit';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Evenimentul tău' => 'success',
                        'Găzduit' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Organizator')
                    ->getStateUsing(fn (Event $record) => $record->tenant?->public_name ?? $record->tenant?->name)
                    ->visible(fn () => $tenant?->ownsVenues() ?? false)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('venue.name')
                    ->label('Locație')
                    ->sortable(),
                Tables\Columns\TextColumn::make('event_date')
                    ->label('Dată')
                    ->date()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_cancelled')
                    ->boolean()
                    ->label('Anulat'),
                Tables\Columns\IconColumn::make('is_sold_out')
                    ->boolean()
                    ->label('Epuizat'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_cancelled'),
                Tables\Filters\TernaryFilter::make('is_sold_out'),
                Tables\Filters\SelectFilter::make('ownership')
                    ->label('Tip eveniment')
                    ->options([
                        'own' => 'Evenimentele tale',
                        'hosted' => 'Evenimente găzduite',
                    ])
                    ->query(function (Builder $query, array $data) use ($tenant) {
                        return match ($data['value'] ?? null) {
                            'own' => $query->where('tenant_id', $tenant?->id),
                            'hosted' => $query->where('tenant_id', '!=', $tenant?->id),
                            default => $query,
                        };
                    })
                    ->visible(fn () => $tenant?->ownsVenues() ?? false),
            ])
            ->actions([])
            ->bulkActions([])
            ->recordActions([
                Action::make('statistics')
                    ->label('Statistici')
                    ->icon('heroicon-o-chart-bar')
                    ->color('info')
                    ->url(fn (Event $record) => static::getUrl('statistics', ['record' => $record])),
                EditAction::make()
                    ->visible(fn (Event $record) => $record->tenant_id === $tenant?->id),
                Action::make('view-guest')
                    ->label('Vezi detalii')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (Event $record) => static::getUrl('view-guest', ['record' => $record]))
                    ->visible(fn (Event $record) => $record->tenant_id !== $tenant?->id),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function ($records) use ($tenant) {
                            // Filter out hosted events - can only delete own events
                            return $records->filter(fn ($record) => $record->tenant_id === $tenant?->id);
                        }),
                ]),
            ])
            ->checkIfRecordIsSelectableUsing(fn (Event $record) => $record->tenant_id === $tenant?->id)
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
            'import' => Pages\ImportEvents::route('/import'),
        ];
    }
}
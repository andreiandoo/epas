<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\EventResource\Pages;
use App\Filament\Marketplace\Resources\ArtistResource;
use App\Filament\Marketplace\Resources\VenueResource;
use App\Models\Artist;
use App\Models\Event;
use App\Models\Tour;
use App\Models\EventGenre;
use App\Models\EventTag;
use App\Models\EventType;
use App\Models\MarketplaceCity;
use App\Models\MarketplaceEventCategory;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceRegion;
use App\Models\Tax\GeneralTax;
use App\Models\Venue;
use App\Models\Seating\SeatingLayout;
use App\Models\MarketplaceTaxTemplate;
use App\Models\EventGeneratedDocument;
use App\Models\OrganizerDocument;
use App\Models\MarketplaceEvent;
use App\Models\TicketType;
use Illuminate\Support\Facades\Storage;
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
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class EventResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = Event::class;
    protected static ?string $navigationLabel = 'Evenimente';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-calendar';
    protected static \UnitEnum|string|null $navigationGroup = null;
    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) return null;

        return (string) static::getEloquentQuery()->count();
    }

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();

        return parent::getEloquentQuery()
            ->with(['ticketTypes:id,event_id,status,active_until,sales_end_at,scheduled_at'])
            ->where('marketplace_client_id', $marketplace?->id)
            ->whereNull('parent_id'); // Hide child events from main list
    }

    public static function form(Schema $schema): Schema
    {
        $today = Carbon::today();
        $marketplace = static::getMarketplaceClient();

        // For past/ended events, remove minDate constraints so all fields can be edited freely
        $minDateForEvent = fn (mixed $record = null) => static::isEventEnded($record instanceof Event ? $record : null) ? null : $today;

        // Inline labels for ticket type fields — set to true for inline, false for stacked
        $il = false;

        // Get tenant's language (check both 'language' and 'locale' columns)
        // Default to 'ro' (Romanian) for this marketplace
        // Use empty() check because ?? doesn't catch empty strings ''
        $lang = $marketplace->language ?? $marketplace->locale ?? null;
        $marketplaceLanguage = (!empty($lang)) ? $lang : 'ro';

        // Translation helper for labels
        $t = function (string $ro, string $en) use ($marketplaceLanguage): string {
            return $marketplaceLanguage === 'ro' ? $ro : $en;
        };

        return $schema->schema([
            // Hidden marketplace_client_id field
            Forms\Components\Hidden::make('marketplace_client_id')
                ->default($marketplace?->id),

            SC\Grid::make(4)->schema([
                // ========== COLOANA STÂNGĂ (3/4) ==========
                SC\Group::make()
                    ->columnSpan(3)
                    ->schema([
                        SC\Tabs::make('EventTabs')
                            ->persistTabInQueryString()
                            ->tabs([
                                // ========== TAB 1: DETALII ==========
                                SC\Tabs\Tab::make($t('Detalii', 'Details'))
                                    ->key('detalii')
                                    ->icon('heroicon-o-document-text')
                                    ->schema([
                        // BASICS - Single Language based on Tenant setting
                        SC\Section::make($t('Detalii eveniment', 'Event Details'))
                            ->schema([
                                SC\Group::make()
                                    ->schema([
                                        Forms\Components\TextInput::make("title.{$marketplaceLanguage}")
                                            ->label($t('Titlu eveniment', 'Event title'))
                                            ->required()
                                            ->maxLength(190)
                                            ->live(onBlur: true)
                                            ->skipRenderAfterStateUpdated()
                                            ->afterStateUpdated(function ($state, SSet $set, SGet $get, ?Event $record) {
                                                // Slug is auto-generated ONLY on initial create.
                                                // On edit, never auto-overwrite an existing slug from title changes;
                                                // the admin can edit the slug manually if desired.
                                                if (!$state) {
                                                    return;
                                                }
                                                $baseSlug = Str::slug($state);
                                                if ($record && $record->exists && $record->id) {
                                                    // EDIT: only fill slug if it's currently empty; never overwrite.
                                                    if (!$get('slug')) {
                                                        $set('slug', $baseSlug . '-' . $record->id);
                                                    }
                                                    if (!$get('event_series')) {
                                                        $set('event_series', 'AMB-' . $record->id);
                                                    }
                                                } else {
                                                    // CREATE: auto-generate from title using next expected ID.
                                                    $nextId = (Event::max('id') ?? 0) + 1;
                                                    $set('slug', $baseSlug . '-' . $nextId);
                                                    if (!$get('event_series')) {
                                                        $set('event_series', 'AMB-' . $nextId);
                                                    }
                                                }
                                            }),
                                        Forms\Components\TextInput::make('slug')
                                            ->label('Slug')
                                            ->maxLength(190)
                                            ->rule('alpha_dash')
                                            ->hintIcon('heroicon-o-information-circle', tooltip: $t('ID-ul se generează automat din titlu', 'ID is auto-generated from title')),
                                        Forms\Components\TextInput::make('event_series')
                                            ->label($t('Serie eveniment', 'Event series'))
                                            ->placeholder($t('Se generează automat: AMB-[ID]', 'Auto-generated: AMB-[ID]'))
                                            ->maxLength(50)
                                            ->hintIcon('heroicon-o-information-circle', tooltip: $t('Codul unic al seriei de bilete pentru acest eveniment', 'Unique ticket series code for this event'))
                                            ->disabled(fn (?Event $record) => $record && $record->exists && $record->event_series)
                                            ->dehydrated(true)
                                            ->afterStateHydrated(function ($state, SSet $set, ?Event $record) {
                                                // Auto-generate event_series if not set and record exists
                                                if (!$state && $record && $record->exists && $record->id) {
                                                    $set('event_series', 'AMB-' . $record->id);
                                                }
                                            }),
                                    ])->columns(3)->columnSpanFull(),
                            ]),

                        // FLAGS (section with background but no visible heading)
                        SC\Section::make()
                            ->headerActions([])
                            ->schema([
                                SC\Grid::make(5)->schema([
                                    Forms\Components\Toggle::make('is_sold_out')
                                        ->label($t('Sold out', 'Sold out'))
                                        ->onIcon('heroicon-m-lock-closed')
                                        ->offIcon('heroicon-m-lock-open')
                                        ->live()
                                        ->partiallyRenderAfterStateUpdated()
                                        ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                            if ($state) {
                                                if ($get('is_cancelled')) $set('is_cancelled', false);
                                            }
                                        })
                                        ->disabled(fn (SGet $get) => (bool) $get('is_cancelled')),
                                    Forms\Components\Toggle::make('door_sales_only')
                                        ->label($t('Doar la intrare', 'Door sales only'))
                                        ->onIcon('heroicon-m-key')
                                        ->offIcon('heroicon-m-key')
                                        ->disabled(fn (SGet $get) => (bool) $get('is_cancelled')),
                                    Forms\Components\Toggle::make('is_cancelled')
                                        ->label($t('Anulat', 'Cancelled'))
                                        ->onIcon('heroicon-m-x-circle')
                                        ->offIcon('heroicon-m-x-circle')
                                        ->live()
                                        ->partiallyRenderAfterStateUpdated()
                                        ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                            if ($state) {
                                                if ($get('is_postponed')) $set('is_postponed', false);
                                                if ($get('is_sold_out'))  $set('is_sold_out', false);
                                                if ($get('is_promoted'))  $set('is_promoted', false);
                                                $set('promoted_until', null);
                                            }
                                        }),
                                    Forms\Components\Toggle::make('is_postponed')
                                        ->label($t('Amânat', 'Postponed'))
                                        ->onIcon('heroicon-m-clock')
                                        ->offIcon('heroicon-m-clock')
                                        ->live()
                                        ->partiallyRenderAfterStateUpdated()
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
                                        ->label($t('Promovat', 'Promoted'))
                                        ->onIcon('heroicon-m-sparkles')
                                        ->offIcon('heroicon-m-sparkles')
                                        ->live()
                                        ->partiallyRenderAfterStateUpdated()
                                        ->afterStateUpdated(function ($state, SSet $set) {
                                            if (!$state) $set('promoted_until', null);
                                        })
                                        ->disabled(fn (SGet $get) => (bool) $get('is_cancelled')),
                                ]),

                                Forms\Components\RichEditor::make('cancel_reason')
                                    ->label($t('Motivul anulării', 'Cancellation reason'))
                                    ->toolbarButtons(['bold', 'italic', 'underline', 'link', 'bulletList', 'orderedList', 'undo', 'redo'])
                                    ->visible(fn (SGet $get) => (bool) $get('is_cancelled')),

                                SC\Grid::make(4)->schema([
                                    Forms\Components\DatePicker::make('postponed_date')
                                        ->label($t('Data nouă', 'New date'))
                                        ->minDate($minDateForEvent)
                                        ->native(false),
                                    Forms\Components\TimePicker::make('postponed_start_time')
                                        ->label($t('Ora start', 'Start time'))
                                        ->seconds(false)
                                        ->native(true),
                                    Forms\Components\TimePicker::make('postponed_door_time')
                                        ->label($t('Ora acces', 'Door time'))
                                        ->seconds(false)
                                        ->native(true),
                                    Forms\Components\TimePicker::make('postponed_end_time')
                                        ->label($t('Ora final', 'End time'))
                                        ->seconds(false)
                                        ->native(true),
                                ])->visible(fn (SGet $get) => (bool) $get('is_postponed')),

                                Forms\Components\RichEditor::make('postponed_reason')
                                    ->label($t('Motivul amânării', 'Postponement reason'))
                                    ->toolbarButtons(['bold', 'italic', 'underline', 'link', 'bulletList', 'orderedList', 'undo', 'redo'])
                                    ->visible(fn (SGet $get) => (bool) $get('is_postponed')),

                                Forms\Components\DatePicker::make('promoted_until')
                                    ->label($t('Promovat până la', 'Promoted until'))
                                    ->minDate($minDateForEvent)
                                    ->native(false)
                                    ->visible(fn (SGet $get) => (bool) $get('is_promoted')),
                            ])->columns(1),

                        // FEATURED SETTINGS (Marketplace only)
                        SC\Section::make($t('Setări Featured', 'Featured Settings'))
                            ->description($t('Controlează unde apare acest eveniment ca featured pe site', 'Control where this event appears as featured on the marketplace website'))
                            ->schema([
                                SC\Grid::make(3)->schema([
                                    Forms\Components\Toggle::make('is_homepage_featured')
                                        ->label($t('Featured pe Homepage', 'Homepage Featured'))
                                        ->hintIcon('heroicon-o-information-circle', tooltip: $t('Afișează în secțiunea hero/featured de pe homepage', 'Show on homepage hero/featured section'))
                                        ->onIcon('heroicon-m-home')
                                        ->offIcon('heroicon-m-home')
                                        ->live(onBlur: true)
                                        ->partiallyRenderAfterStateUpdated(),
                                    Forms\Components\Toggle::make('is_general_featured')
                                        ->label($t('Featured General', 'General Featured'))
                                        ->hintIcon('heroicon-o-information-circle', tooltip: $t('Afișează în listele generale de evenimente featured', 'Show in general featured events lists'))
                                        ->onIcon('heroicon-m-star')
                                        ->offIcon('heroicon-m-star')
                                        ->live(onBlur: true)
                                        ->partiallyRenderAfterStateUpdated(),
                                    Forms\Components\Toggle::make('is_category_featured')
                                        ->label($t('Featured în Categorie', 'Category Featured'))
                                        ->hintIcon('heroicon-o-information-circle', tooltip: $t('Afișează ca featured pe pagina categoriei sale', 'Show as featured in its category page'))
                                        ->onIcon('heroicon-m-tag')
                                        ->offIcon('heroicon-m-tag')
                                        ->live(onBlur: true)
                                        ->partiallyRenderAfterStateUpdated(),
                                ]),

                                // Homepage Featured Image - only shown when Homepage Featured is enabled
                                Forms\Components\FileUpload::make('homepage_featured_image')
                                    ->label($t('Imagine Featured Homepage', 'Homepage Featured Image'))
                                    ->helperText($t('Imagine specială pentru secțiunea featured de pe homepage (recomandat: 1920x600px)', 'Special image for homepage featured section (recommended: 1920x600px)'))
                                    ->image()
                                    ->directory('events/featured/homepage')
                                    ->disk('public')
                                    ->visibility('public')
                                    ->imageResizeMode('cover')
                                    ->imagePreviewHeight('150')
                                    ->visible(fn (SGet $get) => (bool) $get('is_homepage_featured'))
                                    ->columnSpanFull(),

                                // General/Category Featured Image (shared) - only shown when General or Category Featured is enabled
                                Forms\Components\FileUpload::make('featured_image')
                                    ->label($t('Imagine Featured (General/Categorie)', 'Featured Image (General/Category)'))
                                    ->helperText($t('Imagine pentru secțiunile featured generale și de categorie (recomandat: 800x450px)', 'Image for general and category featured sections (recommended: 800x450px)'))
                                    ->image()
                                    ->directory('events/featured')
                                    ->disk('public')
                                    ->visibility('public')
                                    ->imageResizeMode('cover')
                                    ->imagePreviewHeight('150')
                                    ->visible(fn (SGet $get) => (bool) $get('is_general_featured') || (bool) $get('is_category_featured'))
                                    ->columnSpanFull(),

                                // Custom Related Events
                                SC\Grid::make(1)->schema([
                                    Forms\Components\Toggle::make('has_custom_related')
                                        ->label($t('Evenimente Conexe Personalizate', 'Custom Related Events'))
                                        ->hintIcon('heroicon-o-information-circle', tooltip: $t('Selectează manual ce evenimente să apară în secțiunea "Îți recomandăm"', 'Manually select which events to show in the "Îți recomandăm" section'))
                                        ->onIcon('heroicon-m-queue-list')
                                        ->offIcon('heroicon-m-queue-list')
                                        ->live(onBlur: true)
                                        ->partiallyRenderAfterStateUpdated(),

                                    Forms\Components\Select::make('custom_related_event_ids')
                                        ->label($t('Selectează Evenimente Conexe', 'Select Related Events'))
                                        ->hintIcon('heroicon-o-information-circle', tooltip: $t('Alege evenimentele de afișat în secțiunea "Îți recomandăm" (max 8)', 'Choose events to display in the "Îți recomandăm" section (max 8)'))
                                        ->multiple()
                                        ->searchable()
                                        ->preload()
                                        ->maxItems(8)
                                        ->options(function (?Event $record) use ($marketplace) {
                                            return Event::query()
                                                ->where('marketplace_client_id', $marketplace?->id)
                                                ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                                ->where('is_cancelled', false)
                                                ->orderBy('event_date', 'desc')
                                                ->limit(100)
                                                ->get()
                                                ->mapWithKeys(fn ($event) => [
                                                    $event->id => ($event->title['ro'] ?? $event->title['en'] ?? 'Unnamed')
                                                        . ' (' . ($event->event_date?->format('d.m.Y') ?? 'No date') . ')'
                                                ]);
                                        })
                                        ->visible(fn (SGet $get) => (bool) $get('has_custom_related'))
                                        ->columnSpanFull(),
                                ]),
                            ])->columns(1),
                                    ]), // End Tab 1: Detalii

                                // ========== TAB 2: PROGRAM ==========
                                SC\Tabs\Tab::make($t('Program', 'Schedule'))
                                    ->key('program')
                                    ->icon('heroicon-o-calendar')
                                    ->lazy()
                                    ->schema([
                        // SCHEDULE
                        SC\Section::make($t('Program', 'Schedule'))
                            ->schema([
                                Forms\Components\Radio::make('duration_mode')
                                    ->label($t('Durată', 'Duration'))
                                    ->options([
                                        'single_day' => $t('O singură zi', 'Single day'),
                                        'range' => $t('Interval', 'Range'),
                                        'multi_day' => $t('Mai multe zile', 'Multiple days'),
                                        'recurring' => $t('Recurent', 'Recurring'),
                                    ])
                                    ->inline()
                                    ->default('single_day')
                                    ->required()
                                    ->live(),

                                // Single day
                                SC\Grid::make(4)->schema([
                                    Forms\Components\DatePicker::make('event_date')
                                        ->label($t('Data', 'Date'))
                                        ->minDate($minDateForEvent)
                                        ->native(false),
                                    Forms\Components\TimePicker::make('start_time')
                                        ->label($t('Ora start', 'Start time'))
                                        ->required(fn (SGet $get) => $get('duration_mode') === 'single_day'),
                                    Forms\Components\TimePicker::make('door_time')
                                        ->label($t('Ora acces', 'Door time'))
                                        ->seconds(false)
                                        ->native(true),
                                    Forms\Components\TimePicker::make('end_time')
                                        ->label($t('Ora final', 'End time'))
                                        ->seconds(false)
                                        ->native(true),
                                ])->visible(fn (SGet $get) => $get('duration_mode') === 'single_day'),

                                // Range
                                SC\Grid::make(4)->schema([
                                    Forms\Components\DatePicker::make('range_start_date')
                                        ->label($t('Data început', 'Start date'))
                                        ->minDate($minDateForEvent)
                                        ->native(false),
                                    Forms\Components\DatePicker::make('range_end_date')
                                        ->label($t('Data final', 'End date'))
                                        ->native(false),
                                    Forms\Components\TimePicker::make('range_start_time')
                                        ->label($t('Ora start', 'Start time'))
                                        ->seconds(false)
                                        ->native(true),
                                    Forms\Components\TimePicker::make('range_end_time')
                                        ->label($t('Ora final', 'End time'))
                                        ->seconds(false)
                                        ->native(true),
                                ])->visible(fn (SGet $get) => $get('duration_mode') === 'range'),

                                // Multi day
                                Forms\Components\Repeater::make('multi_slots')
                                    ->label($t('Zile și ore', 'Days & times'))
                                    ->schema([
                                        Forms\Components\DatePicker::make('date')
                                            ->label($t('Data', 'Date'))
                                            ->minDate(function (SGet $get) use ($today) {
                                                $currentDate = $get('date');
                                                if ($currentDate && \Carbon\Carbon::parse($currentDate)->isPast()) {
                                                    return null;
                                                }
                                                return $today;
                                            })
                                            ->native(false)
                                            ->required(),
                                        Forms\Components\TimePicker::make('start_time')
                                            ->label($t('Start', 'Start'))
                                            ->seconds(false)
                                            ->native(true),
                                        Forms\Components\TimePicker::make('door_time')
                                            ->label($t('Acces', 'Door'))
                                            ->seconds(false)
                                            ->native(true),
                                        Forms\Components\TimePicker::make('end_time')
                                            ->label($t('Final', 'End'))
                                            ->seconds(false)
                                            ->native(true),
                                    ])
                                    ->addActionLabel($t('Adaugă altă dată', 'Add another date'))
                                    ->default([])
                                    ->visible(fn (SGet $get) => $get('duration_mode') === 'multi_day')
                                    ->columns(4),

                                // Recurring
                                SC\Group::make()
                                    ->visible(fn (SGet $get) => $get('duration_mode') === 'recurring')
                                    ->schema([
                                        SC\Grid::make(4)->schema([
                                            Forms\Components\DatePicker::make('recurring_start_date')
                                                ->label($t('Data inițială', 'Initial date'))
                                                ->minDate($minDateForEvent)
                                                ->native(false)
                                                ->live(onBlur: true)
                                                ->skipRenderAfterStateUpdated()
                                                ->afterStateUpdated(function ($state, SSet $set) {
                                                    if (!$state) { $set('recurring_weekday', null); return; }
                                                    $w = Carbon::parse($state)->dayOfWeekIso;
                                                    $set('recurring_weekday', $w);
                                                }),
                                            Forms\Components\TextInput::make('recurring_weekday')
                                                ->label($t('Ziua săptămânii', 'Weekday'))
                                                ->disabled()
                                                ->dehydrated(false)
                                                ->formatStateUsing(function (SGet $get) use ($t) {
                                                    $mapRo = [1=>'Lun',2=>'Mar',3=>'Mie',4=>'Joi',5=>'Vin',6=>'Sâm',7=>'Dum'];
                                                    $mapEn = [1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'];
                                                    $map = $t('ro', 'en') === 'ro' ? $mapRo : $mapEn;
                                                    return $map[$get('recurring_weekday')] ?? '';
                                                }),
                                            Forms\Components\Select::make('recurring_frequency')
                                                ->label($t('Recurență', 'Recurrence'))
                                                ->options([
                                                    'weekly' => $t('Săptămânal', 'Weekly'),
                                                    'monthly_nth' => $t('Lunar (a N-a zi)', 'Monthly (Nth weekday)'),
                                                ])
                                                ->required()
                                                ->live(onBlur: true),
                                            Forms\Components\TextInput::make('recurring_count')
                                                ->label($t('Ocurențe', 'Occurrences'))
                                                ->numeric()
                                                ->minValue(1),
                                        ]),
                                        SC\Grid::make(2)
                                            ->visible(fn (SGet $get) => $get('recurring_frequency') === 'monthly_nth')
                                            ->schema([
                                                Forms\Components\Select::make('recurring_week_of_month')
                                                    ->label($t('Săptămâna din lună', 'Week of month'))
                                                    ->options([
                                                        1 => $t('Prima', 'First'),
                                                        2 => $t('A doua', 'Second'),
                                                        3 => $t('A treia', 'Third'),
                                                        4 => $t('A patra', 'Fourth'),
                                                        -1 => $t('Ultima', 'Last'),
                                                    ])
                                                    ->required(),
                                            ]),
                                        SC\Grid::make(3)->schema([
                                            Forms\Components\TimePicker::make('recurring_start_time')
                                                ->label($t('Ora start', 'Start time'))
                                                ->seconds(false)->native(true)
                                                ->required(),
                                            Forms\Components\TimePicker::make('recurring_door_time')
                                                ->label($t('Ora acces', 'Door time'))
                                                ->seconds(false)->native(true),
                                            Forms\Components\TimePicker::make('recurring_end_time')
                                                ->label($t('Ora final', 'End time'))
                                                ->seconds(false)->native(true),
                                        ]),
                                    ]),
                            ])->columns(1),

                        // LOCATION & LINKS
                        SC\Section::make($t('Locație și Link-uri', 'Location & Links'))
                            ->schema([
                                Forms\Components\Select::make('venue_id')
                                    ->label($t('Locație', 'Venue'))
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->options(function () use ($marketplace) {
                                        $venueCountries = self::expandCountryVariants($marketplace?->settings['venue_countries'] ?? []);
                                        return Venue::query()
                                            ->when(!empty($venueCountries), fn ($q) => $q->whereIn('country', $venueCountries))
                                            ->get()
                                            ->mapWithKeys(fn ($venue) => [
                                                $venue->id => $venue->getTranslation('name', app()->getLocale())
                                                    . ($venue->city ? ' (' . $venue->city . ')' : '')
                                            ])
                                            ->sort();
                                    })
                                    ->getOptionLabelUsing(function ($value) {
                                        $venue = Venue::find($value);
                                        if (!$venue) return $value;
                                        return $venue->getTranslation('name', app()->getLocale())
                                            . ($venue->city ? ' (' . $venue->city . ')' : '');
                                    })
                                    ->afterStateUpdated(function ($state, SSet $set) use ($marketplace, $marketplaceLanguage) {
                                        if ($state) {
                                            $venue = Venue::find($state);
                                            if ($venue) {
                                                $set('address', $venue->address ?? $venue->full_address ?? '');
                                                $set('website_url', $venue->website_url ?? '');

                                                // Auto-fill marketplace_city_id by matching venue city name
                                                if ($venue->city) {
                                                    $cityName = strtolower(trim(Str::ascii($venue->city)));
                                                    $matchedCity = MarketplaceCity::where('marketplace_client_id', $marketplace?->id)
                                                        ->where('is_visible', true)
                                                        ->get()
                                                        ->first(function ($city) use ($cityName) {
                                                            // Check all language variants (diacritic-insensitive)
                                                            $nameVariants = is_array($city->name) ? $city->name : [];
                                                            foreach ($nameVariants as $lang => $name) {
                                                                if (strtolower(trim(Str::ascii($name))) === $cityName) {
                                                                    return true;
                                                                }
                                                            }
                                                            return false;
                                                        });

                                                    if ($matchedCity) {
                                                        $set('marketplace_city_id', $matchedCity->id);
                                                    } else {
                                                        // City doesn't exist in marketplace — auto-create it
                                                        // Normalize country to ISO 2-letter code
                                                        $countryRaw = $venue->country ?? 'RO';
                                                        $countryCode = mb_strlen($countryRaw) === 2 ? strtoupper($countryRaw) : (collect([
                                                            'Romania' => 'RO', 'Germany' => 'DE', 'France' => 'FR', 'Spain' => 'ES',
                                                            'Italy' => 'IT', 'Austria' => 'AT', 'Hungary' => 'HU', 'Bulgaria' => 'BG',
                                                            'Moldova' => 'MD', 'Serbia' => 'RS', 'Croatia' => 'HR', 'Greece' => 'GR',
                                                            'Poland' => 'PL', 'Czech Republic' => 'CZ', 'Slovakia' => 'SK',
                                                            'United Kingdom' => 'GB', 'Netherlands' => 'NL', 'Belgium' => 'BE',
                                                            'Switzerland' => 'CH', 'Portugal' => 'PT', 'Sweden' => 'SE',
                                                            'România' => 'RO', 'Deutschland' => 'DE',
                                                        ])->get($countryRaw, strtoupper(mb_substr($countryRaw, 0, 2))));
                                                        $newCity = MarketplaceCity::create([
                                                            'marketplace_client_id' => $marketplace?->id,
                                                            'name' => ['ro' => $venue->city, 'en' => $venue->city],
                                                            'country' => $countryCode,
                                                            'latitude' => $venue->lat,
                                                            'longitude' => $venue->lng,
                                                            'is_visible' => true,
                                                        ]);
                                                        $set('marketplace_city_id', $newCity->id);
                                                    }
                                                }

                                                // Try to auto-match tax registry by country/county/city
                                                $normalize = fn ($s) => strtolower(trim(\Illuminate\Support\Str::ascii($s ?? '')));
                                                $vCountry = $normalize($venue->country);
                                                $vCounty = $normalize($venue->state);
                                                $vCity = $normalize($venue->city);

                                                $matchedRegistry = \App\Models\MarketplaceTaxRegistry::where('marketplace_client_id', $marketplace?->id)
                                                    ->where('is_active', true)
                                                    ->get()
                                                    ->first(function ($r) use ($normalize, $vCountry, $vCounty, $vCity) {
                                                        $rCountry = $normalize($r->country);
                                                        $rCounty = $normalize($r->county);
                                                        $rCity = $normalize($r->city);

                                                        $countryMatch = !$rCountry || !$vCountry || str_contains($rCountry, $vCountry) || str_contains($vCountry, $rCountry);
                                                        $countyMatch = !$rCounty || !$vCounty || str_contains($rCounty, $vCounty) || str_contains($vCounty, $rCounty);
                                                        $cityMatch = !$rCity || !$vCity || str_contains($rCity, $vCity) || str_contains($vCity, $rCity);

                                                        return $countryMatch && $countyMatch && $cityMatch && ($vCity || $vCounty);
                                                    });

                                                $set('marketplace_tax_registry_id', $matchedRegistry?->id);
                                            }
                                        }
                                    })
                                    ->suffixActions([
                                        Action::make('edit_venue')
                                            ->icon('heroicon-o-pencil-square')
                                            ->tooltip($t('Editează locația', 'Edit venue'))
                                            ->url(fn (SGet $get) => $get('venue_id')
                                                ? VenueResource::getUrl('edit', ['record' => $get('venue_id')])
                                                : null)
                                            ->openUrlInNewTab()
                                            ->visible(fn (SGet $get) => (bool) $get('venue_id')),
                                        Action::make('create_venue')
                                            ->icon('heroicon-o-plus-circle')
                                            ->tooltip($t('Adaugă locație nouă', 'Add new venue'))
                                            ->url(fn () => VenueResource::getUrl('create'))
                                            ->openUrlInNewTab(),
                                    ])
                                    ->nullable(),
                                Forms\Components\TextInput::make('suggested_venue_name')
                                    ->label($t('Locație sugerată de organizator', 'Suggested venue by organizer'))
                                    ->disabled()
                                    ->visible(fn (?Event $record) => $record && !empty($record->suggested_venue_name))
                                    ->helperText($t(
                                        'Organizatorul a introdus manual acest nume de locație. Adaugă locația în bibliotecă și selecteaz-o din câmpul de mai sus.',
                                        'The organizer manually entered this venue name. Add it to the venue library and select it above.'
                                    ))
                                    ->prefixIcon('heroicon-o-exclamation-triangle')
                                    ->extraAttributes(['class' => 'bg-amber-50 dark:bg-amber-900/20']),
                                Forms\Components\Select::make('seating_layout_id')
                                    ->label($t('Harta de locuri', 'Seating Layout'))
                                    ->searchable()
                                    ->preload()
                                    ->live(onBlur: true)
                                    ->visible(function (SGet $get) {
                                        $venueId = $get('venue_id');
                                        if (!$venueId) return false;
                                        return SeatingLayout::where('venue_id', $venueId)
                                            ->where('status', 'published')
                                            ->exists();
                                    })
                                    ->options(function (SGet $get) {
                                        $venueId = $get('venue_id');
                                        if (!$venueId) return [];

                                        return SeatingLayout::query()
                                            ->where('venue_id', $venueId)
                                            ->where('status', 'published')
                                            ->orderBy('name')
                                            ->get()
                                            ->mapWithKeys(fn ($layout) => [
                                                $layout->id => $layout->name . ' (' . $layout->sections()->count() . ' sections)'
                                            ]);
                                    })
                                    ->helperText($t('Selectează o hartă de locuri pentru locuri numerotate. Lasă gol pentru acces general.', 'Select a seating layout for assigned seating. Leave empty for general admission.'))
                                    ->nullable(),
                                Forms\Components\Select::make('marketplace_city_id')
                                    ->label($t('Oraș', 'City'))
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
                                    ->placeholder($t('Selectează un oraș', 'Select a city'))
                                    ->hintIcon('heroicon-o-information-circle', tooltip: $t('Filtrează evenimentele pe site după oraș', 'Filter events by city on the website'))
                                    ->nullable(),
                                Forms\Components\TextInput::make('address')
                                    ->label($t('Adresă', 'Address'))
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('website_url')
                                    ->label('Website')
                                    ->url()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('facebook_url')
                                    ->label($t('Eveniment Facebook', 'Facebook Event'))
                                    ->url()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('event_website_url')
                                    ->label($t('Website Eveniment', 'Event Website'))
                                    ->url()
                                    ->maxLength(255),
                            ])->columns(2),

                        // TAX REGISTRY (linked to venue location)
                        SC\Section::make($t('Direcție fiscală (Tax Registry)', 'Tax Registry'))
                            ->icon('heroicon-o-building-library')
                            ->description($t('Direcția fiscală locală folosită pentru declarațiile de impozit pe spectacole.', 'Local tax authority used for entertainment tax declarations.'))
                            ->collapsible()
                            ->visible(fn (SGet $get) => (bool) $get('venue_id'))
                            ->schema([
                                Forms\Components\Placeholder::make('tax_registry_match_info')
                                    ->hiddenLabel()
                                    ->content(function (SGet $get) use ($t) {
                                        $registryId = $get('marketplace_tax_registry_id');
                                        if ($registryId) {
                                            $registry = \App\Models\MarketplaceTaxRegistry::find($registryId);
                                            if ($registry) {
                                                $name = e($registry->name);
                                                $location = e(implode(', ', array_filter([$registry->city, $registry->county, $registry->country])));
                                                return new HtmlString(
                                                    '<div style="display:flex;align-items:center;gap:8px;padding:8px 12px;border:1px solid #10b981;background:#f0fdf4;border-radius:6px;color:#065f46;font-size:13px;">'
                                                    . '<svg style="width:18px;height:18px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
                                                    . '<div><strong>' . $t('Tax Registry identificat:', 'Tax Registry found:') . '</strong> ' . $name
                                                    . '<div style="font-size:11px;color:#047857;margin-top:2px;">' . $location . '</div></div>'
                                                    . '</div>'
                                                );
                                            }
                                        }
                                        return new HtmlString(
                                            '<div style="display:flex;align-items:center;gap:8px;padding:8px 12px;border:1px solid #f59e0b;background:#fffbeb;border-radius:6px;color:#92400e;font-size:13px;">'
                                            . '<svg style="width:18px;height:18px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>'
                                            . '<div>' . $t('Niciun Tax Registry nu se potrivește automat. Selectează manual din dropdown-ul de mai jos.', 'No Tax Registry matches automatically. Select manually from dropdown below.') . '</div>'
                                            . '</div>'
                                        );
                                    }),

                                Forms\Components\Select::make('marketplace_tax_registry_id')
                                    ->label($t('Tax Registry', 'Tax Registry'))
                                    ->options(function () use ($marketplace) {
                                        return \App\Models\MarketplaceTaxRegistry::where('marketplace_client_id', $marketplace?->id)
                                            ->where('is_active', true)
                                            ->get()
                                            ->mapWithKeys(fn ($r) => [
                                                $r->id => $r->name . ' — ' . implode(', ', array_filter([$r->city, $r->county, $r->country])),
                                            ]);
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->live(),
                            ]),
                                    ]), // End Tab 2: Program

                                // ========== TAB 3: CONȚINUT ==========
                                SC\Tabs\Tab::make($t('Conținut', 'Content'))
                                    ->key('continut')
                                    ->icon('heroicon-o-pencil-square')
                                    ->lazy()
                                    ->schema([
                        // CONTENT - Single Language
                        SC\Section::make($t('Conținut', 'Content'))
                            ->schema([
                                Forms\Components\Textarea::make("short_description.{$marketplaceLanguage}")
                                    ->label($t('Descriere scurtă', 'Short description'))
                                    ->rows(3)
                                    ->maxLength(1000)
                                    ->live(onBlur: true)
                                    ->helperText(function ($state) use ($t) {
                                        $wordCount = $state ? str_word_count(strip_tags($state)) : 0;
                                        $remaining = 120 - $wordCount;
                                        $color = $remaining < 0 ? 'text-danger-500' : ($remaining < 20 ? 'text-warning-500' : 'text-gray-500');
                                        return new \Illuminate\Support\HtmlString(
                                            "<span class='{$color}'>{$wordCount}/120 " . $t('cuvinte', 'words') . "</span>" .
                                            ($remaining < 0 ? " <span class='font-semibold text-danger-500'>(" . $t('depășit cu', 'exceeded by') . " " . abs($remaining) . " " . $t('cuvinte', 'words') . ")</span>" : '')
                                        );
                                    })
                                    ->rules([
                                        function () use ($t) {
                                            return function (string $attribute, $value, \Closure $fail) use ($t) {
                                                $wordCount = str_word_count(strip_tags($value ?? ''));
                                                if ($wordCount > 120) {
                                                    $fail($t("Descrierea scurtă nu poate depăși 120 de cuvinte. Ai {$wordCount} cuvinte.", "Short description cannot exceed 120 words. You have {$wordCount} words."));
                                                }
                                            };
                                        },
                                    ])
                                    ->columnSpanFull(),
                                Forms\Components\RichEditor::make("description.{$marketplaceLanguage}")
                                    ->label($t('Descriere', 'Description'))
                                    ->columnSpanFull()
                                    ->fileAttachmentsDisk('public')
                                    ->fileAttachmentsDirectory('event-descriptions')
                                    ->fileAttachmentsVisibility('public'),
                                Forms\Components\RichEditor::make("ticket_terms.{$marketplaceLanguage}")
                                    ->label($t('Termeni bilete', 'Ticket terms'))
                                    ->columnSpanFull()
                                    ->default($marketplace?->ticket_terms ?? null),
                            ])->columns(1),

                        // MEDIA - only visible when at least one image is uploaded (use header action to upload)
                        SC\Section::make('Media')
                            ->visible(fn (?Event $record) => $record && (!empty($record->poster_url) || !empty($record->hero_image_url)))
                            ->schema([
                                Forms\Components\Placeholder::make('poster_preview')
                                    ->label($t('Poster (vertical)', 'Poster (vertical)'))
                                    ->content(function (?Event $record) use ($t) {
                                        if (!$record || empty($record->poster_url)) {
                                            return $t('Nicio imagine', 'No image');
                                        }
                                        $url = \Illuminate\Support\Facades\Storage::disk('public')->url($record->poster_url);
                                        return new \Illuminate\Support\HtmlString(
                                            "<img src='{$url}' alt='Poster' class='rounded-lg shadow max-h-48' style='object-fit: contain;' />"
                                        );
                                    }),
                                Forms\Components\Placeholder::make('hero_preview')
                                    ->label($t('Imagine hero (orizontală)', 'Hero image (horizontal)'))
                                    ->content(function (?Event $record) use ($t) {
                                        if (!$record || empty($record->hero_image_url)) {
                                            return $t('Nicio imagine', 'No image');
                                        }
                                        $url = \Illuminate\Support\Facades\Storage::disk('public')->url($record->hero_image_url);
                                        return new \Illuminate\Support\HtmlString(
                                            "<img src='{$url}' alt='Hero' class='rounded-lg shadow max-h-48' style='object-fit: contain;' />"
                                        );
                                    }),
                            ])->columns(2),

                        // TAXONOMIES
                        SC\Section::make($t('Taxonomii și Relații', 'Taxonomies & Relations'))
                            ->schema([
                                Forms\Components\Select::make('marketplace_event_category_id')
                                    ->label($t('Categorie eveniment', 'Event Category'))
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
                                    ->placeholder($t('Selectează o categorie', 'Select a category'))
                                    ->hintIcon('heroicon-o-information-circle', tooltip: $t('Categorie personalizată de eveniment marketplace', 'Custom marketplace event category'))
                                    ->live(onBlur: true)
                                    ->partiallyRenderAfterStateUpdated()
                                    ->afterStateUpdated(function ($state, SSet $set) {
                                        // Auto-fill eventTypes from the category's linked event types
                                        if ($state) {
                                            $category = MarketplaceEventCategory::find($state);
                                            if ($category && !empty($category->event_type_ids)) {
                                                $set('eventTypes', $category->event_type_ids);
                                            }
                                        }
                                    })
                                    ->nullable(),

                                Forms\Components\Select::make('manifestation_type')
                                    ->label($t('Tip manifestare', 'Manifestation Type'))
                                    ->options([
                                        'muzicala' => $t('Muzicală', 'Musical'),
                                        'artistica' => $t('Artistică', 'Artistic'),
                                        'teatrala' => $t('Teatrală', 'Theatrical'),
                                        'standup' => $t('Stand-up', 'Stand-up'),
                                        'sportiva' => $t('Sportivă', 'Sports'),
                                        'altele' => $t('Altele', 'Other'),
                                    ])
                                    ->placeholder($t('Selectează tipul', 'Select type'))
                                    ->nullable(),

                                Forms\Components\Select::make('eventTypes')
                                    ->label($t('Tipuri eveniment', 'Event types'))
                                    ->relationship(
                                        name: 'eventTypes',
                                        modifyQueryUsing: fn (Builder $query) => $query->whereNotNull('parent_id')
                                    )
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('name', app()->getLocale()))
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->maxItems(2)
                                    ->live(onBlur: true)
                                    ->partiallyRenderAfterStateUpdated()
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
                                    ->label($t('Genuri eveniment', 'Event genres'))
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
                                    ->label($t('Artiști', 'Artists'))
                                    ->relationship('artists', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->live(onBlur: true)
                                    ->partiallyRenderAfterStateUpdated()
                                    ->suffixAction(
                                        Action::make('create_artist')
                                            ->icon('heroicon-o-plus-circle')
                                            ->tooltip($t('Adaugă artist nou', 'Add new artist'))
                                            ->url(fn () => ArtistResource::getUrl('create'))
                                            ->openUrlInNewTab()
                                    ),

                                Forms\Components\Select::make('tags')
                                    ->label($t('Tag-uri eveniment', 'Event tags'))
                                    ->relationship('tags', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->label($t('Nume tag', 'Tag name'))
                                            ->required()
                                            ->maxLength(100)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, SSet $set) {
                                                if ($state) {
                                                    $set('slug', Str::slug($state));
                                                }
                                            }),
                                        Forms\Components\TextInput::make('slug')
                                            ->label('Slug')
                                            ->maxLength(100)
                                            ->rule('alpha_dash'),
                                    ])
                                    ->createOptionUsing(function (array $data) use ($marketplace): int {
                                        $tag = EventTag::create([
                                            'marketplace_client_id' => $marketplace?->id,
                                            'name' => $data['name'],
                                            'slug' => $data['slug'] ?: Str::slug($data['name']),
                                        ]);
                                        return $tag->id;
                                    }),

                                // Artist ordering and headliner settings (visible when >2 artists)
                                SC\Section::make($t('Setări afișare artiști', 'Artist Display Settings'))
                                    ->description($t('Configurează ordinea afișării și statusul de headliner pentru artiști', 'Configure display order and headliner status for artists'))
                                    ->visible(fn (SGet $get) => count($get('artists') ?? []) > 2)
                                    ->collapsed()
                                    ->schema([
                                        Forms\Components\Repeater::make('artist_settings')
                                            ->label($t('Ordine și status artiști', 'Artist Order & Status'))
                                            ->hiddenLabel()
                                            ->default([])
                                            ->reorderable()
                                            ->reorderableWithButtons()
                                            ->columns(12)
                                            ->addable(false)
                                            ->deletable(false)
                                            ->schema([
                                                Forms\Components\Select::make('artist_id')
                                                    ->label($t('Artist', 'Artist'))
                                                    ->options(function () use ($marketplace) {
                                                        return \App\Models\Artist::query()
                                                            ->orderBy('name')
                                                            ->pluck('name', 'id');
                                                    })
                                                    ->disabled()
                                                    ->columnSpan(5),
                                                Forms\Components\Toggle::make('is_headliner')
                                                    ->label($t('Headliner', 'Headliner'))
                                                    ->inline(false)
                                                    ->columnSpan(3),
                                                Forms\Components\Toggle::make('is_co_headliner')
                                                    ->label($t('Co-Headliner', 'Co-Headliner'))
                                                    ->inline(false)
                                                    ->columnSpan(3),
                                                Forms\Components\Hidden::make('sort_order'),
                                            ])
                                            ->afterStateHydrated(function (SSet $set, SGet $get, ?Event $record) {
                                                if (!$record) return;

                                                $artists = $record->artists()->orderByPivot('sort_order')->get();
                                                $settings = $artists->map(function ($artist, $index) {
                                                    return [
                                                        'artist_id' => $artist->id,
                                                        'is_headliner' => (bool) $artist->pivot->is_headliner,
                                                        'is_co_headliner' => (bool) $artist->pivot->is_co_headliner,
                                                        'sort_order' => $index,
                                                    ];
                                                })->toArray();

                                                $set('artist_settings', $settings);
                                            })
                                            ->dehydrated(false)
                                            ->columnSpanFull(),

                                        Forms\Components\Placeholder::make('artist_settings_help')
                                            ->content('Drag and drop to reorder artists. The order here determines how they appear on the event page. Headliners will be displayed prominently.')
                                            ->columnSpanFull(),
                                    ])->columnSpanFull(),

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

                                        // Check if venue has historical monument tax enabled
                                        $venueId = $get('venue_id');
                                        $venueHasMonumentTax = $venueId
                                            ? Venue::where('id', $venueId)->value('has_historical_monument_tax')
                                            : false;

                                        foreach ($allTaxes as $tax) {
                                            $isVatTax = str_contains(strtolower($tax->name ?? ''), 'tva') ||
                                                        str_contains(strtolower($tax->name ?? ''), 'vat');

                                            // Skip VAT if tenant is not a VAT payer
                                            if ($isVatTax && !$isVatPayer) {
                                                continue;
                                            }

                                            // Skip monument tax if venue doesn't have it enabled
                                            $isMonumentTax = str_contains(strtolower($tax->name ?? ''), 'monument');
                                            if ($isMonumentTax && !$venueHasMonumentTax) {
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
                            ])->columns(3),
                                    ]), // End Tab 3: Conținut

                                // ========== TAB: CONFIGURARE LOCAȚIE (doar leisure_venue) ==========
                                SC\Tabs\Tab::make($t('Configurare Locație', 'Venue Config'))
                                    ->key('venue-config')
                                    ->icon('heroicon-o-building-office')
                                    ->visible(fn (SGet $get) => ($get('display_template') ?? 'standard') === 'leisure_venue')
                                    ->schema([
                                        SC\Section::make($t('Sezoane', 'Seasons'))
                                            ->description($t('Definește sezoanele cu program și oră ultimă intrare. Exemplu: Vară (01 apr - 31 oct) și Iarnă (01 nov - 31 mar).', 'Define seasons with schedule and last entry time.'))
                                            ->schema([
                                                Forms\Components\Repeater::make('venue_config.seasons')
                                                    ->label('')
                                                    ->schema([
                                                        SC\Grid::make(4)->schema([
                                                            Forms\Components\TextInput::make('name')
                                                                ->label($t('Nume sezon', 'Season name'))
                                                                ->placeholder($t('ex: Vară', 'e.g. Summer'))
                                                                ->required(),
                                                            Forms\Components\TextInput::make('start')
                                                                ->label($t('Început (LL-ZZ)', 'Start (MM-DD)'))
                                                                ->placeholder('04-01')
                                                                ->required(),
                                                            Forms\Components\TextInput::make('end')
                                                                ->label($t('Sfârșit (LL-ZZ)', 'End (MM-DD)'))
                                                                ->placeholder('10-31')
                                                                ->required(),
                                                            Forms\Components\TextInput::make('last_entry')
                                                                ->label($t('Ultima intrare', 'Last entry'))
                                                                ->placeholder('18:30')
                                                                ->helperText($t('Vânzarea online se blochează după această oră', 'Online sales blocked after this time')),
                                                        ]),
                                                        Forms\Components\Repeater::make('schedule_list')
                                                            ->label($t('Program pe zile', 'Daily schedule'))
                                                            ->schema([
                                                                Forms\Components\Select::make('day')
                                                                    ->label($t('Zi', 'Day'))
                                                                    ->options([
                                                                        'mon' => $t('Luni', 'Monday'), 'tue' => $t('Marți', 'Tuesday'),
                                                                        'wed' => $t('Miercuri', 'Wednesday'), 'thu' => $t('Joi', 'Thursday'),
                                                                        'fri' => $t('Vineri', 'Friday'), 'sat' => $t('Sâmbătă', 'Saturday'),
                                                                        'sun' => $t('Duminică', 'Sunday'),
                                                                    ])
                                                                    ->required(),
                                                                Forms\Components\TextInput::make('open')
                                                                    ->label($t('Deschidere', 'Open'))
                                                                    ->placeholder('09:00'),
                                                                Forms\Components\TextInput::make('close')
                                                                    ->label($t('Închidere', 'Close'))
                                                                    ->placeholder('19:00'),
                                                            ])
                                                            ->columns(3)
                                                            ->reorderable(false)
                                                            ->defaultItems(7)
                                                            ->addActionLabel($t('Adaugă zi', 'Add day')),
                                                    ])
                                                    ->addActionLabel($t('Adaugă sezon', 'Add season'))
                                                    ->collapsible()
                                                    ->itemLabel(fn (array $state) => ($state['name'] ?? 'Sezon') . ' (' . ($state['start'] ?? '') . ' — ' . ($state['end'] ?? '') . ')'),
                                            ]),

                                        SC\Section::make($t('Configurare generală', 'General Configuration'))
                                            ->schema([
                                                SC\Grid::make(3)->schema([
                                                    Forms\Components\TextInput::make('venue_config.max_advance_days')
                                                        ->label($t('Zile maxime în avans', 'Max advance days'))
                                                        ->numeric()
                                                        ->default(90)
                                                        ->minValue(1)
                                                        ->maxValue(365)
                                                        ->helperText($t('Câte zile în viitor pot rezerva clienții', 'How many days ahead customers can book')),
                                                    Forms\Components\TextInput::make('venue_config.contact_phone')
                                                        ->label($t('Telefon contact', 'Contact phone'))
                                                        ->tel(),
                                                    Forms\Components\TextInput::make('venue_config.directions_url')
                                                        ->label($t('Link Google Maps', 'Google Maps URL'))
                                                        ->url()
                                                        ->placeholder('https://maps.google.com/...'),
                                                ]),
                                                Forms\Components\TagsInput::make('venue_config.amenities')
                                                    ->label($t('Facilități', 'Amenities'))
                                                    ->placeholder($t('ex: Piscină, Tobogane, Restaurant...', 'e.g. Pool, Slides, Restaurant...'))
                                                    ->helperText($t('Afișate ca badge-uri pe pagina publică', 'Shown as badges on public page')),
                                                Forms\Components\TagsInput::make('venue_config.closed_dates')
                                                    ->label($t('Zile închise', 'Closed dates'))
                                                    ->placeholder('YYYY-MM-DD')
                                                    ->helperText($t('Formatat: 2026-12-25 (Crăciun), 2026-01-01 (An Nou)', 'Format: 2026-12-25 (Christmas), 2026-01-01 (New Year)')),
                                            ]),

                                        SC\Section::make($t('Reguli de preț automate', 'Automatic Pricing Rules'))
                                            ->description($t('Definește majorări automate de preț pe anumite zile ale săptămânii. Override-urile manuale per dată suprascriu aceste reguli.', 'Define automatic price increases on certain days. Manual per-date overrides take priority.'))
                                            ->schema([
                                                Forms\Components\Repeater::make('venue_config.pricing_rules')
                                                    ->label('')
                                                    ->schema([
                                                        Forms\Components\CheckboxList::make('days')
                                                            ->label($t('Zile', 'Days'))
                                                            ->options([
                                                                'mon' => $t('Lu', 'Mon'), 'tue' => $t('Ma', 'Tue'), 'wed' => $t('Mi', 'Wed'),
                                                                'thu' => $t('Jo', 'Thu'), 'fri' => $t('Vi', 'Fri'), 'sat' => $t('Sâ', 'Sat'), 'sun' => $t('Du', 'Sun'),
                                                            ])
                                                            ->columns(7),
                                                        SC\Grid::make(3)->schema([
                                                            Forms\Components\Select::make('type')
                                                                ->label($t('Tip', 'Type'))
                                                                ->options([
                                                                    'percent' => $t('Procent (+%)', 'Percent (+%)'),
                                                                    'fixed' => $t('Sumă fixă (+RON)', 'Fixed amount (+RON)'),
                                                                ])
                                                                ->default('percent'),
                                                            Forms\Components\TextInput::make('value')
                                                                ->label($t('Valoare', 'Value'))
                                                                ->numeric()
                                                                ->placeholder($t('ex: 20 (pentru +20%)', 'e.g. 20 (for +20%)')),
                                                            Forms\Components\TextInput::make('label')
                                                                ->label($t('Etichetă', 'Label'))
                                                                ->placeholder($t('ex: Weekend +20%', 'e.g. Weekend +20%')),
                                                        ]),
                                                    ])
                                                    ->addActionLabel($t('Adaugă regulă', 'Add rule'))
                                                    ->reorderable(false),
                                            ]),

                                        SC\Section::make($t('Regulament', 'Rules'))
                                            ->collapsed()
                                            ->schema([
                                                Forms\Components\RichEditor::make('venue_config.rules_html')
                                                    ->label('')
                                                    ->toolbarButtons(['bold', 'italic', 'bulletList', 'orderedList', 'link']),
                                            ]),
                                    ]),

                                // ========== TAB 4: BILETE ==========
                                SC\Tabs\Tab::make($t('Bilete', 'Tickets'))
                                    ->key('bilete')
                                    ->icon('heroicon-o-ticket')
                                    ->lazy()
                                    ->schema([
                        // TICKETS
                        SC\Section::make($t('Bilete', 'Tickets'))
                            ->schema([
                                // Warning: Cerere avizare document exists
                                Forms\Components\Placeholder::make('cerere_avizare_warning')
                                    ->hiddenLabel()
                                    ->visible(fn (?Event $record) => $record && OrganizerDocument::where('event_id', $record->id)
                                        ->where('document_type', 'cerere_avizare')->exists())
                                    ->content(fn () => new HtmlString(
                                        '<div class="flex items-center gap-2 p-3 text-sm border rounded-lg bg-warning-50 border-warning-300 text-warning-800">' .
                                            '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.072 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>' .
                                            '<span><strong>' . $t('Atenție:', 'Warning:') . '</strong> ' . $t('Există o Cerere de Avizare generată pentru acest eveniment. Modificarea tipurilor de bilete poate necesita regenerarea documentului.', 'A Cerere de Avizare document has been generated for this event. Modifying ticket types may require regenerating the document.') . '</span>' .
                                        '</div>'
                                    ))
                                    ->columnSpanFull(),

                                // Ticket Template, General Quota, Door Price row
                                SC\Grid::make(3)->schema([
                                    Forms\Components\Select::make('ticket_template_id')
                                        ->label($t('Șablon bilet', 'Ticket Template'))
                                        ->relationship(
                                            name: 'ticketTemplate',
                                            modifyQueryUsing: fn (Builder $query) => $query
                                                ->where('marketplace_client_id', static::getMarketplaceClient()?->id)
                                                ->where('status', 'active')
                                        )
                                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->name . ($record->is_default ? ' (Default)' : ''))
                                        ->placeholder($t('Folosește șablonul implicit', 'Use default template'))
                                        ->hintIcon('heroicon-o-information-circle', tooltip: $t('Selectează un șablon pentru biletele generate pentru acest eveniment. Lasă gol pentru a folosi șablonul implicit.', 'Select a template for tickets generated for this event. Leave empty to use the default template.'))
                                        ->searchable()
                                        ->preload()
                                        ->nullable()
                                        ->visible(fn () => static::getMarketplaceClient()?->microservices()
                                            ->where('slug', 'ticket-customizer')
                                            ->wherePivot('is_active', true)
                                            ->exists() ?? false),
                                    Forms\Components\TextInput::make('general_quota')
                                        ->label($t('Capacitate generală', 'General Capacity'))
                                        ->numeric()
                                        ->minValue(1)
                                        ->nullable()
                                        ->required(fn ($operation) => $operation === 'create')
                                        ->hintIcon('heroicon-o-information-circle', tooltip: $t('Numărul maxim total de bilete care pot fi vândute (pool partajat). Obligatoriu la creare. La editare, lasă gol pentru fără limită.', 'Maximum total tickets that can be sold (shared pool). Required on create. On edit, leave empty for no limit.'))
                                        ->placeholder(fn ($operation) => $operation === 'create' ? $t('obligatoriu', 'required') : $t('gol = fără limită', 'empty = no limit'))
                                        ->live(onBlur: true)
                                        ->hint(function (SGet $get, ?Event $record) use ($t) {
                                            $quota = $get('general_quota');
                                            if (!$quota || !$record) return null;
                                            // Count active tickets (exclude cancelled/refunded) from non-independent types
                                            $nonIndepIds = $record->ticketTypes()
                                                ->where('is_independent_stock', false)
                                                ->pluck('id');
                                            $activeCount = $nonIndepIds->isEmpty() ? 0 : \App\Models\Ticket::whereIn('ticket_type_id', $nonIndepIds)
                                                ->whereNotIn('status', ['cancelled', 'refunded'])
                                                ->count();
                                            $remaining = max(0, (int) $quota - $activeCount);
                                            return new HtmlString(
                                                '<span class="text-xs">' . $t('Disponibil', 'Available') . ': <strong>' . $remaining . '</strong> / ' . $quota . '</span>'
                                            );
                                        }),
                                    Forms\Components\TextInput::make('target_price')
                                        ->label($t('Preț la intrare', 'Door Price'))
                                        ->numeric()
                                        ->minValue(0)
                                        ->step(0.01)
                                        ->suffix($marketplace?->currency ?? 'RON')
                                        ->placeholder($t('ex: 100.00', 'e.g. 100.00'))
                                        ->hintIcon('heroicon-o-information-circle', tooltip: $t('Preț de referință pentru planificare și negocieri. Nu este afișat public.', 'Reference price for planning and negotiations. Not displayed publicly.')),
                                ]),

                                SC\Grid::make(3)->schema([
                                    Forms\Components\Toggle::make('has_per_performance_pricing')
                                        ->label($t('Prețuri diferite per reprezentare', 'Different prices per performance'))
                                        ->visible(fn (SGet $get) => $get('duration_mode') === 'multi_day')
                                        ->live()
                                        ->partiallyRenderAfterStateUpdated()
                                        ->dehydrated(false)
                                        ->afterStateHydrated(function ($component, ?Event $record) {
                                            if (!$record) { $component->state(false); return; }
                                            $hasOverrides = $record->performances()
                                                ->whereNotNull('ticket_overrides')
                                                ->where('ticket_overrides', '!=', '[]')
                                                ->exists();
                                            $component->state($hasOverrides);
                                        }),
                                    Forms\Components\Toggle::make('enable_ticket_groups')
                                        ->label($t('Grupează tipurile de bilete', 'Group ticket types'))
                                        ->live()
                                        ->default(false),
                                    Forms\Components\Toggle::make('enable_ticket_perks')
                                        ->label($t('Condiții / Beneficii per tip bilet', 'Perks / Conditions per ticket type'))
                                        ->live()
                                        ->default(false),
                                ]),

                                Forms\Components\Repeater::make('ticketTypes')
                                    ->relationship()
                                    ->label($t('Tipuri de bilete', 'Ticket types'))
                                    ->collapsible()
                                    ->collapsed()
                                    ->reorderable()
                                    ->reorderableWithDragAndDrop()
                                    ->orderColumn('sort_order')
                                    ->addActionLabel($t('Adaugă tip bilet', 'Add ticket type'))
                                    ->itemLabel(function (array $state) use ($t) {
                                        $name = e($state['name'] ?? $t('Bilet', 'Ticket'));
                                        $isActive = $state['is_active'] ?? true;
                                        $isEntryTicket = $state['is_entry_ticket'] ?? false;
                                        $isDeclarable = $state['is_declarable'] ?? true;
                                        $isRefundable = $state['is_refundable'] ?? false;
                                        $isSubscription = $state['is_subscription'] ?? false;

                                        $badges = '';
                                        // Channel badge: Online (default) or Offline (app ticket)
                                        $badges .= $isEntryTicket
                                            ? '<span style="font-size:10px;font-weight:600;color:#7c3aed;background:#f5f3ff;padding:1px 6px;border-radius:4px;margin-left:6px;">Offline</span>'
                                            : '<span style="font-size:10px;font-weight:600;color:#0891b2;background:#ecfeff;padding:1px 6px;border-radius:4px;margin-left:6px;">Online</span>';
                                        // App badge (only when enabled)
                                        if ($isEntryTicket) {
                                            $badges .= '<span style="font-size:10px;font-weight:600;color:#7c3aed;background:#f5f3ff;padding:1px 6px;border-radius:4px;margin-left:4px;">App</span>';
                                        }
                                        // Declarabil badge
                                        if ($isDeclarable) {
                                            $badges .= '<span style="font-size:10px;font-weight:600;color:#0e7490;background:#ecfeff;padding:1px 6px;border-radius:4px;margin-left:4px;">Declarabil</span>';
                                        }
                                        // Returnabil badge
                                        if ($isRefundable) {
                                            $badges .= '<span style="font-size:10px;font-weight:600;color:#059669;background:#ecfdf5;padding:1px 6px;border-radius:4px;margin-left:4px;">Returnabil</span>';
                                        }
                                        // Abonament badge with subscription icon
                                        if ($isSubscription) {
                                            $badges .= '<span style="font-size:10px;font-weight:600;color:#a16207;background:#fefce8;padding:1px 6px;border-radius:4px;margin-left:4px;display:inline-flex;align-items:center;gap:3px;"><svg style="width:11px;height:11px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Abonament</span>';
                                        }

                                        if ($isActive) {
                                            return new \Illuminate\Support\HtmlString('✓ ' . $name . $badges);
                                        }

                                        // Expired: active_until is set and in the past
                                        $activeUntil = $state['active_until'] ?? null;
                                        if ($activeUntil && \Carbon\Carbon::parse($activeUntil, 'Europe/Bucharest')->isPast()) {
                                            return new \Illuminate\Support\HtmlString(
                                                '○ ' . $name . $badges . ' <span style="font-size:11px;font-weight:600;color:#dc2626;background:#fef2f2;padding:1px 6px;border-radius:4px;margin-left:6px;">Expirat</span>'
                                            );
                                        }

                                        // Scheduled: scheduled_at is set and in the future
                                        $scheduledAt = $state['scheduled_at'] ?? null;
                                        if ($scheduledAt) {
                                            try {
                                                $scheduledDate = \Carbon\Carbon::parse($scheduledAt, 'Europe/Bucharest');
                                                if ($scheduledDate->isFuture()) {
                                                    return new \Illuminate\Support\HtmlString(
                                                        '○ ' . $name . $badges . ' <span style="font-size:11px;font-weight:600;color:#7c3aed;background:#f5f3ff;padding:1px 6px;border-radius:4px;margin-left:6px;">Programat ' . $scheduledDate->format('d.m.Y H:i') . '</span>'
                                                    );
                                                }
                                            } catch (\Exception $e) {}
                                        }

                                        // Autostart: waiting for previous ticket type to sell out
                                        if ($state['autostart_when_previous_sold_out'] ?? false) {
                                            return new \Illuminate\Support\HtmlString(
                                                '○ ' . $name . $badges . ' <span style="font-size:11px;font-weight:600;color:#2563eb;background:#eff6ff;padding:1px 6px;border-radius:4px;margin-left:6px;">Autostart</span>'
                                            );
                                        }

                                        // Manually deactivated
                                        return new \Illuminate\Support\HtmlString(
                                            '○ ' . $name . $badges . ' <span style="font-size:11px;font-weight:600;color:#d97706;background:#fffbeb;padding:1px 6px;border-radius:4px;margin-left:6px;">Dezactivat</span>'
                                        );
                                    })
                                    ->extraItemActions([
                                        Action::make('toggleApp')
                                            ->iconButton()
                                            ->icon(fn (array $arguments, Forms\Components\Repeater $component): string =>
                                                ($component->getState()[$arguments['item']]['is_entry_ticket'] ?? false)
                                                    ? 'heroicon-s-device-phone-mobile'
                                                    : 'heroicon-o-device-phone-mobile'
                                            )
                                            ->color(fn (array $arguments, Forms\Components\Repeater $component): string =>
                                                ($component->getState()[$arguments['item']]['is_entry_ticket'] ?? false)
                                                    ? 'success' : 'gray'
                                            )
                                            ->tooltip(fn (array $arguments, Forms\Components\Repeater $component) =>
                                                ($component->getState()[$arguments['item']]['is_entry_ticket'] ?? false)
                                                    ? 'App: ON (click to disable)' : 'App: OFF (click to enable)'
                                            )
                                            ->action(function (array $arguments, Forms\Components\Repeater $component) {
                                                $state = $component->getState();
                                                $state[$arguments['item']]['is_entry_ticket'] = !($state[$arguments['item']]['is_entry_ticket'] ?? false);
                                                $component->state($state);
                                            }),
                                        Action::make('toggleDeclarabil')
                                            ->iconButton()
                                            ->icon(fn (array $arguments, Forms\Components\Repeater $component): string =>
                                                ($component->getState()[$arguments['item']]['is_declarable'] ?? true)
                                                    ? 'heroicon-s-document-check'
                                                    : 'heroicon-o-document-check'
                                            )
                                            ->color(fn (array $arguments, Forms\Components\Repeater $component): string =>
                                                ($component->getState()[$arguments['item']]['is_declarable'] ?? true)
                                                    ? 'info' : 'gray'
                                            )
                                            ->tooltip(fn (array $arguments, Forms\Components\Repeater $component) =>
                                                ($component->getState()[$arguments['item']]['is_declarable'] ?? true)
                                                    ? 'Declarabil: ON (click to disable)' : 'Declarabil: OFF (click to enable)'
                                            )
                                            ->action(function (array $arguments, Forms\Components\Repeater $component) {
                                                $state = $component->getState();
                                                $state[$arguments['item']]['is_declarable'] = !($state[$arguments['item']]['is_declarable'] ?? true);
                                                $component->state($state);
                                            }),
                                        Action::make('toggleReturnabil')
                                            ->iconButton()
                                            ->icon(fn (array $arguments, Forms\Components\Repeater $component): string =>
                                                ($component->getState()[$arguments['item']]['is_refundable'] ?? false)
                                                    ? 'heroicon-s-arrow-uturn-left'
                                                    : 'heroicon-o-arrow-uturn-left'
                                            )
                                            ->color(fn (array $arguments, Forms\Components\Repeater $component): string =>
                                                ($component->getState()[$arguments['item']]['is_refundable'] ?? false)
                                                    ? 'warning' : 'gray'
                                            )
                                            ->tooltip(fn (array $arguments, Forms\Components\Repeater $component) =>
                                                ($component->getState()[$arguments['item']]['is_refundable'] ?? false)
                                                    ? 'Returnabil: ON (click to disable)' : 'Returnabil: OFF (click to enable)'
                                            )
                                            ->action(function (array $arguments, Forms\Components\Repeater $component) {
                                                $state = $component->getState();
                                                $state[$arguments['item']]['is_refundable'] = !($state[$arguments['item']]['is_refundable'] ?? false);
                                                $component->state($state);
                                            }),
                                        Action::make('toggleAbonament')
                                            ->iconButton()
                                            ->icon(fn (array $arguments, Forms\Components\Repeater $component): string =>
                                                ($component->getState()[$arguments['item']]['is_subscription'] ?? false)
                                                    ? 'heroicon-s-clock'
                                                    : 'heroicon-o-clock'
                                            )
                                            ->color(fn (array $arguments, Forms\Components\Repeater $component): string =>
                                                ($component->getState()[$arguments['item']]['is_subscription'] ?? false)
                                                    ? 'warning' : 'gray'
                                            )
                                            ->tooltip(fn (array $arguments, Forms\Components\Repeater $component) =>
                                                ($component->getState()[$arguments['item']]['is_subscription'] ?? false)
                                                    ? 'Abonament: ON (click to disable)' : 'Abonament: OFF (click to enable)'
                                            )
                                            ->action(function (array $arguments, Forms\Components\Repeater $component) {
                                                $state = $component->getState();
                                                $state[$arguments['item']]['is_subscription'] = !($state[$arguments['item']]['is_subscription'] ?? false);
                                                $component->state($state);
                                            }),
                                        Action::make('duplicateTicketType')
                                            ->icon('heroicon-m-document-duplicate')
                                            ->color('gray')
                                            ->tooltip($t('Duplică tipul de bilet', 'Duplicate ticket type'))
                                            ->action(function (array $arguments, Forms\Components\Repeater $component) {
                                                $state = $component->getState();
                                                $itemKey = $arguments['item'];
                                                $itemData = $state[$itemKey] ?? null;
                                                if (!$itemData) return;

                                                $newData = $itemData;
                                                $newData['name'] = '[DUP] ' . ($newData['name'] ?? '');
                                                $newData['id'] = null;
                                                $newData['sku'] = '';
                                                $newData['quota_sold'] = 0;
                                                $newData['series_start'] = null;
                                                $newData['series_end'] = null;

                                                $newUuid = (string) Str::uuid();
                                                $state[$newUuid] = $newData;
                                                $component->state($state);
                                            }),
                                    ])
                                    ->columns(12)
                                    ->schema([
                                        Forms\Components\Hidden::make('id'),

                                        // ── Section 1: Identificare (always visible, not collapsible) ──
                                        SC\Section::make($t('Identificare', 'Identification'))
                                            ->extraAttributes(['class' => 'ep-tt-section'])
                                            ->schema([
                                                // Row 1: Name, SKU, Price, Stock
                                                SC\Grid::make(4)->schema([
                                                    Forms\Components\TextInput::make('name')
                                                        ->label($t('Nume', 'Name'))
                                                        ->placeholder($t('ex: Early Bird, Standard, VIP', 'e.g. Early Bird, Standard, VIP'))
                                                        ->datalist(['Early Bird','Standard','VIP','Backstage','Student','Senior','Child'])
                                                        ->required()
                                                        ->inlineLabel($il)
                                                        ->live(onBlur: true)
                                                        ->skipRenderAfterStateUpdated()
                                                        ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                                            if ($get('sku')) return;
                                                            $set('sku', Str::upper(Str::slug($state, '-')));
                                                        }),
                                                    Forms\Components\TextInput::make('sku')
                                                        ->label('SKU')
                                                        ->inlineLabel($il)
                                                        ->placeholder($t('Se generează automat dacă lași gol', 'AUTO-GEN if left empty')),
                                                    Forms\Components\TextInput::make('price_max')
                                                        ->label($t('Preț', 'Price'))
                                                        ->inlineLabel($il)
                                                        ->placeholder($t('ex: 120.00', 'e.g. 120.00'))
                                                        ->numeric()
                                                        ->minValue(0)
                                                        ->required()
                                                        ->suffix($marketplace?->currency ?? 'RON')
                                                        ->live(onBlur: true)
                                                        ->partiallyRenderAfterStateUpdated()
                                                        ->hint(function (SGet $get) use ($t) {
                                                            $targetPrice = (float) ($get('../../target_price') ?: 0);
                                                            $price = (float) ($get('price_max') ?: 0);
                                                            if ($targetPrice > 0 && $price > $targetPrice) {
                                                                return new \Illuminate\Support\HtmlString(
                                                                    '<span style="color:#dc2626;font-weight:600;">⚠ ' . $t('Depășește prețul la intrare', 'Exceeds door price') . ' (' . number_format($targetPrice, 2) . ')</span>'
                                                                );
                                                            }
                                                            return null;
                                                        }),
                                                    Forms\Components\TextInput::make('capacity')
                                                        ->label($t('Stoc', 'Stock'))
                                                        ->inlineLabel($il)
                                                        ->placeholder($t('obligatoriu', 'required'))
                                                        ->numeric()
                                                        ->minValue(-1)
                                                        ->required()
                                                        ->hintIcon('heroicon-o-information-circle', tooltip: $t('-1 = nelimitat', '-1 = unlimited'))
                                                        ->live(onBlur: true)
                                                        ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                                            // Regenerate series_end when stock changes
                                                            $eventSeries = $get('../../event_series');
                                                            $ticketTypeIdentifier = $get('id') ?: $get('sku');
                                                            if (!$eventSeries || !$ticketTypeIdentifier) return;

                                                            $capacity = (int) ($state ?: 0);
                                                            if ($capacity === -1) $capacity = 1000;
                                                            if ($capacity <= 0) return;

                                                            $set('series_end', $eventSeries . '-' . $ticketTypeIdentifier . '-' . str_pad($capacity, 5, '0', STR_PAD_LEFT));
                                                            // Ensure series_start is set too
                                                            if (!$get('series_start')) {
                                                                $set('series_start', $eventSeries . '-' . $ticketTypeIdentifier . '-00001');
                                                            }
                                                        })
                                                        ->afterStateHydrated(function ($component, $state, SGet $get, $record) {
                                                            // Default for NEW ticket types: remaining pool capacity
                                                            if ($state === null || $state === '') {
                                                                $generalQuota = (int) ($get('../../general_quota') ?: 0);
                                                                if ($generalQuota > 0) {
                                                                    // $record is TicketType inside repeater, get parent event
                                                                    $eventRecord = ($record instanceof \App\Models\TicketType)
                                                                        ? $record->event
                                                                        : (($record instanceof Event) ? $record : null);
                                                                    if (!$eventRecord) {
                                                                        $eventId = $get('../../id');
                                                                        $eventRecord = $eventId ? Event::find($eventId) : null;
                                                                    }
                                                                    if ($eventRecord) {
                                                                        $nonIndepIds = $eventRecord->ticketTypes()
                                                                            ->where('is_independent_stock', false)
                                                                            ->pluck('id');
                                                                        $activeCount = $nonIndepIds->isEmpty() ? 0 : \App\Models\Ticket::whereIn('ticket_type_id', $nonIndepIds)
                                                                            ->whereNotIn('status', ['cancelled', 'refunded'])
                                                                            ->count();
                                                                        $remaining = max(0, $generalQuota - $activeCount);
                                                                        $component->state($remaining);
                                                                    } else {
                                                                        $component->state($generalQuota);
                                                                    }
                                                                }
                                                            }
                                                        })
                                                        ->suffixAction(
                                                            Action::make('toggle_independent')
                                                                ->icon(fn (SGet $get) => $get('is_independent_stock') ? 'heroicon-s-lock-open' : 'heroicon-s-lock-closed')
                                                                ->color(fn (SGet $get) => $get('is_independent_stock') ? 'success' : 'gray')
                                                                ->tooltip(fn (SGet $get) => $get('is_independent_stock')
                                                                    ? $t('Stoc independent (nu consumă din capacitatea generală). Click pentru a dezactiva.', 'Independent stock. Click to disable.')
                                                                    : $t('Stoc partajat (consumă din capacitatea generală). Click pentru a face independent.', 'Shared stock. Click to make independent.'))
                                                                ->action(function (SGet $get, SSet $set) {
                                                                    $set('is_independent_stock', !$get('is_independent_stock'));
                                                                })
                                                        )
                                                        ->hint(function ($record, SGet $get) use ($t) {
                                                            $hints = [];
                                                            if ($record) {
                                                                // Match the same "valid/used" filter used by the Sales table and stats cards
                                                                // so counts reconcile across the UI.
                                                                $activeCount = \App\Models\Ticket::where('ticket_type_id', $record->id)
                                                                    ->whereIn('status', ['valid', 'used'])
                                                                    ->count();
                                                                $cancelledCount = \App\Models\Ticket::where('ticket_type_id', $record->id)
                                                                    ->whereIn('status', ['cancelled', 'refunded'])
                                                                    ->count();
                                                            if ($activeCount > 0 || $cancelledCount > 0) {
                                                                $capacity = $record->quota_total ?? $record->capacity ?? null;
                                                                $soldText = $t('Active', 'Active') . ": {$activeCount}";
                                                                if ($cancelledCount > 0) {
                                                                    $soldText .= ' · <span style="color:#dc2626;">' . $t('Anulate', 'Cancelled') . ": {$cancelledCount}</span>";
                                                                }
                                                                if ($capacity !== null && (int) $capacity > 0) {
                                                                    $soldText .= " / {$capacity}";
                                                                }
                                                                $hints[] = '<span class="text-xs">' . $soldText . '</span>';
                                                            }}
                                                            $generalQuota = (int) ($get('../../general_quota') ?: 0);
                                                            $isIndependent = (bool) $get('is_independent_stock');
                                                            $capacity = (int) ($get('capacity') ?: 0);
                                                            if ($generalQuota > 0 && !$isIndependent && $capacity > $generalQuota) {
                                                                $hints[] = '<span style="color:#dc2626;font-weight:600;">⚠ ' . $t('Depășește capacitatea generală', 'Exceeds general capacity') . ' (' . $generalQuota . ')</span>';
                                                            }
                                                            if ($isIndependent) {
                                                                $hints[] = '<span class="text-xs" style="color:#059669;">🔓 ' . $t('Independent', 'Independent') . '</span>';
                                                            }
                                                            return !empty($hints) ? new \Illuminate\Support\HtmlString(implode(' · ', $hints)) : null;
                                                        }),
                                                    Forms\Components\Hidden::make('is_independent_stock')
                                                        ->default(false)
                                                        ->dehydrated(true),
                                                    Forms\Components\Hidden::make('currency')
                                                        ->default($marketplace?->currency ?? 'RON')
                                                        ->dehydrated(true),
                                                ])->columnSpan(12),

                                                // Row 2: Ticket group, Min/order, Max/order, Multiplier
                                                SC\Grid::make(4)->schema([
                                                    Forms\Components\Select::make('ticket_group')
                                                        ->label($t('Grup', 'Group'))
                                                        ->placeholder($t('Selectează sau creează un grup...', 'Select or create a group...'))
                                                        ->options(function (SGet $get) {
                                                            // Collect all group names from sibling ticket types
                                                            $allTicketTypes = $get('../../ticketTypes') ?? [];
                                                            $groups = [];
                                                            foreach ($allTicketTypes as $tt) {
                                                                $g = $tt['ticket_group'] ?? null;
                                                                if ($g && !isset($groups[$g])) {
                                                                    $groups[$g] = $g;
                                                                }
                                                            }
                                                            // Add default suggestions
                                                            foreach (['Bilete Acces', 'Camping', 'Parcări', 'VIP', 'Add-ons'] as $suggestion) {
                                                                if (!isset($groups[$suggestion])) {
                                                                    $groups[$suggestion] = $suggestion;
                                                                }
                                                            }
                                                            ksort($groups);
                                                            return $groups;
                                                        })
                                                        ->searchable()
                                                        ->createOptionForm([
                                                            Forms\Components\TextInput::make('group_name')
                                                                ->label($t('Nume grup nou', 'New group name'))
                                                                ->required(),
                                                        ])
                                                        ->createOptionUsing(fn (array $data) => $data['group_name'])
                                                        ->visible(fn (SGet $get) => (bool) $get('../../enable_ticket_groups')),
                                                    Forms\Components\TextInput::make('min_per_order')
                                                        ->label($t('Min bilete/comandă', 'Min tickets/order'))
                                                        ->inlineLabel($il)
                                                        ->numeric()
                                                        ->minValue(1)
                                                        ->default(1)
                                                        ->live(onBlur: true)
                                                        ->afterStateUpdated(fn ($state, SSet $set, SGet $get) =>
                                                            ($get('max_per_order') && (int) $state > (int) $get('max_per_order'))
                                                                ? $set('max_per_order', $state) : null
                                                        )
                                                        ->hintIcon('heroicon-o-information-circle', tooltip: $t('Numărul minim de bilete care pot fi cumpărate într-o comandă', 'Minimum tickets that can be purchased in a single order')),
                                                    Forms\Components\TextInput::make('max_per_order')
                                                        ->label($t('Max bilete/comandă', 'Max tickets/order'))
                                                        ->inlineLabel($il)
                                                        ->numeric()
                                                        ->minValue(1)
                                                        ->default(10)
                                                        ->live(onBlur: true)
                                                        ->rules([
                                                            fn (SGet $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                                                $min = (int) ($get('min_per_order') ?: 1);
                                                                if ($value && (int) $value < $min) {
                                                                    $fail("Max bilete/comandă ({$value}) nu poate fi mai mic decât Min bilete/comandă ({$min}).");
                                                                }
                                                            },
                                                        ])
                                                        ->hintIcon('heroicon-o-information-circle', tooltip: $t('Numărul maxim de bilete care pot fi cumpărate într-o comandă', 'Maximum tickets that can be purchased in a single order')),
                                                    Forms\Components\TextInput::make('multiplier')
                                                        ->label($t('Multiplicator', 'Multiplier'))
                                                        ->inlineLabel($il)
                                                        ->numeric()
                                                        ->minValue(1)
                                                        ->default(1)
                                                        ->hintIcon('heroicon-o-information-circle', tooltip: $t('Pasul de incrementare la +/- pe frontend. Ex: 2 = se adaugă câte 2 bilete per click.', 'Step increment for +/- on frontend. E.g. 2 = adds 2 tickets per click.')),
                                                ])->columnSpan(12),

                                                // Row 3: Description + Admin notes side by side
                                                SC\Grid::make(2)->schema([
                                                    Forms\Components\Textarea::make('description')
                                                        ->label($t('Descriere', 'Description'))
                                                        ->placeholder($t('Descriere opțională tip bilet', 'Optional ticket type description'))
                                                        ->rows(2)
                                                        ->afterStateHydrated(function ($state, SSet $set, SGet $get) {
                                                            if (!$state && $get('sales_end_at') && $get('price')) {
                                                                $date = Carbon::parse($get('sales_end_at'))->format('d.m.Y');
                                                                $set('description', "Reducere până la {$date}");
                                                            }
                                                        }),
                                                    Forms\Components\Textarea::make('admin_notes')
                                                        ->label($t('Note interne', 'Internal Notes'))
                                                        ->placeholder($t('Vizibil doar în admin...', 'Visible only in admin...'))
                                                        ->rows(2),
                                                ])->columnSpan(12),

                                                // Color picker (conditional on seating)
                                                Forms\Components\ColorPicker::make('color')
                                                    ->label($t('Culoare pe hartă', 'Map color'))
                                                    ->hexColor()
                                                    ->visible(fn (SGet $get) => (bool) $get('../../seating_layout_id'))
                                                    ->columnSpan(3),

                                                // Hidden fields for header toggle buttons (App, Declarabil, Returnabil, Abonament)
                                                // These are toggled via extraItemActions in the repeater header
                                                Forms\Components\Hidden::make('is_entry_ticket')->default(false),
                                                Forms\Components\Hidden::make('is_declarable')->default(true),
                                                Forms\Components\Hidden::make('is_refundable')->default(false),
                                                Forms\Components\Hidden::make('is_subscription')->default(false),

                                                // Single-day ticket date (visible only for range events)
                                                Forms\Components\DatePicker::make('valid_date')
                                                    ->label($t('Bilet de 1 zi — valabil în data', 'Single-day ticket — valid on date'))
                                                    ->inlineLabel($il)
                                                    ->native(true)
                                                    ->extraInputAttributes(['lang' => 'ro-RO'])
                                                    ->minDate(fn (SGet $get) => $get('../../range_start_date') ? \Carbon\Carbon::parse($get('../../range_start_date'))->format('Y-m-d') : null)
                                                    ->maxDate(fn (SGet $get) => $get('../../range_end_date') ? \Carbon\Carbon::parse($get('../../range_end_date'))->format('Y-m-d') : null)
                                                    ->placeholder($t('Completează doar pentru bilete valabile o singură zi', 'Fill only for tickets valid on a single day'))
                                                    ->hintIcon('heroicon-o-information-circle', tooltip: $t('Lasă gol dacă biletul e valabil pe toată durata evenimentului. Completează o dată specifică pentru bilete de o zi.', 'Leave empty if ticket is valid for the entire event. Fill a specific date for single-day tickets.'))
                                                    ->visible(fn (SGet $get) => $get('../../duration_mode') === 'range')
                                                    ->columnSpan(12),

                                                // ── Leisure Venue Fields (visible only for leisure_venue events) ──
                                                SC\Grid::make(4)
                                                    ->extraAttributes(['style' => '--col-span-default: span 1 / span 1; --col-span-lg: span 12 / span 12;'])
                                                    ->visible(fn (SGet $get) => ($get('../../display_template') ?? 'standard') === 'leisure_venue')
                                                    ->schema([
                                                        Forms\Components\TextInput::make('daily_capacity')
                                                            ->label($t('Capacitate zilnică', 'Daily capacity'))
                                                            ->numeric()
                                                            ->minValue(1)
                                                            ->placeholder($t('ex: 500', 'e.g. 500'))
                                                            ->helperText($t('Bilete maxime pe zi pentru acest tip', 'Max tickets per day for this type')),
                                                        Forms\Components\Toggle::make('is_parking')
                                                            ->label($t('Bilet de parcare', 'Parking ticket'))
                                                            ->live(),
                                                        Forms\Components\Toggle::make('requires_vehicle_info')
                                                            ->label($t('Necesită nr. înmatriculare', 'Requires license plate'))
                                                            ->visible(fn (SGet $get) => (bool) $get('is_parking')),
                                                    ]),
                                            ])
                                            ->columns(12)
                                            ->columnSpan(12),

                                        // ── Section 2: Prețuri per reprezentație (collapsible, collapsed) ──
                                        SC\Section::make($t('Prețuri per reprezentație', 'Prices per performance'))
                                            ->visible(fn (SGet $get) => $get('../../duration_mode') === 'multi_day' && $get('../../has_per_performance_pricing'))
                                            ->schema([
                                                Forms\Components\Placeholder::make('perf_prices_hint')
                                                    ->hiddenLabel()
                                                    ->content(function (\Livewire\Component $livewire) use ($t) {
                                                        $eventId = $livewire->record?->id ?? null;
                                                        if (!$eventId) {
                                                            return new HtmlString('<p class="text-sm text-amber-600 dark:text-amber-400">' . $t('Salvează evenimentul mai întâi pentru a configura prețurile per reprezentație.', 'Save the event first to configure prices per performance.') . '</p>');
                                                        }
                                                        $count = \App\Models\Performance::where('event_id', $eventId)->count();
                                                        if ($count === 0) {
                                                            return new HtmlString('<p class="text-sm text-amber-600 dark:text-amber-400">' . $t('Salvează evenimentul pentru a genera reprezentațiile din tab-ul Program, apoi revino aici.', 'Save the event to generate performances from the Program tab, then come back here.') . '</p>');
                                                        }
                                                        return '';
                                                    }),
                                                Forms\Components\Repeater::make('meta.performance_prices')
                                                    ->label($t('Prețuri per reprezentare', 'Prices per performance'))
                                                    ->visible(fn (\Livewire\Component $livewire) => $livewire->record && \App\Models\Performance::where('event_id', $livewire->record->id)->exists())
                                                    ->schema([
                                                        Forms\Components\Select::make('perf_id')
                                                            ->hiddenLabel()
                                                            ->placeholder($t('Alege reprezentarea...', 'Choose performance...'))
                                                            ->options(function (SGet $get, \Livewire\Component $livewire) {
                                                                $eventId = $livewire->record?->id ?? null;

                                                                // Try Performance records first
                                                                if ($eventId) {
                                                                    $performances = \App\Models\Performance::where('event_id', $eventId)
                                                                        ->where(fn ($q) => $q->where('status', 'active')->orWhereNull('status'))
                                                                        ->orderBy('starts_at')
                                                                        ->get();

                                                                    if ($performances->isNotEmpty()) {
                                                                        return $performances->mapWithKeys(fn ($p) => [
                                                                            $p->id => $p->starts_at->format('D, d M Y · H:i')
                                                                        ])->toArray();
                                                                    }
                                                                }

                                                                // Fallback: build options from multi_slots JSON (before first save)
                                                                $multiSlots = $get('../../multi_slots') ?? [];
                                                                if (!empty($multiSlots)) {
                                                                    $options = [];
                                                                    foreach ($multiSlots as $idx => $slot) {
                                                                        $date = $slot['date'] ?? null;
                                                                        if (!$date) continue;
                                                                        $startTime = $slot['start_time'] ?? '00:00';
                                                                        $label = \Carbon\Carbon::parse("{$date} {$startTime}")->format('D, d M Y · H:i');
                                                                        $options["slot_{$idx}"] = $label;
                                                                    }
                                                                    return $options;
                                                                }

                                                                return [];
                                                            })
                                                            ->disableOptionWhen(function (string $value, SGet $get, $component) {
                                                                $currentPerfId = $get('perf_id');
                                                                // Get the full state path and derive the repeater path
                                                                $statePath = $component->getStatePath();
                                                                // statePath: data.ticketTypes.record-XXX.meta.performance_prices.UUID.perf_id
                                                                // We need: data.ticketTypes.record-XXX.meta.performance_prices
                                                                $repeaterPath = preg_replace('/\.[^.]+\.perf_id$/', '', $statePath);
                                                                $allItems = data_get($component->getLivewire()->data, str_replace('data.', '', $repeaterPath), []);
                                                                $usedIds = collect($allItems)->pluck('perf_id')->filter()->map(fn ($v) => (string) $v)->toArray();
                                                                if ((string) $value === (string) $currentPerfId) return false;
                                                                return in_array((string) $value, $usedIds);
                                                            })
                                                            ->required()
                                                            ->searchable()
                                                            ->live()
                                                            ->columnSpan(3),
                                                        Forms\Components\TextInput::make('price')
                                                            ->hiddenLabel()
                                                            ->numeric()
                                                            ->step(0.01)
                                                            ->placeholder($t('Preț', 'Price'))
                                                            ->columnSpan(1),
                                                        Forms\Components\TextInput::make('stock')
                                                            ->hiddenLabel()
                                                            ->numeric()
                                                            ->minValue(0)
                                                            ->placeholder($t('Stoc (gol = stoc tip bilet)', 'Stock (empty = ticket type stock)'))
                                                            ->columnSpan(1),
                                                        Forms\Components\TextInput::make('series_start')
                                                            ->hiddenLabel()
                                                            ->placeholder('Serie start')
                                                            ->disabled()
                                                            ->dehydrated(true)
                                                            ->extraAttributes(['style' => 'font-family:monospace;font-size:9px;'])
                                                            ->columnSpan(2),
                                                        Forms\Components\TextInput::make('series_end')
                                                            ->hiddenLabel()
                                                            ->placeholder('Serie end')
                                                            ->disabled()
                                                            ->dehydrated(true)
                                                            ->extraAttributes(['style' => 'font-family:monospace;font-size:9px;'])
                                                            ->columnSpan(2),
                                                    ])
                                                    ->columns(9)
                                                    ->grid(1)
                                                    ->itemLabel(fn () => null)
                                                    ->addActionLabel($t('+ Adaugă preț', '+ Add price'))
                                                    ->defaultItems(0)
                                                    ->reorderable(false)
                                                    ->extraAttributes(['class' => 'perf-prices-compact'])
                                                    ->columnSpan(12),
                                            ])
                                            ->collapsible()
                                            ->collapsed()
                                            ->persistCollapsed()
                                            ->compact()
                                            ->columns(12)
                                            ->columnSpan(12),

                                        // ── Section 3: Comision personalizat (collapsible, collapsed) ──
                                        SC\Section::make($t('Comision personalizat', 'Custom commission'))
                                            ->schema([
                                                Forms\Components\Select::make('commission_type')
                                                    ->label($t('Tip comision', 'Commission type'))
                                                    ->inlineLabel($il)
                                                    ->options([
                                                        'inherit' => $t('Moștenește setările', 'Inherit settings'),
                                                        'percentage' => $t('Procentual', 'Percentage'),
                                                        'fixed' => $t('Fix', 'Fixed'),
                                                        'both' => $t('Procentual + Fix', 'Percentage + Fixed'),
                                                    ])
                                                    ->default('inherit')
                                                    ->formatStateUsing(fn ($state) => $state ?: 'inherit')
                                                    ->dehydrateStateUsing(fn ($state) => $state === 'inherit' ? null : $state)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(function ($state, SSet $set, $component) use ($marketplace) {
                                                        $defaultRate = $marketplace?->commission_rate ?? 5;
                                                        $defaultMode = $marketplace?->commission_mode ?? 'included';
                                                        if ($state === 'percentage' || $state === 'both') {
                                                            $set('commission_rate', $defaultRate);
                                                            $set('commission_mode', $defaultMode);
                                                        }
                                                        if ($state === 'fixed' || $state === 'both') {
                                                            $set('commission_mode', $defaultMode);
                                                        }
                                                    })
                                                    ->columnSpan(3),
                                                Forms\Components\TextInput::make('commission_rate')
                                                    ->label($t('Procent %', 'Rate %'))
                                                    ->inlineLabel($il)
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(100)
                                                    ->step(0.01)
                                                    ->placeholder(($marketplace?->commission_rate ?? 5) . '%')
                                                    ->visible(fn (SGet $get) => in_array($get('commission_type'), ['percentage', 'both']))
                                                    ->columnSpan(3),
                                                Forms\Components\TextInput::make('commission_fixed')
                                                    ->label($t('Sumă fixă', 'Fixed amount'))
                                                    ->inlineLabel($il)
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->step(0.01)
                                                    ->placeholder('2.00')
                                                    ->suffix($marketplace?->currency ?? 'RON')
                                                    ->visible(fn (SGet $get) => in_array($get('commission_type'), ['fixed', 'both']))
                                                    ->columnSpan(3),
                                                Forms\Components\Select::make('commission_mode')
                                                    ->label($t('Mod comision', 'Commission mode'))
                                                    ->inlineLabel($il)
                                                    ->options([
                                                        'included' => $t('Inclus în preț', 'Included in price'),
                                                        'added_on_top' => $t('Adăugat la preț', 'Added on top'),
                                                    ])
                                                    ->placeholder($marketplace?->commission_mode === 'added_on_top' ? $t('Adăugat', 'Added') : $t('Inclus', 'Included'))
                                                    ->visible(fn (SGet $get) => !empty($get('commission_type')))
                                                    ->columnSpan(3),
                                            ])
                                            ->collapsible()
                                            ->collapsed()
                                            ->persistCollapsed()
                                            ->compact()
                                            ->columns(12)
                                            ->columnSpan(12),

                                        // ── Section 4: Condiții & Beneficii (collapsible, collapsed) ──
                                        SC\Section::make($t('Condiții & Beneficii', 'Perks & Conditions'))
                                            ->visible(fn (SGet $get) => (bool) $get('../../enable_ticket_perks'))
                                            ->schema([
                                                Forms\Components\Repeater::make('perks')
                                                    ->label($t('Condiții / Beneficii', 'Perks / Conditions'))
                                                    ->simple(
                                                        Forms\Components\TextInput::make('text')
                                                            ->placeholder($t('ex: Include acces la zona VIP', 'e.g. Includes VIP area access'))
                                                            ->required()
                                                    )
                                                    ->defaultItems(0)
                                                    ->addActionLabel($t('Adaugă condiție / beneficiu', 'Add perk / condition'))
                                                    ->reorderable()
                                                    ->columnSpan(12),
                                            ])
                                            ->collapsible()
                                            ->collapsed()
                                            ->persistCollapsed()
                                            ->compact()
                                            ->columns(12)
                                            ->columnSpan(12),

                                        // ── Section 5: Disponibilitate (collapsible, collapsed) ──
                                        SC\Section::make($t('Disponibilitate', 'Availability'))
                                            ->schema([
                                                Forms\Components\Toggle::make('is_active')
                                                    ->label($t('Activ', 'Active'))
                                                    ->default(true)
                                                    ->columnSpan(2),
                                                Forms\Components\DateTimePicker::make('scheduled_at')
                                                    ->label($t('Programează activare', 'Schedule Activation'))
                                                    ->inlineLabel($il)
                                                    ->hintIcon('heroicon-o-information-circle', tooltip: $t('Când acest tip de bilet ar trebui să devină automat activ', 'When this ticket type should automatically become active'))
                                                    ->native(true)
                                                    ->seconds(false)
                                                    ->extraInputAttributes(['lang' => 'ro-RO'])
                                                    ->columnSpan(3),
                                                Forms\Components\DateTimePicker::make('active_until')
                                                    ->label($t('Activ până la', 'Active until'))
                                                    ->inlineLabel($il)
                                                    ->native(true)
                                                    ->seconds(false)
                                                    ->extraInputAttributes(['lang' => 'ro-RO'])
                                                    ->hintIcon('heroicon-o-information-circle', tooltip: $t('Când se atinge această dată, tipul de bilet va fi marcat ca sold out, chiar dacă mai sunt bilete în stoc.', 'When this date is reached, the ticket type will be marked as sold out, even if there are still tickets in stock.'))
                                                    ->columnSpan(3),
                                                Forms\Components\Toggle::make('autostart_when_previous_sold_out')
                                                    ->label($t('Autostart când precedentul e sold out', 'Autostart when previous sold out'))
                                                    ->hintIcon('heroicon-o-information-circle', tooltip: $t('Activează automat când tipurile de bilete anterioare ajung la capacitate 0', 'Activate automatically when previous ticket types reach 0 capacity'))
                                                    ->columnSpan(4),
                                            ])
                                            ->collapsible()
                                            ->collapsed()
                                            ->persistCollapsed()
                                            ->compact()
                                            ->columns(12)
                                            ->columnSpan(12),

                                        // ── Section 6: Reducere (collapsible, collapsed) ──
                                        SC\Section::make($t('Reducere', 'Discount'))
                                            ->schema([
                                                Forms\Components\Toggle::make('has_sale')
                                                    ->label($t('Activează reducere', 'Enable Sale Discount'))
                                                    ->live()
                                                    ->partiallyRenderAfterStateUpdated()
                                                    ->default(false)
                                                    ->dehydrated(false)
                                                    ->afterStateHydrated(function ($state, SSet $set, SGet $get) {
                                                        $hasSaleData = $get('price') || $get('discount_percent') || $get('sales_start_at') || $get('sales_end_at') || $get('sale_stock');
                                                        if ($hasSaleData) {
                                                            $set('has_sale', true);
                                                        }
                                                    })
                                                    ->afterStateUpdated(function ($state, SSet $set) {
                                                        if (!$state) {
                                                            // Clear all sale-related fields when toggle is disabled
                                                            $set('price', null);
                                                            $set('sale_price_cents', null);
                                                            $set('discount_percent', null);
                                                            $set('sales_start_at', null);
                                                            $set('sales_end_at', null);
                                                            $set('sale_stock', null);
                                                        }
                                                    })
                                                    ->columnSpan(12),

                                                Forms\Components\TextInput::make('price')
                                                    ->label($t('Preț promoțional', 'Sale price'))
                                                    ->inlineLabel($il)
                                                    ->placeholder($t('lasă gol dacă nu e reducere', 'leave empty if no sale'))
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->suffix($marketplace?->currency ?? 'RON')
                                                    ->live(onBlur: true)
                                                    ->skipRenderAfterStateUpdated()
                                                    ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                                        $price = (float) ($get('price_max') ?: 0);
                                                        $sale = $state !== null && $state !== '' ? (float)$state : null;
                                                        if ($price > 0 && $sale) {
                                                            $d = round((1 - ($sale / $price)) * 100, 2);
                                                            $set('discount_percent', max(0, min(100, $d)));
                                                        } else {
                                                            $set('discount_percent', null);
                                                        }
                                                    })
                                                    ->visible(fn (SGet $get) => $get('has_sale'))
                                                    ->columnSpan(3),
                                                Forms\Components\TextInput::make('discount_percent')
                                                    ->label($t('Reducere %', 'Discount %'))
                                                    ->inlineLabel($il)
                                                    ->placeholder($t('ex: 20', 'e.g. 20'))
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(100)
                                                    ->live(onBlur: true)
                                                    ->skipRenderAfterStateUpdated()
                                                    ->formatStateUsing(function ($state, SGet $get) {
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
                                                    })
                                                    ->visible(fn (SGet $get) => $get('has_sale'))
                                                    ->columnSpan(3),
                                                Forms\Components\DateTimePicker::make('sales_start_at')
                                                    ->label($t('Început reducere', 'Sale starts'))
                                                    ->inlineLabel($il)
                                                    ->native(true)
                                                    ->seconds(false)
                                                    ->extraInputAttributes(['lang' => 'ro-RO'])
                                                    ->live(onBlur: true)
                                                    ->skipRenderAfterStateUpdated()
                                                    ->afterStateUpdated(function ($state, SSet $set) {
                                                        if (!$state) return;

                                                        $selectedDate = Carbon::parse($state);
                                                        $now = Carbon::now();

                                                        if ($selectedDate->isToday() && $selectedDate->format('H:i') === '00:00') {
                                                            $newTime = $now->copy()->addMinutes(5 - ($now->minute % 5))->second(0);
                                                            $set('sales_start_at', $newTime->format('Y-m-d H:i'));
                                                        }
                                                        elseif ($selectedDate->lt($now)) {
                                                            $newTime = $now->copy()->addMinutes(5 - ($now->minute % 5))->second(0);
                                                            $set('sales_start_at', $newTime->format('Y-m-d H:i'));
                                                        }
                                                    })
                                                    ->visible(fn (SGet $get) => $get('has_sale'))
                                                    ->columnSpan(3),
                                                Forms\Components\DateTimePicker::make('sales_end_at')
                                                    ->label($t('Sfârșit reducere', 'Sale ends'))
                                                    ->inlineLabel($il)
                                                    ->native(true)
                                                    ->seconds(false)
                                                    ->extraInputAttributes(['lang' => 'ro-RO'])
                                                    ->live(onBlur: true)
                                                    ->skipRenderAfterStateUpdated()
                                                    ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                                        if ($state && !$get('description')) {
                                                            $date = Carbon::parse($state)->format('d.m.Y');
                                                            $set('description', "Reducere până la {$date}");
                                                        }
                                                    })
                                                    ->visible(fn (SGet $get) => $get('has_sale'))
                                                    ->columnSpan(3),

                                                Forms\Components\TextInput::make('sale_stock')
                                                    ->label($t('Stoc reducere', 'Sale stock'))
                                                    ->inlineLabel($il)
                                                    ->placeholder($t('Nelimitat', 'Unlimited'))
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->nullable()
                                                    ->hintIcon('heroicon-o-information-circle', tooltip: $t('Numărul de bilete disponibile la preț redus. Când se consumă stocul, oferta se închide automat.', 'Number of tickets available at discounted price. When stock runs out, the offer closes automatically.'))
                                                    ->visible(fn (SGet $get) => $get('has_sale'))
                                                    ->columnSpan(6),
                                            ])
                                            ->collapsible()
                                            ->collapsed()
                                            ->persistCollapsed()
                                            ->compact()
                                            ->columns(12)
                                            ->columnSpan(12),

                                        // ── Section 7: Reduceri la cantitate (collapsible, collapsed) ──
                                        SC\Section::make($t('Reduceri la cantitate', 'Bulk discounts'))
                                            ->schema([
                                                Forms\Components\Repeater::make('bulk_discounts')
                                                    ->label('')
                                                    ->hiddenLabel()
                                                    ->default([])
                                                    ->addActionLabel($t('+ Adaugă reducere', '+ Add discount'))
                                                    ->itemLabel(fn (array $state) => match($state['rule_type'] ?? null) {
                                                        'buy_x_get_y' => $t('Cumperi', 'Buy') . ' ' . ($state['buy_qty'] ?? '?') . ' → ' . $t('primești', 'get') . ' ' . ($state['get_qty'] ?? '?') . ' ' . $t('gratis', 'free'),
                                                        'buy_x_percent_off' => $t('Min', 'Min') . ' ' . ($state['min_qty'] ?? '?') . ' → ' . ($state['percent_off'] ?? '?') . '% off',
                                                        'amount_off_per_ticket' => $t('Min', 'Min') . ' ' . ($state['min_qty'] ?? '?') . ' → -' . ($state['amount_off'] ?? '?') . '/bilet',
                                                        'bundle_price' => ($state['min_qty'] ?? '?') . ' ' . $t('bilete', 'tickets') . ' = ' . ($state['bundle_total_price'] ?? '?'),
                                                        default => $t('Regulă nouă', 'New rule'),
                                                    })
                                                    ->collapsible()
                                                    ->collapsed()
                                                    ->persistCollapsed()
                                                    ->columns(12)
                                                    ->columnSpan(12)
                                                    ->schema([
                                                        Forms\Components\Select::make('rule_type')
                                                            ->label($t('Tip regulă', 'Rule type'))
                                                            ->options([
                                                                'buy_x_get_y' => $t('Cumperi X primești Y gratis', 'Buy X get Y free'),
                                                                'buy_x_percent_off' => $t('Cumperi X bilete → % reducere', 'Buy X tickets → % off'),
                                                                'amount_off_per_ticket' => $t('Reducere pe bilet (min cantitate)', 'Amount off per ticket (min qty)'),
                                                                'bundle_price' => $t('Preț pachet (X bilete la preț total)', 'Bundle price (X tickets for total)'),
                                                            ])
                                                            ->required()
                                                            ->columnSpan(4)
                                                            ->live()
                                                            ->partiallyRenderAfterStateUpdated(),
                                                        Forms\Components\TextInput::make('buy_qty')
                                                            ->label($t('Cumperi', 'Buy'))
                                                            ->numeric()->minValue(1)
                                                            ->visible(fn ($get) => $get('rule_type') === 'buy_x_get_y')
                                                            ->columnSpan(4),
                                                        Forms\Components\TextInput::make('get_qty')
                                                            ->label($t('Primești gratis', 'Get free'))
                                                            ->numeric()->minValue(1)
                                                            ->visible(fn ($get) => $get('rule_type') === 'buy_x_get_y')
                                                            ->columnSpan(4),
                                                        Forms\Components\TextInput::make('min_qty')
                                                            ->label($t('Cantitate min', 'Min qty'))
                                                            ->numeric()->minValue(1)
                                                            ->visible(fn ($get) => in_array($get('rule_type'), ['buy_x_percent_off','amount_off_per_ticket','bundle_price']))
                                                            ->columnSpan(4),
                                                        Forms\Components\TextInput::make('percent_off')
                                                            ->label($t('% reducere', '% off'))
                                                            ->numeric()->minValue(1)->maxValue(100)
                                                            ->visible(fn ($get) => $get('rule_type') === 'buy_x_percent_off')
                                                            ->columnSpan(4),
                                                        Forms\Components\TextInput::make('amount_off')
                                                            ->label($t('Reducere/bilet', 'Amount off/ticket'))
                                                            ->numeric()->minValue(0.01)
                                                            ->visible(fn ($get) => $get('rule_type') === 'amount_off_per_ticket')
                                                            ->columnSpan(4),
                                                        Forms\Components\TextInput::make('bundle_total_price')
                                                            ->label($t('Preț total pachet', 'Bundle total price'))
                                                            ->numeric()->minValue(0.01)
                                                            ->visible(fn ($get) => $get('rule_type') === 'bundle_price')
                                                            ->columnSpan(4),
                                                    ]),
                                            ])
                                            ->collapsible()
                                            ->collapsed()
                                            ->persistCollapsed()
                                            ->compact()
                                            ->columns(12)
                                            ->columnSpan(12),

                                        // ── Section 8: Serie bilete (collapsible, collapsed) ──
                                        SC\Section::make($t('Serie bilete', 'Ticket series'))
                                            ->schema([
                                                Forms\Components\TextInput::make('series_start')
                                                    ->label($t('Serie start', 'Series start'))
                                                    ->inlineLabel($il)
                                                    ->placeholder($t('Ex: AMB-5-00001', 'E.g. AMB-5-00001'))
                                                    ->maxLength(50)
                                                    ->afterStateHydrated(function ($state, SSet $set, SGet $get) {
                                                        if (!$state) {
                                                            $eventSeries = $get('../../event_series');
                                                            $capacity = $get('capacity');
                                                            $ticketTypeIdentifier = $get('id') ?: $get('sku');
                                                            if ($eventSeries && $capacity && (int)$capacity > 0 && $ticketTypeIdentifier) {
                                                                $set('series_start', $eventSeries . '-' . $ticketTypeIdentifier . '-00001');
                                                            }
                                                        }
                                                    })
                                                    ->columnSpan(6),
                                                Forms\Components\TextInput::make('series_end')
                                                    ->label($t('Serie end', 'Series end'))
                                                    ->inlineLabel($il)
                                                    ->placeholder($t('Ex: AMB-5-00500', 'E.g. AMB-5-00500'))
                                                    ->maxLength(50)
                                                    ->afterStateHydrated(function ($state, SSet $set, SGet $get) {
                                                        if (!$state) {
                                                            $eventSeries = $get('../../event_series');
                                                            $capacity = (int) ($get('capacity') ?: 0);
                                                            $ticketTypeIdentifier = $get('id') ?: $get('sku');
                                                            // Use 1000 as default when stock is unlimited (-1)
                                                            if ($capacity === -1) $capacity = 1000;
                                                            if ($eventSeries && $capacity > 0 && $ticketTypeIdentifier) {
                                                                $set('series_end', $eventSeries . '-' . $ticketTypeIdentifier . '-' . str_pad($capacity, 5, '0', STR_PAD_LEFT));
                                                            }
                                                        }
                                                    })
                                                    ->columnSpan(6),
                                            ])
                                            ->collapsible()
                                            ->collapsed()
                                            ->persistCollapsed()
                                            ->compact()
                                            ->columns(12)
                                            ->columnSpan(12),
                                    ]),
                            ])->collapsible()->persistCollapsed(),
                                    ]), // End Tab 4: Bilete

                                // ========== TAB 5: SEO ==========
                                SC\Tabs\Tab::make('SEO')
                                    ->key('seo')
                                    ->icon('heroicon-o-globe-alt')
                                    ->lazy()
                                    ->schema([
                        // SEO Section (not collapsible - always visible when on SEO tab)
                        SC\Section::make('SEO')
                ->schema([
                    Forms\Components\Select::make('seo_presets')
                        ->label($t('Adaugă chei SEO din șablon', 'Add SEO keys from template'))
                        ->multiple()
                        ->dehydrated(false)
                        ->options([
                            'core'        => $t('De bază (title/description/canonical/robots)', 'Core (title/description/canonical/robots)'),
                            'intl'        => $t('Internațional (hreflang, og:locale)', 'International (hreflang, og:locale)'),
                            'open_graph'  => 'Open Graph (og:*)',
                            'article'     => $t('OG Articol extras', 'OG Article extras'),
                            'product'     => $t('OG Produs extras', 'OG Product extras'),
                            'twitter'     => 'Twitter Cards',
                            'jsonld'      => $t('Date structurate (JSON-LD)', 'Structured Data (JSON-LD)'),
                            'robots_adv'  => $t('Robots avansat', 'Robots advanced'),
                            'verify'      => $t('Verificare (Google/Bing/etc.)', 'Verification (Google/Bing/etc.)'),
                            'feeds'       => 'Feeds (RSS/Atom/oEmbed)',
                        ])
                        ->hintIcon('heroicon-o-information-circle', tooltip: $t('Selectează șabloane pentru a adăuga chei. Valorile vor fi pre-completate din datele evenimentului unde este disponibil.', 'Select templates to add keys. Values will be pre-filled from event data where available.'))
                        ->live()
                        ->partiallyRenderAfterStateUpdated()
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
                        ->keyLabel($t('Cheie meta', 'Meta key'))
                        ->valueLabel($t('Valoare meta', 'Meta value'))
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
                        ->hintIcon('heroicon-o-information-circle', tooltip: $t('Adaugă tag-uri meta SEO personalizate. Folosește șabloanele de mai sus pentru a adăuga rapid seturi comune.', 'Add custom SEO meta tags. Use templates above to quickly add common sets.')),
                ]),
                                    ]), // End Tab 5: SEO

                                // ========== TAB 6: HARTA LOCURI ==========
                                SC\Tabs\Tab::make($t('Harta Locuri', 'Seating Map'))
                                    ->key('harta')
                                    ->icon('heroicon-o-map')
                                    ->visible(fn (SGet $get) => (bool) $get('seating_layout_id'))
                                    ->lazy()
                                    ->schema([
                        // Performance selector for multi-day events with seating
                        Forms\Components\Select::make('seating_performance_id')
                            ->label($t('Reprezentare', 'Performance'))
                            ->helperText($t('Selectează reprezentarea pentru care configurezi harta de locuri', 'Select the performance for which you configure the seating map'))
                            ->options(function (?Event $record) {
                                if (!$record) return [];
                                return $record->performances()
                                    ->where(fn ($q) => $q->where('status', 'active')->orWhereNull('status'))
                                    ->orderBy('starts_at')
                                    ->get()
                                    ->mapWithKeys(fn ($p) => [
                                        $p->id => ($p->hasSeatingSnapshot() ? "\u{2713} " : '')
                                                 . $p->starts_at->format('D, d M Y · H:i')
                                    ])
                                    ->toArray();
                            })
                            ->placeholder($t('Toate reprezentările (layout partajat)', 'All performances (shared layout)'))
                            ->live()
                            ->partiallyRenderAfterStateUpdated()
                            ->dehydrated(false)
                            ->visible(fn (SGet $get) => $get('duration_mode') === 'multi_day')
                            ->columnSpanFull(),

                        // Warning for multi-day + seating without per-performance
                        Forms\Components\Placeholder::make('seating_multiday_warning')
                            ->hiddenLabel()
                            ->visible(fn (SGet $get) => $get('duration_mode') === 'multi_day' && !$get('seating_performance_id'))
                            ->content(fn () => new HtmlString(
                                '<div class="flex items-center gap-2 p-3 text-sm border rounded-lg bg-amber-50 border-amber-300 text-amber-800 dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300">' .
                                    '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.072 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>' .
                                    '<span>' . $t('Harta de locuri este partajată între toate reprezentările. Fiecare loc vândut pe o reprezentare va fi indisponibil și pe celelalte. Selectează o reprezentare pentru inventar separat.', 'The seating map is shared between all performances. Each seat sold on one performance will be unavailable on others. Select a performance for separate inventory.') . '</span>' .
                                '</div>'
                            ))
                            ->columnSpanFull(),

                        // Interactive seating map editor with zoom/pan and row assignment
                        Forms\Components\Placeholder::make('seating_map_editor')
                            ->hiddenLabel()
                            ->content(function (?Event $record) use ($t) {
                                if (!$record || !$record->seating_layout_id) {
                                    return new HtmlString(
                                        '<div class="p-6 text-center text-gray-500">' .
                                        $t('Salvați evenimentul cu o hartă de locuri pentru a vedea vizualizarea.', 'Save the event with a seating layout to see the visualization.') .
                                        '</div>'
                                    );
                                }
                                return new HtmlString(
                                    view('filament.forms.components.seating-map-editor', [
                                        'record' => $record,
                                    ])->render()
                                );
                            })
                            ->columnSpanFull(),
                                    ]), // End Tab 6: Harta Locuri

                                // ========== TAB 7: GRUPARE ==========
                                SC\Tabs\Tab::make($t('Grupare', 'Grouping'))
                                    ->key('turneu')
                                    ->icon('heroicon-o-map-pin')
                                    ->lazy()
                                    ->schema([
                        SC\Section::make($t('Setări Grupare', 'Grouping Settings'))
                            ->schema([
                                Forms\Components\Toggle::make('is_in_tour')
                                    ->label($t('Face parte dintr-o grupare', 'Part of a Grouping'))
                                    ->helperText($t('Bifează dacă acest eveniment face parte dintr-o grupare (serie sau turneu)', 'Check if this event is part of a grouping (series or tour)'))
                                    ->dehydrated(false)
                                    ->live()
                                    ->partiallyRenderAfterStateUpdated(),

                                Forms\Components\Radio::make('grouping_type')
                                    ->label($t('Tip grupare', 'Grouping type'))
                                    ->options([
                                        'serie_evenimente' => $t('Serie evenimente', 'Event Series'),
                                        'turneu'           => $t('Turneu', 'Tour'),
                                    ])
                                    ->default('serie_evenimente')
                                    ->dehydrated(false)
                                    ->live()
                                    ->partiallyRenderAfterStateUpdated()
                                    ->visible(fn (SGet $get) => (bool) $get('is_in_tour')),

                                Forms\Components\Radio::make('tour_mode')
                                    ->label($t('Mod grupare', 'Grouping mode'))
                                    ->options([
                                        'new'      => $t('Grupare nouă', 'New grouping'),
                                        'existing' => $t('Grupare existentă', 'Existing grouping'),
                                    ])
                                    ->default('new')
                                    ->dehydrated(false)
                                    ->live()
                                    ->partiallyRenderAfterStateUpdated()
                                    ->visible(fn (SGet $get) => (bool) $get('is_in_tour')),

                                Forms\Components\TextInput::make('tour_name')
                                    ->label($t('Nume grupare', 'Grouping name'))
                                    ->helperText($t('Introduceți un nume pentru grupare (ex: "Dirtylicious Decade Tour")', 'Enter a name for the grouping'))
                                    ->dehydrated(false)
                                    ->maxLength(255)
                                    ->visible(fn (SGet $get) => (bool) $get('is_in_tour') && $get('tour_mode') === 'new'),

                                Forms\Components\TextInput::make('tour_slug')
                                    ->label($t('Slug grupare (pentru URL)', 'Grouping slug (for URL)'))
                                    ->helperText($t('Folosit pentru link-uri anchor: /artist#slug. Ex: "ploiesti"', 'Used for anchor links: /artist#slug. E.g. "ploiesti"'))
                                    ->dehydrated(false)
                                    ->maxLength(100)
                                    ->regex('/^[a-z0-9-]*$/')
                                    ->visible(fn (SGet $get) => (bool) $get('is_in_tour')),

                                Forms\Components\Select::make('existing_tour_id')
                                    ->label($t('Selectează gruparea', 'Select grouping'))
                                    ->helperText($t('Alege o grupare existentă. Lista este filtrată după formațiile acestui eveniment.', 'Choose an existing grouping. List is filtered by this event\'s artists.'))
                                    ->searchable()
                                    ->dehydrated(false)
                                    ->options(function (?Event $record) use ($marketplace) {
                                        if (!$marketplace) return [];

                                        $query = Tour::where('marketplace_client_id', $marketplace->id);

                                        // Filter by tours that share artists with this event
                                        $artistIds = $record?->artists?->pluck('id')->toArray() ?? [];
                                        if (!empty($artistIds)) {
                                            $tourIds = Event::whereHas('artists', fn ($q) => $q->whereIn('artists.id', $artistIds))
                                                ->whereNotNull('tour_id')
                                                ->pluck('tour_id')
                                                ->unique()
                                                ->toArray();

                                            if (!empty($tourIds)) {
                                                // Include current event's tour even if artist filter misses it
                                                if ($record?->tour_id) {
                                                    $tourIds[] = $record->tour_id;
                                                }
                                                $query->whereIn('id', array_unique($tourIds));
                                            }
                                        }

                                        return $query->orderBy('name')->get()->mapWithKeys(fn ($tour) => [
                                            $tour->id => $tour->name ?: ('Grupare #' . $tour->id),
                                        ]);
                                    })
                                    ->visible(fn (SGet $get) => (bool) $get('is_in_tour') && $get('tour_mode') === 'existing'),
                            ]),
                                    ]), // End Tab 7: Grupare

                                SC\Tabs\Tab::make($t('Observații', 'Notes'))
                                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                                    ->schema([
                                        SC\Section::make($t('Observații interne eveniment', 'Internal Event Notes'))
                                            ->schema([
                                                Forms\Components\Textarea::make('admin_notes')
                                                    ->label($t('Observații', 'Notes'))
                                                    ->placeholder($t('Adaugă observații interne despre acest eveniment...', 'Add internal notes about this event...'))
                                                    ->rows(5)
                                                    ->columnSpanFull(),
                                            ]),

                                        SC\Section::make($t('Observații pe tipuri de bilete', 'Ticket Type Notes'))
                                            ->schema([
                                                Forms\Components\Placeholder::make('ticket_notes_list')
                                                    ->hiddenLabel()
                                                    ->content(function (?Event $record) use ($t) {
                                                        if (!$record || !$record->exists) {
                                                            return $t('Salvează evenimentul pentru a vedea observațiile de pe tipurile de bilete.', 'Save the event to see ticket type notes.');
                                                        }

                                                        $ticketTypes = $record->ticketTypes()->get();
                                                        $hasNotes = $ticketTypes->filter(fn ($tt) => !empty($tt->admin_notes));

                                                        if ($hasNotes->isEmpty()) {
                                                            return new \Illuminate\Support\HtmlString(
                                                                '<p class="text-sm text-gray-500">' . $t('Nicio observație pe tipurile de bilete.', 'No notes on ticket types.') . '</p>'
                                                            );
                                                        }

                                                        $html = '<div class="space-y-3">';
                                                        foreach ($hasNotes as $tt) {
                                                            $name = e($tt->name);
                                                            $notes = nl2br(e($tt->admin_notes));
                                                            $html .= '<div class="p-3 border border-gray-200 rounded-lg bg-gray-50 dark:bg-gray-800 dark:border-gray-700">';
                                                            $html .= '<div class="mb-1 text-xs font-semibold text-primary-600 dark:text-primary-400">🎫 ' . $name . '</div>';
                                                            $html .= '<div class="text-sm text-gray-700 dark:text-gray-300">' . $notes . '</div>';
                                                            $html .= '</div>';
                                                        }
                                                        $html .= '</div>';

                                                        return new \Illuminate\Support\HtmlString($html);
                                                    }),
                                            ]),
                                    ]), // End Tab 8: Observatii

                                // ── TAB 9: Documente ──
                                SC\Tabs\Tab::make($t('Documente', 'Documents'))
                                    ->key('documente')
                                    ->icon('heroicon-o-document-text')
                                    ->badge(function (?Event $record) {
                                        if (!$record || !$record->exists) return null;

                                        $isEventFinished = $record->isPast() || $record->status === 'archived';
                                        $isEventPublished = (bool) $record->is_published;
                                        $hasSales = \App\Models\Order::where('event_id', $record->id)
                                            ->whereIn('status', ['paid', 'confirmed', 'completed'])
                                            ->where('total', '>', 0)
                                            ->exists();
                                        $decontTypes = ['decont', 'decont_ontop', 'decont_inclus'];

                                        $templates = MarketplaceTaxTemplate::where('marketplace_client_id', $record->marketplace_client_id)
                                            ->where('is_active', true)
                                            ->where(function ($q) {
                                                $q->whereIn('trigger', ['after_event_published', 'after_event_finished', 'after_payout_completed'])
                                                  ->orWhereNull('trigger')
                                                  ->orWhereIn('type', ['cerere_avizare', 'declaratie_impozite', 'decont', 'decont_ontop', 'decont_inclus', 'pv_distrugere']);
                                            })
                                            ->where('type', '!=', 'organizer_contract')
                                            ->get();

                                        $generatedTemplateIds = \App\Models\EventGeneratedDocument::where('event_id', $record->id)
                                            ->pluck('marketplace_tax_template_id')
                                            ->toArray();
                                        $organizerDocTypes = \App\Models\OrganizerDocument::where('event_id', $record->id)
                                            ->pluck('document_type')
                                            ->toArray();

                                        $available = 0;
                                        foreach ($templates as $template) {
                                            if (in_array($template->id, $generatedTemplateIds)) continue;
                                            if (in_array($template->type, $organizerDocTypes)) continue;
                                            // Decont templates require sales
                                            if (in_array($template->type, $decontTypes) && !$hasSales) continue;

                                            $canGenerate = match ($template->trigger) {
                                                'after_event_published' => $isEventPublished,
                                                'after_event_finished' => $isEventFinished,
                                                'after_payout_completed' => true,
                                                default => true,
                                            };

                                            if ($canGenerate) $available++;
                                        }

                                        return $available > 0 ? (string) $available : null;
                                    })
                                    ->badgeColor('warning')
                                    ->schema([
                                        SC\Section::make($t('Documente eveniment', 'Event Documents'))
                                            ->icon('heroicon-o-document-text')
                                            ->visible(fn (?Event $record) => $record && $record->exists)
                                            ->schema([
                                                Forms\Components\Placeholder::make('document_status_list_tab')
                                                    ->hiddenLabel()
                                                    ->content(function (?Event $record) {
                                                        if (!$record) return '';

                                                        $eventTemplates = MarketplaceTaxTemplate::where('marketplace_client_id', $record->marketplace_client_id)
                                                            ->where('is_active', true)
                                                            ->where(function ($q) {
                                                                $q->whereIn('trigger', ['after_event_published', 'after_event_finished', 'after_payout_completed'])
                                                                  ->orWhereNull('trigger')
                                                                  ->orWhereIn('type', ['cerere_avizare', 'declaratie_impozite', 'decont', 'decont_ontop', 'decont_inclus', 'pv_distrugere']);
                                                            })
                                                            ->where('type', '!=', 'organizer_contract')
                                                            ->orderBy('name')
                                                            ->get();

                                                        $generatedDocs = \App\Models\EventGeneratedDocument::where('event_id', $record->id)->get();
                                                        $organizerDocs = \App\Models\OrganizerDocument::where('event_id', $record->id)->get();

                                                        return new HtmlString(
                                                            view('filament.marketplace.components.event-document-status', [
                                                                'event' => $record,
                                                                'templates' => $eventTemplates,
                                                                'generatedDocs' => $generatedDocs,
                                                                'organizerDocs' => $organizerDocs,
                                                            ])->render()
                                                        );
                                                    }),
                                            ]),
                                    ]), // End Tab 9: Documente

                            ]), // End Tabs component
                    ]),
                // ========== COLOANA DREAPTĂ - SIDEBAR (1/4) ==========
                SC\Group::make()
                    ->columnSpan(1)
                    ->schema([
                        SC\Grid::make(2)->schema([
                            Forms\Components\Toggle::make('is_published')
                                ->label($t('Publicat', 'Published'))
                                ->hintIcon('heroicon-o-information-circle', tooltip: $t('Când este activat, evenimentul va fi vizibil pe site-ul marketplace. Când este dezactivat, evenimentul nu va apărea nicăieri.', 'When enabled, the event will be visible on the marketplace site. When disabled, the event will not appear anywhere.'))
                                ->onIcon('heroicon-m-eye')
                                ->offIcon('heroicon-m-eye-slash')
                                ->default(false)
                                ->columnSpan(1),
                            Forms\Components\Placeholder::make('event_status_badge_inline')
                                ->hiddenLabel()
                                ->columnSpan(1)
                                ->visible(fn (?Event $record) => $record && $record->exists)
                                ->content(function (?Event $record) {
                                    if (!$record || !$record->exists) {
                                        return null;
                                    }

                                    if ($record->is_cancelled) {
                                        return new HtmlString('<span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold text-red-400 rounded-full bg-red-500/20 ring-1 ring-inset ring-red-500/30"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>ANULAT</span>');
                                    }

                                    if ($record->is_postponed) {
                                        return new HtmlString('<span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold rounded-full bg-amber-500/20 text-amber-400 ring-1 ring-inset ring-amber-500/30"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>AMÂNAT</span>');
                                    }

                                    $eventEndDateTime = null;
                                    if ($record->duration_mode === 'single_day' && $record->event_date) {
                                        $endTime = $record->end_time ?? '23:59';
                                        $eventEndDateTime = Carbon::parse($record->event_date->format('Y-m-d') . ' ' . $endTime);
                                    } elseif ($record->duration_mode === 'range' && $record->range_end_date) {
                                        $endTime = $record->range_end_time ?? '23:59';
                                        $eventEndDateTime = Carbon::parse($record->range_end_date->format('Y-m-d') . ' ' . $endTime);
                                    } elseif ($record->duration_mode === 'multi_day' && !empty($record->multi_slots)) {
                                        $slots = collect($record->multi_slots);
                                        $lastSlot = $slots->sortByDesc('date')->first();
                                        if ($lastSlot) {
                                            $endTime = $lastSlot['end_time'] ?? '23:59';
                                            $eventEndDateTime = Carbon::parse($lastSlot['date'] . ' ' . $endTime);
                                        }
                                    }

                                    if ($eventEndDateTime && $eventEndDateTime->isPast()) {
                                        return new HtmlString('<span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold text-gray-400 rounded-full bg-gray-500/20 ring-1 ring-inset ring-gray-500/30"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>ÎNCHEIAT</span>');
                                    }

                                    return null;
                                }),
                            Forms\Components\Placeholder::make('preview_link')
                                ->hiddenLabel()
                                ->content(function (?Event $record) use ($marketplace, $t) {
                                    if (!$record || !$record->exists) {
                                        return new \Illuminate\Support\HtmlString('<span class="text-gray-500">' . $t('Salvați evenimentul pentru a genera link-ul de previzualizare', 'Save the event to generate the preview link') . '</span>');
                                    }
                                    // Use the marketplace from form context (not from record) for consistency
                                    $eventMarketplace = $record->marketplaceClient ?? $marketplace;
                                    if (!$eventMarketplace) {
                                        return new \Illuminate\Support\HtmlString('<span class="text-warning-600">' . $t('Niciun marketplace configurat', 'No marketplace configured') . '</span>');
                                    }
                                    // MarketplaceClient has a single 'domain' field, not a 'domains' relationship
                                    $domain = $eventMarketplace->domain;
                                    if (!$domain) {
                                        return new \Illuminate\Support\HtmlString('<span class="text-warning-600">' . $t('Niciun domeniu configurat pentru marketplace', 'No domain configured for marketplace') . '</span>');
                                    }
                                    // Strip any existing protocol from domain (handle various formats)
                                    $domain = preg_replace('#^(https?:?/?/?|//)#i', '', $domain);
                                    $domain = ltrim($domain, '/');
                                    $protocol = str_contains($domain, 'localhost') ? 'http' : 'https';
                                    $eventUrl = $protocol . '://' . $domain . '/bilete/' . $record->slug;
                                    $previewUrl = $eventUrl . '?preview=1';

                                    return new \Illuminate\Support\HtmlString(
                                        '<a href="' . e($previewUrl) . '" target="_blank" class="inline-flex items-center justify-center gap-2 w-full px-4 py-2.5 text-sm font-semibold text-white rounded-lg bg-primary-600 hover:bg-primary-500 transition-colors shadow-sm">' .
                                            '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>' .
                                            $t('Previzualizare', 'Preview') .
                                        '</a>'
                                    );
                                }),
                            Forms\Components\Placeholder::make('test_order_link')
                                ->hiddenLabel()
                                ->visible(fn (?Event $record) => $record && $record->exists)
                                ->content(function (?Event $record) use ($marketplace, $t) {
                                    if (!$record || !$record->exists || !$marketplace) {
                                        return null;
                                    }
                                    $slug = $record->slug ?? $record->id;
                                    $domain = $marketplace->domain ?? $marketplace->primary_domain ?? 'localhost';
                                    $domain = preg_replace('#^https?://#', '', rtrim($domain, '/'));
                                    $protocol = str_contains($domain, 'localhost') ? 'http' : 'https';
                                    $token = \App\Http\Controllers\Api\MarketplaceClient\BaseController::generatePreviewToken($record->id, auth()->id());
                                    $url = "{$protocol}://{$domain}/bilete/{$slug}?preview=1&preview_token={$token}";

                                    return new \Illuminate\Support\HtmlString(
                                        '<button type="button" onclick="navigator.clipboard.writeText(\'' . e($url) . '\'); this.querySelector(\'span\').textContent=\'' . $t('Copiat!', 'Copied!') . '\'; setTimeout(() => this.querySelector(\'span\').textContent=\'' . $t('Link test', 'Test link') . '\', 2000);" class="inline-flex items-center justify-center w-full gap-2 px-4 py-2 text-sm font-semibold no-underline transition-colors rounded-lg cursor-pointer text-amber-200 bg-amber-600/30 hover:bg-amber-600/50">' .
                                            '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>' .
                                            '<span>' . $t('Link test', 'Test link') . '</span>' .
                                        '</button>'
                                    );
                                }),
                            Forms\Components\TextInput::make('access_password')
                                ->label($t('Parolă acces eveniment', 'Event access password'))
                                ->hintIcon('heroicon-o-information-circle', tooltip: $t('Dacă setezi o parolă, pagina evenimentului va fi accesibilă doar după introducerea parolei. Lasă gol pentru acces liber.', 'If you set a password, the event page will only be accessible after entering the password. Leave empty for open access.'))
                                ->placeholder($t('Lasă gol pentru acces liber', 'Leave empty for open access'))
                                ->helperText(fn (?Event $record) => $record?->access_password
                                    ? new \Illuminate\Support\HtmlString('<span class="font-medium text-warning-600">' . $t('🔒 Evenimentul este protejat cu parolă', '🔒 Event is password protected') . '</span>')
                                    : null
                                )
                                ->prefixIcon('heroicon-o-lock-closed')
                                ->columnSpanFull(),
                            Forms\Components\Select::make('display_template')
                                ->label($t('Tip pagină', 'Page template'))
                                ->options([
                                    'standard' => $t('Standard (eveniment)', 'Standard (event)'),
                                    'leisure_venue' => $t('Locație de agrement', 'Leisure venue'),
                                ])
                                ->default('standard')
                                ->live()
                                ->hintIcon('heroicon-o-information-circle', tooltip: $t('Locație de agrement: calendar + bilete zilnice + parcare', 'Leisure venue: calendar + daily tickets + parking'))
                                ->columnSpanFull(),
                            Forms\Components\TextInput::make('redirect_url')
                                ->label($t('Redirect', 'Redirect'))
                                ->url()
                                ->maxLength(500)
                                ->placeholder('https://...')
                                ->prefixIcon('heroicon-o-arrow-top-right-on-square')
                                ->hintIcon('heroicon-o-information-circle', tooltip: $t(
                                    'Dacă setezi un URL, evenimentul va apărea în listări dar link-ul va duce către acest URL (filă nouă). Pagina evenimentului nu va fi accesibilă direct.',
                                    'If you set a URL, the event will appear in listings but the link will go to this URL (new tab). The event page will not be directly accessible.'
                                ))
                                ->columnSpanFull(),
                        ]),

                        // 1. Quick Stats Card - Vânzări LIVE
                        SC\Section::make(fn () => new HtmlString($t('Vânzări', 'Sales') . ' <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-500/20 text-green-400 ring-1 ring-inset ring-green-500/30">LIVE</span>'))
                            ->icon('heroicon-o-chart-bar')
                            ->compact()
                            ->extraAttributes(['class' => 'fi-section-sales-live'])
                            ->headerActions([
                                Action::make('edit_views_count')
                                    ->label($t('Vizualizări', 'Views'))
                                    ->icon('heroicon-o-eye')
                                    ->tooltip($t('Editează numărul de vizualizări', 'Edit view count'))
                                    ->color('gray')
                                    ->visible(fn (?Event $record) => $record && $record->exists)
                                    ->fillForm(fn (?Event $record) => [
                                        'views_count' => (int) ($record->views_count ?? 0),
                                    ])
                                    ->schema([
                                        Forms\Components\TextInput::make('views_count')
                                            ->label($t('Vizualizări', 'Views'))
                                            ->numeric()
                                            ->minValue(0)
                                            ->required()
                                            ->helperText($t(
                                                'Modifică manual numărul afișat. Se folosește pentru a corecta date sau a echivala traficul migrat de pe alte platforme.',
                                                'Override the displayed count. Used to correct data or match traffic migrated from other platforms.'
                                            )),
                                    ])
                                    ->modalHeading($t('Editează vizualizări', 'Edit views'))
                                    ->modalSubmitActionLabel($t('Salvează', 'Save'))
                                    ->action(function (array $data, ?Event $record) use ($t): void {
                                        if (!$record) return;
                                        $record->update(['views_count' => (int) $data['views_count']]);
                                        \Filament\Notifications\Notification::make()
                                            ->title($t('Vizualizări actualizate', 'Views updated'))
                                            ->success()
                                            ->send();
                                    }),
                            ])
                            ->schema([
                                Forms\Components\Placeholder::make('stats_overview')
                                    ->hiddenLabel()
                                    ->content(function (?Event $record) use ($t) {
                                        if (!$record || !$record->exists) {
                                            return new HtmlString('<div class="text-sm text-gray-500">' . $t('Salvează evenimentul pentru a vedea statisticile.', 'Save the event to see statistics.') . '</div>');
                                        }

                                        // Revenue now comes from the "valid tickets only" breakdown below.
                                        $eventId = $record->id;

                                        // Count valid tickets — EXCLUDE external imports
                                        // Use the same status filter as the "Bilete" button below so both numbers match.
                                        $ticketsSold = \App\Models\Ticket::where(fn($q) => $q->where('event_id', $eventId)->orWhere('marketplace_event_id', $eventId))
                                            ->whereIn('status', ['valid', 'used'])
                                            ->whereHas('order', fn($q) => $q->where('source', '!=', 'external_import'))
                                            ->count();

                                        $totalCapacity = $record->general_quota ?? $record->capacity ?? $record->ticketTypes->sum(fn ($tt) => $tt->capacity ?? 0) ?? 0;
                                        $views = $record->views ?? $record->views_count ?? 0;

                                        $percentSold = $totalCapacity > 0 ? round(($ticketsSold / $totalCapacity) * 100) : 0;
                                        $conversion = $views > 0 ? round(($ticketsSold / $views) * 100, 1) : 0;

                                        $ticketsLabel = $t('Bilete', 'Tickets');
                                        $revenueLabel = $t('Venituri (RON)', 'Revenue (RON)');
                                        $capacityLabel = $t('Capacitate totală', 'Total capacity');
                                        $conversionLabel = $t('Conversie', 'Conversion');
                                        $viewsLabel = $t('Vizualizări', 'Views');

                                        $statisticsUrl = static::getUrl('statistics', ['record' => $record]);
                                        $analyticsUrl = static::getUrl('analytics', ['record' => $record]);
                                        $statisticsLabel = $t('Statistici', 'Statistics');
                                        $analyticsLabel = $t('Analiză', 'Analytics');

                                        // Tickets & Orders counts and URLs — EXCLUDE external imports
                                        $eventId = $record->id;
                                        $ticketsQuery = \App\Models\Ticket::where(fn ($q) => $q->where('event_id', $eventId)->orWhere('marketplace_event_id', $eventId))
                                            ->whereHas('order', fn($q) => $q->where('source', '!=', 'external_import'));
                                        $ticketCountValid = (clone $ticketsQuery)->whereIn('status', ['valid', 'used'])->count();
                                        $ticketCountCancelled = (clone $ticketsQuery)->where('status', 'cancelled')->count();
                                        $ticketCountRefunded = (clone $ticketsQuery)->whereIn('status', ['refunded', 'void'])->count();
                                        $ordersQuery = \App\Models\Order::where(fn ($q) => $q
                                            ->where('event_id', $eventId)
                                            ->orWhereHas('tickets', fn ($tq) => $tq->where('event_id', $eventId)->orWhere('marketplace_event_id', $eventId))
                                        )->where('source', '!=', 'external_import');
                                        $orderCountCompleted = (clone $ordersQuery)->whereIn('status', ['completed', 'confirmed'])->count();
                                        $ticketsUrl = \App\Filament\Marketplace\Resources\TicketResource::getUrl('index') . '?event_id=' . $eventId;
                                        $ordersUrl = \App\Filament\Marketplace\Resources\OrderResource::getUrl('index') . '?event_id=' . $eventId;
                                        $ticketsBtnLabel = $t('Bilete', 'Tickets') . ($ticketCountValid > 0 ? " ({$ticketCountValid})" : '');
                                        $ordersBtnLabel = $t('Comenzi', 'Orders') . ($orderCountCompleted > 0 ? " ({$orderCountCompleted})" : '');

                                        $btnClass = 'inline-flex items-center justify-center gap-1.5 px-3 py-1.5 text-sm font-semibold rounded-lg transition-colors no-underline';

                                        $cancelledLabel = $t('Anulate', 'Cancelled');
                                        $refundedLabel = $t('Rambursate', 'Refunded');

                                        // Shared helper: Revenue/Net/Commission/Extras/Discount all derived from
                                        // the same breakdown. Revenue reflects money customers paid for CURRENTLY
                                        // VALID tickets (excludes later-cancelled portion). See getSalesBreakdown.
                                        $breakdown = self::getSalesBreakdown($record);
                                        $totalRevenue = $breakdown['total_revenue'];
                                        $totalNet = $breakdown['total_net'];
                                        $totalCommission = $breakdown['total_commission'];
                                        $totalExtras = $breakdown['total_extras'];
                                        $totalDiscount = $breakdown['total_discount'];
                                        $revenueFormatted = number_format($totalRevenue, 2, ',', '.');
                                        $netLabel = $t('Net (RON)', 'Net (RON)');
                                        $commissionLabel = $t('Comisioane (RON)', 'Commissions (RON)');
                                        $extrasLabel = $t('Taxe / Asigurări (RON)', 'Fees / Insurance (RON)');
                                        $discountLabel = $t('Discounturi (RON)', 'Discounts (RON)');
                                        $netFormatted = number_format($totalNet, 2, ',', '.');
                                        $commissionFormatted = number_format($totalCommission, 2, ',', '.');
                                        $extrasFormatted = number_format($totalExtras, 2, ',', '.');
                                        $discountFormatted = number_format($totalDiscount, 2, ',', '.');

                                        return new HtmlString("
                                            <div class='grid grid-cols-2 gap-3'>
                                                <div class='p-3 text-center bg-gray-800 rounded-lg'>
                                                    <div class='text-2xl font-bold text-white'>" . number_format($ticketsSold) . "</div>
                                                    <div class='text-xs text-gray-400'>{$ticketsLabel}</div>
                                                </div>
                                                <div class='p-3 text-center bg-gray-800 rounded-lg'>
                                                    <div class='text-2xl font-bold text-emerald-400'>{$revenueFormatted}</div>
                                                    <div class='text-xs text-gray-400'>{$revenueLabel}</div>
                                                </div>
                                                <div class='p-3 text-center bg-gray-800 rounded-lg'>
                                                    <div class='text-2xl font-bold text-red-400'>" . number_format($ticketCountCancelled) . "</div>
                                                    <div class='text-xs text-gray-400'>{$cancelledLabel}</div>
                                                </div>
                                                <div class='p-3 text-center bg-gray-800 rounded-lg'>
                                                    <div class='text-2xl font-bold text-amber-400'>" . number_format($ticketCountRefunded) . "</div>
                                                    <div class='text-xs text-gray-400'>{$refundedLabel}</div>
                                                </div>
                                                <div class='p-3 text-center bg-gray-800 rounded-lg'>
                                                    <div class='text-2xl font-bold text-emerald-300'>{$netFormatted}</div>
                                                    <div class='text-xs text-gray-400'>{$netLabel}</div>
                                                </div>
                                                <div class='p-3 text-center bg-gray-800 rounded-lg'>
                                                    <div class='text-2xl font-bold text-sky-400'>{$commissionFormatted}</div>
                                                    <div class='text-xs text-gray-400'>{$commissionLabel}</div>
                                                </div>
                                            </div>
                                            <div class='grid grid-cols-2 gap-3 mt-3'>
                                                <div class='p-3 text-center bg-gray-800 rounded-lg'>
                                                    <div class='text-2xl font-bold text-purple-400'>{$extrasFormatted}</div>
                                                    <div class='text-xs text-gray-400'>{$extrasLabel}</div>
                                                </div>
                                                <div class='p-3 text-center bg-gray-800 rounded-lg'>
                                                    <div class='text-2xl font-bold text-orange-400'>{$discountFormatted}</div>
                                                    <div class='text-xs text-gray-400'>{$discountLabel}</div>
                                                </div>
                                            </div>
                                            <div class='mt-3'>
                                                <div class='flex justify-between mb-1 text-xs text-gray-400'>
                                                    <span>{$capacityLabel}</span>
                                                    <span>" . number_format($ticketsSold) . " / " . number_format($totalCapacity) . " ({$percentSold}%)</span>
                                                </div>
                                                <div class='h-2 overflow-hidden bg-gray-700 rounded-full'>
                                                    <div class='h-full transition-all rounded-full' style='width: {$percentSold}%; background: linear-gradient(to right, #10b981, #34d399);'></div>
                                                </div>
                                            </div>
                                            <div class='flex justify-between mt-3 text-xs'>
                                                <span class='text-gray-400'>{$conversionLabel}</span>
                                                <span class='font-semibold text-primary-400'>{$conversion}%</span>
                                            </div>
                                            <div class='flex justify-between mt-1 text-xs'>
                                                <span class='text-gray-400'>{$viewsLabel}</span>
                                                <span class='text-white'>" . number_format($views) . "</span>
                                            </div>
                                            <div class='grid grid-cols-2 gap-2 pt-3 mt-4 border-t border-gray-700'>
                                                <a href='{$ticketsUrl}' class='{$btnClass} text-gray-200 bg-gray-700 hover:bg-gray-600'>
                                                    <svg class='w-3.5 h-3.5' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z'/></svg>
                                                    {$ticketsBtnLabel}
                                                </a>
                                                <a href='{$ordersUrl}' class='{$btnClass} text-gray-200 bg-gray-700 hover:bg-gray-600'>
                                                    <svg class='w-3.5 h-3.5' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z'/></svg>
                                                    {$ordersBtnLabel}
                                                </a>
                                            </div>
                                            <div class='grid grid-cols-2 gap-2 mt-2'>
                                                <a href='{$statisticsUrl}' class='{$btnClass} text-white bg-blue-600 hover:bg-blue-800'>
                                                    <svg class='w-3.5 h-3.5' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'/></svg>
                                                    {$statisticsLabel}
                                                </a>
                                                <a href='{$analyticsUrl}' class='{$btnClass} text-white bg-emerald-600 hover:bg-emerald-800'>
                                                    <svg class='w-3.5 h-3.5' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z'/></svg>
                                                    {$analyticsLabel}
                                                </a>
                                            </div>
                                        ");
                                    }),

                                Forms\Components\Placeholder::make('ticket_type_breakdown')
                                    ->hiddenLabel()
                                    ->content(function (?Event $record) use ($t) {
                                        if (!$record || !$record->exists) return '';

                                        $eventId = $record->id;
                                        $totals = ['online' => 0, 'app' => 0, 'invitations' => 0];

                                        // Pull per-type Net from the same breakdown used by the top metric so
                                        // the table rows sum to the headline Net (discounts allocated, commission
                                        // inheritance resolved, invitations excluded from Net).
                                        $breakdown = self::getSalesBreakdown($record);
                                        $perType = $breakdown['per_type']; // keyed by tt_id

                                        $rows = $record->ticketTypes->map(function ($tt) use (&$totals, $perType) {
                                            // Valid/cancelled counts include all non-external-import tickets
                                            // (including invitations without an order) — displayed for inventory.
                                            $base = \App\Models\Ticket::where('ticket_type_id', $tt->id)
                                                ->where(function ($q) {
                                                    $q->whereDoesntHave('order')
                                                      ->orWhereHas('order', fn ($qq) => $qq->where('source', '!=', 'external_import'));
                                                });
                                            $valid = (clone $base)->whereIn('status', ['valid', 'used'])->count();
                                            $cancelled = (clone $base)->where('status', 'cancelled')->count();
                                            $stock = $tt->quota_total ?? $tt->capacity ?? 0;

                                            // Net comes from the authoritative breakdown (paid orders only).
                                            $netTotal = $perType[$tt->id]['net'] ?? 0.0;

                                            $isInvitation = ($tt->name === 'Invitatie') || ($tt->meta['is_invitation'] ?? false);
                                            if ($isInvitation) {
                                                $totals['invitations'] += $valid;
                                            } elseif ($tt->is_entry_ticket) {
                                                $totals['app'] += $valid;
                                            } else {
                                                $totals['online'] += $valid;
                                            }

                                            return [
                                                'name' => $tt->name,
                                                'valid' => $valid,
                                                'cancelled' => $cancelled,
                                                'stock' => $stock,
                                                'net' => $netTotal,
                                            ];
                                        });

                                        $nameLabel = $t('Tip bilet', 'Ticket type');
                                        $validLabel = $t('Valide', 'Valid');
                                        $cancelledLabel = $t('Anulate', 'Cancelled');
                                        $stockLabel = $t('Stoc', 'Stock');
                                        $netLabel = $t('Net', 'Net');

                                        $rowsHtml = '';
                                        foreach ($rows as $r) {
                                            $name = e($r['name'] ?? '—');
                                            $netFmt = number_format($r['net'] ?? 0, 2, ',', '.');
                                            $rowsHtml .= "
                                                <tr class='border-t border-gray-700'>
                                                    <td class='py-2 pr-2 text-gray-200'>{$name}</td>
                                                    <td class='py-2 px-2 text-right text-emerald-400 font-semibold'>{$r['valid']}</td>
                                                    <td class='py-2 px-2 text-right text-red-400 font-semibold'>{$r['cancelled']}</td>
                                                    <td class='py-2 px-2 text-right text-gray-300'>{$r['stock']}</td>
                                                    <td class='py-2 pl-2 text-right text-emerald-300 font-semibold'>{$netFmt}</td>
                                                </tr>";
                                        }

                                        $exportUrl = url('/marketplace/tickets-export-csv?event_id=' . $eventId);
                                        $exportLabel = $t('Export date', 'Export data');

                                        $onlineLabel = $t('Online', 'Online');
                                        $appLabel = $t('Aplicație', 'App');
                                        $invitationsLabel = $t('Invitații', 'Invitations');

                                        return new HtmlString("
                                            <div class='pt-3 mt-3 border-t border-gray-700'>
                                                <table class='w-full text-xs'>
                                                    <thead>
                                                        <tr class='text-gray-400 uppercase tracking-wide'>
                                                            <th class='py-1 pr-2 text-left'>{$nameLabel}</th>
                                                            <th class='py-1 px-2 text-right'>{$validLabel}</th>
                                                            <th class='py-1 px-2 text-right'>{$cancelledLabel}</th>
                                                            <th class='py-1 px-2 text-right'>{$stockLabel}</th>
                                                            <th class='py-1 pl-2 text-right'>{$netLabel}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>{$rowsHtml}</tbody>
                                                </table>
                                                <div class='grid grid-cols-3 gap-2 mt-3'>
                                                    <div class='p-2 text-center bg-gray-800 rounded-lg'>
                                                        <div class='text-lg font-bold text-emerald-400'>" . number_format($totals['online']) . "</div>
                                                        <div class='text-[10px] text-gray-400 leading-tight'>{$onlineLabel}</div>
                                                    </div>
                                                    <div class='p-2 text-center bg-gray-800 rounded-lg'>
                                                        <div class='text-lg font-bold text-sky-400'>" . number_format($totals['app']) . "</div>
                                                        <div class='text-[10px] text-gray-400 leading-tight'>{$appLabel}</div>
                                                    </div>
                                                    <div class='p-2 text-center bg-gray-800 rounded-lg'>
                                                        <div class='text-lg font-bold text-purple-400'>" . number_format($totals['invitations']) . "</div>
                                                        <div class='text-[10px] text-gray-400 leading-tight'>{$invitationsLabel}</div>
                                                    </div>
                                                </div>
                                                <a href='{$exportUrl}' class='inline-flex items-center justify-center w-full gap-1.5 px-3 py-2 mt-3 text-sm font-semibold text-white transition-colors rounded-lg bg-indigo-600 hover:bg-indigo-700 no-underline'>
                                                    <svg class='w-4 h-4' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3'/></svg>
                                                    {$exportLabel}
                                                </a>
                                            </div>
                                        ");
                                    }),
                            ]),

                        // External Sales Section (only if external imports exist)
                        SC\Section::make(fn () => new HtmlString($t('Vânzări terți', 'Third-party Sales') . ' <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-500/20 text-indigo-400 ring-1 ring-inset ring-indigo-500/30">EXTERN</span>'))
                            ->icon('heroicon-o-globe-alt')
                            ->compact()
                            ->visible(function (?Event $record) {
                                if (!$record?->exists) return false;
                                return \App\Models\Order::where('event_id', $record->id)
                                    ->where('source', 'external_import')
                                    ->exists();
                            })
                            ->schema([
                                Forms\Components\Placeholder::make('external_sales')
                                    ->hiddenLabel()
                                    ->content(function (?Event $record) use ($t) {
                                        if (!$record?->exists) return '';
                                        $eventId = $record->id;

                                        $extRevenue = (float) \App\Models\Order::where('event_id', $eventId)
                                            ->where('source', 'external_import')
                                            ->whereIn('status', ['paid', 'confirmed', 'completed'])
                                            ->sum('total');

                                        $extTickets = \App\Models\Ticket::where('event_id', $eventId)
                                            ->whereNotIn('status', ['cancelled', 'refunded', 'void'])
                                            ->whereHas('order', fn($q) => $q->where('source', 'external_import'))
                                            ->count();

                                        $extOrders = \App\Models\Order::where('event_id', $eventId)
                                            ->where('source', 'external_import')
                                            ->whereIn('status', ['paid', 'confirmed', 'completed'])
                                            ->count();

                                        // Get platforms
                                        $platforms = \App\Models\Order::where('event_id', $eventId)
                                            ->where('source', 'external_import')
                                            ->get()
                                            ->pluck('meta.external_platform')
                                            ->filter()
                                            ->unique()
                                            ->implode(', ');

                                        $ticketsLabel = $t('Bilete', 'Tickets');
                                        $revenueLabel = $t('Venituri (RON)', 'Revenue (RON)');
                                        $ordersLabel = $t('Comenzi', 'Orders');

                                        return new HtmlString("
                                            <div style='font-size:11px;color:#818CF8;margin-bottom:8px;'>🌐 {$platforms}</div>
                                            <div class='grid grid-cols-3 gap-2'>
                                                <div class='p-2 text-center bg-gray-800 rounded-lg'>
                                                    <div class='text-lg font-bold text-indigo-400'>" . number_format($extTickets) . "</div>
                                                    <div class='text-xs text-gray-400'>{$ticketsLabel}</div>
                                                </div>
                                                <div class='p-2 text-center bg-gray-800 rounded-lg'>
                                                    <div class='text-lg font-bold text-indigo-400'>" . number_format($extRevenue, 2, ',', '.') . "</div>
                                                    <div class='text-xs text-gray-400'>{$revenueLabel}</div>
                                                </div>
                                                <div class='p-2 text-center bg-gray-800 rounded-lg'>
                                                    <div class='text-lg font-bold text-indigo-400'>" . number_format($extOrders) . "</div>
                                                    <div class='text-xs text-gray-400'>{$ordersLabel}</div>
                                                </div>
                                            </div>
                                        ");
                                    }),
                            ]),

                        // Blocked Seats Overview
                        SC\Section::make($t('Locuri Blocate', 'Blocked Seats'))
                            ->icon('heroicon-o-lock-closed')
                            ->compact()
                            ->visible(fn (?Event $record) => $record && $record->exists && $record->venue?->seatingLayouts()->withoutGlobalScopes()->where('status', 'published')->exists())
                            ->schema([
                                Forms\Components\Placeholder::make('blocked_seats_overview')
                                    ->hiddenLabel()
                                    ->content(function (?Event $record) use ($t) {
                                        if (!$record || !$record->exists) {
                                            return '';
                                        }

                                        // Get event seating layout
                                        $eventSeating = \App\Models\Seating\EventSeatingLayout::where('event_id', $record->id)
                                            ->published()
                                            ->first();

                                        if (!$eventSeating) {
                                            return new HtmlString('<div class="text-xs text-gray-500">' . $t('Nu există layout de locuri activ.', 'No active seating layout.') . '</div>');
                                        }

                                        // Get all blocked seats
                                        $blockedSeats = \App\Models\Seating\EventSeat::where('event_seating_id', $eventSeating->id)
                                            ->where('status', 'blocked')
                                            ->orderBy('section_name')
                                            ->orderByRaw(\DB::getDriverName() === 'pgsql'
                                                ? "CASE WHEN row_label ~ '^[0-9]+$' THEN CAST(row_label AS INTEGER) ELSE 0 END, row_label"
                                                : "CAST(row_label AS UNSIGNED), row_label")
                                            ->orderByRaw(\DB::getDriverName() === 'pgsql'
                                                ? "CASE WHEN seat_label ~ '^[0-9]+$' THEN CAST(seat_label AS INTEGER) ELSE 0 END, seat_label"
                                                : "CAST(seat_label AS UNSIGNED), seat_label")
                                            ->get();

                                        if ($blockedSeats->isEmpty()) {
                                            return new HtmlString('<div class="text-xs text-gray-500">' . $t('Nu există locuri blocate.', 'No blocked seats.') . '</div>');
                                        }

                                        // Get invitations for this event that have seat_ref
                                        $batchIds = \App\Models\InviteBatch::where('marketplace_client_id', $record->marketplace_client_id)
                                            ->where('event_ref', $record->id)
                                            ->pluck('id')
                                            ->toArray();

                                        $inviteSeatRefs = [];
                                        if (!empty($batchIds)) {
                                            $inviteSeatRefs = \App\Models\Invite::whereIn('batch_id', $batchIds)
                                                ->whereNotNull('seat_ref')
                                                ->where('seat_ref', '!=', '')
                                                ->where('status', '!=', 'void')
                                                ->pluck('seat_ref')
                                                ->map(fn ($ref) => trim($ref))
                                                ->toArray();
                                        }

                                        // Build display
                                        $totalBlocked = $blockedSeats->count();
                                        $totalWithInvite = 0;

                                        $html = "<div class='space-y-2'>";
                                        $html .= "<div class='flex items-center justify-between'>";
                                        $html .= "<span class='text-sm font-semibold text-red-400'>{$totalBlocked} locuri blocate</span>";
                                        $html .= "</div>";

                                        // Group by section then row
                                        $grouped = $blockedSeats->groupBy('section_name');
                                        foreach ($grouped as $sectionName => $sectionSeats) {
                                            $html .= "<div class='mt-2'>";
                                            $html .= "<div class='mb-1 text-xs font-medium text-gray-300'>" . e($sectionName) . "</div>";

                                            $byRow = $sectionSeats->groupBy('row_label');
                                            foreach ($byRow as $rowLabel => $rowSeats) {
                                                $html .= "<div class='flex flex-wrap items-center gap-1 mb-1 ml-3 text-xs text-gray-400'>";
                                                $html .= "<span class='text-gray-500 shrink-0'>R{$rowLabel}:</span> ";

                                                foreach ($rowSeats->sortBy(fn($s) => (int)$s->seat_label) as $seat) {
                                                    $seatLabel = $seat->seat_label;
                                                    // Check if this seat has an invitation by matching seat_ref
                                                    $hasInvite = in_array($seatLabel, $inviteSeatRefs);
                                                    if ($hasInvite) {
                                                        $totalWithInvite++;
                                                        $html .= "<span class='inline-flex items-center px-1.5 py-0.5 rounded bg-purple-900/50 text-purple-300 ring-1 ring-purple-700/50' title='Are invitație'>{$seatLabel}</span>";
                                                    } else {
                                                        $html .= "<span class='inline-flex items-center px-1.5 py-0.5 rounded bg-red-900/40 text-red-300 ring-1 ring-red-800/50'>{$seatLabel}</span>";
                                                    }
                                                }

                                                $html .= "</div>";
                                            }

                                            $html .= "</div>";
                                        }

                                        // Legend
                                        $html .= "<div class='flex flex-wrap gap-3 pt-2 mt-3 text-xs border-t border-gray-700/50'>";
                                        $html .= "<span class='flex items-center gap-1'><span class='w-3 h-3 rounded bg-red-900/40 ring-1 ring-red-800/50'></span> Blocat</span>";
                                        $html .= "<span class='flex items-center gap-1'><span class='w-3 h-3 rounded bg-purple-900/50 ring-1 ring-purple-700/50'></span> Cu invitație</span>";
                                        $html .= "</div>";

                                        if ($totalWithInvite > 0) {
                                            $html .= "<div class='mt-1 text-xs text-purple-400'>{$totalWithInvite} din {$totalBlocked} au invitații</div>";
                                        }

                                        $html .= "</div>";
                                        return new HtmlString($html);
                                    }),
                            ]),

                            // 2. Organizer Quick Info
                        SC\Section::make($t('Organizator', 'Organizer'))
                            ->icon('heroicon-o-building-office-2')
                            ->compact()
                            ->schema([
                                Forms\Components\Select::make('marketplace_organizer_id')
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
                                    ->live(onBlur: true)
                                    ->partiallyRenderAfterStateUpdated()
                                    ->placeholder($t('Selectează organizator...', 'Select organizer...'))
                                    ->afterStateUpdated(function ($state, SSet $set) use ($marketplace, $marketplaceLanguage) {
                                        // When organizer changes, update commission info and ticket terms
                                        if ($state) {
                                            $organizer = MarketplaceOrganizer::find($state);
                                            if ($organizer) {
                                                // Pre-fill commission rate with organizer's rate if set
                                                $rate = $organizer->commission_rate ?? $marketplace?->commission_rate;
                                                $set('commission_rate', $rate);

                                                // Pre-fill commission mode with organizer's default if set
                                                if ($organizer->default_commission_mode) {
                                                    $set('commission_mode', $organizer->default_commission_mode);
                                                }

                                                // Pre-fill ticket terms from organizer if available
                                                if ($organizer->ticket_terms) {
                                                    $set("ticket_terms.{$marketplaceLanguage}", $organizer->ticket_terms);
                                                }
                                            }
                                        }
                                    })
                                    ->hintIcon('heroicon-o-information-circle', tooltip: $t('Organizatorul selectat va primi plățile pentru acest eveniment', 'The selected organizer will receive payouts for this event'))
                                    ->prefixIcon('heroicon-m-building-office-2'),

                                Forms\Components\Placeholder::make('organizer_quick_info')
                                    ->hiddenLabel()
                                    ->visible(fn (SGet $get) => (bool) $get('marketplace_organizer_id'))
                                    ->content(function (SGet $get) use ($marketplace, $t) {
                                        $organizerId = $get('marketplace_organizer_id');
                                        if (!$organizerId) return '';
                                        
                                        $organizer = MarketplaceOrganizer::find($organizerId);
                                        if (!$organizer) return '';
                                        
                                        $commissionRate = $organizer->commission_rate ?? $marketplace?->commission_rate ?? 5;

                                        $organizerId = $get('marketplace_organizer_id');
                                        if (!$organizerId) return '';

                                        $status = match($organizer->status) {
                                            'active' => '<span class="text-green-600">' . $t('Activ', 'Active') . '</span>',
                                            'pending' => '<span class="text-yellow-600">' . $t('În așteptare', 'Pending') . '</span>',
                                            'suspended' => '<span class="text-red-600">' . $t('Suspendat', 'Suspended') . '</span>',
                                            default => $organizer->status,
                                        };

                                        $verified = $organizer->verified_at
                                            ? '<span class="text-green-600">✓ ' . $t('Verificat', 'Verified') . '</span>'
                                            : '<span class="text-gray-500">' . $t('Neverificat', 'Unverified') . '</span>';

                                        $commissionRate = $organizer->commission_rate ?? $marketplace?->commission_rate ?? 5;
                                        $commissionMode = $organizer->default_commission_mode ?? $marketplace->commission_mode ?? 'included';
                                        $commissionModeLabel = $commissionMode === 'included' ? $t('inclus', 'included') : $t('peste', 'on top');

                                        $statusLabel = 'Status';
                                        $commissionLabel = $t('Comision', 'Commission');
                                        $eventsLabel = $t('Evenimente', 'Events');
                                        $revenueLabel = $t('Venit', 'Revenue');

                                        return new HtmlString("
                                            <div class='text-sm'>
                                                <div class='flex items-center gap-2 pb-2'>
                                                    <div class='flex items-center justify-center w-8 h-8 text-xs font-bold text-white rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600'>
                                                        " . strtoupper(substr($organizer->name, 0, 2)) . "
                                                    </div>
                                                    <div>
                                                        <div class='font-semibold text-white'>{$organizer->name}</div>
                                                        <div class='text-xs text-gray-400'>{$organizer->email}</div>
                                                    </div>
                                                </div>
                                                <div class='flex justify-between py-1 border-t border-gray-700'>
                                                    <span class='text-gray-400'>{$statusLabel}</span>
                                                    <span class='font-medium text-white'>{$status} | {$verified}</span>
                                                </div>
                                                <div class='flex justify-between py-1 border-t border-gray-700'>
                                                    <span class='text-gray-400'>{$commissionLabel} ({$commissionModeLabel})</span>
                                                    <span class='font-medium text-white'>{$commissionRate}%</span>
                                                </div>
                                                <div class='flex justify-between py-1 border-t border-gray-700'>
                                                    <span class='text-gray-400'>{$eventsLabel}</span>
                                                    <span class='font-medium text-white'>{$organizer->total_events}</span>
                                                </div>
                                                <div class='flex justify-between py-1 border-t border-gray-700'>
                                                    <span class='text-gray-400'>{$revenueLabel}</span>
                                                    <span class='font-medium text-white'>" . number_format($organizer->total_revenue, 2) . " RON</span>
                                                </div>
                                            </div>
                                        ");
                                    }),

                                Forms\Components\Toggle::make('organizer_notify_enabled')
                                    ->label($t('Trimite notificări automate organizator', 'Send automatic notifications to organizer'))
                                    ->live()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(function ($component, $record) {
                                        $notifications = $record?->organizer_notifications ?? [];
                                        $component->state(!empty($notifications) && count(array_filter($notifications)) > 0);
                                    }),

                                Forms\Components\CheckboxList::make('organizer_notifications')
                                    ->label($t('Tipuri de notificări', 'Notification types'))
                                    ->visible(fn (SGet $get) => (bool) $get('organizer_notify_enabled'))
                                    ->options(function () use ($marketplace) {
                                        return \App\Models\MarketplaceEmailTemplate::where('marketplace_client_id', $marketplace?->id)
                                            ->where('notify_organizer', true)
                                            ->where('is_active', true)
                                            ->pluck('name', 'slug')
                                            ->toArray();
                                    })
                                    ->columns(1)
                                    ->helperText($t('Selectează ce notificări primește organizatorul pentru acest eveniment.', 'Select which notifications the organizer receives for this event.')),
                            ]),


                        // 6. Activity Log (doar pentru edit)
                        SC\Section::make($t('Activitate recentă', 'Recent activity'))
                            ->icon('heroicon-o-clock')
                            ->compact()
                            ->collapsed()
                            ->visible(fn (?Event $record) => $record && $record->exists)
                            ->schema([
                                Forms\Components\Placeholder::make('recent_activity')
                                    ->hiddenLabel()
                                    ->content(function (?Event $record) use ($t) {
                                        if (!$record) return '';

                                        $html = "<div class='space-y-3 text-sm'>";

                                        // Try to get activity log from spatie/laravel-activitylog
                                        try {
                                            $activities = \Spatie\Activitylog\Models\Activity::query()
                                                ->where('subject_type', Event::class)
                                                ->where('subject_id', $record->id)
                                                ->orderByDesc('created_at')
                                                ->limit(3)
                                                ->get();

                                            if ($activities->isNotEmpty()) {
                                                foreach ($activities as $activity) {
                                                    $eventName = match ($activity->event ?? $activity->description) {
                                                        'created' => $t('Creat', 'Created'),
                                                        'updated' => $t('Modificat', 'Modified'),
                                                        'deleted' => $t('Șters', 'Deleted'),
                                                        'published' => $t('Publicat', 'Published'),
                                                        'unpublished' => $t('Nepublicat', 'Unpublished'),
                                                        default => ucfirst($activity->event ?? $activity->description ?? $t('Acțiune', 'Action')),
                                                    };

                                                    $iconBg = match ($activity->event ?? $activity->description) {
                                                        'created' => 'bg-emerald-900',
                                                        'updated' => 'bg-blue-900',
                                                        'deleted' => 'bg-red-900',
                                                        'published' => 'bg-green-900',
                                                        default => 'bg-gray-700',
                                                    };

                                                    $iconColor = match ($activity->event ?? $activity->description) {
                                                        'created' => 'text-emerald-400',
                                                        'updated' => 'text-blue-400',
                                                        'deleted' => 'text-red-400',
                                                        'published' => 'text-green-400',
                                                        default => 'text-gray-400',
                                                    };

                                                    $causer = $activity->causer?->name ?? 'Sistem';
                                                    $time = $activity->created_at->diffForHumans();

                                                    $html .= "
                                                        <div class='flex gap-2'>
                                                            <div class='w-6 h-6 {$iconBg} rounded-full flex items-center justify-center flex-shrink-0'>
                                                                <svg class='w-3 h-3 {$iconColor}' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'/></svg>
                                                            </div>
                                                            <div class='flex-1 min-w-0'>
                                                                <div class='text-gray-300'>{$eventName}</div>
                                                                <div class='text-xs text-gray-500'>{$causer} · {$time}</div>
                                                            </div>
                                                        </div>
                                                    ";
                                                }
                                            } else {
                                                throw new \Exception('No activities found');
                                            }
                                        } catch (\Exception $e) {
                                            // Fallback to basic info from timestamps
                                            $lastModLabel = $t('Ultima modificare', 'Last modified');
                                            $createdLabel = $t('Creat', 'Created');
                                            $html .= "
                                                <div class='flex gap-2'>
                                                    <div class='flex items-center justify-center flex-shrink-0 w-6 h-6 bg-gray-700 rounded-full'>
                                                        <svg class='w-3 h-3 text-gray-400' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'/></svg>
                                                    </div>
                                                    <div>
                                                        <div class='text-gray-300'>{$lastModLabel}</div>
                                                        <div class='text-xs text-gray-500'>" . $record->updated_at->diffForHumans() . "</div>
                                                    </div>
                                                </div>
                                                <div class='flex gap-2'>
                                                    <div class='flex items-center justify-center flex-shrink-0 w-6 h-6 rounded-full bg-emerald-900'>
                                                        <svg class='w-3 h-3 text-emerald-400' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 6v6m0 0v6m0-6h6m-6 0H6'/></svg>
                                                    </div>
                                                    <div>
                                                        <div class='text-gray-300'>{$createdLabel}</div>
                                                        <div class='text-xs text-gray-500'>" . $record->created_at->format('d M Y, H:i') . "</div>
                                                    </div>
                                                </div>
                                            ";
                                        }

                                        $html .= "</div>";

                                        // Link to full activity log page
                                        $viewHistoryLabel = $t('Vezi tot istoricul →', 'View full history →');
                                        $html .= "<a href='" . static::getUrl('activity-log', ['record' => $record]) . "' class='block mt-3 text-xs transition-colors text-primary-400 hover:text-primary-300'>{$viewHistoryLabel}</a>";

                                        return new HtmlString($html);
                                    }),
                            ]),

                        // 6. Publish Checklist (sticky)
                        SC\Section::make($t('Checklist publicare', 'Publish Checklist'))
                            ->icon('heroicon-o-clipboard-document-check')
                            ->compact()
                            ->collapsible()
                            ->extraAttributes(['class' => 'sticky top-8 z-10'])
                            ->schema([
                                Forms\Components\Placeholder::make('publish_checklist')
                                    ->hiddenLabel()
                                    ->live(onBlur: true)
                                    ->content(function (SGet $get, ?Event $record) use ($marketplaceLanguage, $t) {
                                        // Check ticket types from form state or database
                                        $ticketTypesData = $get('ticketTypes') ?? [];
                                        $hasTicketTypes = false;

                                        if (!empty($ticketTypesData)) {
                                            // Check if any ticket type has a name set
                                            foreach ($ticketTypesData as $tt) {
                                                if (!empty($tt['name'])) {
                                                    $hasTicketTypes = true;
                                                    break;
                                                }
                                            }
                                        } elseif ($record && $record->exists) {
                                            // Fallback to database
                                            $hasTicketTypes = $record->ticketTypes()->count() > 0;
                                        }

                                        // For images, check record directly to avoid triggering re-renders during upload
                                        $hasImages = ($record && (!empty($record->poster_url) || !empty($record->hero_image_url)));

                                        $checks = [
                                            ['done' => !empty($get("title.{$marketplaceLanguage}")), 'label' => $t('Titlu eveniment', 'Event title'), 'icon' => 'text'],
                                            ['done' => $hasImages, 'label' => $t('Imagini încărcate', 'Images uploaded'), 'icon' => 'image'],
                                            ['done' => !empty($get('venue_id')) || !empty($get('venue_name')), 'label' => $t('Locație setată', 'Location set'), 'icon' => 'location'],
                                            ['done' => !empty($get('event_date')) || !empty($get('range_start_date')), 'label' => $t('Date setate', 'Dates set'), 'icon' => 'calendar'],
                                            ['done' => !empty($get('marketplace_organizer_id')), 'label' => $t('Organizator selectat', 'Organizer selected'), 'icon' => 'user'],
                                            ['done' => $hasTicketTypes, 'label' => $t('Tipuri de bilete', 'Ticket types'), 'icon' => 'ticket'],
                                        ];

                                        $completed = collect($checks)->where('done', true)->count();
                                        $total = count($checks);
                                        $isReady = $completed === $total;

                                        $html = "<div class='space-y-1.5'>";
                                        foreach ($checks as $check) {
                                            $icon = $check['done']
                                                ? '<svg class="w-4 h-4 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>'
                                                : '<svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/></svg>';
                                            $textClass = $check['done'] ? 'text-gray-400 line-through' : 'text-white';
                                            $html .= "<div class='flex items-center gap-2'>{$icon}<span class='text-sm {$textClass}'>{$check['label']}</span></div>";
                                        }
                                        $html .= "</div>";

                                        // Status badge
                                        $statusColor = $isReady ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400';
                                        $statusText = $isReady ? $t('Gata pentru publicare', 'Ready to publish') : $t('Incomplet', 'Incomplete');
                                        $completedLabel = $t('completate', 'completed');
                                        $html .= "<div class='flex items-center justify-between mt-3'>";
                                        $html .= "<span class='text-xs text-gray-400'>{$completed}/{$total} {$completedLabel}</span>";
                                        $html .= "<span class='px-2 py-0.5 text-[10px] font-bold rounded {$statusColor}'>{$statusText}</span>";
                                        $html .= "</div>";

                                        return new HtmlString($html);
                                    }),
                            ]),
                    ]),
            ]),
        ])->columns(1);
    }

    /**
     * Check if an event has already ended (date is in the past).
     * Used to disable minDate constraints on past events so they can still be edited.
     */
    protected static function isEventEnded(?Event $record): bool
    {
        if (!$record || !$record->exists) {
            return false;
        }

        $eventEndDateTime = null;
        if ($record->duration_mode === 'single_day' && $record->event_date) {
            $endTime = $record->end_time ?? '23:59';
            $eventEndDateTime = Carbon::parse($record->event_date->format('Y-m-d') . ' ' . $endTime);
        } elseif ($record->duration_mode === 'range' && $record->range_end_date) {
            $endTime = $record->range_end_time ?? '23:59';
            $eventEndDateTime = Carbon::parse($record->range_end_date->format('Y-m-d') . ' ' . $endTime);
        } elseif ($record->duration_mode === 'multi_day' && !empty($record->multi_slots)) {
            $lastSlot = collect($record->multi_slots)->sortByDesc('date')->first();
            if ($lastSlot) {
                $endTime = $lastSlot['end_time'] ?? '23:59';
                $eventEndDateTime = Carbon::parse($lastSlot['date'] . ' ' . $endTime);
            }
        }

        return $eventEndDateTime && $eventEndDateTime->isPast();
    }

    public static function table(Table $table): Table
    {
        $marketplace = static::getMarketplaceClient();
        $lang = $marketplace?->settings['default_language'] ?? null;
        $marketplaceLanguage = (!empty($lang)) ? $lang : 'ro';

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->getStateUsing(fn (Event $record) => $record->getTranslation('title', 'ro') ?: $record->getTranslation('title', 'en'))
                    ->searchable(query: function ($query, string $search): void {
                        $term = '%' . mb_strtolower($search) . '%';
                        $isPgsql = \DB::getDriverName() === 'pgsql';
                        $query->where(function ($q) use ($term, $isPgsql) {
                            if ($isPgsql) {
                                $q->whereRaw("unaccent(LOWER(title::jsonb->>'ro')) LIKE unaccent(?)", [$term])
                                  ->orWhereRaw("unaccent(LOWER(title::jsonb->>'en')) LIKE unaccent(?)", [$term]);
                            } else {
                                $q->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(title, '$.ro'))) LIKE ?", [$term])
                                  ->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(title, '$.en'))) LIKE ?", [$term]);
                            }
                        });
                    })
                    ->sortable()
                    ->toggleable()
                    ->extraAttributes(['class' => 'ep-title-cell'])
                    ->formatStateUsing(function ($state, Event $record) {
                        $expiringIcon = '';
                        if ($record->hasExpiringTicketsWithoutReplacement()) {
                            $expiringIcon = '<span title="Tip de bilet expiră în următoarele 24h fără înlocuitor" style="display:inline-flex;vertical-align:middle;margin-right:6px;color:#d97706;">'
                                . '<svg xmlns="http://www.w3.org/2000/svg" style="width:16px;height:16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
                                . '</span>';
                        }

                        $html = $expiringIcon . '<a href="' . static::getUrl('edit', ['record' => $record]) . '" class="ep-title-link">' . e($state) . '</a>' .
                            '<button type="button" wire:click="mountTableAction(\'editTitle\', \'' . $record->getKey() . '\')" class="ep-title-edit" title="Quick Edit">' .
                                '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>' .
                            '</button>';

                        // Show child events (representations) for multi_day/recurring parents
                        if ($record->is_template && $record->children()->exists()) {
                            $children = $record->children()->orderBy('occurrence_number')->limit(10)->get();
                            $html .= '<div class="mt-1 space-y-0.5">';
                            foreach ($children as $child) {
                                $childDate = $child->event_date?->format('d M Y') ?? '-';
                                $childTime = $child->start_time ?? '';
                                $childStatus = $child->isPast() ? '<span class="text-gray-400">încheiat</span>' : '<span class="text-green-600">activ</span>';
                                $childUrl = static::getUrl('edit', ['record' => $child]);
                                $html .= '<div class="flex items-center gap-1.5 text-[11px] text-gray-500 dark:text-gray-400 pl-3 border-l-2 border-gray-200 dark:border-gray-700">'
                                    . '<svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>'
                                    . '<a href="' . $childUrl . '" class="hover:text-primary-600 hover:underline">' . $childDate . ($childTime ? " · {$childTime}" : '') . '</a>'
                                    . ' ' . $childStatus
                                    . '</div>';
                            }
                            if ($record->children()->count() > 10) {
                                $html .= '<div class="text-[10px] text-gray-400 pl-3">+' . ($record->children()->count() - 10) . ' mai multe...</div>';
                            }
                            $html .= '</div>';
                        }

                        return new HtmlString($html);
                    }),
                Tables\Columns\IconColumn::make('seating_layout_id')
                    ->label('Seating')
                    ->boolean()
                    ->trueIcon('heroicon-o-map')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->getStateUsing(fn ($record) => !empty($record->seating_layout_id))
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('general_quota')
                    ->label('Cap.')
                    ->getStateUsing(function ($record) {
                        if ($record->general_quota === null) return '—';
                        $nonIndepIds = $record->ticketTypes
                            ->where('is_independent_stock', false)
                            ->pluck('id');
                        $activeCount = $nonIndepIds->isEmpty() ? 0 : \App\Models\Ticket::whereIn('ticket_type_id', $nonIndepIds)
                            ->whereNotIn('status', ['cancelled', 'refunded'])
                            ->count();
                        $remaining = max(0, $record->general_quota - $activeCount);
                        return $remaining . '/' . $record->general_quota;
                    })
                    ->color(function ($record) {
                        if ($record->general_quota === null) return 'gray';
                        $nonIndepIds = $record->ticketTypes->where('is_independent_stock', false)->pluck('id');
                        $activeCount = $nonIndepIds->isEmpty() ? 0 : \App\Models\Ticket::whereIn('ticket_type_id', $nonIndepIds)
                            ->whereNotIn('status', ['cancelled', 'refunded'])->count();
                        $pct = ($record->general_quota > 0) ? ($activeCount / $record->general_quota) * 100 : 0;
                        if ($pct >= 80) return 'success';
                        if ($pct >= 50) return 'warning';
                        return 'danger';
                    })
                    ->badge()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('marketplaceOrganizer.name')
                    ->label('Organizer')
                    ->searchable(query: function ($query, string $search): void {
                        $term = '%' . mb_strtolower($search) . '%';
                        if (\DB::getDriverName() === 'pgsql') {
                            $query->whereHas('marketplaceOrganizer', fn ($q) =>
                                $q->whereRaw('unaccent(LOWER(name)) LIKE unaccent(?)', [$term])
                                  ->orWhereRaw('unaccent(LOWER(company_name)) LIKE unaccent(?)', [$term])
                            );
                        } else {
                            $query->whereHas('marketplaceOrganizer', fn ($q) =>
                                $q->whereRaw('LOWER(name) LIKE ?', [$term])
                                  ->orWhereRaw('LOWER(company_name) LIKE ?', [$term])
                            );
                        }
                    })
                    ->sortable()
                    ->toggleable()
                    ->limit(20)
                    ->tooltip(fn ($record) => $record->marketplaceOrganizer?->name)
                    ->extraAttributes(['style' => 'max-width:140px; overflow:hidden; text-overflow:ellipsis; font-size:0.75rem;'])
                    ->url(fn (Event $record) => $record->marketplace_organizer_id
                        ? \App\Filament\Marketplace\Resources\OrganizerResource::getUrl('edit', ['record' => $record->marketplace_organizer_id])
                        : null),
                Tables\Columns\TextColumn::make('venue_id')
                    ->label('Venue')
                    ->formatStateUsing(fn ($state, $record) => $record->venue?->getTranslation('name', app()->getLocale()) ?? '-')
                    ->limit(20)
                    ->tooltip(fn ($state, $record) => $record->venue?->getTranslation('name', app()->getLocale()))
                    ->extraAttributes(['style' => 'max-width:140px; overflow:hidden; text-overflow:ellipsis; font-size:0.75rem;'])
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('marketplace_city_id')
                    ->label('Oraș')
                    ->formatStateUsing(fn ($state, $record) => $record->marketplaceCity?->getTranslation('name', app()->getLocale()) ?? '-')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('event_date')
                    ->label('Event Date')
                    ->getStateUsing(function ($record) {
                        // Range: show interval
                        if ($record->duration_mode === 'range') {
                            $start = $record->range_start_date;
                            $end = $record->range_end_date;

                            if ($start && $end) {
                                // Same month and year: "15-20 Ian 2025"
                                if ($start->format('m Y') === $end->format('m Y')) {
                                    return $start->format('d') . '-' . $end->format('d M Y');
                                }
                                // Same year, different months: "15 Ian - 20 Feb 2025"
                                if ($start->format('Y') === $end->format('Y')) {
                                    return $start->format('d M') . ' - ' . $end->format('d M Y');
                                }
                                // Different years: "15 Dec 2024 - 5 Ian 2025"
                                return $start->format('d M Y') . ' - ' . $end->format('d M Y');
                            }

                            if ($start) {
                                return 'din ' . $start->format('d M Y');
                            }
                        }

                        // Multi-day: show first and last slot dates
                        if ($record->duration_mode === 'multi_day' && !empty($record->multi_slots)) {
                            $slots = collect($record->multi_slots)->pluck('date')->filter()->sort();
                            if ($slots->count() > 1) {
                                $first = Carbon::parse($slots->first());
                                $last = Carbon::parse($slots->last());
                                if ($first->format('m Y') === $last->format('m Y')) {
                                    return $first->format('d') . '-' . $last->format('d M Y');
                                }
                                return $first->format('d M') . ' - ' . $last->format('d M Y');
                            } elseif ($slots->count() === 1) {
                                return Carbon::parse($slots->first())->format('d M Y');
                            }
                        }

                        // Single day: event_date or range_start_date fallback
                        return $record->event_date?->format('d M Y')
                            ?? $record->range_start_date?->format('d M Y')
                            ?? '-';
                    })
                    ->badge()
                    ->color('gray')
                    ->sortable(query: function ($query, string $direction) {
                        $query->orderByRaw('COALESCE(event_date, range_start_date) ' . $direction);
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('eventTypes_display')
                    ->label('Categorie')
                    ->badge()
                    ->getStateUsing(fn (Event $record) => $record->eventTypes->map(fn($t) => $t->getTranslation('name', app()->getLocale()))->implode(', '))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('eventGenres_display')
                    ->label('Gen')
                    ->badge()
                    ->color('info')
                    ->getStateUsing(fn (Event $record) => $record->eventGenres->map(fn($g) => $g->getTranslation('name', app()->getLocale()))->implode(', '))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\BadgeColumn::make('manifestation_type')
                    ->label('Tip manifestare')
                    ->formatStateUsing(fn ($state) => match($state) {
                        'muzicala' => 'Muzicală',
                        'artistica' => 'Artistică',
                        'sportiva' => 'Sportivă',
                        'altele' => 'Altele',
                        default => '-',
                    })
                    ->colors([
                        'primary' => 'muzicala',
                        'success' => 'artistica',
                        'warning' => 'sportiva',
                        'gray' => 'altele',
                    ])
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('artists_display')
                    ->label('Artiști')
                    ->getStateUsing(fn (Event $record) => $record->artists->pluck('name')->implode(', ') ?: '-')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\BadgeColumn::make('status_display')
                    ->label('Status')
                    ->getStateUsing(function ($record) {
                        // Determine event end date based on duration mode
                        $endDate = null;

                        if ($record->duration_mode === 'range') {
                            $endDate = $record->range_end_date ?? $record->range_start_date;
                        } elseif ($record->duration_mode === 'multi_day' && !empty($record->multi_slots)) {
                            $lastSlot = collect($record->multi_slots)->pluck('date')->filter()->sort()->last();
                            $endDate = $lastSlot ? Carbon::parse($lastSlot) : null;
                        } else {
                            // Single day
                            $endDate = $record->event_date;
                        }

                        if (!$endDate) {
                            return 'unknown';
                        }

                        return $endDate->endOfDay()->isPast() ? 'ended' : 'active';
                    })
                    ->formatStateUsing(fn ($state) => match($state) {
                        'active' => 'Activ',
                        'ended' => 'Încheiat',
                        default => '-',
                    })
                    ->colors([
                        'success' => 'active',
                        'gray' => 'ended',
                    ])
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_published')
                    ->boolean()
                    ->label('Publicat')
                    ->trueIcon('heroicon-o-eye')
                    ->falseIcon('heroicon-o-eye-slash')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_cancelled')
                    ->boolean()
                    ->label('Cancelled')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_sold_out')
                    ->boolean()
                    ->label('Sold Out')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('Publicat'),
                Tables\Filters\TernaryFilter::make('is_cancelled'),
                Tables\Filters\TernaryFilter::make('is_sold_out'),
                Tables\Filters\SelectFilter::make('marketplace_organizer_id')
                    ->label('Organizer')
                    ->relationship('marketplaceOrganizer', 'name'),
            ])
            ->actions([
                Action::make('view_on_site')
                    ->label('')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(function (Event $record) {
                        $marketplace = $record->marketplaceClient;
                        if (!$marketplace) {
                            return null;
                        }
                        // MarketplaceClient has a single 'domain' field, not a 'domains' relationship
                        $domain = $marketplace->domain;
                        if (!$domain) {
                            return null;
                        }
                        // Strip any existing protocol from domain
                        $domain = preg_replace('#^(https?:?/?/?|//)#i', '', $domain);
                        $domain = ltrim($domain, '/');
                        $protocol = str_contains($domain, 'localhost') ? 'http' : 'https';
                        $url = $protocol . '://' . $domain . '/bilete/' . $record->slug;
                        // Add preview param if not published
                        if (!$record->is_published) {
                            $url .= '?preview=1';
                        }
                        return $url;
                    })
                    ->openUrlInNewTab(),
                Action::make('editTitle')
                    ->label('')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading('Editează titlul')
                    ->modalWidth('md')
                    ->modalSubmitActionLabel('Salvează')
                    ->extraAttributes(['class' => '!hidden'])
                    ->fillForm(fn (Event $record) => [
                        'title_ro' => $record->getTranslation('title', 'ro'),
                        'title_en' => $record->getTranslation('title', 'en'),
                    ])
                    ->form([
                        Forms\Components\TextInput::make('title_ro')
                            ->label('Titlu (RO)')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('title_en')
                            ->label('Title (EN)')
                            ->maxLength(255),
                    ])
                    ->action(function (Event $record, array $data): void {
                        if (!empty($data['title_ro'])) {
                            $record->setTranslation('title', 'ro', $data['title_ro']);
                        }
                        if (!empty($data['title_en'])) {
                            $record->setTranslation('title', 'en', $data['title_en']);
                        }
                        $record->save();

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Titlu actualizat')
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('assign_venue')
                        ->label('Alocă venue')
                        ->icon('heroicon-o-map-pin')
                        ->form([
                            Forms\Components\Select::make('venue_id')
                                ->label('Venue')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->options(function () use ($marketplace) {
                                    $venueCountries = self::expandCountryVariants($marketplace?->settings['venue_countries'] ?? []);
                                    return Venue::query()
                                        ->when(!empty($venueCountries), fn ($q) => $q->whereIn('country', $venueCountries))
                                        ->get()
                                        ->mapWithKeys(fn ($venue) => [
                                            $venue->id => $venue->getTranslation('name', app()->getLocale())
                                                . ($venue->city ? ' (' . $venue->city . ')' : '')
                                        ])
                                        ->sort();
                                }),
                        ])
                        ->action(function (Collection $records, array $data) use ($marketplace) {
                            $venue = Venue::find($data['venue_id']);
                            if (!$venue) return;

                            $updateData = [
                                'venue_id' => $venue->id,
                                'address' => $venue->address ?? $venue->full_address ?? '',
                            ];

                            // Auto-match marketplace city
                            if ($venue->city) {
                                $cityName = strtolower(trim(Str::ascii($venue->city)));
                                $matchedCity = MarketplaceCity::where('marketplace_client_id', $marketplace?->id)
                                    ->where('is_visible', true)
                                    ->get()
                                    ->first(function ($city) use ($cityName) {
                                        $nameVariants = is_array($city->name) ? $city->name : [];
                                        foreach ($nameVariants as $lang => $name) {
                                            if (strtolower(trim(Str::ascii($name))) === $cityName) {
                                                return true;
                                            }
                                        }
                                        return false;
                                    });

                                if ($matchedCity) {
                                    $updateData['marketplace_city_id'] = $matchedCity->id;
                                }
                            }

                            $count = 0;
                            foreach ($records as $record) {
                                $record->venue_id = $updateData['venue_id'];
                                $record->address = $updateData['address'] ?? $record->address;
                                if (isset($updateData['marketplace_city_id'])) {
                                    $record->marketplace_city_id = $updateData['marketplace_city_id'];
                                }
                                $record->save();
                                $count++;
                            }

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title("Venue alocat pentru {$count} evenimente")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('bulk_publish')
                        ->label('Publică')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $records->each(fn ($record) => $record->update(['is_published' => true]));
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('bulk_unpublish')
                        ->label('Depublică')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $records->each(fn ($record) => $record->update(['is_published' => false]));
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('change_status')
                        ->label('Schimbă status')
                        ->icon('heroicon-o-arrow-path')
                        ->form([
                            Forms\Components\Select::make('status')
                                ->label('Status')
                                ->options([
                                    'active' => 'Activ',
                                    'cancelled' => 'Anulat',
                                    'ended' => 'Încheiat',
                                ])
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            foreach ($records as $record) {
                                match ($data['status']) {
                                    'cancelled' => $record->update(['is_cancelled' => true, 'cancelled_at' => now()]),
                                    'ended' => $record->update(['is_cancelled' => false, 'cancelled_at' => null, 'status' => 'archived']),
                                    'active' => $record->update(['is_cancelled' => false, 'cancelled_at' => null, 'status' => 'active']),
                                };
                            }
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('change_organizer')
                        ->label('Schimbă organizator')
                        ->icon('heroicon-o-user-group')
                        ->form([
                            Forms\Components\Select::make('marketplace_organizer_id')
                                ->label('Organizator')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->options(function () use ($marketplace) {
                                    return MarketplaceOrganizer::where('marketplace_client_id', $marketplace?->id)
                                        ->whereNotNull('verified_at')
                                        ->orderBy('name')
                                        ->pluck('name', 'id');
                                }),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $records->each(fn ($record) => $record->update([
                                'marketplace_organizer_id' => $data['marketplace_organizer_id'],
                            ]));
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('assign_category')
                        ->label('Setează categorie')
                        ->icon('heroicon-o-tag')
                        ->form([
                            Forms\Components\Select::make('marketplace_event_category_id')
                                ->label('Categorie eveniment')
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
                                ->required(),

                            Forms\Components\Select::make('event_types')
                                ->label('Tipuri eveniment (opțional, override)')
                                ->options(function () {
                                    return EventType::whereNotNull('parent_id')
                                        ->get()
                                        ->mapWithKeys(fn ($t) => [$t->id => $t->getTranslation('name', app()->getLocale())])
                                        ->sort();
                                })
                                ->multiple()
                                ->preload()
                                ->searchable()
                                ->helperText('Lasă gol pentru a folosi tipurile din categorie'),

                            Forms\Components\Select::make('event_genres')
                                ->label('Genuri eveniment (opțional)')
                                ->options(function () {
                                    return EventGenre::all()
                                        ->mapWithKeys(fn ($g) => [$g->id => $g->getTranslation('name', app()->getLocale())])
                                        ->sort();
                                })
                                ->multiple()
                                ->preload()
                                ->searchable(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $categoryId = $data['marketplace_event_category_id'];
                            $category = MarketplaceEventCategory::find($categoryId);

                            // Determine event types: from form override or from category
                            $eventTypeIds = !empty($data['event_types'])
                                ? $data['event_types']
                                : ($category?->event_type_ids ?? []);

                            $genreIds = $data['event_genres'] ?? [];

                            foreach ($records as $record) {
                                $record->update(['marketplace_event_category_id' => $categoryId]);

                                if (!empty($eventTypeIds)) {
                                    $record->eventTypes()->sync($eventTypeIds);
                                }

                                if (!empty($genreIds)) {
                                    $record->eventGenres()->sync($genreIds);
                                }
                            }
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('assign_manifestation_type')
                        ->label('Setează tip manifestare')
                        ->icon('heroicon-o-sparkles')
                        ->form([
                            Forms\Components\Select::make('manifestation_type')
                                ->label('Tip manifestare')
                                ->options([
                                    'muzicala' => 'Muzicală',
                                    'artistica' => 'Artistică',
                                    'teatrala' => 'Teatrală',
                                    'standup' => 'Stand-up',
                                    'sportiva' => 'Sportivă',
                                    'altele' => 'Altele',
                                ])
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            foreach ($records as $record) {
                                $record->update(['manifestation_type' => $data['manifestation_type']]);
                            }
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('assign_genres')
                        ->label('Setează genuri eveniment')
                        ->icon('heroicon-o-puzzle-piece')
                        ->form([
                            Forms\Components\Select::make('genre_ids')
                                ->label('Genuri eveniment')
                                ->options(function () {
                                    return EventGenre::all()
                                        ->mapWithKeys(fn ($g) => [$g->id => $g->getTranslation('name', app()->getLocale())])
                                        ->sort();
                                })
                                ->multiple()
                                ->preload()
                                ->searchable()
                                ->required(),

                        ])
                        ->action(function (Collection $records, array $data) {
                            $genreIds = $data['genre_ids'];
                            foreach ($records as $record) {
                                $record->eventGenres()->syncWithoutDetaching($genreIds);
                            }
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('assign_artists')
                        ->label('Setează artiști')
                        ->icon('heroicon-o-musical-note')
                        ->form([
                            Forms\Components\Select::make('artist_ids')
                                ->label('Artiști')
                                ->options(function () use ($marketplace) {
                                    $artistCountries = self::expandCountryVariants($marketplace?->settings['artist_countries'] ?? []);
                                    return Artist::withoutGlobalScopes()
                                        ->where('is_active', true)
                                        ->when(!empty($artistCountries), fn ($q) => $q->whereIn('country', $artistCountries))
                                        ->orderBy('name')
                                        ->pluck('name', 'id');
                                })
                                ->multiple()
                                ->preload()
                                ->searchable()
                                ->required(),

                        ])
                        ->action(function (Collection $records, array $data) {
                            $artistIds = $data['artist_ids'];

                            // Collect genre IDs from all selected artists
                            $artistGenreIds = \App\Models\ArtistGenre::withoutGlobalScopes()
                                ->whereHas('artists', fn ($q) => $q->whereIn('artists.id', $artistIds))
                                ->pluck('id')
                                ->toArray();

                            // Map artist genres to event genres by slug/name matching
                            $eventGenreIds = [];
                            if (!empty($artistGenreIds)) {
                                $artistGenres = \App\Models\ArtistGenre::withoutGlobalScopes()
                                    ->whereIn('id', $artistGenreIds)
                                    ->get();
                                foreach ($artistGenres as $ag) {
                                    $agName = $ag->getTranslation('name', 'en') ?: $ag->getTranslation('name', 'ro');
                                    $agSlug = $ag->slug;
                                    // Find matching event genre by slug or name
                                    $isPgsql = \DB::getDriverName() === 'pgsql';
                                    $nameTerm = '%' . mb_strtolower($agName) . '%';
                                    $match = EventGenre::where('slug', $agSlug)
                                        ->orWhereRaw($isPgsql ? "LOWER((name::jsonb)->>'ro') LIKE ?" : "LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, '$.ro'))) LIKE ?", [$nameTerm])
                                        ->orWhereRaw($isPgsql ? "LOWER((name::jsonb)->>'en') LIKE ?" : "LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, '$.en'))) LIKE ?", [$nameTerm])
                                        ->first();
                                    if ($match) {
                                        $eventGenreIds[] = $match->id;
                                    }
                                }
                                $eventGenreIds = array_unique($eventGenreIds);
                            }

                            foreach ($records as $record) {
                                // Attach artists
                                $existing = $record->artists()->pluck('artist_id')->toArray();
                                $newIds = array_diff($artistIds, $existing);
                                if (!empty($newIds)) {
                                    $maxSort = DB::table('event_artist')
                                        ->where('event_id', $record->id)
                                        ->max('sort_order') ?? 0;

                                    $pivotData = [];
                                    foreach ($newIds as $id) {
                                        $maxSort++;
                                        $pivotData[$id] = ['sort_order' => $maxSort];
                                    }
                                    $record->artists()->attach($pivotData);
                                }

                                // Copy artist genres to event genres
                                if (!empty($eventGenreIds)) {
                                    $record->eventGenres()->syncWithoutDetaching($eventGenreIds);
                                }
                            }
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ])
            ->recordUrl(null)
            ->recordClasses(function (Event $record) {
                if ($record->is_cancelled) {
                    return 'event-row-cancelled';
                }

                if (!$record->is_published) {
                    return 'event-row-unpublished';
                }

                $endDate = match ($record->duration_mode) {
                    'range' => $record->range_end_date ?? $record->range_start_date,
                    'multi_day' => !empty($record->multi_slots)
                        ? Carbon::parse(collect($record->multi_slots)->pluck('date')->filter()->sort()->last())
                        : null,
                    default => $record->event_date,
                };

                if ($endDate && $endDate->endOfDay()->isPast()) {
                    return 'event-row-ended';
                }

                return 'event-row-active';
            })
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
            'analytics' => Pages\EventAnalytics::route('/{record}/analytics'),
            'activity-log' => Pages\EventActivityLog::route('/{record}/activity-log'),
            'view-guest' => Pages\ViewGuestEvent::route('/{record}/view'),
            'import-external-tickets' => Pages\ImportExternalTickets::route('/{record}/external-tickets'),
            'import' => Pages\ImportEvents::route('/import'),
            'daily-capacities' => Pages\DailyCapacities::route('/{record}/daily-capacities'),
        ];
    }

    /**
     * Per-request memoized sales breakdown for an event.
     *
     * Organizer-facing formulas (valid tickets only for deductions):
     *
     *   valid_gross        = sum over valid tickets of ticket.price
     *   on_top_commission  = commission for valid tickets whose tt mode is on_top
     *   included_commission = commission for valid tickets whose tt mode is included
     *   discount_valid     = per-order discount attributable to valid tickets:
     *                         percentage → valid_gross × (discount / subtotal)   [exact]
     *                         fixed      → (valid_count / total_order_tickets) × discount
     *   extras_valid       = (insurance + cultural_surcharge) × valid_gross / subtotal
     *
     *   Venituri (card)    = valid_gross + on_top_commission + extras_valid
     *   Net (card)         = valid_gross - discount_valid - included_commission - extras_valid
     *   Commission (card)  = on_top_commission + included_commission
     *   Discount (card)    = sum Order.discount_amount  (FULL, informational)
     *   Extras (card)      = sum (insurance + surcharge) (FULL, informational)
     *
     *   Per-type Net in the breakdown table = tt's gross minus its share of
     *   discount/included_commission/extras, allocated with the same rules used
     *   for the order-level totals. Sum of per-type Net = Net card.
     */
    protected static array $salesBreakdownCache = [];

    public static function getSalesBreakdown(Event $event): array
    {
        if (isset(self::$salesBreakdownCache[$event->id])) {
            return self::$salesBreakdownCache[$event->id];
        }

        // Source of truth lives in SalesBreakdownService now. Strip the
        // payout-only fields from per_type so callers that expect the legacy
        // shape ({tt, valid_count, net, commission}) keep working.
        $service = app(\App\Services\Marketplace\SalesBreakdownService::class);
        $breakdown = $service->build($event);

        $legacyPerType = [];
        foreach ($breakdown['per_type'] as $ttId => $row) {
            $legacyPerType[$ttId] = [
                'tt' => $row['tt'],
                'valid_count' => $row['qty'],
                'net' => $row['net'],
                'commission' => $row['commission_amount'],
            ];
        }

        return self::$salesBreakdownCache[$event->id] = [
            'total_revenue' => $breakdown['total_revenue'],
            'total_net' => $breakdown['total_net'],
            'total_commission' => $breakdown['total_commission'],
            'total_extras' => $breakdown['total_extras'],
            'total_discount' => $breakdown['total_discount'],
            'per_type' => $legacyPerType,
        ];
    }

}

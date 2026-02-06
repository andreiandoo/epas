<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\EventResource\Pages;
use App\Filament\Marketplace\Resources\ArtistResource;
use App\Filament\Marketplace\Resources\VenueResource;
use App\Models\Event;
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
use App\Models\Seating\SeatingSection;
use App\Rules\UniqueSeatingSectionPerEvent;
use App\Models\MarketplaceTaxTemplate;
use App\Models\EventGeneratedDocument;
use App\Models\OrganizerDocument;
use App\Models\MarketplaceEvent;
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
        // Default to 'ro' (Romanian) for this marketplace
        $marketplaceLanguage = $marketplace->language ?? $marketplace->locale ?? 'ro';

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
                                            ->afterStateUpdated(function ($state, SSet $set, ?Event $record) {
                                                // Slug is NOT translatable - it's a plain string field
                                                // Format: event-name-[id] (ID is appended after save if record exists)
                                                if ($state) {
                                                    $baseSlug = Str::slug($state);
                                                    if ($record && $record->exists && $record->id) {
                                                        $set('slug', $baseSlug . '-' . $record->id);
                                                    } else {
                                                        $set('slug', $baseSlug);
                                                    }
                                                }
                                            }),
                                        Forms\Components\TextInput::make('slug')
                                            ->label('Slug')
                                            ->maxLength(190)
                                            ->rule('alpha_dash')
                                            ->hintIcon('heroicon-o-information-circle', tooltip: $t('ID-ul evenimentului va fi adăugat automat la salvare', 'Event ID will be added automatically on save')),
                                        Forms\Components\TextInput::make('event_series')
                                            ->label($t('Serie eveniment', 'Event series'))
                                            ->placeholder($t('Se generează automat: AMB-[ID]', 'Auto-generated: AMB-[ID]'))
                                            ->maxLength(50)
                                            ->hintIcon('heroicon-o-information-circle', tooltip: $t('Codul unic al seriei de bilete pentru acest eveniment. Se generează automat la salvare.', 'Unique ticket series code for this event. Auto-generated on save.'))
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
                                        ->afterStateUpdated(function ($state, SSet $set) {
                                            if (!$state) $set('promoted_until', null);
                                        })
                                        ->disabled(fn (SGet $get) => (bool) $get('is_cancelled')),
                                ]),

                                Forms\Components\Textarea::make('cancel_reason')
                                    ->label($t('Motivul anulării', 'Cancellation reason'))
                                    ->rows(2)
                                    ->visible(fn (SGet $get) => (bool) $get('is_cancelled')),

                                SC\Grid::make(4)->schema([
                                    Forms\Components\DatePicker::make('postponed_date')
                                        ->label($t('Data nouă', 'New date'))
                                        ->minDate($today)
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

                                Forms\Components\Textarea::make('postponed_reason')
                                    ->label($t('Motivul amânării', 'Postponement reason'))
                                    ->rows(2)
                                    ->visible(fn (SGet $get) => (bool) $get('is_postponed')),

                                Forms\Components\DatePicker::make('promoted_until')
                                    ->label($t('Promovat până la', 'Promoted until'))
                                    ->minDate($today)
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
                                        ->live(),
                                    Forms\Components\Toggle::make('is_general_featured')
                                        ->label($t('Featured General', 'General Featured'))
                                        ->hintIcon('heroicon-o-information-circle', tooltip: $t('Afișează în listele generale de evenimente featured', 'Show in general featured events lists'))
                                        ->onIcon('heroicon-m-star')
                                        ->offIcon('heroicon-m-star')
                                        ->live(),
                                    Forms\Components\Toggle::make('is_category_featured')
                                        ->label($t('Featured în Categorie', 'Category Featured'))
                                        ->hintIcon('heroicon-o-information-circle', tooltip: $t('Afișează ca featured pe pagina categoriei sale', 'Show as featured in its category page'))
                                        ->onIcon('heroicon-m-tag')
                                        ->offIcon('heroicon-m-tag')
                                        ->live(),
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
                                        ->live(),

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
                                        ->minDate($today)
                                        ->native(false),
                                    Forms\Components\TimePicker::make('start_time')
                                        ->label($t('Ora start', 'Start time'))
                                        ->seconds(false)
                                        ->native(true)
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
                                        ->minDate($today)
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
                                            ->minDate($today)
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
                                                ->minDate(now()->startOfDay())
                                                ->native(false)
                                                ->live()
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
                                                ->live(),
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
                                    ->afterStateUpdated(function ($state, SSet $set) use ($marketplace, $marketplaceLanguage) {
                                        if ($state) {
                                            $venue = Venue::find($state);
                                            if ($venue) {
                                                $set('address', $venue->address ?? $venue->full_address ?? '');
                                                $set('website_url', $venue->website_url ?? '');

                                                // Auto-fill marketplace_city_id by matching venue city name
                                                if ($venue->city) {
                                                    $cityName = strtolower(trim($venue->city));
                                                    $matchedCity = MarketplaceCity::where('marketplace_client_id', $marketplace?->id)
                                                        ->where('is_visible', true)
                                                        ->get()
                                                        ->first(function ($city) use ($cityName) {
                                                            // Check all language variants of the city name
                                                            $nameVariants = is_array($city->name) ? $city->name : [];
                                                            foreach ($nameVariants as $lang => $name) {
                                                                if (strtolower(trim($name)) === $cityName) {
                                                                    return true;
                                                                }
                                                            }
                                                            return false;
                                                        });

                                                    if ($matchedCity) {
                                                        $set('marketplace_city_id', $matchedCity->id);
                                                    }
                                                }
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
                                Forms\Components\Select::make('seating_layout_id')
                                    ->label($t('Harta de locuri', 'Seating Layout'))
                                    ->searchable()
                                    ->preload()
                                    ->live()
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
                                            "<img src='{$url}' alt='Poster' class='max-h-48 rounded-lg shadow' />"
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
                                            "<img src='{$url}' alt='Hero' class='max-h-48 rounded-lg shadow' />"
                                        );
                                    }),
                            ])->columns(2),

                        // CONTENT - Single Language
                        SC\Section::make($t('Conținut', 'Content'))
                            ->schema([
                                Forms\Components\Textarea::make("short_description.{$marketplaceLanguage}")
                                    ->label($t('Descriere scurtă', 'Short description'))
                                    ->rows(3)
                                    ->maxLength(1000)
                                    ->live(debounce: 300)
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
                                    ->live()
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

                                Forms\Components\Select::make('eventTypes')
                                    ->label($t('Tipuri eveniment', 'Event types'))
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
                                    ->label($t('Artiști', 'Artists'))
                                    ->relationship('artists', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->live()
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
                            ])->columns(3),

                        // TICKETS
                        SC\Section::make($t('Bilete', 'Tickets'))
                            ->schema([
                                // Ticket Template and General Stock row
                                SC\Grid::make(2)->schema([
                                    Forms\Components\Select::make('ticket_template_id')
                                        ->label($t('Șablon bilet', 'Ticket Template'))
                                        ->relationship(
                                            name: 'ticketTemplate',
                                            modifyQueryUsing: fn (Builder $query) => $query
                                                ->where('marketplace_client_id', static::getMarketplaceClient()?->id)
                                                ->where('status', 'active')
                                                ->orderBy('name')
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
                                    Forms\Components\TextInput::make('general_stock')
                                        ->label($t('Stoc general', 'General Stock'))
                                        ->numeric()
                                        ->minValue(0)
                                        ->nullable()
                                        ->hintIcon('heroicon-o-information-circle', tooltip: $t('Stoc implicit folosit pentru seria de bilete când un tip de bilet nu are stoc setat.', 'Default stock used for ticket series when a ticket type has no stock set.'))
                                        ->placeholder($t('ex: 500', 'e.g. 500')),
                                ]),

                                // Commission Mode and Rate for event
                                SC\Grid::make(4)->schema([
                                    Forms\Components\Select::make('commission_mode')
                                        ->label($t('Mod comision', 'Commission Mode'))
                                        ->options([
                                            'included' => $t('Inclus în preț', 'Include in price'),
                                            'added_on_top' => $t('Adăugat la preț', 'Add on top'),
                                        ])
                                        ->placeholder(function (SGet $get) use ($marketplace, $t) {
                                            $organizerId = $get('marketplace_organizer_id');
                                            if ($organizerId) {
                                                $organizer = MarketplaceOrganizer::find($organizerId);
                                                if ($organizer && $organizer->default_commission_mode) {
                                                    $modeText = $organizer->default_commission_mode === 'included' ? $t('Inclus', 'Included') : $t('Adăugat', 'Added on top');
                                                    return "{$modeText} " . $t('(implicit organizator)', '(organizer default)');
                                                }
                                            }
                                            $mode = $marketplace->commission_mode ?? 'included';
                                            $modeText = $mode === 'included' ? $t('Inclus', 'Included') : $t('Adăugat', 'Added on top');
                                            return "{$modeText} " . $t('(implicit marketplace)', '(marketplace default)');
                                        })
                                        ->hintIcon('heroicon-o-information-circle', tooltip: $t('Lasă gol pentru a folosi modul implicit al organizatorului sau marketplace-ului', 'Leave empty to use organizer\'s or marketplace default mode'))
                                        ->live()
                                        ->nullable(),

                                    Forms\Components\Toggle::make('use_fixed_commission')
                                        ->label('Comision Fix')
                                        ->helperText(fn () => $marketplace->fixed_commission
                                            ? "{$marketplace->fixed_commission} LEI per bilet"
                                            : 'Nu este setat un comision fix în setările marketplace')
                                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Când este activat, se va folosi comisionul fix din setările marketplace în loc de comisionul procentual.')
                                        ->live()
                                        ->default(false),

                                    Forms\Components\TextInput::make('commission_rate')
                                        ->label($t('Comision personalizat (%)', 'Custom Commission (%)'))
                                        ->numeric()
                                        ->minValue(0)
                                        ->maxValue(50)
                                        ->step(0.5)
                                        ->suffix('%')
                                        ->placeholder(function (SGet $get) use ($marketplace, $t) {
                                            $organizerId = $get('marketplace_organizer_id');
                                            if ($organizerId) {
                                                $organizer = MarketplaceOrganizer::find($organizerId);
                                                if ($organizer && $organizer->commission_rate !== null) {
                                                    return $organizer->commission_rate . '% ' . $t('(implicit organizator)', '(organizer default)');
                                                }
                                            }
                                            return ($marketplace->commission_rate ?? 5) . '% ' . $t('(implicit marketplace)', '(marketplace default)');
                                        })
                                        ->hintIcon('heroicon-o-information-circle', tooltip: $t('Lasă gol pentru a folosi rata implicită a organizatorului sau marketplace-ului', 'Leave empty to use organizer\'s or marketplace default rate'))
                                        ->live()
                                        ->nullable()
                                        ->visible(fn (SGet $get) => !$get('use_fixed_commission')),

                                    Forms\Components\TextInput::make('target_price')
                                        ->label($t('Preț la intrare', 'Door Price'))
                                        ->numeric()
                                        ->minValue(0)
                                        ->step(0.01)
                                        ->suffix($marketplace?->currency ?? 'RON')
                                        ->placeholder($t('ex: 100.00', 'e.g. 100.00'))
                                        ->hintIcon('heroicon-o-information-circle', tooltip: $t('Preț de referință pentru planificare și negocieri. Nu este afișat public.', 'Reference price for planning and negotiations. Not displayed publicly.')),
                                ]),

                                Forms\Components\Repeater::make('ticketTypes')
                                    ->relationship()
                                    ->label($t('Tipuri de bilete', 'Ticket types'))
                                    ->collapsible()
                                    ->reorderable()
                                    ->reorderableWithDragAndDrop()
                                    ->orderColumn('sort_order')
                                    ->addActionLabel($t('Adaugă tip bilet', 'Add ticket type'))
                                    ->itemLabel(fn (array $state) => ($state['is_active'] ?? true)
                                        ? '✓ ' . ($state['name'] ?? $t('Bilet', 'Ticket'))
                                        : '○ ' . ($state['name'] ?? $t('Bilet', 'Ticket')))
                                    ->columns(12)
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label($t('Nume', 'Name'))
                                            ->placeholder($t('ex: Early Bird, Standard, VIP', 'e.g. Early Bird, Standard, VIP'))
                                            ->datalist(['Early Bird','Standard','VIP','Backstage','Student','Senior','Child'])
                                            ->required()
                                            ->columnSpan(6)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                                if ($get('sku')) return;
                                                $set('sku', Str::upper(Str::slug($state, '-')));
                                            }),
                                        Forms\Components\TextInput::make('sku')
                                            ->label('SKU')
                                            ->placeholder($t('Se generează automat dacă lași gol', 'AUTO-GEN if left empty'))
                                            ->columnSpan(6),

                                        Forms\Components\Textarea::make('description')
                                            ->label($t('Descriere', 'Description'))
                                            ->placeholder($t('Descriere opțională tip bilet (ex: "Include acces backstage și meet & greet")', 'Optional ticket type description (e.g. "Includes backstage access and meet & greet")'))
                                            ->rows(2)
                                            ->columnSpan(12),

                                        SC\Grid::make(3)->schema([
                                            Forms\Components\TextInput::make('currency')
                                                ->label($t('Monedă', 'Currency'))
                                                ->default($marketplace?->currency ?? 'RON')
                                                ->disabled()
                                                ->dehydrated(true),
                                            Forms\Components\TextInput::make('price_max')
                                                ->label($t('Preț', 'Price'))
                                                ->placeholder($t('ex: 120.00', 'e.g. 120.00'))
                                                ->numeric()
                                                ->minValue(0)
                                                ->required()
                                                ->live(onBlur: true)
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
                                            Forms\Components\TextInput::make('capacity')
                                                ->label($t('Stoc bilete', 'Ticket stock'))
                                                ->placeholder($t('Necompletat = folosește stoc general', 'Empty = use general stock'))
                                                ->numeric()
                                                ->minValue(0)
                                                ->nullable()
                                                ->dehydrateStateUsing(fn ($state) => $state === '' || $state === null ? null : (int) $state)
                                                ->hintIcon('heroicon-o-information-circle', tooltip: $t('Dacă lași necompletat, se va folosi stocul general pentru seria de bilete.', 'If left empty, general stock will be used for ticket series.'))
                                                ->hint(function ($record) use ($t) {
                                                    return $record && $record->quota_sold > 0
                                                        ? $t('Vândute', 'Sold') . ": {$record->quota_sold}"
                                                        : null;
                                                })
                                                ->live(onBlur: true)
                                                ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                                    // Auto-generate series_end based on quantity if not already set
                                                    // Use capacity if set, otherwise use general_stock
                                                    $seriesEnd = $get('series_end');
                                                    $capacity = $state && (int)$state > 0 ? (int)$state : null;
                                                    $generalStock = $get('../../general_stock');
                                                    $stockToUse = $capacity ?? ($generalStock ? (int)$generalStock : null);

                                                    if (!$seriesEnd && $stockToUse) {
                                                        $eventSeries = $get('../../event_series');
                                                        // Use ticket type ID if available, otherwise use SKU
                                                        $ticketTypeIdentifier = $get('id') ?: $get('sku');
                                                        if ($eventSeries && $ticketTypeIdentifier) {
                                                            $set('series_end', $eventSeries . '-' . $ticketTypeIdentifier . '-' . str_pad($stockToUse, 5, '0', STR_PAD_LEFT));
                                                        }
                                                    }
                                                    // Auto-generate series_start if not already set
                                                    $seriesStart = $get('series_start');
                                                    if (!$seriesStart && $stockToUse) {
                                                        $eventSeries = $get('../../event_series');
                                                        // Use ticket type ID if available, otherwise use SKU
                                                        $ticketTypeIdentifier = $get('id') ?: $get('sku');
                                                        if ($eventSeries && $ticketTypeIdentifier) {
                                                            $set('series_start', $eventSeries . '-' . $ticketTypeIdentifier . '-00001');
                                                        }
                                                    }
                                                }),
                                        ])->columnSpan(12),

                                        // Seating Sections selector (visible when event has a seating layout)
                                        Forms\Components\Select::make('seatingSections')
                                            ->label($t('Secțiuni locuri asignate', 'Assigned Seating Sections'))
                                            ->relationship('seatingSections', 'name')
                                            ->multiple()
                                            ->preload()
                                            ->searchable()
                                            ->visible(fn (SGet $get) => (bool) $get('../../seating_layout_id'))
                                            ->options(function (SGet $get) {
                                                $layoutId = $get('../../seating_layout_id');
                                                if (!$layoutId) return [];

                                                return SeatingSection::query()
                                                    ->where('layout_id', $layoutId)
                                                    ->where('section_type', 'standard')
                                                    ->orderBy('display_order')
                                                    ->get()
                                                    ->mapWithKeys(fn ($section) => [
                                                        $section->id => $section->name . ' (' . $section->total_seats . ' seats)'
                                                    ]);
                                            })
                                            ->disableOptionWhen(function (string $value, SGet $get) {
                                                // Get event ID and current ticket type ID
                                                $eventId = $get('../../id');
                                                $currentTicketTypeId = $get('id');

                                                if (!$eventId) return false;

                                                // Check if this section is already assigned to another ticket type
                                                $assignedToOther = \App\Models\TicketType::where('event_id', $eventId)
                                                    ->when($currentTicketTypeId, fn($q) => $q->where('id', '!=', $currentTicketTypeId))
                                                    ->whereHas('seatingSections', fn($q) => $q->where('seating_sections.id', $value))
                                                    ->exists();

                                                return $assignedToOther;
                                            })
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                                // Auto-update capacity based on total seats in selected sections
                                                if (!empty($state)) {
                                                    $totalSeats = SeatingSection::whereIn('id', $state)
                                                        ->get()
                                                        ->sum(fn ($s) => $s->total_seats);
                                                    $set('capacity', $totalSeats);
                                                }
                                            })
                                            ->helperText($t('Atribuie secțiuni de locuri acestui tip de bilet. Secțiunile deja atribuite altor tipuri de bilete sunt dezactivate.', 'Assign seating sections to this ticket type. Sections already assigned to other ticket types are disabled.'))
                                            ->columnSpan(12),

                                        // Visual preview of selected sections
                                        Forms\Components\Placeholder::make('sections_preview')
                                            ->label('')
                                            ->visible(fn (SGet $get) => (bool) $get('../../seating_layout_id') && !empty($get('seatingSections')))
                                            ->content(function (SGet $get) {
                                                $layoutId = $get('../../seating_layout_id');
                                                $selectedSections = $get('seatingSections') ?? [];

                                                if (!$layoutId || empty($selectedSections)) {
                                                    return '';
                                                }

                                                $layout = \App\Models\Seating\SeatingLayout::withoutGlobalScopes()
                                                    ->with('sections')
                                                    ->find($layoutId);

                                                if (!$layout) return '';

                                                // Get all sections for this layout
                                                $allSections = $layout->sections;

                                                if ($allSections->isEmpty()) return '';

                                                // Use full canvas as viewBox for proper proportions
                                                $canvasW = $layout->canvas_w ?? 1920;
                                                $canvasH = $layout->canvas_h ?? 1080;

                                                // Background image
                                                $bgImage = '';
                                                $bgPath = $layout->background_image_path ?? $layout->background_image_url;
                                                if ($bgPath) {
                                                    $bgUrl = str_starts_with($bgPath, 'http') ? $bgPath : asset('storage/' . $bgPath);
                                                    $bgScale = $layout->background_scale ?? 1;
                                                    $bgX = $layout->background_x ?? 0;
                                                    $bgY = $layout->background_y ?? 0;
                                                    $bgOpacity = $layout->background_opacity ?? 0.5;
                                                    $bgW = $canvasW * $bgScale;
                                                    $bgH = $canvasH * $bgScale;
                                                    $bgImage = "<image href=\"{$bgUrl}\" x=\"{$bgX}\" y=\"{$bgY}\" width=\"{$bgW}\" height=\"{$bgH}\" opacity=\"{$bgOpacity}\" preserveAspectRatio=\"xMidYMid meet\"/>";
                                                }

                                                // Build SVG paths for sections
                                                $svgSections = '';
                                                foreach ($allSections as $section) {
                                                    $isSelected = in_array($section->id, $selectedSections);
                                                    $x = $section->origin_x ?? 0;
                                                    $y = $section->origin_y ?? 0;
                                                    $w = max(50, $section->width ?? 200);
                                                    $h = max(30, $section->height ?? 100);
                                                    $rotation = $section->rotation ?? 0;
                                                    $name = e($section->name);

                                                    // Colors based on selection - more visible
                                                    $fill = $isSelected ? 'rgba(34, 197, 94, 0.7)' : 'rgba(55, 65, 81, 0.5)';
                                                    $stroke = $isSelected ? '#22c55e' : '#6b7280';
                                                    $textColor = $isSelected ? '#ffffff' : '#d1d5db';
                                                    $strokeWidth = $isSelected ? '4' : '2';

                                                    // Section rectangle with rotation
                                                    $cx = $x + $w / 2;
                                                    $cy = $y + $h / 2;
                                                    $transform = $rotation != 0 ? " transform=\"rotate({$rotation} {$cx} {$cy})\"" : '';

                                                    $svgSections .= "<g{$transform}>";
                                                    $svgSections .= "<rect x=\"{$x}\" y=\"{$y}\" width=\"{$w}\" height=\"{$h}\" fill=\"{$fill}\" stroke=\"{$stroke}\" stroke-width=\"{$strokeWidth}\" rx=\"6\"/>";
                                                    // Section name - scale font based on section size
                                                    $fontSize = max(14, min(28, min($w, $h) / 4));
                                                    $svgSections .= "<text x=\"{$cx}\" y=\"{$cy}\" text-anchor=\"middle\" dominant-baseline=\"middle\" fill=\"{$textColor}\" font-size=\"{$fontSize}\" font-weight=\"700\" stroke=\"#000\" stroke-width=\"0.5\">{$name}</text>";
                                                    $svgSections .= "</g>";
                                                }

                                                $selectedCount = count($selectedSections);
                                                $totalSeats = SeatingSection::whereIn('id', $selectedSections)->get()->sum(fn ($s) => $s->total_seats);

                                                // Calculate aspect ratio for responsive height
                                                $aspectRatio = $canvasH / $canvasW;
                                                $heightClass = $aspectRatio > 0.7 ? 'h-72' : 'h-48';

                                                return new \Illuminate\Support\HtmlString("
                                                    <div class='p-3 bg-gray-900 border border-gray-700 rounded-lg'>
                                                        <div class='flex items-center justify-between mb-2'>
                                                            <span class='text-sm font-medium text-gray-300'>
                                                                Secțiuni selectate: <span class='font-bold text-green-400'>{$selectedCount}</span>
                                                                <span class='text-gray-500'>({$totalSeats} locuri)</span>
                                                            </span>
                                                            <div class='flex items-center gap-3 text-xs text-gray-500'>
                                                                <span class='flex items-center gap-1'><span class='w-3 h-3 bg-green-500 rounded'></span> Selectate</span>
                                                                <span class='flex items-center gap-1'><span class='w-3 h-3 bg-gray-600 rounded'></span> Disponibile</span>
                                                            </div>
                                                        </div>
                                                        <svg viewBox=\"0 0 {$canvasW} {$canvasH}\" preserveAspectRatio=\"xMidYMid meet\" class='w-full {$heightClass} bg-gray-950 rounded border border-gray-800'>
                                                            {$bgImage}
                                                            {$svgSections}
                                                        </svg>
                                                    </div>
                                                ");
                                            })
                                            ->columnSpan(12),

                                        // Blocked seats info for assigned sections
                                        Forms\Components\Placeholder::make('blocked_seats_info')
                                            ->label('')
                                            ->visible(fn (SGet $get) => (bool) $get('../../seating_layout_id') && !empty($get('seatingSections')))
                                            ->content(function (SGet $get) {
                                                $eventId = $get('../../id');
                                                $selectedSections = $get('seatingSections') ?? [];

                                                if (!$eventId || empty($selectedSections)) {
                                                    return '';
                                                }

                                                // Get the event seating layout
                                                $eventSeating = \App\Models\Seating\EventSeatingLayout::where('event_id', $eventId)
                                                    ->published()
                                                    ->first();

                                                if (!$eventSeating) {
                                                    return '';
                                                }

                                                // Get section names from IDs
                                                $sectionNames = SeatingSection::whereIn('id', $selectedSections)
                                                    ->pluck('name')
                                                    ->toArray();

                                                if (empty($sectionNames)) {
                                                    return '';
                                                }

                                                // Get blocked seats for these sections
                                                $blockedSeats = \App\Models\Seating\EventSeat::where('event_seating_id', $eventSeating->id)
                                                    ->whereIn('section_name', $sectionNames)
                                                    ->where('status', 'blocked')
                                                    ->orderBy('section_name')
                                                    ->orderByRaw('CAST(row_label AS UNSIGNED), row_label')
                                                    ->orderByRaw('CAST(seat_label AS UNSIGNED), seat_label')
                                                    ->get();

                                                if ($blockedSeats->isEmpty()) {
                                                    return '';
                                                }

                                                // Group by section and row
                                                $grouped = $blockedSeats->groupBy('section_name');
                                                $html = "<div class='p-3 mt-2 border rounded-lg bg-red-950/30 border-red-800/50'>";
                                                $html .= "<div class='flex items-center gap-2 mb-2'>";
                                                $html .= "<svg class='w-4 h-4 text-red-400' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'/></svg>";
                                                $html .= "<span class='text-sm font-medium text-red-400'>Locuri blocate: " . $blockedSeats->count() . "</span>";
                                                $html .= "</div>";

                                                foreach ($grouped as $sectionName => $seats) {
                                                    $byRow = $seats->groupBy('row_label');
                                                    foreach ($byRow as $rowLabel => $rowSeats) {
                                                        $seatLabels = $rowSeats->pluck('seat_label')->sort(fn($a, $b) => (int)$a - (int)$b)->values()->implode(', ');
                                                        $html .= "<div class='ml-6 text-xs text-gray-300'>";
                                                        $html .= "<span class='text-gray-500'>{$sectionName} / Rând {$rowLabel}:</span> ";
                                                        $html .= "<span class='text-red-300'>{$seatLabels}</span>";
                                                        $html .= "</div>";
                                                    }
                                                }

                                                $html .= "</div>";
                                                return new \Illuminate\Support\HtmlString($html);
                                            })
                                            ->columnSpan(12),

                                        // Sale toggle - controls visibility of sale fields
                                        Forms\Components\Toggle::make('has_sale')
                                            ->label($t('Activează reducere', 'Enable Sale Discount'))
                                            ->live()
                                            ->default(false)
                                            ->dehydrated(false)
                                            ->afterStateHydrated(function ($state, SSet $set, SGet $get) {
                                                // Auto-enable if there's existing sale data
                                                $hasSaleData = $get('price') || $get('discount_percent') || $get('sales_start_at') || $get('sales_end_at') || $get('sale_stock');
                                                if ($hasSaleData) {
                                                    $set('has_sale', true);
                                                }
                                            })
                                            ->columnSpan(12),

                                        SC\Grid::make(4)->schema([
                                            Forms\Components\TextInput::make('price')
                                                ->label($t('Preț promoțional', 'Sale price'))
                                                ->placeholder($t('lasă gol dacă nu e reducere', 'leave empty if no sale'))
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
                                                ->label($t('Reducere %', 'Discount %'))
                                                ->placeholder($t('ex: 20', 'e.g. 20'))
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
                                            Forms\Components\DateTimePicker::make('sales_start_at')
                                                ->label($t('Început reducere', 'Sale starts'))
                                                ->native(false)
                                                ->seconds(false)
                                                ->displayFormat('Y-m-d H:i')
                                                ->minDate(now())
                                                ->live(debounce: 500)
                                                ->afterStateUpdated(function ($state, SSet $set) {
                                                    if (!$state) return;

                                                    $selectedDate = Carbon::parse($state);
                                                    $now = Carbon::now();

                                                    // If the selected date is today and time is midnight (default), set current time
                                                    if ($selectedDate->isToday() && $selectedDate->format('H:i') === '00:00') {
                                                        // Set to current time, rounded up to next 5 minutes
                                                        $newTime = $now->copy()->addMinutes(5 - ($now->minute % 5))->second(0);
                                                        $set('sales_start_at', $newTime->format('Y-m-d H:i'));
                                                    }
                                                    // Ensure the datetime is not in the past
                                                    elseif ($selectedDate->lt($now)) {
                                                        $newTime = $now->copy()->addMinutes(5 - ($now->minute % 5))->second(0);
                                                        $set('sales_start_at', $newTime->format('Y-m-d H:i'));
                                                    }
                                                }),
                                            Forms\Components\DateTimePicker::make('sales_end_at')
                                                ->label($t('Sfârșit reducere', 'Sale ends'))
                                                ->native(false)
                                                ->seconds(false)
                                                ->displayFormat('Y-m-d H:i'),
                                        ])
                                            ->visible(fn (SGet $get) => $get('has_sale'))
                                            ->columnSpan(12),

                                        // Sale stock - limit how many tickets can be sold at sale price
                                        Forms\Components\TextInput::make('sale_stock')
                                            ->label($t('Stoc reducere', 'Sale stock'))
                                            ->placeholder($t('Nelimitat', 'Unlimited'))
                                            ->numeric()
                                            ->minValue(0)
                                            ->nullable()
                                            ->hintIcon('heroicon-o-information-circle', tooltip: $t('Numărul de bilete disponibile la preț redus. Când se consumă stocul, oferta se închide automat.', 'Number of tickets available at discounted price. When stock runs out, the offer closes automatically.'))
                                            ->visible(fn (SGet $get) => $get('has_sale'))
                                            ->columnSpan(6),

                                        // Activ, Returnabil, Serie start, Serie end - all on same row with unequal columns
                                        Forms\Components\Toggle::make('is_active')
                                            ->label($t('Activ', 'Active'))
                                            ->default(true)
                                            ->live()
                                            ->columnSpan(2),
                                        Forms\Components\Toggle::make('is_refundable')
                                            ->label($t('Returnabil', 'Refundable'))
                                            ->hintIcon('heroicon-o-information-circle', tooltip: $t('Dacă evenimentul este anulat sau amânat, clienții pot cere retur pentru acest tip de bilet', 'If the event is cancelled or postponed, customers can request a refund for this ticket type'))
                                            ->default(false)
                                            ->columnSpan(2),
                                        Forms\Components\TextInput::make('series_start')
                                            ->label($t('Serie start', 'Series start'))
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
                                            ->columnSpan(4),
                                        Forms\Components\TextInput::make('series_end')
                                            ->label($t('Serie end', 'Series end'))
                                            ->placeholder($t('Ex: AMB-5-00500', 'E.g. AMB-5-00500'))
                                            ->maxLength(50)
                                            ->afterStateHydrated(function ($state, SSet $set, SGet $get) {
                                                if (!$state) {
                                                    $eventSeries = $get('../../event_series');
                                                    $capacity = $get('capacity');
                                                    $ticketTypeIdentifier = $get('id') ?: $get('sku');
                                                    if ($eventSeries && $capacity && (int)$capacity > 0 && $ticketTypeIdentifier) {
                                                        $endNumber = (int)$capacity;
                                                        $set('series_end', $eventSeries . '-' . $ticketTypeIdentifier . '-' . str_pad($endNumber, 5, '0', STR_PAD_LEFT));
                                                    }
                                                }
                                            })
                                            ->columnSpan(4),

                                        Forms\Components\DateTimePicker::make('active_until')
                                            ->label($t('Activ până la', 'Active until'))
                                            ->native(false)
                                            ->seconds(false)
                                            ->displayFormat('Y-m-d H:i')
                                            ->minDate(now())
                                            ->hintIcon('heroicon-o-information-circle', tooltip: $t('Când se atinge această dată, tipul de bilet va fi marcat ca sold out, chiar dacă mai sunt bilete în stoc.', 'When this date is reached, the ticket type will be marked as sold out, even if there are still tickets in stock.'))
                                            ->visible(fn (SGet $get) => $get('is_active'))
                                            ->columnSpan(6),

                                        // Scheduling fields - shown when ticket is NOT active
                                        Forms\Components\DateTimePicker::make('scheduled_at')
                                            ->label($t('Programează activare', 'Schedule Activation'))
                                            ->hintIcon('heroicon-o-information-circle', tooltip: $t('Când acest tip de bilet ar trebui să devină automat activ', 'When this ticket type should automatically become active'))
                                            ->native(false)
                                            ->seconds(false)
                                            ->displayFormat('Y-m-d H:i')
                                            ->minDate(now())
                                            ->visible(fn (SGet $get) => !$get('is_active'))
                                            ->columnSpan(4),

                                        Forms\Components\Toggle::make('autostart_when_previous_sold_out')
                                            ->label($t('Autostart când precedentul e sold out', 'Autostart when previous sold out'))
                                            ->hintIcon('heroicon-o-information-circle', tooltip: $t('Activează automat când tipurile de bilete anterioare ajung la capacitate 0', 'Activate automatically when previous ticket types reach 0 capacity'))
                                            ->visible(fn (SGet $get) => !$get('is_active'))
                                            ->columnSpan(4),

                                        // Bulk discounts
                                        Forms\Components\Repeater::make('bulk_discounts')
                                            ->label($t('Reduceri la cantitate', 'Bulk discounts'))
                                            ->collapsed()
                                            ->default([])
                                            ->addActionLabel($t('Adaugă regulă', 'Add bulk rule'))
                                            ->itemLabel(fn (array $state) => $state['rule_type'] ?? $t('Regulă', 'Rule'))
                                            ->columns(12)
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
                                                    ->columnSpan(3)
                                                    ->live(),
                                                Forms\Components\TextInput::make('buy_qty')
                                                    ->label($t('Cumperi X', 'Buy X'))
                                                    ->numeric()->minValue(1)
                                                    ->visible(fn ($get) => $get('rule_type') === 'buy_x_get_y')
                                                    ->columnSpan(3),
                                                Forms\Components\TextInput::make('get_qty')
                                                    ->label($t('Primești Y gratis', 'Get Y free'))
                                                    ->numeric()->minValue(1)
                                                    ->visible(fn ($get) => $get('rule_type') === 'buy_x_get_y')
                                                    ->columnSpan(3),
                                                Forms\Components\TextInput::make('min_qty')
                                                    ->label($t('Cantitate min', 'Min qty'))
                                                    ->numeric()->minValue(1)
                                                    ->visible(fn ($get) => in_array($get('rule_type'), ['buy_x_percent_off','amount_off_per_ticket','bundle_price']))
                                                    ->columnSpan(3),
                                                Forms\Components\TextInput::make('percent_off')
                                                    ->label($t('% reducere', '% off'))
                                                    ->numeric()->minValue(1)->maxValue(100)
                                                    ->visible(fn ($get) => $get('rule_type') === 'buy_x_percent_off')
                                                    ->columnSpan(3),
                                                Forms\Components\TextInput::make('amount_off')
                                                    ->label($t('Reducere sumă', 'Amount off'))
                                                    ->numeric()->minValue(0.01)
                                                    ->visible(fn ($get) => $get('rule_type') === 'amount_off_per_ticket')
                                                    ->columnSpan(3),
                                                Forms\Components\TextInput::make('bundle_total_price')
                                                    ->label($t('Total pachet', 'Bundle total'))
                                                    ->numeric()->minValue(0.01)
                                                    ->visible(fn ($get) => $get('rule_type') === 'bundle_price')
                                                    ->columnSpan(3),
                                            ])
                                            ->columnSpan(12),
                                    ]),
                            ])->collapsible(),

                        // SEO Section
                        SC\Section::make('SEO')
                ->collapsed()
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
                    ]),
                // ========== COLOANA DREAPTĂ - SIDEBAR (1/4) ==========
                SC\Group::make()
                    ->columnSpan(1)
                    ->schema([
                        SC\Grid::make(1)->schema([
                            Forms\Components\Toggle::make('is_published')
                                ->label($t('Publicat', 'Published'))
                                ->hintIcon('heroicon-o-information-circle', tooltip: $t('Când este activat, evenimentul va fi vizibil pe site-ul marketplace. Când este dezactivat, evenimentul nu va apărea nicăieri.', 'When enabled, the event will be visible on the marketplace site. When disabled, the event will not appear anywhere.'))
                                ->onIcon('heroicon-m-eye')
                                ->offIcon('heroicon-m-eye-slash')
                                ->default(true)
                                ->live(),
                            Forms\Components\Placeholder::make('preview_link')
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
                                        '<div class="space-y-2">' .
                                        '<a href="' . e($eventUrl) . '" target="_blank" class="inline-flex items-center gap-1 text-primary-600 hover:underline">' .
                                            '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>' .
                                            $t('Vezi pe site', 'View on site') .
                                        '</a>' .
                                        (!$record->is_published ? '<br><a href="' . e($previewUrl) . '" target="_blank" class="inline-flex items-center gap-1 text-sm text-warning-600 hover:underline">' .
                                            '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>' .
                                            $t('Previzualizare (doar admin)', 'Preview (admin only)') .
                                        '</a>' : '') .
                                        '</div>'
                                    );
                                }),
                        ]),

                        // Event Status Badge (Încheiat/Amânat/Anulat)
                        Forms\Components\Placeholder::make('event_status_badge')
                            ->hiddenLabel()
                            ->visible(fn (?Event $record) => $record && $record->exists)
                            ->content(function (?Event $record) {
                                if (!$record || !$record->exists) {
                                    return null;
                                }

                                // Check if cancelled
                                if ($record->is_cancelled) {
                                    return new HtmlString('
                                        <div class="flex items-center justify-center p-3 border rounded-lg bg-red-500/10 border-red-500/20">
                                            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-semibold bg-red-500/20 text-red-400 ring-1 ring-inset ring-red-500/30">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                ANULAT
                                            </span>
                                        </div>
                                    ');
                                }

                                // Check if postponed
                                if ($record->is_postponed) {
                                    return new HtmlString('
                                        <div class="flex items-center justify-center p-3 border rounded-lg bg-amber-500/10 border-amber-500/20">
                                            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-semibold bg-amber-500/20 text-amber-400 ring-1 ring-inset ring-amber-500/30">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                AMÂNAT
                                            </span>
                                        </div>
                                    ');
                                }

                                // Check if event has ended
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
                                    return new HtmlString('
                                        <div class="flex items-center justify-center p-3 border rounded-lg bg-gray-500/10 border-gray-500/20">
                                            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-semibold bg-gray-500/20 text-gray-400 ring-1 ring-inset ring-gray-500/30">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                ÎNCHEIAT
                                            </span>
                                        </div>
                                    ');
                                }

                                return null;
                            }),

                        // 1. Quick Stats Card - Vânzări LIVE
                        SC\Section::make(fn () => new HtmlString($t('Vânzări', 'Sales') . ' <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-500/20 text-green-400 ring-1 ring-inset ring-green-500/30">LIVE</span>'))
                            ->icon('heroicon-o-chart-bar')
                            ->compact()
                            ->extraAttributes(['class' => 'fi-section-sales-live'])
                            ->schema([
                                Forms\Components\Placeholder::make('stats_overview')
                                    ->hiddenLabel()
                                    ->content(function (?Event $record) use ($t) {
                                        if (!$record || !$record->exists) {
                                            return new HtmlString('<div class="text-sm text-gray-500">' . $t('Salvează evenimentul pentru a vedea statisticile.', 'Save the event to see statistics.') . '</div>');
                                        }

                                        // Get stats from the event or calculate from ticket types
                                        // TicketType uses quota_sold for sold count and capacity accessor for total
                                        $ticketsSold = $record->tickets_sold ?? $record->ticketTypes->sum('quota_sold') ?? 0;
                                        // Calculate revenue from ticket types (sold * display_price)
                                        $calculatedRevenue = $record->ticketTypes->sum(fn ($tt) => ($tt->quota_sold ?? 0) * ($tt->display_price ?? 0));
                                        $totalRevenue = $record->revenue ?? $calculatedRevenue ?? 0;
                                        $totalCapacity = $record->capacity ?? $record->ticketTypes->sum(fn ($tt) => $tt->capacity ?? 0) ?? 0;
                                        $views = $record->views ?? $record->views_count ?? 0;

                                        $percentSold = $totalCapacity > 0 ? round(($ticketsSold / $totalCapacity) * 100) : 0;
                                        $conversion = $views > 0 ? round(($ticketsSold / $views) * 100, 1) : 0;

                                        $revenueFormatted = $totalRevenue >= 1000
                                            ? number_format($totalRevenue / 1000, 1) . 'K'
                                            : number_format($totalRevenue, 0);

                                        $ticketsLabel = $t('Bilete', 'Tickets');
                                        $revenueLabel = $t('Venituri (RON)', 'Revenue (RON)');
                                        $capacityLabel = $t('Capacitate totală', 'Total capacity');
                                        $conversionLabel = $t('Conversie', 'Conversion');
                                        $viewsLabel = $t('Vizualizări', 'Views');

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
                                            </div>
                                            <div class='mt-3'>
                                                <div class='flex justify-between mb-1 text-xs text-gray-400'>
                                                    <span>{$capacityLabel}</span>
                                                    <span>" . number_format($ticketsSold) . " / " . number_format($totalCapacity) . " ({$percentSold}%)</span>
                                                </div>
                                                <div class='h-2 overflow-hidden bg-gray-700 rounded-full'>
                                                    <div class='h-full transition-all rounded-full bg-gradient-to-r from-primary-500 to-primary-400' style='width: {$percentSold}%'></div>
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
                                            ->orderByRaw('CAST(row_label AS UNSIGNED), row_label')
                                            ->orderByRaw('CAST(seat_label AS UNSIGNED), seat_label')
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
                                    ->live()
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
                            ]),

                        // 4. Quick Actions
                        SC\Section::make($t('Acțiuni rapide', 'Quick Actions'))
                            ->icon('heroicon-o-bolt')
                            ->compact()
                            ->collapsed()
                            ->schema([
                                SC\Actions::make([
                                    Action::make('duplicate')
                                        ->label($t('Duplică', 'Duplicate'))
                                        ->icon('heroicon-o-document-duplicate')
                                        ->color('gray')
                                        ->size('sm')
                                        ->visible(fn (?Event $record) => $record && $record->exists)
                                        ->requiresConfirmation()
                                        ->modalHeading($t('Duplică evenimentul', 'Duplicate event'))
                                        ->modalDescription($t('Sigur vrei să duplici acest eveniment? Se va crea o copie draft fără bilete vândute.', 'Are you sure you want to duplicate this event? A draft copy will be created without sold tickets.'))
                                        ->modalSubmitActionLabel($t('Duplică', 'Duplicate'))
                                        ->action(function (?Event $record) use ($t) {
                                            if (!$record) return;

                                            // Explicitly copy only fillable attributes that exist
                                            $newEvent = $record->replicate([
                                                // Exclude non-existent or auto-generated columns
                                                'id', 'slug', 'event_series', 'created_at', 'updated_at',
                                                // Exclude columns that don't exist in this schema
                                                'status', 'is_public', 'submitted_at', 'approved_at', 'approved_by',
                                                'venue_name', 'city', 'starts_at', 'ends_at',
                                                'seo_title', 'seo_description', 'revenue_target', 'capacity', 'event_type',
                                            ]);

                                            // Prepend "[Duplicat]" to all title translations
                                            $titleArray = $record->title ?? [];
                                            if (is_array($titleArray)) {
                                                foreach ($titleArray as $locale => $value) {
                                                    if (!empty($value)) {
                                                        $titleArray[$locale] = '[Duplicat] ' . $value;
                                                    }
                                                }
                                            }
                                            $newEvent->title = $titleArray;

                                            // Generate unique slug from title
                                            $baseTitle = is_array($titleArray) ? ($titleArray['ro'] ?? $titleArray['en'] ?? reset($titleArray)) : $titleArray;
                                            $baseSlug = \Illuminate\Support\Str::slug($baseTitle ?: 'eveniment');
                                            $slug = $baseSlug;
                                            $counter = 1;
                                            while (Event::where('slug', $slug)->exists()) {
                                                $slug = $baseSlug . '-' . $counter;
                                                $counter++;
                                            }
                                            $newEvent->slug = $slug;

                                            // Reset fields for the duplicate
                                            $newEvent->is_featured = false;
                                            $newEvent->is_homepage_featured = false;
                                            $newEvent->is_general_featured = false;
                                            $newEvent->is_category_featured = false;
                                            $newEvent->is_published = false;
                                            $newEvent->views_count = 0;
                                            $newEvent->interested_count = 0;
                                            $newEvent->save();

                                            // Duplicate ticket types
                                            foreach ($record->ticketTypes as $ticketType) {
                                                $newTicketType = $ticketType->replicate([
                                                    'id', 'created_at', 'updated_at',
                                                ]);
                                                $newTicketType->event_id = $newEvent->id;
                                                $newTicketType->quota_sold = 0;
                                                $newTicketType->save();
                                            }

                                            // Get display title for notification
                                            $displayTitle = $newEvent->getTranslation('title') ?? 'Eveniment';

                                            \Filament\Notifications\Notification::make()
                                                ->title($t('Eveniment duplicat', 'Event duplicated'))
                                                ->body($t('Evenimentul', 'Event') . " \"{$displayTitle}\" " . $t('a fost creat.', 'has been created.'))
                                                ->success()
                                                ->send();

                                            return redirect(static::getUrl('edit', ['record' => $newEvent]));
                                        }),
                                    Action::make('preview')
                                        ->label('Preview')
                                        ->icon('heroicon-o-eye')
                                        ->color('gray')
                                        ->size('sm')
                                        ->visible(fn (?Event $record) => $record && $record->exists)
                                        ->url(function (?Event $record) use ($marketplace) {
                                            if (!$record) return null;
                                            $domain = $marketplace?->domain;
                                            if (!$domain) return null;
                                            // Strip any existing protocol from domain
                                            $domain = preg_replace('#^(https?:?/?/?|//)#i', '', $domain);
                                            $domain = ltrim($domain, '/');
                                            $protocol = str_contains($domain, 'localhost') ? 'http' : 'https';
                                            return "{$protocol}://{$domain}/bilete/{$record->slug}?preview=1";
                                        })
                                        ->openUrlInNewTab(),
                                ])->fullWidth(),
                                SC\Actions::make([
                                    Action::make('export')
                                        ->label('Export')
                                        ->icon('heroicon-o-arrow-down-tray')
                                        ->color('gray')
                                        ->size('sm')
                                        ->visible(fn (?Event $record) => $record && $record->exists)
                                        ->action(function (?Event $record) {
                                            if (!$record) return;

                                            // Export event data as JSON
                                            $data = [
                                                'event' => $record->only(['name', 'slug', 'description', 'short_description', 'starts_at', 'ends_at', 'doors_open_at', 'venue_name', 'venue_address', 'venue_city', 'status', 'capacity', 'tickets_sold', 'revenue', 'views']),
                                                'ticket_types' => $record->ticketTypes->map(fn ($tt) => $tt->only(['name', 'display_price', 'capacity', 'quota_sold', 'status']))->toArray(),
                                                'exported_at' => now()->toIso8601String(),
                                            ];

                                            return response()->streamDownload(function () use ($data) {
                                                echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                                            }, "event-{$record->slug}-export.json", [
                                                'Content-Type' => 'application/json',
                                            ]);
                                        }),
                                    Action::make('statistics')
                                        ->label($t('Statistici', 'Statistics'))
                                        ->icon('heroicon-o-chart-pie')
                                        ->color('gray')
                                        ->size('sm')
                                        ->visible(fn (?Event $record) => $record && $record->exists)
                                        ->url(fn (?Event $record) => $record ? static::getUrl('statistics', ['record' => $record]) : null),
                                ])->fullWidth(),
                                SC\Actions::make([
                                    Action::make('generate_document')
                                        ->label($t('Generează', 'Generate'))
                                        ->icon('heroicon-o-document-plus')
                                        ->color('gray')
                                        ->size('sm')
                                        ->visible(fn (?Event $record) => $record && $record->exists)
                                        ->form(function () use ($marketplace, $t) {
                                            $templates = MarketplaceTaxTemplate::where('marketplace_client_id', $marketplace?->id)
                                                ->where('is_active', true)
                                                ->orderBy('name')
                                                ->pluck('name', 'id')
                                                ->toArray();

                                            return [
                                                Forms\Components\Select::make('template_id')
                                                    ->label($t('Selectează template', 'Select template'))
                                                    ->options($templates)
                                                    ->required()
                                                    ->searchable()
                                                    ->helperText($t('Alege un template de document pentru a genera PDF-ul.', 'Choose a document template to generate the PDF.')),
                                            ];
                                        })
                                        ->modalHeading($t('Generează documente', 'Generate documents'))
                                        ->modalDescription($t('Selectează un template pentru a genera documentul PDF pentru acest eveniment.', 'Select a template to generate the PDF document for this event.'))
                                        ->modalSubmitActionLabel($t('Generează', 'Generate'))
                                        ->action(function (array $data, ?Event $record) {
                                            if (!$record) return;

                                            $template = MarketplaceTaxTemplate::find($data['template_id']);
                                            if (!$template) {
                                                \Filament\Notifications\Notification::make()
                                                    ->title($t('Eroare', 'Error'))
                                                    ->body($t('Template-ul selectat nu a fost găsit.', 'Selected template was not found.'))
                                                    ->danger()
                                                    ->send();
                                                return;
                                            }

                                            try {
                                                $user = auth()->user();
                                                $document = EventGeneratedDocument::generateDocument(
                                                    event: $record,
                                                    template: $template,
                                                    generatedBy: $user
                                                );

                                                \Filament\Notifications\Notification::make()
                                                    ->title($t('Document generat', 'Document generated'))
                                                    ->body($t('Documentul', 'Document') . " \"{$document->filename}\" " . $t('a fost generat cu succes.', 'was generated successfully.'))
                                                    ->success()
                                                    ->actions([
                                                        \Filament\Notifications\Actions\Action::make('download')
                                                            ->label($t('Descarcă', 'Download'))
                                                            ->url(Storage::disk('public')->url($document->file_path))
                                                            ->openUrlInNewTab(),
                                                    ])
                                                    ->send();
                                            } catch (\Exception $e) {
                                                \Filament\Notifications\Notification::make()
                                                    ->title($t('Eroare la generare', 'Generation error'))
                                                    ->body($e->getMessage())
                                                    ->danger()
                                                    ->send();
                                            }
                                        }),
                                    Action::make('view_documents')
                                        ->label($t('Documente', 'Documents'))
                                        ->icon('heroicon-o-folder-open')
                                        ->color('gray')
                                        ->size('sm')
                                        ->visible(fn (?Event $record) => $record && $record->exists)
                                        ->modalHeading($t('Documente generate', 'Generated documents'))
                                        ->modalSubmitAction(false)
                                        ->modalCancelActionLabel($t('Închide', 'Close'))
                                        ->modalContent(function (?Event $record) use ($t) {
                                            if (!$record) return new HtmlString('<p>' . $t('Nu există documente.', 'No documents.') . '</p>');

                                            // Fetch both document types for this event
                                            $generatedDocs = EventGeneratedDocument::where('event_id', $record->id)
                                                ->orderByDesc('created_at')
                                                ->get();

                                            $organizerDocs = OrganizerDocument::where('event_id', $record->id)
                                                ->orderByDesc('created_at')
                                                ->get();

                                            $noDocsMsg = $t('Nu există documente generate pentru acest eveniment.', 'No documents generated for this event.');
                                            $generateHint = $t('Folosește butonul "Generează" pentru a crea un nou document.', 'Use the "Generate" button to create a new document.');

                                            if ($generatedDocs->isEmpty() && $organizerDocs->isEmpty()) {
                                                return new HtmlString("
                                                    <div class=\"text-center py-8\">
                                                        <svg class=\"mx-auto h-12 w-12 text-gray-400\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\">
                                                            <path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z\"/>
                                                        </svg>
                                                        <p class=\"mt-2 text-sm text-gray-500\">{$noDocsMsg}</p>
                                                        <p class=\"text-xs text-gray-400\">{$generateHint}</p>
                                                    </div>
                                                ");
                                            }

                                            $html = '';
                                            $organizerDocsLabel = $t('Documente organizator', 'Organizer documents');
                                            $taxDocsLabel = $t('Documente fiscale', 'Tax documents');
                                            $downloadLabel = $t('Descarcă', 'Download');

                                            // Organizer documents (cerere avizare, declaratie impozite)
                                            if ($organizerDocs->isNotEmpty()) {
                                                $html .= "<div class=\"mb-4\"><h4 class=\"text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2\">{$organizerDocsLabel}</h4>";
                                                $html .= '<div class="divide-y divide-gray-200 dark:divide-gray-700">';
                                                foreach ($organizerDocs as $doc) {
                                                    $typeLabel = OrganizerDocument::TYPES[$doc->document_type] ?? ucfirst($doc->document_type ?? '');
                                                    $title = e($doc->title ?: $typeLabel);
                                                    $createdAt = $doc->created_at?->format('d M Y, H:i') ?? '-';
                                                    $fileSize = $doc->formatted_file_size ?? '';
                                                    $downloadUrl = $doc->file_path ? Storage::disk('public')->url($doc->file_path) : '#';

                                                    $html .= "
                                                        <div class='flex items-center justify-between gap-4 py-3'>
                                                            <div class='flex items-center min-w-0 gap-3'>
                                                                <div class='flex items-center justify-center flex-shrink-0 w-10 h-10 bg-blue-100 rounded-lg dark:bg-blue-900/30'>
                                                                    <svg class='w-5 h-5 text-blue-600 dark:text-blue-400' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                                                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'/>
                                                                    </svg>
                                                                </div>
                                                                <div class='min-w-0'>
                                                                    <div class='text-sm font-medium text-gray-900 truncate dark:text-white'>{$title}</div>
                                                                    <div class='text-xs text-gray-500 dark:text-gray-400'>
                                                                        <span class='inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 dark:bg-blue-800 text-blue-600 dark:text-blue-300 mr-1'>{$typeLabel}</span>
                                                                    </div>
                                                                    <div class='text-xs text-gray-400 dark:text-gray-500 mt-0.5'>
                                                                        {$createdAt}" . ($fileSize ? " · {$fileSize}" : "") . "
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <a href='{$downloadUrl}' target='_blank' class='flex-shrink-0 inline-flex items-center px-2.5 py-1.5 border border-gray-300 dark:border-gray-600 shadow-sm text-xs font-medium rounded text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700'>
                                                                <svg class='w-4 h-4 mr-1' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                                                    <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4'/>
                                                                </svg>
                                                                {$downloadLabel}
                                                            </a>
                                                        </div>
                                                    ";
                                                }
                                                $html .= '</div></div>';
                                            }

                                            // Tax template generated documents
                                            if ($generatedDocs->isNotEmpty()) {
                                                if ($organizerDocs->isNotEmpty()) {
                                                    $html .= "<h4 class=\"text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2\">{$taxDocsLabel}</h4>";
                                                }
                                                $html .= '<div class="divide-y divide-gray-200 dark:divide-gray-700">';
                                                foreach ($generatedDocs as $doc) {
                                                    $downloadUrl = Storage::disk('public')->url($doc->file_path);
                                                    $templateName = e($doc->template?->name ?? $doc->meta['template_name'] ?? 'Unknown');
                                                    $templateType = $doc->template?->type ?? $doc->meta['template_type'] ?? '';
                                                    $typeLabel = MarketplaceTaxTemplate::TYPES[$templateType] ?? ucfirst($templateType);
                                                    $generatedBy = e($doc->generated_by_name ?? 'System');
                                                    $createdAt = $doc->created_at->format('d M Y, H:i');
                                                    $fileSize = $doc->file_size_formatted;

                                                    $html .= "
                                                        <div class='flex items-center justify-between gap-4 py-3'>
                                                            <div class='flex items-center min-w-0 gap-3'>
                                                                <div class='flex items-center justify-center flex-shrink-0 w-10 h-10 bg-red-100 rounded-lg dark:bg-red-900/30'>
                                                                    <svg class='w-5 h-5 text-red-600 dark:text-red-400' fill='currentColor' viewBox='0 0 20 20'>
                                                                        <path fill-rule='evenodd' d='M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z' clip-rule='evenodd'/>
                                                                    </svg>
                                                                </div>
                                                                <div class='min-w-0'>
                                                                    <div class='text-sm font-medium text-gray-900 truncate dark:text-white'>{$doc->filename}</div>
                                                                    <div class='text-xs text-gray-500 dark:text-gray-400'>
                                                                        <span class='inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 mr-1'>{$typeLabel}</span>
                                                                        {$templateName}
                                                                    </div>
                                                                    <div class='text-xs text-gray-400 dark:text-gray-500 mt-0.5'>
                                                                        {$generatedBy} · {$createdAt} · {$fileSize}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <a href='{$downloadUrl}' target='_blank' class='flex-shrink-0 inline-flex items-center px-2.5 py-1.5 border border-gray-300 dark:border-gray-600 shadow-sm text-xs font-medium rounded text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700'>
                                                                <svg class='w-4 h-4 mr-1' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                                                    <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4'/>
                                                                </svg>
                                                                {$downloadLabel}
                                                            </a>
                                                        </div>
                                                    ";
                                                }
                                                $html .= '</div>';
                                            }

                                            return new HtmlString($html);
                                        }),
                                ])->fullWidth(),
                            ]),

                        // 5. Activity Log (doar pentru edit)
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
                Tables\Columns\TextColumn::make('venue_id')
                    ->label('Venue')
                    ->formatStateUsing(fn ($state, $record) => $record->venue?->getTranslation('name', app()->getLocale()) ?? '-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('marketplace_city_id')
                    ->label('Oraș')
                    ->formatStateUsing(fn ($state, $record) => $record->marketplaceCity?->getTranslation('name', app()->getLocale()) ?? '-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('event_date')
                    ->label('Event Date')
                    ->formatStateUsing(function ($state, $record) {
                        // Handle different duration modes
                        if ($record->duration_mode === 'range') {
                            $start = $record->range_start_date;
                            $end = $record->range_end_date;

                            // If we have both start and end dates
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

                            // If only start date, show "from X"
                            if ($start) {
                                return 'from ' . $start->format('d M Y');
                            }
                        }

                        // For multi_day, show first and last slot dates
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

                        // Default: single day - try event_date, then range_start_date as fallback
                        return $state?->format('d M Y') ?? $record->range_start_date?->format('d M Y') ?? '-';
                    })
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_published')
                    ->boolean()
                    ->label('Publicat')
                    ->trueIcon('heroicon-o-eye')
                    ->falseIcon('heroicon-o-eye-slash')
                    ->trueColor('success')
                    ->falseColor('danger'),
                Tables\Columns\IconColumn::make('is_cancelled')
                    ->boolean()
                    ->label('Cancelled'),
                Tables\Columns\IconColumn::make('is_sold_out')
                    ->boolean()
                    ->label('Sold Out'),
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
                    ]),
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
                Action::make('analytics')
                    ->label('Analytics')
                    ->icon('heroicon-o-presentation-chart-line')
                    ->color('success')
                    ->url(fn (Event $record) => static::getUrl('analytics', ['record' => $record])),
                Action::make('statistics')
                    ->label('')
                    ->icon('heroicon-o-chart-bar')
                    ->color('info')
                    ->url(fn (Event $record) => static::getUrl('statistics', ['record' => $record])),
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
            'analytics' => Pages\EventAnalytics::route('/{record}/analytics'),
            'activity-log' => Pages\EventActivityLog::route('/{record}/activity-log'),
            'view-guest' => Pages\ViewGuestEvent::route('/{record}/view'),
        ];
    }
}

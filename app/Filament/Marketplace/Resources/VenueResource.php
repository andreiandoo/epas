<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\VenueResource\Pages;
use App\Filament\Marketplace\Resources\EventResource;
use App\Filament\Marketplace\Resources\TenantResource;
use App\Models\Venue;
use App\Models\MarketplaceVenueCategory;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Utilities\Set as SSet;
use Filament\Schemas\Components\Utilities\Get as SGet;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use Illuminate\Support\Str;

class VenueResource extends Resource
{
    use \App\Filament\Marketplace\Concerns\TranslatesResourceLabels;

    use HasMarketplaceContext;

    protected static ?string $model = Venue::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationLabel = 'Locații';
    protected static \UnitEnum|string|null $navigationGroup = null;
    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) return null;

        return (string) static::getEloquentQuery()->count();
    }

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        $venueCountries = $marketplace?->settings['venue_countries'] ?? [];
        $expandedCountries = self::expandCountryVariants($venueCountries);
        return parent::getEloquentQuery()
            ->whereHas('marketplaceClients', fn (Builder $q) => $q->where('marketplace_client_id', $marketplace?->id))
            ->when(!empty($expandedCountries), fn ($q) => $q->whereIn('country', $expandedCountries));
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();

        return $schema->schema([
            // Hidden tenant_id
            Forms\Components\Hidden::make('marketplace_client_id')
                ->default($marketplace?->id),

            // CSS strict pentru această pagină (vezi view-ul)
            SC\View::make('filament.marketplace.venues.styles')->columnSpanFull(),

            SC\Grid::make(['default' => 1, 'xl' => 12, '2xl' => 12])
                ->extraAttributes(['class' => 'ep-venue-form'])
                ->schema([
                    // Navigație secțiuni (doar la editare)
                    SC\View::make('filament.marketplace.venues.section-nav')
                        ->visible(fn ($operation) => $operation === 'edit')
                        ->columnSpan(['default' => 'full', 'xl' => 'full', '2xl' => 2])
                        ->viewData(fn (?Venue $record) => ['sections' => static::completeness($record)['sections']]),

                    SC\Group::make()
                        ->columnSpan(fn ($operation) => $operation === 'edit'
                            ? ['default' => 'full', 'xl' => 8, '2xl' => 7]
                            : ['default' => 'full', 'xl' => 12, '2xl' => 12])
                        ->schema([
                    // ============================================================
                    // SEARCH EXISTING VENUES (only on create page)
                    // ============================================================
                    SC\Section::make(__('Caută locații existente'))
                        ->description(__('Caută în toate locațiile din sistem. Dacă găsești locația dorită, o poți adăuga ca partener în loc să creezi una nouă.'))
                        ->icon('heroicon-o-magnifying-glass')
                        ->extraAttributes(['class' => 'bg-gradient-to-r from-emerald-500/10 to-emerald-600/5 border-emerald-500/30', 'style' => 'overflow: visible;'])
                        ->visible(fn ($operation) => $operation === 'create')
                        ->columnSpanFull()
                        ->schema([
                            Forms\Components\Select::make('search_existing_venue')
                                ->label(__('Caută o locație existentă'))
                                ->placeholder(__('Scrie numele sau orașul pentru a căuta...'))
                                ->searchable()
                                ->prefixIcon('heroicon-o-magnifying-glass')
                                ->getSearchResultsUsing(function (string $search) use ($marketplace): array {
                                    if (strlen($search) < 2) {
                                        return [];
                                    }

                                    // Normalize search (remove diacritics)
                                    $normalizedSearch = mb_strtolower($search);
                                    $diacritics = ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ț' => 't'];
                                    $normalizedSearch = strtr($normalizedSearch, $diacritics);

                                    return Venue::query()
                                        // Search ALL venues (including this marketplace's own to prevent duplicates)
                                        ->where(function (Builder $q) use ($normalizedSearch, $search) {
                                            // Search in name JSON field
                                            $isPgsql = DB::getDriverName() === 'pgsql';
                                            $q->whereRaw(
                                                $isPgsql ? "LOWER(name::jsonb->>'ro') LIKE ?" : "LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, '$.ro'))) LIKE ?",
                                                ["%{$normalizedSearch}%"]
                                            )
                                                ->orWhereRaw(
                                                    $isPgsql ? "LOWER(name::jsonb->>'en') LIKE ?" : "LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, '$.en'))) LIKE ?",
                                                    ["%{$normalizedSearch}%"]
                                                )
                                                ->orWhereRaw("LOWER(city) LIKE ?", ["%{$normalizedSearch}%"])
                                                // Also search with original term
                                                ->orWhereRaw(
                                                    $isPgsql ? "LOWER(name::jsonb->>'ro') LIKE ?" : "LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, '$.ro'))) LIKE ?",
                                                    ["%" . mb_strtolower($search) . "%"]
                                                )
                                                ->orWhereRaw("LOWER(city) LIKE ?", ["%" . mb_strtolower($search) . "%"]);
                                        })
                                        ->limit(20)
                                        ->get()
                                        ->mapWithKeys(function (Venue $venue) use ($marketplace) {
                                            $name = $venue->getTranslation('name', 'ro') ?? $venue->getTranslation('name', 'en') ?? 'Locație';
                                            $city = $venue->city ? " - {$venue->city}" : '';
                                            $capacity = $venue->capacity_total ? " ({$venue->capacity_total} locuri)" : '';
                                            // Show status based on pivot relationship
                                            $status = $venue->isInMarketplace($marketplace?->id ?? 0)
                                                ? ' [Deja în lista ta]'
                                                : '';
                                            return [$venue->id => $name . $city . $capacity . $status];
                                        })
                                        ->toArray();
                                })
                                ->getOptionLabelUsing(function ($value) use ($marketplace) {
                                    $venue = Venue::find($value);
                                    if (!$venue) return $value;
                                    $name = $venue->getTranslation('name', 'ro') ?? $venue->getTranslation('name', 'en') ?? 'Locație';
                                    $city = $venue->city ? " - {$venue->city}" : '';
                                    $status = $venue->isInMarketplace($marketplace?->id ?? 0)
                                        ? ' [Deja în lista ta]'
                                        : '';
                                    return $name . $city . $status;
                                })
                                ->live()
                                ->afterStateUpdated(function ($state, SSet $set) {
                                    // Store the selected venue ID for the action to use
                                    $set('selected_venue_id', $state);
                                })
                                ->hintIcon('heroicon-o-information-circle', tooltip: 'Selectează o locație pentru a vedea detaliile și opțiunea de adăugare ca partener')
                                ->columnSpanFull(),

                            Forms\Components\Hidden::make('selected_venue_id'),

                            // Show selected venue details and add button
                            Forms\Components\Placeholder::make('venue_preview')
                                ->label('')
                                ->visible(fn (SGet $get) => !empty($get('search_existing_venue')))
                                ->content(function (SGet $get) use ($marketplace) {
                                    $venueId = $get('search_existing_venue');
                                    if (!$venueId) return '';

                                    $venue = Venue::find($venueId);
                                    if (!$venue) return '';

                                    $name = $venue->getTranslation('name', 'ro') ?? $venue->getTranslation('name', 'en') ?? 'Locație';
                                    $city = $venue->city ?? '-';
                                    $address = $venue->address ?? '-';
                                    $capacity = $venue->capacity_total ?? '-';

                                    // Determine status based on pivot relationship
                                    if ($venue->isInMarketplace($marketplace?->id ?? 0)) {
                                        $statusBadge = '<span class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-700 bg-blue-100 rounded-full dark:text-blue-400 dark:bg-blue-900/30">Deja în lista ta</span>';
                                    } else {
                                        $statusBadge = '<span class="inline-flex items-center px-2 py-1 text-xs font-medium text-green-700 bg-green-100 rounded-full dark:text-green-400 dark:bg-green-900/30">Disponibilă pentru parteneriat</span>';
                                    }

                                    return new HtmlString("
                                        <div class='p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700'>
                                            <div class='flex justify-between items-start mb-3'>
                                                <h4 class='text-lg font-semibold text-gray-900 dark:text-white'>{$name}</h4>
                                                {$statusBadge}
                                            </div>
                                            <div class='grid grid-cols-3 gap-4 text-sm'>
                                                <div>
                                                    <span class='text-gray-500 dark:text-gray-400'>Oraș:</span>
                                                    <span class='ml-2 text-gray-900 dark:text-white'>{$city}</span>
                                                </div>
                                                <div>
                                                    <span class='text-gray-500 dark:text-gray-400'>Adresă:</span>
                                                    <span class='ml-2 text-gray-900 dark:text-white'>{$address}</span>
                                                </div>
                                                <div>
                                                    <span class='text-gray-500 dark:text-gray-400'>Capacitate:</span>
                                                    <span class='ml-2 text-gray-900 dark:text-white'>{$capacity}</span>
                                                </div>
                                            </div>
                                        </div>
                                    ");
                                })
                                ->columnSpanFull(),

                            SC\Actions::make([
                                Action::make('add_as_partner')
                                    ->label(fn () => 'Preia în biblioteca ' . ($marketplace?->public_name ?? $marketplace?->name ?? 'marketplace'))
                                    ->icon('heroicon-o-plus-circle')
                                    ->color('success')
                                    ->size('lg')
                                    ->visible(function (SGet $get) use ($marketplace) {
                                        $venueId = $get('search_existing_venue');
                                        if (!$venueId) return false;
                                        $venue = Venue::find($venueId);
                                        // Show button if venue is NOT yet in this marketplace's partner list
                                        return $venue && !$venue->isInMarketplace($marketplace?->id ?? 0);
                                    })
                                    ->requiresConfirmation()
                                    ->modalHeading(fn () => 'Preia locația în biblioteca ' . ($marketplace?->public_name ?? $marketplace?->name ?? 'marketplace'))
                                    ->modalDescription(__('Această locație va fi adăugată în lista ta de locații partenere. Vei putea să o folosești pentru evenimentele tale.'))
                                    ->action(function (SGet $get) use ($marketplace) {
                                        $venueId = $get('search_existing_venue');
                                        $venue = Venue::find($venueId);

                                        if (!$venue) {
                                            Notification::make()
                                                ->title(__('Eroare'))
                                                ->body(__('Locația nu a fost găsită.'))
                                                ->danger()
                                                ->send();
                                            return;
                                        }

                                        $venueName = $venue->getTranslation('name', 'ro') ?? $venue->getTranslation('name', 'en') ?? 'Locație';

                                        // Attach to this marketplace via pivot (syncWithoutDetaching preserves other marketplaces)
                                        $venue->marketplaceClients()->syncWithoutDetaching([
                                            $marketplace?->id => ['is_partner' => true],
                                        ]);

                                        Notification::make()
                                            ->title(__('Locație adăugată'))
                                            ->body('"' . $venueName . '" a fost adăugată ca partener. Vei fi redirecționat către lista de locații.')
                                            ->success()
                                            ->send();

                                        // Redirect to the venues list
                                        return redirect(static::getUrl('index'));
                                    }),
                            ])->visible(fn (SGet $get) => !empty($get('search_existing_venue'))),
                        ]),

                    // Separator between search and create new (only on create page)
                    Forms\Components\Placeholder::make('or_create_new')
                        ->hiddenLabel()
                        ->visible(fn ($operation) => $operation === 'create')
                        ->content(new HtmlString('
                            <div class="flex items-center gap-4 py-4">
                                <div class="flex-1 border-t border-gray-300 dark:border-gray-600"></div>
                                <span class="text-sm text-gray-500 dark:text-gray-400">sau creează o locație nouă mai jos</span>
                                <div class="flex-1 border-t border-gray-300 dark:border-gray-600"></div>
                            </div>
                        '))
                        ->columnSpanFull(),

                    // ============================================================
                    // IDENTITATE
                    // ============================================================
                    SC\Section::make(__('Identitate'))
                        ->id('s-id')
                        ->icon('heroicon-o-identification')
                        ->schema([
                            SC\Tabs::make('Name Translations')
                                ->tabs([
                                    SC\Tabs\Tab::make('Română')
                                        ->schema([
                                            Forms\Components\TextInput::make('name.ro')
                                                ->label(__('Nume locație (RO)'))
                                                ->required()
                                                ->maxLength(255)
                                                ->live(onBlur: true)
                                                ->afterStateUpdated(function ($state, SSet $set) {
                                                    if ($state) $set('slug', Str::slug($state));
                                                }),
                                        ]),
                                    SC\Tabs\Tab::make('English')
                                        ->schema([
                                            Forms\Components\TextInput::make('name.en')
                                                ->label(__('Venue name (EN)'))
                                                ->maxLength(255),
                                        ]),
                                ])->columnSpanFull(),

                            Forms\Components\TextInput::make('slug')
                                ->label(__('Adresa paginii (slug)'))
                                ->required()
                                ->maxLength(190)
                                ->unique(ignoreRecord: true)
                                ->rule('alpha_dash')
                                ->prefix('/locatie/')
                                ->placeholder('auto-generated-from-name'),

                            Forms\Components\Select::make('venueCategories')
                                ->label(__('Categorii locație'))
                                ->relationship(
                                    'venueCategories',
                                    'name',
                                    fn (Builder $query) => $query->where('marketplace_client_id', static::getMarketplaceClient()?->id)
                                )
                                ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('name', 'ro') ?? $record->getTranslation('name', 'en'))
                                ->multiple()
                                ->preload()
                                ->searchable()
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('name.ro')
                                        ->label(__('Nume categorie (RO)'))
                                        ->required(),
                                    Forms\Components\TextInput::make('name.en')
                                        ->label(__('Category name (EN)')),
                                    Forms\Components\TextInput::make('icon')
                                        ->label(__('Icon (emoji)'))
                                        ->placeholder('🎭'),
                                    Forms\Components\ColorPicker::make('color')
                                        ->label(__('Culoare')),
                                ])
                                ->createOptionUsing(function (array $data) {
                                    $data['marketplace_client_id'] = static::getMarketplaceClient()?->id;
                                    return MarketplaceVenueCategory::create($data)->id;
                                })
                                ->columnSpanFull(),

                            Forms\Components\Toggle::make('is_featured')
                                ->label(__('Locație promovată'))
                                ->helperText(__('Va apărea în secțiunea promovată'))
                                ->default(false),
                        ]),

                    // ============================================================
                    // IMAGINI
                    // ============================================================
                    SC\Section::make(__('Imagini'))
                        ->id('s-img')
                        ->icon('heroicon-o-photo')
                        ->schema([
                            SC\Grid::make(['default' => 1, 'md' => 3])->schema([
                                Forms\Components\FileUpload::make('image_url')
                                    ->label(__('Main image'))
                                    ->helperText(__('Desktop, orizontală'))
                                    ->image()
                                    ->imagePreviewHeight('200')
                                    ->disk('public')
                                    ->directory('venues')
                                    ->visibility('public')
                                    ->afterStateUpdated(fn ($livewire) => $livewire->skipRender())
                                    ->columnSpan(['md' => 2]),
                                Forms\Components\FileUpload::make('meta.portrait')
                                    ->label(__('Portret (mobile hero)'))
                                    ->helperText(__('Imagine verticală afișată pe mobile în hero-ul paginii locației'))
                                    ->image()
                                    ->imagePreviewHeight('200')
                                    ->disk('public')
                                    ->directory('venues/portraits')
                                    ->visibility('public')
                                    ->afterStateUpdated(fn ($livewire) => $livewire->skipRender())
                                    ->columnSpan(['md' => 1]),
                            ]),
                            Forms\Components\FileUpload::make('gallery')
                                ->label(__('Gallery'))
                                ->image()
                                ->multiple()
                                ->maxFiles(5)
                                ->disk('public')
                                ->directory('venues/gallery')
                                ->visibility('public')
                                ->reorderable()
                                ->panelLayout('grid')
                                ->imagePreviewHeight('80')
                                ->afterStateUpdated(fn ($livewire) => $livewire->skipRender())
                                ->columnSpanFull(),

                            // YouTube URL
                            Forms\Components\TextInput::make('video_url')
                                ->label(__('Video de prezentare (YouTube)'))
                                ->url()
                                ->placeholder('https://www.youtube.com/watch?v=...')
                                ->prefixIcon('heroicon-o-play')
                                ->helperText(__('Link YouTube către un video de prezentare al locației (opțional).'))
                                ->afterStateHydrated(function (\Filament\Forms\Components\TextInput $component, $state, ?\App\Models\Venue $record) {
                                    // Ensure video_type gets marked as youtube whenever a URL exists,
                                    // so existing records created before this simplification still work.
                                    if ($record && filled($record->video_url) && empty($record->video_type)) {
                                        $record->forceFill(['video_type' => 'youtube'])->saveQuietly();
                                    }
                                })
                                ->columnSpanFull(),

                            // Keep video_type as a hidden companion so legacy rows stay consistent.
                            Forms\Components\Hidden::make('video_type')
                                ->dehydrateStateUsing(fn ($state, \Filament\Schemas\Components\Utilities\Get $get) => filled($get('video_url')) ? 'youtube' : null),
                        ]),

                    // ============================================================
                    // LOCATIE
                    // ============================================================
                    SC\Section::make(__('Locație'))
                        ->id('s-loc')
                        ->icon('heroicon-o-map-pin')
                        ->schema([
                            Forms\Components\TextInput::make('address')
                                ->label(__('Address'))
                                ->maxLength(255)
                                ->placeholder(__('Street and number')),
                            // Canonical județ + localitate pickers (helpers).
                            // They pre-fill from / write into the free-text
                            // city/state fields in "Detalii adresă" — see LocationSelectFields.
                            SC\Grid::make(['default' => 1, 'md' => 2])->schema(
                                collect(\App\Filament\Support\LocationSelectFields::make())
                                    ->map(fn ($field) => match ($field->getName()) {
                                        'geo_county_id' => $field
                                            ->label(__('Județ'))
                                            ->helperText(__('Din listă; completează județul oficial în „Detalii adresă”.')),
                                        'geo_city_id' => $field
                                            ->label(__('Localitate'))
                                            ->helperText(__('Din listă; completează orașul oficial în „Detalii adresă”.')),
                                        default => $field,
                                    })
                                    ->all()
                            ),
                            SC\Section::make(__('Detalii adresă'))
                                ->description(__('oraș, regiune, țară, coordonate, link Google Maps'))
                                ->compact()
                                ->collapsible()
                                ->collapsed(fn ($livewire) => ! static::hasErrorsOn($livewire, ['city', 'state', 'country', 'lat', 'lng', 'google_maps_url']))
                                ->columns(6)
                                ->schema([
                                    Forms\Components\TextInput::make('city')
                                        ->label(__('City'))
                                        ->maxLength(120)
                                        ->placeholder(__('e.g. București'))
                                        ->columnSpan(['default' => 6, 'md' => 2]),
                                    Forms\Components\TextInput::make('state')
                                        ->label(__('State/Region'))
                                        ->maxLength(120)
                                        ->placeholder(__('e.g. Ilfov'))
                                        ->columnSpan(['default' => 6, 'md' => 2]),
                                    Forms\Components\TextInput::make('country')
                                        ->label(__('Country'))
                                        ->maxLength(120)
                                        ->placeholder(__('e.g. RO'))
                                        ->columnSpan(['default' => 6, 'md' => 2]),
                                    Forms\Components\TextInput::make('lat')
                                        ->label(__('Latitude'))
                                        ->numeric()
                                        ->step('0.0000001')
                                        ->placeholder('44.4268')
                                        ->columnSpan(['default' => 6, 'md' => 3]),
                                    Forms\Components\TextInput::make('lng')
                                        ->label(__('Longitude'))
                                        ->numeric()
                                        ->step('0.0000001')
                                        ->placeholder('26.1025')
                                        ->columnSpan(['default' => 6, 'md' => 3]),
                                    Forms\Components\TextInput::make('google_maps_url')
                                        ->label(__('Google Maps Link'))
                                        ->url()
                                        ->placeholder('https://maps.google.com/...')
                                        ->prefixIcon('heroicon-o-map')
                                        ->suffixAction(
                                            Action::make('open_google_maps')
                                                ->icon('heroicon-o-arrow-top-right-on-square')
                                                ->tooltip(__('Deschide link-ul într-o filă nouă'))
                                                ->url(fn ($state) => $state ?: null, shouldOpenInNewTab: true)
                                                ->visible(fn ($state) => filled($state))
                                        )
                                        ->columnSpanFull(),
                                \Filament\Schemas\Components\Actions::make([
                                    Action::make('geocode')
                                        ->label(__('Auto-detect coordinates'))
                                        ->icon('heroicon-o-map-pin')
                                        ->color('info')
                                        ->action(function (SGet $get, SSet $set) {
                                            $address = $get('address');
                                            $city = $get('city');
                                            $country = $get('country');

                                            if (empty($city)) {
                                                Notification::make()
                                                    ->title(__('City is required for geocoding'))
                                                    ->warning()
                                                    ->send();
                                                return;
                                            }

                                            $fullAddress = collect([$address, $city, $country])
                                                ->filter()
                                                ->implode(', ');

                                            $apiKey = \App\Models\Setting::first()?->google_maps_api_key;

                                            if (empty($apiKey)) {
                                                Notification::make()
                                                    ->title(__('Google Maps API key not configured'))
                                                    ->body(__('Please add your API key in Settings > Connections'))
                                                    ->danger()
                                                    ->send();
                                                return;
                                            }

                                            try {
                                                $response = \Illuminate\Support\Facades\Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                                                    'address' => $fullAddress,
                                                    'key' => $apiKey,
                                                ]);

                                                $data = $response->json();

                                                if (($data['status'] ?? null) === 'OK' && !empty($data['results'])) {
                                                    $location = $data['results'][0]['geometry']['location'];
                                                    $set('lat', $location['lat']);
                                                    $set('lng', $location['lng']);

                                                    Notification::make()
                                                        ->title(__('Coordinates detected'))
                                                        ->body("Lat: {$location['lat']}, Lng: {$location['lng']}")
                                                        ->success()
                                                        ->send();
                                                } else {
                                                    Notification::make()
                                                        ->title(__('Could not find coordinates'))
                                                        ->body(__('Try adding more address details'))
                                                        ->warning()
                                                        ->send();
                                                }
                                            } catch (\Exception $e) {
                                                Notification::make()
                                                    ->title(__('Geocoding failed'))
                                                    ->body($e->getMessage())
                                                    ->danger()
                                                    ->send();
                                            }
                                        }),
                                ])->columnSpanFull(),
                                ]),
                        ]),

                    // ============================================================
                    // CAPACITATE
                    // ============================================================
                    SC\Section::make(__('Capacitate'))
                        ->id('s-cap')
                        ->icon('heroicon-o-users')
                        ->columns(['default' => 1, 'md' => 3])
                        ->schema([
                            Forms\Components\TextInput::make('capacity_total')
                                ->label(__('Total capacity'))
                                ->numeric()
                                ->minValue(0)
                                ->placeholder(__('e.g. 12000')),
                            Forms\Components\TextInput::make('capacity_seated')
                                ->label(__('Seated'))
                                ->numeric()
                                ->minValue(0)
                                ->placeholder(__('e.g. 4000')),
                            Forms\Components\TextInput::make('capacity_standing')
                                ->label(__('Standing'))
                                ->numeric()
                                ->minValue(0)
                                ->placeholder(__('e.g. 8000')),
                        ]),

                    // ============================================================
                    // CONTACT SI PROGRAM
                    // ============================================================
                    SC\Section::make(__('Contact și program'))
                        ->id('s-contact')
                        ->icon('heroicon-o-phone')
                        ->columns(['default' => 1, 'md' => 2])
                        ->schema([
                            Forms\Components\TextInput::make('phone')
                                ->label(__('Phone 1'))
                                ->maxLength(64)
                                ->placeholder('+40 ...')
                                ->prefixIcon('heroicon-o-phone'),
                            Forms\Components\TextInput::make('phone2')
                                ->label(__('Phone 2'))
                                ->maxLength(64)
                                ->placeholder('+40 ...')
                                ->prefixIcon('heroicon-o-phone'),
                            Forms\Components\TextInput::make('email')
                                ->label(__('Email 1'))
                                ->email()
                                ->placeholder(__('contact@example.com'))
                                ->prefixIcon('heroicon-o-envelope'),
                            Forms\Components\TextInput::make('website_url')
                                ->label(__('Website'))
                                ->url()
                                ->placeholder('https://...')
                                ->prefixIcon('heroicon-o-globe-alt'),
                            Forms\Components\TextInput::make('facebook_url')
                                ->label(__('Facebook'))
                                ->url()
                                ->placeholder('https://facebook.com/...')
                                ->prefixIcon('heroicon-o-link')
                                ->columnSpanFull(),

                            // Mai multe contacte: deschis doar dacă are deja valori / erori
                            SC\Section::make(__('Mai multe contacte'))
                                ->description(__('Email 2, Instagram, TikTok'))
                                ->compact()
                                ->collapsible()
                                ->collapsed(fn ($livewire, ?Venue $record) => ! ($record && (filled($record->email2) || filled($record->instagram_url) || filled($record->tiktok_url)))
                                    && ! static::hasErrorsOn($livewire, ['email2', 'instagram_url', 'tiktok_url']))
                                ->columns(['default' => 1, 'md' => 2])
                                ->columnSpanFull()
                                ->schema([
                                    Forms\Components\TextInput::make('email2')
                                        ->label(__('Email 2'))
                                        ->email()
                                        ->placeholder(__('reservations@example.com'))
                                        ->prefixIcon('heroicon-o-envelope'),
                                    Forms\Components\TextInput::make('instagram_url')
                                        ->label(__('Instagram'))
                                        ->url()
                                        ->placeholder('https://instagram.com/...')
                                        ->prefixIcon('heroicon-o-link'),
                                    Forms\Components\TextInput::make('tiktok_url')
                                        ->label(__('TikTok'))
                                        ->url()
                                        ->placeholder('https://tiktok.com/@...')
                                        ->prefixIcon('heroicon-o-link'),
                                ]),

                            // SCHEDULE
                            Forms\Components\Textarea::make('schedule')
                                ->label(__('Program'))
                                ->placeholder("Luni - Vineri: 10:00 - 22:00\nSâmbătă - Duminică: 12:00 - 24:00")
                                ->rows(4)
                                ->columnSpanFull(),
                        ]),

                    // ============================================================
                    // FACILITATI (afisate ca bule prin CSS)
                    // ============================================================
                    SC\Section::make(__('Facilități'))
                        ->id('s-fac')
                        ->description(__('Selectează facilitățile disponibile la această locație'))
                        ->icon('heroicon-o-wrench-screwdriver')
                        ->extraAttributes(['class' => 'ep-vf-chips'])
                        ->schema([
                            Forms\Components\CheckboxList::make('facilities')
                                ->label('')
                                ->options(Venue::getFacilitiesOptions())
                                ->columns(4)
                                ->gridDirection('row')
                                ->searchable()
                                ->bulkToggleable()
                                ->columnSpanFull(),
                        ]),

                    // ============================================================
                    // DESCRIERE
                    // ============================================================
                    SC\Section::make(__('Descriere'))
                        ->id('s-desc')
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            SC\Tabs::make('Description Translations')
                                ->tabs([
                                    SC\Tabs\Tab::make('Română')
                                        ->schema([
                                            Forms\Components\RichEditor::make('description.ro')
                                                ->label(__('Descriere (RO)'))
                                                ->columnSpanFull(),
                                        ]),
                                    SC\Tabs\Tab::make('English')
                                        ->schema([
                                            Forms\Components\RichEditor::make('description.en')
                                                ->label(__('Description (EN)'))
                                                ->columnSpanFull(),
                                        ]),
                                ])->columnSpanFull(),
                        ]),

                    // ============================================================
                    // CONDITII SI TAXE
                    // ============================================================
                    SC\Section::make(__('Condiții și taxe'))
                        ->id('s-cond')
                        ->description(__('Condiții specifice locației și taxe aplicabile'))
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            Forms\Components\Toggle::make('has_historical_monument_tax')
                                ->label(__('Taxa de Monument Istoric (2%)'))
                                ->helperText(__('Dacă este activată, evenimentele din acest venue vor avea automat aplicată taxa de 2% pentru monument istoric.'))
                                ->default(false),

                            // Condițiile rămân strânse cât timp locația nu are niciuna
                            SC\Section::make(__('Condiții ale locației'))
                                ->description(__('Nicio condiție? Deschide pentru a adăuga.'))
                                ->compact()
                                ->collapsible()
                                ->collapsed(fn ($livewire, ?Venue $record) => ! ($record && filled(trim(strip_tags((string) $record->venue_conditions))))
                                    && ! static::hasErrorsOn($livewire, ['venue_conditions']))
                                ->schema([
                                    Forms\Components\RichEditor::make('venue_conditions')
                                        ->label(__('Condiții locație'))
                                        ->helperText(__('Condiții specifice care se vor adăuga automat la termenii biletelor evenimentelor din acest venue.'))
                                        ->columnSpanFull(),
                                ]),
                        ]),

                    // RECENZII GOOGLE
                    SC\Section::make(__('Google Reviews'))
                        ->id('s-rev')
                        ->icon('heroicon-o-star')
                        ->description(__('Rating mediu, număr total de recenzii și 3 recenzii de pe Google. Cachate local — nu se apelează API la fiecare vizualizare. Folosește butonul "Sincronizare recenzii" pentru a re-citi datele din Google.'))
                        ->schema([
                            Forms\Components\TextInput::make('google_place_id')
                                ->label(__('Google Place ID'))
                                ->maxLength(255)
                                ->placeholder(__('ChIJ...'))
                                ->helperText(new HtmlString(
                                    'Copiază Place ID-ul de la <a href="https://developers.google.com/maps/documentation/places/web-service/place-id#find-id" target="_blank" rel="noopener" style="color:#2563eb;text-decoration:underline;">Google Place ID Finder</a>. Apoi salvează și folosește butonul de sincronizare.'
                                ))
                                ->columnSpanFull(),
                            Forms\Components\Placeholder::make('google_reviews_preview')
                                ->label(__('Stare cache'))
                                ->content(function (?Venue $record) {
                                    if (!$record || empty($record->google_place_id)) {
                                        return new HtmlString('<span style="color:#6b7280;">Setează Place ID și salvează pentru a putea sincroniza.</span>');
                                    }
                                    $cache = $record->google_reviews_cached ?? [];
                                    if (empty($cache)) {
                                        return new HtmlString('<span style="color:#b45309;">Niciun cache. Apasă butonul de sincronizare.</span>');
                                    }
                                    $rating = $cache['rating'] ?? null;
                                    $count = $cache['review_count'] ?? 0;
                                    $updated = $record->google_reviews_updated_at?->diffForHumans() ?? '—';
                                    $ratingStr = $rating !== null ? number_format((float) $rating, 1) . ' ⭐' : '—';
                                    return new HtmlString(
                                        '<div style="display:flex;gap:1rem;flex-wrap:wrap;font-size:14px;">'
                                        . '<span><strong>Rating:</strong> ' . e($ratingStr) . '</span>'
                                        . '<span><strong>Recenzii:</strong> ' . number_format((int) $count) . '</span>'
                                        . '<span><strong>Ultima sincronizare:</strong> ' . e($updated) . '</span>'
                                        . '</div>'
                                    );
                                })
                                ->columnSpanFull(),
                            SC\Actions::make([
                                Action::make('sync_google_reviews')
                                    ->label(__('Sincronizare recenzii Google'))
                                    ->icon('heroicon-o-arrow-path')
                                    ->color('primary')
                                    ->requiresConfirmation()
                                    ->modalDescription(__('Se va face un apel către Google Places API pentru a aduce ratingul, numărul de recenzii și primele recenzii disponibile. Continui?'))
                                    ->visible(fn (?Venue $record) => $record && !empty($record->google_place_id))
                                    ->action(function (Venue $record) {
                                        try {
                                            $service = app(\App\Services\GooglePlacesReviewsService::class);
                                            $data = $service->fetchReviewsForPlaceId($record->google_place_id);
                                            $record->forceFill([
                                                'google_reviews_cached' => $data,
                                                'google_reviews_updated_at' => now(),
                                            ])->save();
                                            Notification::make()
                                                ->title('Sincronizat ' . count($data['reviews']) . ' recenzii')
                                                ->body('Rating: ' . ($data['rating'] ?? '—') . ' • Total recenzii Google: ' . $data['review_count'])
                                                ->success()
                                                ->send();
                                        } catch (\Throwable $e) {
                                            Notification::make()
                                                ->title(__('Eroare la sincronizare'))
                                                ->body($e->getMessage())
                                                ->danger()
                                                ->persistent()
                                                ->send();
                                        }
                                    }),
                            ])->columnSpanFull(),
                        ])->columns(1),

                    // PARTNER NOTES (internal)
                    SC\Section::make(__('Note interne'))
                        ->id('s-notes')
                        ->description(__('Note interne despre această locație (nu sunt vizibile public)'))
                        ->icon('heroicon-o-lock-closed')
                        ->schema([
                            Forms\Components\Textarea::make('partner_notes')
                                ->label(__('Note'))
                                ->placeholder(__('Note despre parteneriat, contracte, etc.'))
                                ->rows(4)
                                ->columnSpanFull(),
                        ]),

                    ]),

                    // Coloana din dreapta (doar la editare)
                    SC\Group::make()
                        ->columnSpan(['default' => 'full', 'xl' => 4, '2xl' => 3])
                        ->visible(fn ($operation) => $operation === 'edit')
                        ->schema([
                    // DE COMPLETAT (calculat din înregistrare)
                    SC\Section::make(__('De completat'))
                        ->icon('heroicon-o-clipboard-document-check')
                        ->compact()
                        ->description(fn (?Venue $record) => collect(static::completeness($record)['sections'])->where('done', true)->count() . ' din ' . count(static::completeness($record)['sections']) . ' secțiuni completate')
                        ->visible(fn ($operation) => $operation === 'edit')
                        ->schema([
                            SC\View::make('filament.marketplace.venues.todo-list')
                                ->viewData(fn (?Venue $record) => ['items' => static::completeness($record)['todo']]),
                        ]),
                    // Statistici (doar pe Edit)
                    SC\Section::make(__('Statistici'))
                        ->icon('heroicon-o-chart-bar')
                        ->compact()
                        ->visible(fn ($operation) => $operation === 'edit')
                        ->schema([
                            Forms\Components\Placeholder::make('stats')
                                ->hiddenLabel()
                                ->content(function (?Venue $record) {
                                    if (!$record) return '';

                                    $totalEvents = $record->events()->count();
                                    $upcomingEvents = $record->events()->where('event_date', '>', now())->count();
                                    $pastEvents = $record->events()->where('event_date', '<=', now())->count();
                                    $categoriesCount = $record->venueCategories()->count();

                                    return new HtmlString("
                                        <div class='space-y-2 text-sm'>
                                            <div class='flex justify-between'>
                                                <span class='text-gray-500 dark:text-gray-400'>Evenimente totale:</span>
                                                <span class='font-semibold text-gray-900 dark:text-white'>{$totalEvents}</span>
                                            </div>
                                            <div class='flex justify-between'>
                                                <span class='text-gray-500 dark:text-gray-400'>Evenimente viitoare:</span>
                                                <span class='font-semibold text-green-600 dark:text-green-400'>{$upcomingEvents}</span>
                                            </div>
                                            <div class='flex justify-between'>
                                                <span class='text-gray-500 dark:text-gray-400'>Evenimente trecute:</span>
                                                <span class='font-semibold text-gray-500'>{$pastEvents}</span>
                                            </div>
                                            <div class='flex justify-between'>
                                                <span class='text-gray-500 dark:text-gray-400'>Categorii:</span>
                                                <span class='font-semibold text-gray-900 dark:text-white'>{$categoriesCount}</span>
                                            </div>
                                        </div>
                                    ");
                                }),
                        ]),

                    // Meta Info (doar pe Edit)
                    SC\Section::make(__('Informații'))
                        ->icon('heroicon-o-information-circle')
                        ->compact()
                        ->visible(fn ($operation) => $operation === 'edit')
                        ->schema([
                            Forms\Components\Placeholder::make('meta_info')
                                ->hiddenLabel()
                                ->content(function (?Venue $record) {
                                    if (!$record) return '';

                                    $createdAt = \App\Support\MarketplaceTz::fmt($record->created_at, 'd.m.Y H:i', fallback: '-');
                                    $updatedAt = \App\Support\MarketplaceTz::fmt($record->updated_at, 'd.m.Y H:i', fallback: '-');
                                    $id = $record->id;

                                    return new HtmlString("
                                        <div class='space-y-2 text-sm'>
                                            <div class='flex justify-between'>
                                                <span class='text-gray-500 dark:text-gray-400'>ID:</span>
                                                <span class='font-mono text-xs text-gray-900 dark:text-white'>{$id}</span>
                                            </div>
                                            <div class='flex justify-between'>
                                                <span class='text-gray-500 dark:text-gray-400'>Creat:</span>
                                                <span class='text-gray-900 dark:text-white'>{$createdAt}</span>
                                            </div>
                                            <div class='flex justify-between'>
                                                <span class='text-gray-500 dark:text-gray-400'>Actualizat:</span>
                                                <span class='text-gray-900 dark:text-white'>{$updatedAt}</span>
                                            </div>
                                        </div>
                                    ");
                                }),
                        ]),

                    // Proprietar locație — appears only on edit when the
                    // venue is linked to a marketplace-created tenant
                    // (venues.tenant_id → tenants.owner_id → users). Click
                    // on the owner name jumps to the tenant edit page.
                    SC\Section::make(__('Proprietar locație'))
                        ->icon('heroicon-o-building-storefront')
                        ->visible(fn ($record) => $record && $record->tenant_id)
                        ->extraAttributes(['class' => 'ep-vf-owner'])
                        ->schema([
                            Forms\Components\Placeholder::make('venue_owner_info')
                                ->hiddenLabel()
                                ->content(function ($record) {
                                    if (!$record?->tenant_id) return new HtmlString('<span class="text-sm text-gray-500">Nu există proprietar linked.</span>');
                                    $tenant = \App\Models\Tenant::with('owner')->find($record->tenant_id);
                                    if (!$tenant) return new HtmlString('<span class="text-sm text-gray-500">Tenant #' . $record->tenant_id . ' negăsit.</span>');
                                    $tenantName = htmlspecialchars($tenant->public_name ?? $tenant->name ?? ('Tenant #' . $tenant->id), ENT_QUOTES);
                                    $ownerName = htmlspecialchars($tenant->owner?->name ?? '—', ENT_QUOTES);
                                    $ownerEmail = htmlspecialchars($tenant->owner?->email ?? '—', ENT_QUOTES);
                                    $editUrl = TenantResource::getUrl('edit', ['record' => $tenant->id]);
                                    return new HtmlString(
                                        '<div class="flex flex-col gap-2 py-1">'
                                        . '<div class="flex items-center gap-3">'
                                        . '<span class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">Owner:</span>'
                                        . '<a href="' . $editUrl . '" class="text-sm font-semibold text-primary-600 dark:text-primary-400 hover:underline">'
                                        . $ownerName
                                        . '</a>'
                                        . '<span class="text-xs text-gray-500 dark:text-gray-400">' . $ownerEmail . '</span>'
                                        . '</div>'
                                        . '<div class="flex items-center gap-3">'
                                        . '<span class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">Cont tenant:</span>'
                                        . '<a href="' . $editUrl . '" class="text-sm text-primary-600 dark:text-primary-400 hover:underline">'
                                        . $tenantName
                                        . '</a>'
                                        . '</div>'
                                        . '</div>'
                                    );
                                }),
                        ]),
                    ]),
                ]),
        ])->columns(1);
    }

    /**
     * Adevărat dacă formularul are erori de validare pe oricare din câmpurile date
     * (ține deschise secțiunile strânse când o eroare ar fi altfel ascunsă).
     */
    protected static function hasErrorsOn($livewire, array $fields): bool
    {
        if (! is_object($livewire) || ! method_exists($livewire, 'getErrorBag')) {
            return false;
        }
        $bag = $livewire->getErrorBag();
        foreach ($fields as $field) {
            if ($bag->has('data.' . $field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ce e completat / de completat la o locație, pentru navigația de secțiuni
     * și cardul „De completat”. Doar citire din înregistrare.
     */
    public static function completeness(?Venue $r): array
    {
        $txt = fn ($v) => filled(is_string($v) ? trim(strip_tags($v)) : $v);
        $anyLang = function ($v) use ($txt) {
            if (is_array($v)) {
                foreach ($v as $item) {
                    if ($txt($item)) {
                        return true;
                    }
                }

                return false;
            }

            return $txt($v);
        };
        $has = fn ($v) => is_array($v) ? count(array_filter($v, fn ($x) => filled($x))) > 0 : filled($v);

        $gallery = $r ? $has($r->gallery) : false;
        $portrait = $r ? filled($r->meta['portrait'] ?? null) : false;
        $mainImg = $r ? filled($r->image_url) : false;
        $address = $r ? (filled($r->address) && filled($r->city)) : false;
        $coords = $r ? (filled($r->lat) && filled($r->lng)) : false;
        $capacity = $r ? filled($r->capacity_total) : false;
        $contact = $r ? (filled($r->phone) || filled($r->phone2) || filled($r->email) || filled($r->email2) || filled($r->website_url) || filled($r->facebook_url) || filled($r->instagram_url) || filled($r->tiktok_url)) : false;
        $schedule = $r ? filled($r->schedule) : false;
        $facilities = $r ? $has($r->facilities) : false;
        $desc = $r ? $anyLang($r->description) : false;
        $cond = $r ? ($txt($r->venue_conditions) || (bool) $r->has_historical_monument_tax) : false;
        $place = $r ? filled($r->google_place_id) : false;
        $notes = $r ? filled($r->partner_notes) : false;
        $ident = $r ? ($anyLang($r->name) && filled($r->slug)) : false;

        $sections = [
            ['id' => 's-id', 'label' => 'Identitate', 'done' => $ident],
            ['id' => 's-img', 'label' => 'Imagini', 'done' => $mainImg],
            ['id' => 's-loc', 'label' => 'Locație', 'done' => $address],
            ['id' => 's-cap', 'label' => 'Capacitate', 'done' => $capacity],
            ['id' => 's-contact', 'label' => 'Contact și program', 'done' => $contact],
            ['id' => 's-fac', 'label' => 'Facilități', 'done' => $facilities],
            ['id' => 's-desc', 'label' => 'Descriere', 'done' => $desc],
            ['id' => 's-cond', 'label' => 'Condiții și taxe', 'done' => $cond],
            ['id' => 's-rev', 'label' => 'Recenzii Google', 'done' => $place],
            ['id' => 's-notes', 'label' => 'Note interne', 'done' => $notes],
        ];

        $todo = [];
        $add = function (bool $done, string $id, string $label) use (&$todo) {
            if (! $done) {
                $todo[] = ['id' => $id, 'label' => $label];
            }
        };
        $add($mainImg, 's-img', 'Imagine principală');
        $add($portrait, 's-img', 'Imagine portret (mobil)');
        $add($gallery, 's-img', 'Galerie foto');
        $add($address, 's-loc', 'Adresă și oraș');
        $add($coords, 's-loc', 'Coordonate');
        $add($capacity, 's-cap', 'Capacitate');
        $add($contact, 's-contact', 'Date de contact');
        $add($schedule, 's-contact', 'Program');
        $add($facilities, 's-fac', 'Facilități');
        $add($desc, 's-desc', 'Descriere');
        $add($place, 's-rev', 'Recenzii Google (Place ID)');

        return ['sections' => $sections, 'todo' => $todo];
    }

    /**
     * Adresa publică a paginii locației (https://{domeniu marketplace}/locatie/{slug}),
     * aceeași formă ca la MarketplaceVanityUrl; null dacă nu se poate deduce.
     */
    public static function publicUrl(?Venue $record): ?string
    {
        $marketplace = static::getMarketplaceClient();
        if (! $marketplace || blank($record?->slug)) {
            return null;
        }
        $domain = $marketplace->domain ?? $marketplace->primary_domain ?? null;
        if (! $domain) {
            return null;
        }
        $domain = preg_replace('#^https?://#', '', rtrim($domain, '/'));
        $protocol = str_contains($domain, 'localhost') ? 'http' : 'https';

        return "{$protocol}://{$domain}/locatie/{$record->slug}";
    }
    public static function table(Table $table): Table
    {
        $marketplace = static::getMarketplaceClient();
        $lang = $marketplace->language ?? $marketplace->locale ?? 'ro';

        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label(__('Imagine'))
                    ->circular()
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?name=V&color=7F9CF5&background=EBF4FF')
                    ->toggleable(),
                Tables\Columns\TextColumn::make("name.{$lang}")
                    ->label(__('Nume'))
                    ->searchable(query: function (\Illuminate\Database\Eloquent\Builder $query, string $search) use ($lang): void {
                        $term = '%' . mb_strtolower($search) . '%';
                        if (DB::getDriverName() === 'pgsql') {
                            $query->whereRaw("unaccent(LOWER(name::jsonb->>'{$lang}')) LIKE unaccent(?)", [$term])
                                ->orWhereRaw("unaccent(LOWER(city)) LIKE unaccent(?)", [$term]);
                        } else {
                            $query->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(`name`, '$.{$lang}'))) LIKE ?", [$term])
                                ->orWhereRaw("LOWER(`city`) LIKE ?", [$term]);
                        }
                    })
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('city')
                    ->label(__('Oraș'))
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('capacity_total')
                    ->label(__('Capacitate'))
                    ->sortable()
                    ->numeric()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('venueCategories.name')
                    ->label(__('Categorii'))
                    ->badge()
                    ->separator(',')
                    ->getStateUsing(fn ($record) => $record->venueCategories->map(fn ($c) => $c->icon . ' ' . ($c->getTranslation('name', 'ro') ?? $c->getTranslation('name', 'en')))->join(', '))
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_partner')
                    ->label(__('Partener'))
                    ->boolean()
                    ->toggleable(),
                // Owner = venues.tenant_id → tenants.owner_id set (see "Proprietar locație" on edit)
                Tables\Columns\IconColumn::make('has_owner')
                    ->label(__('Owner'))
                    ->state(fn ($record) => (bool) $record->tenant?->owner_id)
                    ->boolean()
                    ->tooltip(fn ($record) => $record->tenant?->owner
                        ? $record->tenant->owner->name . ' · ' . $record->tenant->owner->email
                        : null)
                    ->toggleable(),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->with('tenant.owner'))
            ->filters([
                Tables\Filters\TernaryFilter::make('is_partner')
                    ->label(__('Partener'))
                    ->placeholder(__('Toate'))
                    ->trueLabel(__('Doar partenere'))
                    ->falseLabel(__('Doar ne-partenere')),
                Tables\Filters\SelectFilter::make('venueCategories')
                    ->label(__('Categorie'))
                    ->relationship('venueCategories', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->icon . ' ' . ($record->getTranslation('name', 'ro') ?? $record->getTranslation('name', 'en')))
                    ->preload()
                    ->multiple(),
            ], layout: \Filament\Tables\Enums\FiltersLayout::Dropdown)
            ->filtersTriggerAction(fn (\Filament\Actions\Action $action) => $action->label(__('Filtre'))->icon('heroicon-o-funnel')->button())
            ->actions([])
            ->bulkActions([
                \Filament\Actions\BulkAction::make('make_partner')
                    ->label(__('Setează ca partener'))
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(__('Setează locațiile selectate ca partener'))
                    ->modalDescription(__('Toate locațiile selectate vor fi marcate ca partenere.'))
                    ->deselectRecordsAfterCompletion()
                    ->action(function (\Illuminate\Support\Collection $records) use ($marketplace) {
                        foreach ($records as $venue) {
                            $venue->forceFill(['is_partner' => true])->save();
                            if ($marketplace) {
                                $venue->marketplaceClients()->syncWithoutDetaching([
                                    $marketplace->id => ['is_partner' => true],
                                ]);
                            }
                        }

                        Notification::make()
                            ->title($records->count() . ' locații setate ca partener')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVenues::route('/'),
            'create' => Pages\CreateVenue::route('/create'),
            'edit' => Pages\EditVenue::route('/{record}/edit'),
        ];
    }
}

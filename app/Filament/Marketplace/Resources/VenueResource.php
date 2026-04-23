<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\VenueResource\Pages;
use App\Filament\Marketplace\Resources\EventResource;
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

            SC\Grid::make(4)->schema([
                SC\Group::make()->columnSpan(3)->schema([
                    // ============================================================
                    // SEARCH EXISTING VENUES (only on create page)
                    // ============================================================
                    SC\Section::make('Caută locații existente')
                        ->description('Caută în toate locațiile din sistem. Dacă găsești locația dorită, o poți adăuga ca partener în loc să creezi una nouă.')
                        ->icon('heroicon-o-magnifying-glass')
                        ->extraAttributes(['class' => 'bg-gradient-to-r from-emerald-500/10 to-emerald-600/5 border-emerald-500/30', 'style' => 'overflow: visible;'])
                        ->visible(fn ($operation) => $operation === 'create')
                        ->columnSpanFull()
                        ->schema([
                            Forms\Components\Select::make('search_existing_venue')
                                ->label('Caută o locație existentă')
                                ->placeholder('Scrie numele sau orașul pentru a căuta...')
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
                                    ->label('Adaugă ca partener')
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
                                    ->modalHeading('Adaugă locație ca partener')
                                    ->modalDescription('Această locație va fi adăugată în lista ta de locații partenere. Vei putea să o folosești pentru evenimentele tale.')
                                    ->action(function (SGet $get) use ($marketplace) {
                                        $venueId = $get('search_existing_venue');
                                        $venue = Venue::find($venueId);

                                        if (!$venue) {
                                            Notification::make()
                                                ->title('Eroare')
                                                ->body('Locația nu a fost găsită.')
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
                                            ->title('Locație adăugată')
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
                    
                    SC\Grid::make(5)->schema([
                        // NAME & SLUG - EN/RO
                        SC\Section::make('Venue Identity')
                            ->icon('heroicon-o-identification')
                            ->columnSpan(2)
                            ->schema([
                                SC\Tabs::make('Name Translations')
                                    ->tabs([
                                        SC\Tabs\Tab::make('Română')
                                            ->schema([
                                                Forms\Components\TextInput::make('name.ro')
                                                    ->label('Nume locație (RO)')
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
                                                    ->label('Venue name (EN)')
                                                    ->maxLength(255),
                                            ]),
                                    ])->columnSpanFull(),

                                Forms\Components\TextInput::make('slug')
                                    ->label('Slug')
                                    ->required()
                                    ->maxLength(190)
                                    ->unique(ignoreRecord: true)
                                    ->rule('alpha_dash')
                                    ->placeholder('auto-generated-from-name'),
                            ]),
                        // IMAGE & GALLERY
                        SC\Section::make('Media')
                            ->icon('heroicon-o-photo')
                            ->columnSpan(3)
                            ->schema([
                                Forms\Components\FileUpload::make('image_url')
                                    ->label('Main image')
                                    ->image()
                                    ->imagePreviewHeight('200')
                                    ->disk('public')
                                    ->directory('venues')
                                    ->visibility('public')
                                    ->afterStateUpdated(fn ($livewire) => $livewire->skipRender()),
                                Forms\Components\FileUpload::make('meta.portrait')
                                    ->label('Portret (mobile hero)')
                                    ->helperText('Imagine verticală afișată pe mobile în hero-ul paginii locației')
                                    ->image()
                                    ->imagePreviewHeight('200')
                                    ->disk('public')
                                    ->directory('venues/portraits')
                                    ->visibility('public')
                                    ->afterStateUpdated(fn ($livewire) => $livewire->skipRender()),
                                Forms\Components\FileUpload::make('gallery')
                                    ->label('Gallery')
                                    ->image()
                                    ->multiple()
                                    ->maxFiles(3)
                                    ->disk('public')
                                    ->directory('venues/gallery')
                                    ->visibility('public')
                                    ->reorderable()
                                    ->panelLayout('grid')
                                    ->imagePreviewHeight('80')
                                    ->afterStateUpdated(fn ($livewire) => $livewire->skipRender())
                                    ->columnSpanFull(),
                            ]),
                    ]),

                    // Row 1: Location (3 cols) + Capacity (2 cols) — same row totalling 5
                    SC\Grid::make(5)->schema([
                        SC\Section::make('Location')
                            ->icon('heroicon-o-map-pin')
                            ->columnSpan(3)
                            ->schema([
                                Forms\Components\TextInput::make('address')
                                    ->label('Address')
                                    ->maxLength(255)
                                    ->placeholder('Street and number'),
                                Forms\Components\TextInput::make('city')
                                    ->label('City')
                                    ->maxLength(120)
                                    ->placeholder('e.g. București'),
                                Forms\Components\TextInput::make('state')
                                    ->label('State/Region')
                                    ->maxLength(120)
                                    ->placeholder('e.g. Ilfov'),
                                Forms\Components\TextInput::make('country')
                                    ->label('Country')
                                    ->maxLength(120)
                                    ->placeholder('e.g. RO'),
                                Forms\Components\TextInput::make('lat')
                                    ->label('Latitude')
                                    ->numeric()
                                    ->step('0.0000001')
                                    ->placeholder('44.4268'),
                                Forms\Components\TextInput::make('lng')
                                    ->label('Longitude')
                                    ->numeric()
                                    ->step('0.0000001')
                                    ->placeholder('26.1025'),
                                Forms\Components\TextInput::make('google_maps_url')
                                    ->label('Google Maps Link')
                                    ->url()
                                    ->placeholder('https://maps.google.com/...')
                                    ->prefixIcon('heroicon-o-map')
                                    ->columnSpanFull(),
                            ])->columns(2),
                        SC\Section::make('Capacity')
                            ->icon('heroicon-o-users')
                            ->columnSpan(2)
                            ->schema([
                                Forms\Components\TextInput::make('capacity_total')
                                    ->label('Total capacity')
                                    ->numeric()
                                    ->minValue(0)
                                    ->placeholder('e.g. 12000'),
                                Forms\Components\TextInput::make('capacity_standing')
                                    ->label('Standing')
                                    ->numeric()
                                    ->minValue(0)
                                    ->placeholder('e.g. 8000'),
                                Forms\Components\TextInput::make('capacity_seated')
                                    ->label('Seated')
                                    ->numeric()
                                    ->minValue(0)
                                    ->placeholder('e.g. 4000'),
                            ]),
                    ]),

                    // Row 2: Google Reviews full-width
                    SC\Section::make('Google Reviews')
                        ->icon('heroicon-o-star')
                        ->description('Rating mediu, număr total de recenzii și 3 recenzii de pe Google. Cachate local — nu se apelează API la fiecare vizualizare. Folosește butonul "Sincronizare recenzii" pentru a re-citi datele din Google.')
                        ->columnSpanFull()
                        ->schema([
                            Forms\Components\TextInput::make('google_place_id')
                                ->label('Google Place ID')
                                ->maxLength(255)
                                ->placeholder('ChIJ...')
                                ->helperText(new HtmlString(
                                    'Copiază Place ID-ul de la <a href="https://developers.google.com/maps/documentation/places/web-service/place-id#find-id" target="_blank" rel="noopener" style="color:#2563eb;text-decoration:underline;">Google Place ID Finder</a>. Apoi salvează și folosește butonul de sincronizare.'
                                ))
                                ->columnSpanFull(),
                            Forms\Components\Placeholder::make('google_reviews_preview')
                                ->label('Stare cache')
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
                                    ->label('Sincronizare recenzii Google')
                                    ->icon('heroicon-o-arrow-path')
                                    ->color('primary')
                                    ->requiresConfirmation()
                                    ->modalDescription('Se va face un apel către Google Places API pentru a aduce ratingul, numărul de recenzii și primele recenzii disponibile. Continui?')
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
                                                ->title('Eroare la sincronizare')
                                                ->body($e->getMessage())
                                                ->danger()
                                                ->persistent()
                                                ->send();
                                        }
                                    }),
                            ])->columnSpanFull(),
                        ])->columns(1),

                    // CONTACT & LINKS
                    SC\Section::make('Contact & Links')
                        ->icon('heroicon-o-phone')
                        ->schema([
                            Forms\Components\TextInput::make('phone')
                                ->label('Phone 1')
                                ->maxLength(64)
                                ->placeholder('+40 ...')
                                ->prefixIcon('heroicon-o-phone'),
                            Forms\Components\TextInput::make('phone2')
                                ->label('Phone 2')
                                ->maxLength(64)
                                ->placeholder('+40 ...')
                                ->prefixIcon('heroicon-o-phone'),
                            Forms\Components\TextInput::make('email')
                                ->label('Email 1')
                                ->email()
                                ->placeholder('contact@example.com')
                                ->prefixIcon('heroicon-o-envelope'),
                            Forms\Components\TextInput::make('email2')
                                ->label('Email 2')
                                ->email()
                                ->placeholder('reservations@example.com')
                                ->prefixIcon('heroicon-o-envelope'),
                            Forms\Components\TextInput::make('website_url')
                                ->label('Website')
                                ->url()
                                ->placeholder('https://...')
                                ->prefixIcon('heroicon-o-globe-alt'),
                            Forms\Components\TextInput::make('facebook_url')
                                ->label('Facebook')
                                ->url()
                                ->placeholder('https://facebook.com/...')
                                ->prefixIcon('heroicon-o-link'),
                            Forms\Components\TextInput::make('instagram_url')
                                ->label('Instagram')
                                ->url()
                                ->placeholder('https://instagram.com/...')
                                ->prefixIcon('heroicon-o-link'),
                            Forms\Components\TextInput::make('tiktok_url')
                                ->label('TikTok')
                                ->url()
                                ->placeholder('https://tiktok.com/@...')
                                ->prefixIcon('heroicon-o-link'),
                        ])->columns(2),

                    // FACILITIES
                    SC\Section::make('Facilități')
                        ->description('Selectează facilitățile disponibile la această locație')
                        ->icon('heroicon-o-wrench-screwdriver')
                        ->collapsible()
                        ->collapsed()
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

                    // DESCRIPTION - EN/RO
                    SC\Section::make('Description')
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            SC\Tabs::make('Description Translations')
                                ->tabs([
                                    SC\Tabs\Tab::make('Română')
                                        ->schema([
                                            Forms\Components\RichEditor::make('description.ro')
                                                ->label('Descriere (RO)')
                                                ->columnSpanFull(),
                                        ]),
                                    SC\Tabs\Tab::make('English')
                                        ->schema([
                                            Forms\Components\RichEditor::make('description.en')
                                                ->label('Description (EN)')
                                                ->columnSpanFull(),
                                        ]),
                                ])->columnSpanFull(),
                        ]),

                    // CONDITIONS & TAXES
                    SC\Section::make('Condiții & Taxe')
                        ->description('Condiții specifice locației și taxe aplicabile')
                        ->icon('heroicon-o-document-text')
                        ->collapsible()
                        ->schema([
                            Forms\Components\RichEditor::make('venue_conditions')
                                ->label('Condiții locație')
                                ->helperText('Condiții specifice care se vor adăuga automat la termenii biletelor evenimentelor din acest venue.')
                                ->columnSpanFull(),

                            Forms\Components\Toggle::make('has_historical_monument_tax')
                                ->label('Taxa de Monument Istoric (2%)')
                                ->helperText('Dacă este activată, evenimentele din acest venue vor avea automat aplicată taxa de 2% pentru monument istoric.')
                                ->default(false),
                        ]),

                    // PARTNER NOTES (internal)
                    SC\Section::make('Note interne')
                        ->description('Note interne despre această locație (nu sunt vizibile public)')
                        ->icon('heroicon-o-lock-closed')
                        ->collapsible()
                        ->collapsed()
                        ->schema([
                            Forms\Components\Textarea::make('partner_notes')
                                ->label('Note')
                                ->placeholder('Note despre parteneriat, contracte, etc.')
                                ->rows(4)
                                ->columnSpanFull(),
                        ]),
                ]),

                SC\Group::make()->columnSpan(1)->schema([
                    // STATUS FLAGS
                    SC\Section::make('Status')
                        ->icon('heroicon-o-eye')
                        ->compact()
                        ->schema([
                            Forms\Components\Toggle::make('is_featured')
                                ->label('Locație promovată')
                                ->helperText('Va apărea în secțiunea promovată')
                                ->default(false),
                        ]),

                    // VENUE CATEGORIES
                    SC\Section::make('Categorii')
                        ->icon('heroicon-o-tag')
                        ->compact()
                        ->schema([
                            Forms\Components\Select::make('venueCategories')
                                ->label('Categorii locație')
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
                                        ->label('Nume categorie (RO)')
                                        ->required(),
                                    Forms\Components\TextInput::make('name.en')
                                        ->label('Category name (EN)'),
                                    Forms\Components\TextInput::make('icon')
                                        ->label('Icon (emoji)')
                                        ->placeholder('🎭'),
                                    Forms\Components\ColorPicker::make('color')
                                        ->label('Culoare'),
                                ])
                                ->createOptionUsing(function (array $data) {
                                    $data['marketplace_client_id'] = static::getMarketplaceClient()?->id;
                                    return MarketplaceVenueCategory::create($data)->id;
                                })
                                ->columnSpanFull(),
                        ]),

                    // SCHEDULE
                    SC\Section::make('Program')
                        ->icon('heroicon-o-clock')
                        ->compact()
                        ->schema([
                            Forms\Components\Textarea::make('schedule')
                                ->label('Program')
                                ->placeholder("Luni - Vineri: 10:00 - 22:00\nSâmbătă - Duminică: 12:00 - 24:00")
                                ->rows(5)
                                ->columnSpanFull(),
                        ]),

                    // YouTube URL
                    Forms\Components\TextInput::make('video_url')
                        ->label('YouTube URL')
                        ->url()
                        ->placeholder('https://www.youtube.com/watch?v=...')
                        ->prefixIcon('heroicon-o-play')
                        ->helperText('Link YouTube către un video de prezentare al locației (opțional).')
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


                    // Statistici (doar pe Edit)
                    SC\Section::make('Statistici')
                        ->icon('heroicon-o-chart-bar')
                        ->compact()
                        ->collapsed()
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

                    // Quick Actions (doar pe Edit)
                    SC\Section::make('Acțiuni rapide')
                        ->icon('heroicon-o-bolt')
                        ->compact()
                        ->visible(fn ($operation) => $operation === 'edit')
                        ->schema([
                            SC\Actions::make([
                                Action::make('view_events')
                                    ->label('Vezi evenimente')
                                    ->icon('heroicon-o-calendar')
                                    ->color('gray')
                                    ->url(fn (?Venue $record) => $record ? EventResource::getUrl('index', ['tableFilters[venue_id][value]' => $record->id]) : null),
                                Action::make('create_event')
                                    ->label('Eveniment nou')
                                    ->icon('heroicon-o-plus')
                                    ->color('primary')
                                    ->url(fn (?Venue $record) => $record ? EventResource::getUrl('create', ['venue_id' => $record->id]) : null),
                            ]),
                        ]),

                    // Meta Info (doar pe Edit)
                    SC\Section::make('Informații')
                        ->icon('heroicon-o-information-circle')
                        ->compact()
                        ->collapsed()
                        ->visible(fn ($operation) => $operation === 'edit')
                        ->schema([
                            Forms\Components\Placeholder::make('meta_info')
                                ->hiddenLabel()
                                ->content(function (?Venue $record) {
                                    if (!$record) return '';

                                    $createdAt = $record->created_at?->format('d.m.Y H:i') ?? '-';
                                    $updatedAt = $record->updated_at?->format('d.m.Y H:i') ?? '-';
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
                ]),

            ]),
        
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        $marketplace = static::getMarketplaceClient();
        $lang = $marketplace->language ?? $marketplace->locale ?? 'ro';

        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('Imagine')
                    ->circular()
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?name=V&color=7F9CF5&background=EBF4FF')
                    ->toggleable(),
                Tables\Columns\TextColumn::make("name.{$lang}")
                    ->label('Nume')
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
                    ->label('Oraș')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('capacity_total')
                    ->label('Capacitate')
                    ->sortable()
                    ->numeric()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('venueCategories.name')
                    ->label('Categorii')
                    ->badge()
                    ->separator(',')
                    ->getStateUsing(fn ($record) => $record->venueCategories->map(fn ($c) => $c->icon . ' ' . ($c->getTranslation('name', 'ro') ?? $c->getTranslation('name', 'en')))->join(', '))
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_partner')
                    ->label('Partener')
                    ->boolean()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_partner')
                    ->label('Doar parteneri'),
                Tables\Filters\SelectFilter::make('venueCategories')
                    ->label('Categorie')
                    ->relationship('venueCategories', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->icon . ' ' . ($record->getTranslation('name', 'ro') ?? $record->getTranslation('name', 'en')))
                    ->preload()
                    ->multiple(),
            ])
            ->actions([])
            ->bulkActions([]);
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

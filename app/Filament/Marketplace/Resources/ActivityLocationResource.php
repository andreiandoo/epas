<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\ActivityLocationResource\Pages;
use App\Models\ActivityLocation;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceCity;
use App\Models\MarketplaceOrganizer;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Locations of the activities module (bilete.online): the operator's place,
 * e.g. "Rezervația naturală Sfânta Ana", with its seasons, closed days,
 * display categories and accommodation info. Products point at it.
 * Gated by the activities-module microservice, like ActivityResource.
 */
class ActivityLocationResource extends Resource
{
    use \App\Filament\Marketplace\Concerns\TranslatesResourceLabels;

    use HasMarketplaceContext;

    protected static ?string $model = ActivityLocation::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'Locații';

    protected static ?string $modelLabel = 'Locație';

    protected static ?string $pluralModelLabel = 'Locații';

    protected static ?int $navigationSort = 4;

    protected static ?string $maxContentWidth = 'full';

    /**
     * The same keys the operator sees in their own editor (OrganizerCatalog::FACILITIES, labels in the site's
     * includes/v2/am-labels.php). An operator can also write their own, stored as "custom:<label>" — those have no
     * checkbox here, are listed read-only in the form and are kept on save (see EditActivityLocation).
     */
    public const FACILITIES = [
        'parking' => 'Parcare', 'free_parking' => 'Parcare gratuită', 'bus_parking' => 'Parcare autocare',
        'bike_parking' => 'Parcare biciclete', 'ev_charging' => 'Încărcare mașini electrice',
        'accessible' => 'Acces persoane cu dizabilități', 'stroller' => 'Acces cărucior de copil',
        'card' => 'Plată cu cardul', 'atm' => 'Bancomat', 'shop' => 'Magazin', 'rentals' => 'Închirieri',
        'guide' => 'Ghid', 'audio_guide' => 'Audioghid', 'lockers' => 'Seif / dulapuri',
        'luggage' => 'Depozit bagaje', 'wifi' => 'Wi-Fi', 'first_aid' => 'Prim ajutor',
        'restaurant' => 'Restaurant', 'bar' => 'Bar / cafenea', 'terrace' => 'Terasă', 'picnic' => 'Zonă de picnic',
        'bbq' => 'Grătare', 'gazebo' => 'Foișoare', 'drinking_water' => 'Apă potabilă',
        'toilets' => 'Toalete', 'changing_rooms' => 'Vestiare', 'showers' => 'Dușuri',
        'baby_change' => 'Masă de înfășat', 'playground' => 'Loc de joacă', 'smoking_area' => 'Zonă de fumat',
        'pets' => 'Animale acceptate',
        'beach' => 'Plajă', 'pool' => 'Piscină', 'sauna' => 'Saună', 'boat_ramp' => 'Rampă de barcă',
        'fishing' => 'Pescuit',
        'lodging' => 'Cazare', 'camping' => 'Camping',
    ];

    public const LODGING_FACILITIES = [
        'wifi' => 'Wi-Fi', 'parking' => 'Parcare', 'breakfast' => 'Mic dejun', 'restaurant' => 'Restaurant', 'kitchen' => 'Bucătărie',
        'ac' => 'Aer condiționat', 'heating' => 'Încălzire', 'private_bathroom' => 'Baie proprie', 'tv' => 'TV', 'pets' => 'Animale acceptate',
        'pool' => 'Piscină', 'spa' => 'Spa / saună', 'terrace' => 'Terasă', 'bbq' => 'Grătar', 'playground' => 'Loc de joacă',
        'accessible' => 'Acces persoane cu dizabilități', 'fridge' => 'Frigider', 'kettle' => 'Fierbător', 'safe' => 'Seif',
        'towels' => 'Prosoape', 'washing_machine' => 'Mașină de spălat', 'balcony' => 'Balcon', 'garden' => 'Grădină',
        'sauna' => 'Saună', 'fireplace' => 'Șemineu', 'crib' => 'Pătuț copil', 'ev_charging' => 'Încărcare mașini electrice',
        'non_smoking' => 'Nefumători',
    ];

    /** The operator's own facilities ("custom:<label>") of a record, as labels. */
    public static function customFacilities($record, string $key = 'facilities'): array
    {
        $values = $key === 'facilities'
            ? (array) ($record->facilities ?? [])
            : (array) (($record->lodging ?? [])['facilities'] ?? []);

        return array_values(array_map(
            fn ($f) => trim(substr((string) $f, strlen(\App\Services\Activities\OrganizerCatalog::CUSTOM_FACILITY))),
            array_filter($values, fn ($f) => is_string($f) && str_starts_with($f, \App\Services\Activities\OrganizerCatalog::CUSTOM_FACILITY))
        ));
    }

    public static function canAccess(): bool
    {
        return static::marketplaceHasMicroservice('activities-module');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice('activities-module');
    }

    public static function getNavigationBadge(): ?string
    {
        if (!static::marketplaceHasMicroservice('activities-module')) {
            return null;
        }
        $pending = static::getEloquentQuery()->where('review_status', ActivityLocation::REVIEW_PENDING)->count();
        return $pending > 0 ? (string) $pending : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('marketplace_client_id', static::getMarketplaceClient()?->id);
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();
        $lang = $marketplace?->language ?? 'ro';
        $days = ['mon' => 'Luni', 'tue' => 'Marți', 'wed' => 'Miercuri', 'thu' => 'Joi', 'fri' => 'Vineri', 'sat' => 'Sâmbătă', 'sun' => 'Duminică'];
        $monthDay = '/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/';

        return $schema->schema([
            Forms\Components\Hidden::make('marketplace_client_id')->default($marketplace?->id),

            SC\Grid::make(4)->schema([
                SC\Group::make()->columnSpan(3)->schema([
                    SC\Tabs::make('LocationTabs')->persistTabInQueryString()->tabs([

                        SC\Tabs\Tab::make('Detalii')->icon('heroicon-o-document-text')->schema([
                            SC\Section::make(__('Identitate'))->schema([
                                Forms\Components\TextInput::make('name.ro')
                                    ->label(__('Nume'))
                                    ->required()->maxLength(190)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if ($state && !$get('slug')) {
                                            $set('slug', Str::slug($state));
                                        }
                                    }),
                                Forms\Components\TextInput::make('slug')
                                    ->label(__('Adresă pagină (/locatie/…)'))
                                    ->required()->maxLength(191)
                                    ->regex('/^[a-z0-9]+(-[a-z0-9]+)*$/')
                                    ->rules(fn ($record) => [Rule::unique('activity_locations', 'slug')
                                        ->where('marketplace_client_id', $marketplace?->id)
                                        ->ignore($record?->id)]),
                                Forms\Components\TextInput::make('subtitle.ro')->label(__('Subtitlu'))->maxLength(190),
                                Forms\Components\Textarea::make('short_description.ro')->label(__('Descriere scurtă'))->rows(2)->maxLength(280),
                                Forms\Components\RichEditor::make('description.ro')->label(__('Descriere'))->columnSpanFull(),
                                Forms\Components\RichEditor::make('rules.ro')->label(__('Reguli pentru vizitatori'))->columnSpanFull(),
                            ])->columns(2),
                            SC\Section::make(__('Operator și taxonomie'))->schema([
                                Forms\Components\Select::make('marketplace_organizer_id')
                                    ->label(__('Operator (proprietar)'))
                                    ->options(fn () => MarketplaceOrganizer::where('marketplace_client_id', $marketplace?->id)->orderBy('name')->pluck('name', 'id')->toArray())
                                    ->searchable()
                                    ->helperText(__('Doar operatorul ales poate edita locația din contul lui.')),
                                Forms\Components\Select::make('marketplace_city_id')
                                    ->label(__('Oraș'))
                                    ->options(fn () => MarketplaceCity::where('marketplace_client_id', $marketplace?->id)->orderBy('sort_order')->get()
                                        ->mapWithKeys(fn ($c) => [$c->id => $c->name[$lang] ?? $c->name['ro'] ?? $c->slug])->toArray())
                                    ->searchable(),
                                Forms\Components\Select::make('marketplace_category_id')
                                    ->label(__('Categorie'))
                                    ->options(fn () => MarketplaceCategory::where('marketplace_client_id', $marketplace?->id)->orderBy('sort_order')->get()
                                        ->mapWithKeys(fn ($c) => [$c->id => $c->name[$lang] ?? $c->name['ro'] ?? $c->slug])->toArray())
                                    ->searchable(),
                            ])->columns(3),
                            SC\Section::make(__('Adresă, hartă, contact'))->schema([
                                Forms\Components\TextInput::make('address')->label(__('Adresă'))->maxLength(255)->columnSpan(2),
                                Forms\Components\TextInput::make('google_maps_url')->label(__('Link Google Maps'))->url()->maxLength(500)->columnSpan(2),
                                Forms\Components\TextInput::make('latitude')->label(__('Latitudine'))->numeric()->minValue(-90)->maxValue(90),
                                Forms\Components\TextInput::make('longitude')->label(__('Longitudine'))->numeric()->minValue(-180)->maxValue(180),
                                Forms\Components\TextInput::make('phone')->label(__('Telefon'))->tel()->maxLength(40),
                                Forms\Components\TextInput::make('email')->label(__('E-mail'))->email()->maxLength(255),
                                Forms\Components\TextInput::make('website_url')->label(__('Site'))->url()->maxLength(500)->columnSpan(2),
                            ])->columns(4),
                            SC\Section::make(__('Poze'))->schema([
                                Forms\Components\FileUpload::make('cover_image_url')
                                    ->label(__('Poză principală'))->image()->disk('public')->directory('activity-locations/covers')->maxSize(10240),
                                Forms\Components\FileUpload::make('gallery')
                                    ->label(__('Galerie'))->image()->multiple()->reorderable()->maxFiles(20)->disk('public')->directory('activity-locations/gallery')->maxSize(10240),
                            ])->columns(2),
                            SC\Section::make(__('Facilități'))->schema([
                                Forms\Components\CheckboxList::make('facilities')->label(false)->options(self::FACILITIES)->columns(3),
                                Forms\Components\Placeholder::make('custom_facilities')
                                    ->label(__('Adăugate de operator'))
                                    ->content(fn ($record) => $record && self::customFacilities($record)
                                        ? implode(' · ', self::customFacilities($record))
                                        : '—')
                                    ->helperText(__('Le scrie operatorul din contul lui. Rămân salvate și dacă modifici bifele de mai sus.'))
                                    ->visible(fn ($record) => $record && self::customFacilities($record)),
                            ]),
                        ]),

                        SC\Tabs\Tab::make('Program')->icon('heroicon-o-clock')->schema([
                            SC\Section::make(__('Sezoane'))
                                ->description(__('Perioade care se repetă în fiecare an (pot trece peste Anul Nou, ex: 11-01 → 03-31). Zi fără ore = închis.'))
                                ->schema([
                                    Forms\Components\Repeater::make('seasons')->label(false)->schema(array_merge([
                                        Forms\Components\TextInput::make('name')->label(__('Nume'))->required()->maxLength(60)->placeholder(__('Vara')),
                                        Forms\Components\TextInput::make('start')->label(__('De la (LL-ZZ)'))->required()->regex($monthDay)->placeholder('04-01'),
                                        Forms\Components\TextInput::make('end')->label(__('Până la (LL-ZZ)'))->required()->regex($monthDay)->placeholder('10-31'),
                                        Forms\Components\TimePicker::make('last_entry')->label(__('Ultima intrare'))->seconds(false),
                                    ], array_merge(...array_map(fn ($key, $label) => [
                                        Forms\Components\TimePicker::make("schedule.$key.open")->label("$label de la")->seconds(false),
                                        Forms\Components\TimePicker::make("schedule.$key.close")->label(__('până la'))->seconds(false),
                                    ], array_keys($days), $days))))
                                        ->columns(4)
                                        ->collapsible()
                                        ->itemLabel(fn (array $state) => trim(($state['name'] ?? '') . ' · ' . ($state['start'] ?? '') . ' → ' . ($state['end'] ?? ''), ' ·'))
                                        ->addActionLabel(__('Adaugă sezon')),
                                ]),
                            SC\Section::make(__('Zile închise și rezervări'))->schema([
                                Forms\Components\TagsInput::make('closed_dates')
                                    ->label(__('Zile închise (AAAA-LL-ZZ)'))
                                    ->placeholder('2026-12-25')
                                    ->nestedRecursiveRules(['regex:/^\d{4}-\d{2}-\d{2}$/']),
                                Forms\Components\TextInput::make('max_advance_days')
                                    ->label(__('Cu câte zile înainte se poate rezerva'))
                                    ->numeric()->default(90)->minValue(1)->maxValue(365),
                            ])->columns(2),
                            SC\Section::make(__('Categorii de afișare'))
                                ->description(__('Grupează produsele pe pagina locației (ex: Individuale, Familie, Grup, Pachete, Servicii).'))
                                ->schema([
                                    Forms\Components\Repeater::make('display_categories')->label(false)->schema([
                                        Forms\Components\TextInput::make('name')->label(__('Nume'))->required()->maxLength(60)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                if ($state && !$get('id')) {
                                                    $set('id', Str::slug($state));
                                                }
                                            }),
                                        Forms\Components\TextInput::make('id')->label(__('Cod'))->required()->maxLength(64)->regex('/^[a-z0-9-]+$/'),
                                        Forms\Components\TextInput::make('sort_order')->label(__('Ordine'))->numeric()->default(0),
                                    ])->columns(3)->addActionLabel(__('Adaugă categorie')),
                                ]),
                        ]),

                        SC\Tabs\Tab::make('Cazare')->icon('heroicon-o-home-modern')->schema([
                            SC\Section::make(__('Cazare la locație'))
                                ->description(__('Doar informații: rezervarea se face pe platforma pe care o folosește deja operatorul (Booking, Airbnb, site propriu).'))
                                ->schema([
                                    Forms\Components\Toggle::make('lodging.enabled')->label(__('Locația oferă cazare'))->live()->columnSpanFull(),
                                    SC\Group::make()->visible(fn (Get $get) => (bool) $get('lodging.enabled'))->columnSpanFull()->schema([
                                        SC\Grid::make(4)->schema([
                                            Forms\Components\Select::make('lodging.type')->label(__('Tip'))->options([
                                                'pensiune' => __('Pensiune'), 'hotel' => __('Hotel'), 'cabana' => __('Cabană'), 'vila' => __('Vilă'),
                                                'apartamente' => __('Apartamente'), 'camping' => __('Camping'), 'glamping' => __('Glamping'), 'altele' => __('Altele'),
                                            ]),
                                            Forms\Components\TextInput::make('lodging.classification')->label(__('Clasificare'))->placeholder(__('3 stele / 3 flori'))->maxLength(40),
                                            Forms\Components\TimePicker::make('lodging.check_in')->label(__('Check-in de la'))->seconds(false),
                                            Forms\Components\TimePicker::make('lodging.check_out')->label(__('Check-out până la'))->seconds(false),
                                            Forms\Components\TextInput::make('lodging.price_from')->label(__('De la (lei / noapte)'))->numeric()->minValue(0),
                                            Forms\Components\TextInput::make('lodging.phone')->label(__('Telefon rezervări'))->tel()->maxLength(40),
                                            Forms\Components\TextInput::make('lodging.email')->label(__('E-mail rezervări'))->email()->maxLength(255)->columnSpan(2),
                                        ]),
                                        Forms\Components\RichEditor::make('lodging.description.ro')->label(__('Descriere')),
                                        Forms\Components\RichEditor::make('lodging.policies.ro')->label(__('Politici (anulare, copii, animale)')),
                                        Forms\Components\CheckboxList::make('lodging.facilities')->label(__('Facilități'))->options(self::LODGING_FACILITIES)->columns(4),
                                        Forms\Components\Placeholder::make('custom_lodging_facilities')
                                            ->label(__('Adăugate de operator'))
                                            ->content(fn ($record) => $record && self::customFacilities($record, 'lodging')
                                                ? implode(' · ', self::customFacilities($record, 'lodging'))
                                                : '—')
                                            ->visible(fn ($record) => $record && self::customFacilities($record, 'lodging')),
                                        Forms\Components\Repeater::make('lodging.rooms')->label(__('Tipuri de camere'))->schema([
                                            Forms\Components\TextInput::make('name.ro')->label(__('Nume'))->required()->maxLength(80)->placeholder(__('Cameră dublă')),
                                            Forms\Components\TextInput::make('capacity')->label(__('Persoane'))->numeric()->minValue(1)->maxValue(50),
                                            Forms\Components\TextInput::make('beds')->label(__('Paturi'))->maxLength(80)->placeholder(__('1 pat dublu')),
                                            Forms\Components\TextInput::make('count')->label(__('Câte camere'))->numeric()->minValue(1),
                                            Forms\Components\TextInput::make('price_from')->label(__('De la (lei / noapte)'))->numeric()->minValue(0),
                                            Forms\Components\Textarea::make('description.ro')->label(__('Descriere'))->rows(2)->columnSpan(3),
                                            Forms\Components\CheckboxList::make('facilities')->label(__('Facilități cameră'))->options(self::LODGING_FACILITIES)->columns(4)->columnSpanFull(),
                                            Forms\Components\FileUpload::make('images')->label(__('Poze'))->image()->multiple()->maxFiles(8)->disk('public')->directory('activity-locations/rooms')->maxSize(10240)->columnSpanFull(),
                                        ])->columns(4)->collapsible()->addActionLabel(__('Adaugă tip de cameră')),
                                        Forms\Components\Repeater::make('lodging.links')->label(__('Unde se rezervă'))->schema([
                                            Forms\Components\Select::make('platform')->label(__('Platformă'))->options([
                                                'booking' => __('Booking.com'), 'airbnb' => __('Airbnb'), 'travelminit' => __('Travelminit'), 'website' => __('Site propriu'), 'other' => __('Altă platformă'),
                                            ])->required(),
                                            Forms\Components\TextInput::make('url')->label(__('Link'))->url()->required()->maxLength(1000)->columnSpan(2),
                                            Forms\Components\TextInput::make('label')->label(__('Text buton (opțional)'))->maxLength(60),
                                        ])->columns(4)->addActionLabel(__('Adaugă link')),
                                        Forms\Components\FileUpload::make('lodging.gallery')->label(__('Poze cazare'))->image()->multiple()->reorderable()->maxFiles(20)->disk('public')->directory('activity-locations/lodging')->maxSize(10240),
                                    ]),
                                ]),
                        ]),

                        SC\Tabs\Tab::make('SEO & FAQ')->icon('heroicon-o-magnifying-glass')->schema([
                            SC\Section::make(__('SEO'))->schema([
                                Forms\Components\TextInput::make('seo.title_ro')->label(__('Titlu SEO'))->maxLength(70),
                                Forms\Components\Textarea::make('seo.description_ro')->label(__('Descriere SEO'))->rows(2)->maxLength(160),
                            ]),
                            SC\Section::make(__('Întrebări frecvente'))->schema([
                                Forms\Components\Repeater::make('faqs')->label(false)->schema([
                                    Forms\Components\TextInput::make('q')->label(__('Întrebare'))->required()->maxLength(200),
                                    Forms\Components\Textarea::make('a')->label(__('Răspuns'))->required()->rows(3),
                                ])->collapsible()->addActionLabel(__('Adaugă întrebare')),
                            ]),
                        ]),
                    ]),
                ]),

                SC\Group::make()->columnSpan(1)->schema([
                    SC\Section::make(__('Publicare'))->schema([
                        Forms\Components\Select::make('review_status')
                            ->label(__('Stare'))
                            ->options(['draft' => __('Ciornă'), 'pending' => __('Trimisă spre aprobare'), 'approved' => __('Aprobată'), 'rejected' => __('Respinsă')])
                            ->default('approved')
                            ->required()
                            ->live(),
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label(__('Motivul respingerii (îl vede operatorul)'))
                            ->rows(3)
                            ->visible(fn (Get $get) => $get('review_status') === 'rejected'),
                        Forms\Components\Toggle::make('is_published')
                            ->label(__('Publicată pe site'))
                            ->helperText(__('Apare pe site doar aprobată și publicată.')),
                    ]),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Locație'))
                    ->formatStateUsing(fn ($state, $record) => $record->name['ro'] ?? $record->slug)
                    ->description(fn ($record) => '/locatie/' . $record->slug),
                Tables\Columns\TextColumn::make('organizer.name')->label(__('Operator'))->placeholder('—'),
                Tables\Columns\TextColumn::make('products_count')->label(__('Produse'))->counts('products'),
                Tables\Columns\TextColumn::make('review_status')->label(__('Stare'))->badge()
                    ->formatStateUsing(fn ($state) => ['draft' => 'Ciornă', 'pending' => 'De aprobat', 'approved' => 'Aprobată', 'rejected' => 'Respinsă'][$state] ?? $state)
                    ->color(fn ($state) => ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'][$state] ?? 'gray'),
                Tables\Columns\IconColumn::make('is_published')->label(__('Publicată'))->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->label(__('Modificată'))->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('review_status')->label(__('Stare'))
                    ->options(['draft' => __('Ciornă'), 'pending' => __('De aprobat'), 'approved' => __('Aprobată'), 'rejected' => __('Respinsă')]),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListActivityLocations::route('/'),
            'create' => Pages\CreateActivityLocation::route('/create'),
            'edit'   => Pages\EditActivityLocation::route('/{record}/edit'),
        ];
    }
}

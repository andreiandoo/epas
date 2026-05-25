<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\ActivityResource\Pages;
use App\Filament\Marketplace\Resources\ActivityResource\RelationManagers;
use App\Models\Activity;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceCity;
use App\Models\MarketplaceOrganizer;
use App\Models\Venue;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Filament admin resource for the Activities module.
 *
 * Gated by the `activities-module` microservice toggle — when the
 * marketplace doesn't have the microservice active, this resource is
 * hidden from the sidebar AND inaccessible by URL (canAccess() returns
 * false). No other marketplace can see Activities unless they explicitly
 * activate it.
 *
 * Form is intentionally simpler than EventResource (6 sections vs ~12):
 * Activities don't need artists, ticket groups, fiscal declaration, or
 * postpone/cancel flow.
 */
class ActivityResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = Activity::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rocket-launch';

    protected static ?string $navigationLabel = 'Activități';

    protected static ?string $modelLabel = 'Activitate';

    protected static ?string $pluralModelLabel = 'Activități';

    protected static ?int $navigationSort = 5;

    protected static ?string $maxContentWidth = 'full';

    /**
     * Gate the whole resource by the `activities-module` microservice.
     * Until a super-admin or marketplace admin flips it on, this resource
     * is invisible and unreachable.
     */
    public static function canAccess(): bool
    {
        return static::marketplaceHasMicroservice('activities-module');
    }

    /**
     * Sidebar counter — same UX as EventResource. Hidden when the
     * microservice is off (counter on a hidden item makes no sense).
     */
    public static function getNavigationBadge(): ?string
    {
        if (! static::marketplaceHasMicroservice('activities-module')) {
            return null;
        }
        $marketplace = static::getMarketplaceClient();
        if (! $marketplace) {
            return null;
        }
        return (string) static::getEloquentQuery()->count();
    }

    /**
     * Hide from sidebar when microservice is off (canAccess already gates
     * URL access — this just keeps the menu clean).
     */
    public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice('activities-module');
    }

    /**
     * Scope every query to the current marketplace (same pattern as every
     * other Marketplace resource).
     */
    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();

        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $marketplace?->id);
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();
        $lang = $marketplace?->language ?? 'ro';

        // Reused select option callbacks — keep the Detalii + Locație tabs lean.
        $organizerOptions = fn () => MarketplaceOrganizer::where('marketplace_client_id', $marketplace?->id)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();

        $venueOptions = fn () => Venue::where('marketplace_client_id', $marketplace?->id)
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn ($v) => [
                $v->id => ($v->getTranslation('name', 'ro') ?? $v->getTranslation('name', 'en') ?? 'Venue #'.$v->id)
                    . ($v->city ? ' — '.$v->city : ''),
            ])
            ->toArray();

        $cityOptions = fn () => MarketplaceCity::where('marketplace_client_id', $marketplace?->id)
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn ($c) => [$c->id => $c->name[$lang] ?? $c->name['en'] ?? $c->slug])
            ->toArray();

        $categoryOptions = fn () => MarketplaceCategory::where('marketplace_client_id', $marketplace?->id)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn ($c) => [$c->id => $c->name[$lang] ?? $c->name['en'] ?? $c->slug])
            ->toArray();

        $subcategoryOptions = function (\Filament\Schemas\Components\Utilities\Get $get) use ($marketplace, $lang) {
            $parentId = $get('marketplace_category_id');
            if (! $parentId) return [];
            return MarketplaceCategory::where('marketplace_client_id', $marketplace?->id)
                ->where('parent_id', $parentId)
                ->orderBy('sort_order')
                ->get()
                ->mapWithKeys(fn ($c) => [$c->id => $c->name[$lang] ?? $c->name['en'] ?? $c->slug])
                ->toArray();
        };

        return $schema->schema([
            Forms\Components\Hidden::make('marketplace_client_id')
                ->default($marketplace?->id),

            SC\Grid::make(4)->schema([

                // ============================================================
                // COLOANA STÂNGĂ (3/4) — TABS
                // ============================================================
                SC\Group::make()
                    ->columnSpan(3)
                    ->schema([
                        SC\Tabs::make('ActivityTabs')
                            ->persistTabInQueryString()
                            ->tabs([

                                // ====================================================
                                // TAB 1: DETALII (titlu, slug, descrieri, media)
                                // ====================================================
                                SC\Tabs\Tab::make('Detalii')
                                    ->key('detalii')
                                    ->icon('heroicon-o-document-text')
                                    ->schema([
                                        SC\Section::make('Conținut text')
                                            ->schema([
                                                SC\Tabs::make('Text Translations')
                                                    ->tabs([
                                                        SC\Tabs\Tab::make('Română')
                                                            ->schema([
                                                                Forms\Components\TextInput::make('title.ro')
                                                                    ->label('Titlu (RO)')
                                                                    ->required()
                                                                    ->maxLength(190)
                                                                    ->live(onBlur: true)
                                                                    ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get) {
                                                                        if ($state && ! $get('slug')) {
                                                                            $set('slug', Str::slug($state));
                                                                        }
                                                                    }),
                                                                Forms\Components\TextInput::make('subtitle.ro')
                                                                    ->label('Subtitlu (RO)')
                                                                    ->maxLength(190),
                                                                Forms\Components\Textarea::make('short_description.ro')
                                                                    ->label('Descriere scurtă (RO)')
                                                                    ->rows(2)
                                                                    ->maxLength(280)
                                                                    ->helperText('Apare în carduri de listing și în meta description.'),
                                                                Forms\Components\RichEditor::make('description.ro')
                                                                    ->label('Descriere completă (RO)')
                                                                    ->toolbarButtons(['bold', 'italic', 'link', 'h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'undo', 'redo'])
                                                                    ->columnSpanFull(),
                                                            ]),
                                                        SC\Tabs\Tab::make('English')
                                                            ->schema([
                                                                Forms\Components\TextInput::make('title.en')
                                                                    ->label('Title (EN)')
                                                                    ->maxLength(190),
                                                                Forms\Components\TextInput::make('subtitle.en')
                                                                    ->label('Subtitle (EN)')
                                                                    ->maxLength(190),
                                                                Forms\Components\Textarea::make('short_description.en')
                                                                    ->label('Short description (EN)')
                                                                    ->rows(2)
                                                                    ->maxLength(280),
                                                                Forms\Components\RichEditor::make('description.en')
                                                                    ->label('Description (EN)')
                                                                    ->toolbarButtons(['bold', 'italic', 'link', 'h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'undo', 'redo'])
                                                                    ->columnSpanFull(),
                                                            ]),
                                                    ])
                                                    ->columnSpanFull(),

                                                Forms\Components\TextInput::make('slug')
                                                    ->label('Slug')
                                                    ->required()
                                                    ->maxLength(191)
                                                    ->rule('alpha_dash')
                                                    ->placeholder('auto-generate din titlu RO')
                                                    ->helperText('URL public: /activitate/{slug}')
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(1),

                                        SC\Section::make('Media')
                                            ->description('Imagini afișate pe pagina publică a activității.')
                                            ->schema([
                                                Forms\Components\FileUpload::make('cover_image_url')
                                                    ->label('Imagine de copertă (card listing)')
                                                    ->image()
                                                    ->disk('public')
                                                    ->directory('activities/covers')
                                                    ->visibility('public'),

                                                Forms\Components\FileUpload::make('hero_image_url')
                                                    ->label('Imagine hero (pagina activității)')
                                                    ->image()
                                                    ->disk('public')
                                                    ->directory('activities/heroes')
                                                    ->visibility('public'),

                                                Forms\Components\TagsInput::make('gallery')
                                                    ->label('Galerie (URL-uri imagini)')
                                                    ->placeholder('https://...')
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(2),
                                    ]),

                                // ====================================================
                                // TAB 2: LOCAȚIE & CATEGORII
                                // ====================================================
                                SC\Tabs\Tab::make('Locație')
                                    ->key('locatie')
                                    ->icon('heroicon-o-map-pin')
                                    ->schema([
                                        SC\Section::make('Organizator & venue')
                                            ->schema([
                                                Forms\Components\Select::make('marketplace_organizer_id')
                                                    ->label('Organizator (locație)')
                                                    ->options($organizerOptions)
                                                    ->searchable()
                                                    ->preload(),

                                                Forms\Components\Select::make('venue_id')
                                                    ->label('Locație fizică (venue)')
                                                    ->options($venueOptions)
                                                    ->searchable()
                                                    ->preload(),
                                            ])
                                            ->columns(2),

                                        SC\Section::make('Geo & taxonomie')
                                            ->schema([
                                                Forms\Components\Select::make('marketplace_city_id')
                                                    ->label('Oraș')
                                                    ->options($cityOptions)
                                                    ->searchable()
                                                    ->preload(),

                                                Forms\Components\Select::make('marketplace_category_id')
                                                    ->label('Categorie principală')
                                                    ->options($categoryOptions)
                                                    ->searchable()
                                                    ->preload()
                                                    ->live()
                                                    ->afterStateUpdated(fn (\Filament\Schemas\Components\Utilities\Set $set) => $set('marketplace_subcategory_id', null)),

                                                Forms\Components\Select::make('marketplace_subcategory_id')
                                                    ->label('Subcategorie')
                                                    ->options($subcategoryOptions)
                                                    ->searchable(),

                                                Forms\Components\Textarea::make('meeting_point')
                                                    ->label('Punct de întâlnire')
                                                    ->rows(3)
                                                    ->placeholder('ex: Recepția mall-ului, etajul 2, lângă scara rulantă')
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(3),
                                    ]),

                                // ====================================================
                                // TAB 3: PROGRAM & SLOTURI
                                // ====================================================
                                SC\Tabs\Tab::make('Program')
                                    ->key('program')
                                    ->icon('heroicon-o-clock')
                                    ->schema([
                                        SC\Section::make('Slot-uri')
                                            ->description('Cum se generează intervalele rezervabile din programul săptămânal.')
                                            ->schema([
                                                Forms\Components\TextInput::make('duration_minutes')
                                                    ->label('Durată sesiune (min)')
                                                    ->numeric()
                                                    ->default(60)
                                                    ->minValue(5)
                                                    ->maxValue(1440)
                                                    ->required(),

                                                Forms\Components\TextInput::make('slot_interval_minutes')
                                                    ->label('Interval start slot-uri (min)')
                                                    ->numeric()
                                                    ->default(60)
                                                    ->minValue(5)
                                                    ->maxValue(1440)
                                                    ->required()
                                                    ->helperText('Ex: 60 = slot la fiecare oră.'),

                                                Forms\Components\TextInput::make('buffer_minutes')
                                                    ->label('Buffer între slot-uri (min)')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->minValue(0)
                                                    ->maxValue(120),

                                                Forms\Components\TextInput::make('capacity_per_slot')
                                                    ->label('Capacitate per slot')
                                                    ->numeric()
                                                    ->default(1)
                                                    ->minValue(1)
                                                    ->maxValue(1000)
                                                    ->required(),
                                            ])
                                            ->columns(4),

                                        SC\Section::make('Program săptămânal')
                                            ->description('Adaugă intervale de funcționare. Mai multe intervale pe aceeași zi sunt OK (ex: 10-14 + 17-22).')
                                            ->schema([
                                                Forms\Components\Repeater::make('schedules')
                                                    ->relationship()
                                                    ->label(false)
                                                    ->schema([
                                                        Forms\Components\Select::make('day_of_week')
                                                            ->label('Ziua')
                                                            ->options([
                                                                1 => 'Luni', 2 => 'Marți', 3 => 'Miercuri',
                                                                4 => 'Joi', 5 => 'Vineri', 6 => 'Sâmbătă', 7 => 'Duminică',
                                                            ])
                                                            ->required(),
                                                        Forms\Components\TimePicker::make('open_time')
                                                            ->label('Deschis')
                                                            ->seconds(false)
                                                            ->required(),
                                                        Forms\Components\TimePicker::make('close_time')
                                                            ->label('Închis')
                                                            ->seconds(false)
                                                            ->required(),
                                                        Forms\Components\Toggle::make('is_active')
                                                            ->label('Activ')
                                                            ->default(true)
                                                            ->inline(false),
                                                    ])
                                                    ->columns(4)
                                                    ->reorderable(false)
                                                    ->collapsible()
                                                    ->itemLabel(function (array $state): ?string {
                                                        $days = [1 => 'Lu', 2 => 'Ma', 3 => 'Mi', 4 => 'Jo', 5 => 'Vi', 6 => 'Sâ', 7 => 'Du'];
                                                        $d = $state['day_of_week'] ?? null;
                                                        $open = $state['open_time'] ?? '';
                                                        $close = $state['close_time'] ?? '';
                                                        return $d ? ($days[$d] ?? '?') . " · {$open} – {$close}" : null;
                                                    })
                                                    ->addActionLabel('Adaugă interval'),
                                            ])
                                            ->columns(1),

                                        SC\Section::make('Excepții (zile speciale)')
                                            ->description('Override pentru sărbători, închideri ad-hoc, ore speciale.')
                                            ->collapsed()
                                            ->schema([
                                                Forms\Components\Repeater::make('scheduleExceptions')
                                                    ->relationship()
                                                    ->label(false)
                                                    ->schema([
                                                        Forms\Components\DatePicker::make('exception_date')
                                                            ->label('Data')
                                                            ->required(),
                                                        Forms\Components\Toggle::make('is_closed')
                                                            ->label('Închis')
                                                            ->default(true)
                                                            ->live(),
                                                        Forms\Components\TimePicker::make('open_time')
                                                            ->label('Deschis (dacă nu e închis)')
                                                            ->seconds(false)
                                                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => ! $get('is_closed')),
                                                        Forms\Components\TimePicker::make('close_time')
                                                            ->label('Închis (dacă nu e închis)')
                                                            ->seconds(false)
                                                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => ! $get('is_closed')),
                                                        Forms\Components\TextInput::make('reason')
                                                            ->label('Motiv')
                                                            ->maxLength(190)
                                                            ->columnSpanFull(),
                                                    ])
                                                    ->columns(4)
                                                    ->collapsible()
                                                    ->itemLabel(function (array $state): ?string {
                                                        $date = $state['exception_date'] ?? null;
                                                        $closed = ! empty($state['is_closed']);
                                                        return $date ? "{$date} · " . ($closed ? 'închis' : 'program special') : null;
                                                    })
                                                    ->addActionLabel('Adaugă excepție'),
                                            ])
                                            ->columns(1),
                                    ]),

                                // ====================================================
                                // TAB 4: REZERVARE & CONȚINUT
                                // ====================================================
                                SC\Tabs\Tab::make('Rezervare')
                                    ->key('rezervare')
                                    ->icon('heroicon-o-ticket')
                                    ->schema([
                                        SC\Section::make('Constraints rezervare')
                                            ->schema([
                                                Forms\Components\TextInput::make('min_participants')
                                                    ->label('Minim participanți / rezervare')
                                                    ->numeric()
                                                    ->default(1)
                                                    ->minValue(1),

                                                Forms\Components\TextInput::make('max_participants')
                                                    ->label('Maxim participanți / rezervare')
                                                    ->numeric()
                                                    ->default(10)
                                                    ->minValue(1),

                                                Forms\Components\TextInput::make('booking_lead_time_hours')
                                                    ->label('Timp minim înainte de slot (ore)')
                                                    ->numeric()
                                                    ->default(2)
                                                    ->minValue(0)
                                                    ->helperText('Ex: 2 = nu se poate rezerva cu mai puțin de 2h înainte.'),

                                                Forms\Components\TextInput::make('booking_max_advance_days')
                                                    ->label('Maxim avans (zile)')
                                                    ->numeric()
                                                    ->default(60)
                                                    ->minValue(1)
                                                    ->helperText('Cât în avans se poate rezerva.'),
                                            ])
                                            ->columns(4),

                                        SC\Section::make('Conținut pagină')
                                            ->schema([
                                                Forms\Components\Textarea::make('cancellation_policy')
                                                    ->label('Politică de anulare')
                                                    ->rows(3)
                                                    ->placeholder('ex: Anularea cu minim 24h înainte aduce refund integral.')
                                                    ->columnSpanFull(),

                                                Forms\Components\TagsInput::make('included_items')
                                                    ->label('Incluse')
                                                    ->placeholder('Acces 60 min, instructor, băutură…')
                                                    ->columnSpanFull(),

                                                Forms\Components\TagsInput::make('not_included')
                                                    ->label('Neincluse')
                                                    ->placeholder('Transport, masă…')
                                                    ->columnSpanFull(),

                                                Forms\Components\TagsInput::make('requirements')
                                                    ->label('Cerințe')
                                                    ->placeholder('Minim 14 ani, pantofi sport…')
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(1),
                                    ]),

                                // ====================================================
                                // TAB 5: SEO + FAQ
                                // ====================================================
                                SC\Tabs\Tab::make('SEO')
                                    ->key('seo')
                                    ->icon('heroicon-o-magnifying-glass')
                                    ->schema([
                                        SC\Section::make('Meta SEO')
                                            ->description('Apar în <title> și meta description pe pagina publică.')
                                            ->schema([
                                                SC\Tabs::make('Meta Translations')
                                                    ->tabs([
                                                        SC\Tabs\Tab::make('Română')
                                                            ->schema([
                                                                Forms\Components\TextInput::make('seo.title_ro')
                                                                    ->label('Meta title (RO)')
                                                                    ->maxLength(70),
                                                                Forms\Components\Textarea::make('seo.description_ro')
                                                                    ->label('Meta description (RO)')
                                                                    ->rows(2)
                                                                    ->maxLength(160),
                                                            ]),
                                                        SC\Tabs\Tab::make('English')
                                                            ->schema([
                                                                Forms\Components\TextInput::make('seo.title_en')
                                                                    ->label('Meta title (EN)')
                                                                    ->maxLength(70),
                                                                Forms\Components\Textarea::make('seo.description_en')
                                                                    ->label('Meta description (EN)')
                                                                    ->rows(2)
                                                                    ->maxLength(160),
                                                            ]),
                                                    ])
                                                    ->columnSpanFull(),
                                            ]),

                                        SC\Section::make('Corp SEO (rich text)')
                                            ->description('Body editorial afișat sub conținutul principal — inclus pentru SEO long-tail.')
                                            ->collapsed()
                                            ->schema([
                                                SC\Tabs::make('SEO Body Translations')
                                                    ->tabs([
                                                        SC\Tabs\Tab::make('Română')
                                                            ->schema([
                                                                Forms\Components\TextInput::make('seo_body_title.ro')
                                                                    ->label('Titlu corp (RO)')
                                                                    ->maxLength(190),
                                                                Forms\Components\RichEditor::make('seo_body.ro')
                                                                    ->label('Corp text (RO)')
                                                                    ->toolbarButtons(['bold', 'italic', 'link', 'h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'undo', 'redo'])
                                                                    ->columnSpanFull(),
                                                            ]),
                                                        SC\Tabs\Tab::make('English')
                                                            ->schema([
                                                                Forms\Components\TextInput::make('seo_body_title.en')
                                                                    ->label('Body title (EN)')
                                                                    ->maxLength(190),
                                                                Forms\Components\RichEditor::make('seo_body.en')
                                                                    ->label('Body (EN)')
                                                                    ->toolbarButtons(['bold', 'italic', 'link', 'h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'undo', 'redo'])
                                                                    ->columnSpanFull(),
                                                            ]),
                                                    ])
                                                    ->columnSpanFull(),
                                            ]),

                                        SC\Section::make('Întrebări frecvente (FAQ)')
                                            ->description('Apar pe pagină + emise ca FAQPage JSON-LD pentru rich SERP.')
                                            ->schema([
                                                Forms\Components\Repeater::make('faqs')
                                                    ->label(false)
                                                    ->schema([
                                                        Forms\Components\TextInput::make('q')
                                                            ->label('Întrebare')
                                                            ->required()
                                                            ->maxLength(200),
                                                        Forms\Components\Textarea::make('a')
                                                            ->label('Răspuns')
                                                            ->required()
                                                            ->rows(3),
                                                    ])
                                                    ->columns(1)
                                                    ->reorderable()
                                                    ->collapsible()
                                                    ->cloneable()
                                                    ->itemLabel(fn (array $state): ?string => $state['q'] ?? null)
                                                    ->addActionLabel('Adaugă întrebare')
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),

                                // ====================================================
                                // TAB 6: AUDIENȚĂ & FILTRE INTENȚIE
                                // ====================================================
                                SC\Tabs\Tab::make('Audiență')
                                    ->key('audienta')
                                    ->icon('heroicon-o-users')
                                    ->schema([
                                        SC\Section::make('Caracteristici activitate')
                                            ->description('Alimentează filtrele de intenție pe pagini de oraș și categorie.')
                                            ->schema([
                                                Forms\Components\Toggle::make('is_indoor')->label('Indoor'),
                                                Forms\Components\Toggle::make('is_outdoor')->label('Outdoor'),
                                                Forms\Components\Toggle::make('is_kid_friendly')->label('Potrivit copiilor'),
                                                Forms\Components\Toggle::make('is_accessible')->label('Accesibil dizabilități'),
                                                Forms\Components\Toggle::make('is_weather_sensitive')->label('Depinde de vreme'),
                                            ])
                                            ->columns(5),

                                        SC\Section::make('Audiență & limbi')
                                            ->schema([
                                                Forms\Components\TextInput::make('age_min')
                                                    ->label('Vârsta minimă')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(99),

                                                Forms\Components\TextInput::make('age_max')
                                                    ->label('Vârsta maximă')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(99),

                                                Forms\Components\Select::make('difficulty_level')
                                                    ->label('Dificultate')
                                                    ->options([
                                                        'easy' => 'Ușor',
                                                        'medium' => 'Mediu',
                                                        'hard' => 'Greu',
                                                        'expert' => 'Expert',
                                                    ])
                                                    ->placeholder('—'),

                                                Forms\Components\TagsInput::make('languages_offered')
                                                    ->label('Limbi disponibile')
                                                    ->placeholder('ro, en, hu, de…')
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(3),
                                    ]),

                            ]), // end Tabs
                    ]), // end Group 3/4

                // ============================================================
                // COLOANA DREAPTĂ (1/4) — SIDEBAR STATUS + PROMOVARE
                // ============================================================
                SC\Group::make()
                    ->columnSpan(1)
                    ->schema([
                        // Preview button — opens the public /activitate/{slug} page in a new
                        // tab. Hidden until the record is saved (we need a slug + a domain
                        // resolved from the marketplace). Same Placeholder + HtmlString
                        // pattern as EventResource's preview block for visual consistency.
                        SC\Section::make('Acțiuni')
                            ->visible(fn (?\App\Models\Activity $record) => $record && $record->exists)
                            ->schema([
                                Forms\Components\Placeholder::make('preview_link')
                                    ->hiddenLabel()
                                    ->content(function (?\App\Models\Activity $record) use ($marketplace) {
                                        if (! $record || ! $record->exists) {
                                            return new \Illuminate\Support\HtmlString('<span class="text-xs text-gray-500">Salvează pentru a previzualiza.</span>');
                                        }
                                        $activityMarketplace = $record->marketplaceClient ?? $marketplace;
                                        $domain = $activityMarketplace?->domain;
                                        if (! $domain) {
                                            return new \Illuminate\Support\HtmlString('<span class="text-xs text-warning-600">Niciun domeniu marketplace configurat.</span>');
                                        }
                                        $domain = preg_replace('#^(https?:?/?/?|//)#i', '', $domain);
                                        $domain = ltrim($domain, '/');
                                        $protocol = str_contains($domain, 'localhost') ? 'http' : 'https';
                                        $url = $protocol . '://' . $domain . '/activitate/' . $record->slug;

                                        return new \Illuminate\Support\HtmlString(
                                            '<a href="' . e($url) . '" target="_blank" class="inline-flex items-center justify-center gap-2 w-full px-4 py-2.5 text-sm font-semibold text-white rounded-lg bg-primary-600 hover:bg-primary-500 transition-colors shadow-sm">' .
                                            '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>' .
                                            'Previzualizare' .
                                            '</a>'
                                        );
                                    }),
                            ])
                            ->columns(1),

                        SC\Section::make('Status')
                            ->schema([
                                Forms\Components\Toggle::make('is_published')
                                    ->label('Publicat')
                                    ->helperText('Activitățile publicate apar pe site-ul public.')
                                    ->onIcon('heroicon-m-eye')
                                    ->offIcon('heroicon-m-eye-slash')
                                    ->default(false),

                                Forms\Components\Placeholder::make('status_badge')
                                    ->hiddenLabel()
                                    ->visible(fn (?\App\Models\Activity $record) => $record && $record->exists)
                                    ->content(function (?\App\Models\Activity $record) {
                                        if (! $record || ! $record->exists) return null;
                                        if ($record->is_published) {
                                            return new \Illuminate\Support\HtmlString(
                                                '<span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold text-green-400 rounded-full bg-green-500/20 ring-1 ring-inset ring-green-500/30">●  LIVE</span>'
                                            );
                                        }
                                        return new \Illuminate\Support\HtmlString(
                                            '<span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold rounded-full bg-amber-500/20 text-amber-400 ring-1 ring-inset ring-amber-500/30">●  DRAFT</span>'
                                        );
                                    }),
                            ])
                            ->columns(1),

                        SC\Section::make('Promovare')
                            ->description('Cum apare în listing-uri.')
                            ->schema([
                                Forms\Components\Toggle::make('is_featured')
                                    ->label('Promovat')
                                    ->onIcon('heroicon-m-sparkles')
                                    ->offIcon('heroicon-m-sparkles'),
                                Forms\Components\Toggle::make('is_homepage_featured')->label('Pe homepage'),
                                Forms\Components\Toggle::make('is_category_featured')->label('Pe pagina categoriei'),
                                Forms\Components\Toggle::make('is_city_featured')->label('Pe pagina orașului'),
                            ])
                            ->collapsible()
                            ->columns(1),

                        SC\Section::make('Statistici')
                            ->visible(fn (?\App\Models\Activity $record) => $record && $record->exists)
                            ->schema([
                                Forms\Components\Placeholder::make('cheapest_price_display')
                                    ->label('De la')
                                    ->content(fn (?\App\Models\Activity $record) => $record?->cheapest_price_cents
                                        ? number_format($record->cheapest_price_cents / 100, 0, ',', '.') . ' lei'
                                        : 'Adaugă variante de preț'),

                                Forms\Components\Placeholder::make('views_display')
                                    ->label('Vizualizări')
                                    ->content(fn (?\App\Models\Activity $record) => number_format((int) ($record?->views_count ?? 0), 0, ',', '.')),

                                Forms\Components\Placeholder::make('created_at_display')
                                    ->label('Creat')
                                    ->content(fn (?\App\Models\Activity $record) => $record?->created_at?->isoFormat('D MMM Y, HH:mm')),

                                Forms\Components\Placeholder::make('updated_at_display')
                                    ->label('Modificat')
                                    ->content(fn (?\App\Models\Activity $record) => $record?->updated_at?->isoFormat('D MMM Y, HH:mm')),
                            ])
                            ->collapsible()
                            ->columns(1),
                    ]), // end Group 1/4

            ]), // end Grid(4)
        ]);
    }

    public static function table(Table $table): Table
    {
        $marketplace = static::getMarketplaceClient();
        $lang = $marketplace?->language ?? 'ro';

        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('cover_image_url')
                    ->label('')
                    ->disk('public')
                    ->circular()
                    ->size(40),

                Tables\Columns\TextColumn::make("title.{$lang}")
                    ->label('Titlu')
                    // Diacritic-insensitive + case-insensitive search across title (RO+EN)
                    // and short_description. On PostgreSQL we use `translate()` to strip
                    // Romanian diacritics from the DB value, then `LOWER` for case folding.
                    // The needle gets the same treatment in PHP before being bound, so
                    // both sides of the comparison are normalised the same way.
                    ->searchable(query: function ($query, string $search) {
                        if ($search === '') {
                            return $query;
                        }

                        // Strip diacritics + lowercase on the PHP side. Same characters
                        // listed as in the SQL translate() so the symmetry holds.
                        $needle = '%' . mb_strtolower(strtr($search, [
                            'ș' => 's', 'Ș' => 's', 'ş' => 's', 'Ş' => 's',
                            'ț' => 't', 'Ț' => 't', 'ţ' => 't', 'Ţ' => 't',
                            'ă' => 'a', 'Ă' => 'a',
                            'â' => 'a', 'Â' => 'a',
                            'î' => 'i', 'Î' => 'i',
                        ])) . '%';

                        // pg_translate args: in-chars vs out-chars (1:1 mapping).
                        $inChars  = 'șȘşŞțȚţŢăĂâÂîÎ';
                        $outChars = 'sssstttaaaaaiI';

                        $cols = ["title->>'ro'", "title->>'en'", "short_description->>'ro'"];
                        $query->where(function ($q) use ($cols, $needle, $inChars, $outChars) {
                            foreach ($cols as $col) {
                                $q->orWhereRaw(
                                    "LOWER(translate(COALESCE({$col}, ''), ?, ?)) LIKE ?",
                                    [$inChars, $outChars, $needle]
                                );
                            }
                        });

                        return $query;
                    })
                    ->sortable()
                    ->wrap()
                    ->limit(60),

                Tables\Columns\TextColumn::make('city.name')
                    ->label('Oraș')
                    ->formatStateUsing(fn ($state) => is_array($state) ? ($state[$lang] ?? $state['en'] ?? '-') : ($state ?? '-')),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categorie')
                    ->formatStateUsing(fn ($state) => is_array($state) ? ($state[$lang] ?? $state['en'] ?? '-') : ($state ?? '-')),

                Tables\Columns\TextColumn::make('organizer.name')
                    ->label('Organizator')
                    ->limit(30),

                Tables\Columns\TextColumn::make('duration_minutes')
                    ->label('Durată')
                    ->formatStateUsing(fn ($state) => $state ? "{$state} min" : '-'),

                Tables\Columns\TextColumn::make('capacity_per_slot')
                    ->label('Cap./slot')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('cheapest_price_cents')
                    ->label('De la')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / 100, 0, ',', '.') . ' lei' : '—')
                    ->alignRight(),

                Tables\Columns\IconColumn::make('is_published')
                    ->label('Publicat')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('★')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_published')->label('Publicat'),
                Tables\Filters\TernaryFilter::make('is_featured')->label('Promovat'),
                Tables\Filters\SelectFilter::make('marketplace_city_id')
                    ->label('Oraș')
                    ->options(function () use ($marketplace, $lang) {
                        return MarketplaceCity::where('marketplace_client_id', $marketplace?->id)
                            ->orderBy('sort_order')
                            ->get()
                            ->mapWithKeys(fn ($c) => [$c->id => $c->name[$lang] ?? $c->name['en'] ?? $c->slug])
                            ->toArray();
                    }),
                Tables\Filters\SelectFilter::make('marketplace_category_id')
                    ->label('Categorie')
                    ->options(function () use ($marketplace, $lang) {
                        return MarketplaceCategory::where('marketplace_client_id', $marketplace?->id)
                            ->whereNull('parent_id')
                            ->orderBy('sort_order')
                            ->get()
                            ->mapWithKeys(fn ($c) => [$c->id => $c->name[$lang] ?? $c->name['en'] ?? $c->slug])
                            ->toArray();
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ActivityVariantsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivities::route('/'),
            'create' => Pages\CreateActivity::route('/create'),
            'edit' => Pages\EditActivity::route('/{record}/edit'),
        ];
    }
}

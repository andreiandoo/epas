<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\ActivityResource\Pages;
use App\Filament\Marketplace\Resources\ActivityResource\RelationManagers;
use App\Filament\Marketplace\Resources\OrganizerResource;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as DBSchema;
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

    /**
     * Fill any empty SEO fields from the activity's Detalii + Locație values.
     * Called from CreateActivity::mutateFormDataBeforeCreate and
     * EditActivity::mutateFormDataBeforeSave — admin overrides survive
     * because we only ever touch empty strings/null.
     *
     * FAQs are NEVER auto-populated (per task brief — keep them manual).
     *
     * Sources:
     *   seo.title_{ro,en}        ← title.{ro,en} (+ " — bilete.online")
     *   seo.description_{ro,en}  ← short_description.{ro,en} or fallback
     *   seo_body_title.{ro,en}   ← "Despre {title}" / "About {title}"
     *   seo_body.{ro,en}         ← short HTML built from title + city + organizer
     */
    public static function autoFillSeo(array $data): array
    {
        $titleRo = data_get($data, 'title.ro');
        $titleEn = data_get($data, 'title.en');
        $shortRo = data_get($data, 'short_description.ro');
        $shortEn = data_get($data, 'short_description.en');

        // Resolve city + organizer names for richer copy.
        $cityName = null;
        if ($cityId = $data['marketplace_city_id'] ?? null) {
            $city = MarketplaceCity::find($cityId);
            $cityName = $city ? ($city->name['ro'] ?? $city->name['en'] ?? null) : null;
        }
        $organizerName = null;
        if ($orgId = $data['marketplace_organizer_id'] ?? null) {
            $organizer = MarketplaceOrganizer::find($orgId);
            $organizerName = $organizer?->name;
        }

        $seo = is_array($data['seo'] ?? null) ? $data['seo'] : [];

        // Meta title — RO + EN. Pad with " — bilete.online" to fit ~60-70 chars.
        if (empty($seo['title_ro']) && $titleRo) {
            $seo['title_ro'] = mb_substr($titleRo . ($cityName ? ' în ' . $cityName : '') . ' — bilete.online', 0, 70);
        }
        if (empty($seo['title_en']) && $titleEn) {
            $seo['title_en'] = mb_substr($titleEn . ($cityName ? ' in ' . $cityName : '') . ' — bilete.online', 0, 70);
        }

        // Meta description — use short_description as-is; fall back to a
        // generic line built from title + city + organizer.
        if (empty($seo['description_ro'])) {
            $seo['description_ro'] = mb_substr(
                $shortRo
                    ?: trim(($titleRo ?? 'Activitate')
                        . ($cityName ? ' în ' . $cityName : '')
                        . ($organizerName ? ' organizată de ' . $organizerName : '')
                        . '. Rezervă online cu QR instant.'),
                0,
                160
            );
        }
        if (empty($seo['description_en'])) {
            $seo['description_en'] = mb_substr(
                $shortEn
                    ?: trim(($titleEn ?? 'Activity')
                        . ($cityName ? ' in ' . $cityName : '')
                        . ($organizerName ? ' by ' . $organizerName : '')
                        . '. Book online, instant QR ticket.'),
                0,
                160
            );
        }
        $data['seo'] = $seo;

        // SEO body title — translatable JSON.
        $bodyTitle = is_array($data['seo_body_title'] ?? null) ? $data['seo_body_title'] : [];
        if (empty($bodyTitle['ro']) && $titleRo) {
            $bodyTitle['ro'] = 'Despre ' . $titleRo;
        }
        if (empty($bodyTitle['en']) && $titleEn) {
            $bodyTitle['en'] = 'About ' . $titleEn;
        }
        $data['seo_body_title'] = $bodyTitle;

        // SEO body — translatable JSON. Compose a short editorial paragraph
        // from the structured fields. Admin can rewrite in the SEO tab.
        $body = is_array($data['seo_body'] ?? null) ? $data['seo_body'] : [];
        if (empty($body['ro']) && $titleRo) {
            $parts = [];
            $parts[] = '<p>' . e($titleRo) . ($cityName ? ' este o experiență disponibilă în ' . e($cityName) : '') . ($organizerName ? ', oferită de ' . e($organizerName) : '') . '.</p>';
            if ($shortRo) {
                $parts[] = '<p>' . e($shortRo) . '</p>';
            }
            $parts[] = '<p>Rezervă online — primești biletul cu QR pe email și în cont, gata de scanat la intrare.</p>';
            $body['ro'] = implode('', $parts);
        }
        if (empty($body['en']) && $titleEn) {
            $parts = [];
            $parts[] = '<p>' . e($titleEn) . ($cityName ? ' is an experience available in ' . e($cityName) : '') . ($organizerName ? ', operated by ' . e($organizerName) : '') . '.</p>';
            if ($shortEn) {
                $parts[] = '<p>' . e($shortEn) . '</p>';
            }
            $parts[] = '<p>Book online — get a QR-coded ticket by email and in your account, ready to scan at the door.</p>';
            $body['en'] = implode('', $parts);
        }
        $data['seo_body'] = $body;

        return $data;
    }

    /**
     * Conexiuni tab schema — built at schema construction time (not closure time)
     * so we can skip the Select::relationship component entirely when the
     * activity_related table doesn't exist yet. fillForm() walks every component
     * to hydrate state regardless of visible(), so a missing pivot table would
     * 500 the entire page even if the Section was hidden.
     */
    protected static function conexiuniTabSchema($marketplace, string $lang): array
    {
        // No table → show only the migrate instruction. No Select, no DB queries
        // anywhere in the tab body.
        if (! DBSchema::hasTable('activity_related')) {
            return [
                SC\Section::make('Migrare lipsă')
                    ->schema([
                        Forms\Components\Placeholder::make('migrate_required')
                            ->hiddenLabel()
                            ->content(new \Illuminate\Support\HtmlString(
                                '<div class="p-4 rounded-xl ring-1 ring-inset ring-warning-500/30 bg-warning-500/10 text-warning-400 text-sm">' .
                                '<p class="font-semibold mb-1">Tabela <code>activity_related</code> nu există încă.</p>' .
                                '<p>Rulează pe prod: <code>php artisan migrate</code>. După aceea reîncarcă această pagină ca să poți gestiona conexiunile între activități.</p>' .
                                '</div>'
                            )),
                    ]),
            ];
        }

        return [
            SC\Section::make('Activități conectate')
                ->description('Apar ca recomandări pe pagina publică a activității (cross-sell / upsell). Activitățile aceluiași organizator se conectează automat. Poți adăuga și manual.')
                ->visible(fn (?\App\Models\Activity $record) => $record && $record->exists)
                ->schema([
                    Forms\Components\Select::make('relatedActivities')
                        ->label('Activități')
                        ->relationship(
                            'relatedActivities',
                            'slug',
                            fn (Builder $query) => $query->where('marketplace_client_id', $marketplace?->id)
                        )
                        ->getOptionLabelFromRecordUsing(function ($rec) use ($lang): string {
                            // Filament's getOptionLabelFromRecord() has a strict `string`
                            // return type, so we must return a string here even when the
                            // record is missing — null returns 500 the page. We:
                            //   - return '' for null records (orphan pivot rows)
                            //   - defensively null-coalesce title/slug
                            if (! $rec) return '';

                            $title = is_array($rec->title ?? null)
                                ? ($rec->title[$lang] ?? $rec->title['en'] ?? ($rec->slug ?? ''))
                                : (string) ($rec->title ?? $rec->slug ?? '');
                            $organizer = $rec->organizer?->name ?? null;
                            return $organizer ? "{$title} — {$organizer}" : $title;
                        })
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->columnSpanFull()
                        ->helperText('Activitățile sincronizate automat din organizator au sursa "auto" în pivot. Cele adăugate manual aici primesc "manual".')
                        ->saveRelationshipsUsing(function ($component, $state) {
                            $record = $component->getRecord();
                            if (! $record) return;

                            $selected = collect($state ?? [])->map(fn ($v) => (int) $v)->filter()->unique()->values();

                            $existing = DB::table('activity_related')
                                ->where('activity_id', $record->id)
                                ->get()
                                ->keyBy('related_activity_id');

                            foreach ($selected as $relId) {
                                if ($relId === $record->id) continue; // never link to self
                                if (! isset($existing[$relId])) {
                                    DB::table('activity_related')->insert([
                                        'activity_id' => $record->id,
                                        'related_activity_id' => $relId,
                                        'source' => 'manual',
                                        'sort_order' => 0,
                                        'created_at' => now(),
                                        'updated_at' => now(),
                                    ]);
                                    DB::table('activity_related')->insertOrIgnore([[
                                        'activity_id' => $relId,
                                        'related_activity_id' => $record->id,
                                        'source' => 'manual',
                                        'sort_order' => 0,
                                        'created_at' => now(),
                                        'updated_at' => now(),
                                    ]]);
                                }
                            }

                            foreach ($existing as $relId => $row) {
                                if (! $selected->contains($relId)) {
                                    DB::table('activity_related')
                                        ->where('activity_id', $record->id)
                                        ->where('related_activity_id', $relId)
                                        ->delete();
                                    DB::table('activity_related')
                                        ->where('activity_id', $relId)
                                        ->where('related_activity_id', $record->id)
                                        ->delete();
                                }
                            }
                        }),

                    Forms\Components\Placeholder::make('connections_legend')
                        ->hiddenLabel()
                        ->visible(fn (?\App\Models\Activity $record) => $record && $record->exists)
                        ->content(function (?\App\Models\Activity $record) use ($lang) {
                            if (! $record || ! $record->exists) return null;

                            $rows = DB::table('activity_related')
                                ->join('activities', 'activities.id', '=', 'activity_related.related_activity_id')
                                ->where('activity_related.activity_id', $record->id)
                                ->select('activities.id', 'activities.title', 'activities.slug', 'activity_related.source')
                                ->orderBy('activity_related.sort_order')
                                ->orderBy('activities.id')
                                ->get();

                            if ($rows->isEmpty()) {
                                return new \Illuminate\Support\HtmlString('<p class="text-sm text-gray-500">Nicio conexiune încă. Adaugă activități organizatorului sau alege manual aici.</p>');
                            }

                            $html = '<div class="space-y-2">';
                            foreach ($rows as $r) {
                                $titleArr = is_string($r->title) ? json_decode($r->title, true) : (array) $r->title;
                                $title = $titleArr[$lang] ?? $titleArr['en'] ?? $r->slug;
                                $badgeClass = $r->source === 'auto'
                                    ? 'bg-sky-500/20 text-sky-400 ring-sky-500/30'
                                    : 'bg-emerald-500/20 text-emerald-400 ring-emerald-500/30';
                                $badgeLabel = $r->source === 'auto' ? 'Auto' : 'Manual';
                                $html .= '<div class="flex items-center gap-2 text-sm">' .
                                    '<span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full ring-1 ring-inset ' . $badgeClass . '">' . $badgeLabel . '</span>' .
                                    '<span class="font-medium">' . e($title) . '</span>' .
                                    '</div>';
                            }
                            $html .= '</div>';

                            return new \Illuminate\Support\HtmlString($html);
                        }),
                ])
                ->columns(1),

            SC\Section::make('Activități conectate')
                ->visible(fn (?\App\Models\Activity $record) => ! $record || ! $record->exists)
                ->schema([
                    Forms\Components\Placeholder::make('save_first_for_connections')
                        ->hiddenLabel()
                        ->content(new \Illuminate\Support\HtmlString('<p class="text-sm text-gray-500">Salvează activitatea pentru a putea adăuga conexiuni.</p>')),
                ]),
        ];
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
                                                            // The default `fi-sc-tabs-tab` panel class has no padding;
                                                            // inputs would sit flush against the panel border. Adding
                                                            // p-4 sm:p-6 + gap-4 produces the same breathing room the
                                                            // rest of the form has inside Sections.
                                                            // Inline !important needed to beat epas-skin.css's
                                                            // `.dark .fi-sc-tabs-tab { padding: 0 !important; }`.
                                                            // Inline declarations override stylesheet declarations
                                                            // when both use !important, since inline has higher
                                                            // specificity.
                                                            ->extraAttributes(['style' => 'padding: 1.5rem !important; display: flex; flex-direction: column; gap: 1rem;'])
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
                                                            // Inline !important needed to beat epas-skin.css's
                                                            // `.dark .fi-sc-tabs-tab { padding: 0 !important; }`.
                                                            // Inline declarations override stylesheet declarations
                                                            // when both use !important, since inline has higher
                                                            // specificity.
                                                            ->extraAttributes(['style' => 'padding: 1.5rem !important; display: flex; flex-direction: column; gap: 1rem;'])
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
                                // TAB 6: CONEXIUNI (cross-sell / upsell related activities)
                                // ====================================================
                                // Schema-time check — if activity_related migration hasn't run yet,
                                // we don't include the Select::relationship at all (Filament's
                                // fillForm() walks every component to load state, regardless of
                                // visible() — so hiding the parent Section wasn't enough).
                                SC\Tabs\Tab::make('Conexiuni')
                                    ->key('conexiuni')
                                    ->icon('heroicon-o-link')
                                    ->schema(static::conexiuniTabSchema($marketplace, $lang)),

                                // ====================================================
                                // TAB 7: VÂNZĂRI (placeholder until A5 wires bookings)
                                // ====================================================
                                SC\Tabs\Tab::make('Vânzări')
                                    ->key('vanzari')
                                    ->icon('heroicon-o-chart-bar')
                                    ->schema([
                                        SC\Section::make('Sumar rezervări')
                                            ->visible(fn (?\App\Models\Activity $record) => $record && $record->exists)
                                            ->schema([
                                                Forms\Components\Placeholder::make('sales_summary')
                                                    ->hiddenLabel()
                                                    ->content(function (?\App\Models\Activity $record) {
                                                        if (! $record || ! $record->exists) return null;

                                                        // Aggregate over activity_bookings — table exists from A1.
                                                        // A5 will start populating it; until then we render an empty state.
                                                        $stats = DB::table('activity_bookings')
                                                            ->where('activity_id', $record->id)
                                                            ->whereNull('deleted_at')
                                                            ->selectRaw('
                                                                COUNT(*) AS total_bookings,
                                                                SUM(CASE WHEN status IN (\'paid\', \'confirmed\', \'checked_in\') THEN 1 ELSE 0 END) AS confirmed_bookings,
                                                                SUM(CASE WHEN status = \'pending_payment\' THEN 1 ELSE 0 END) AS pending_bookings,
                                                                SUM(CASE WHEN status = \'cancelled\' THEN 1 ELSE 0 END) AS cancelled_bookings,
                                                                SUM(CASE WHEN status IN (\'paid\', \'confirmed\', \'checked_in\') THEN total_cents ELSE 0 END) AS revenue_cents,
                                                                SUM(CASE WHEN status IN (\'paid\', \'confirmed\', \'checked_in\') THEN participants_count ELSE 0 END) AS total_participants
                                                            ')
                                                            ->first();

                                                        $totalBookings = (int) ($stats->total_bookings ?? 0);
                                                        $confirmedBookings = (int) ($stats->confirmed_bookings ?? 0);
                                                        $pendingBookings = (int) ($stats->pending_bookings ?? 0);
                                                        $cancelledBookings = (int) ($stats->cancelled_bookings ?? 0);
                                                        $revenueCents = (int) ($stats->revenue_cents ?? 0);
                                                        $totalParticipants = (int) ($stats->total_participants ?? 0);

                                                        $revenueDisplay = number_format($revenueCents / 100, 0, ',', '.') . ' lei';

                                                        $html = '<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-2">';
                                                        $tile = function (string $label, string $value, string $tone = 'primary') {
                                                            $bg = match ($tone) {
                                                                'success' => 'bg-success-500/10 text-success-400 ring-success-500/30',
                                                                'warning' => 'bg-warning-500/10 text-warning-400 ring-warning-500/30',
                                                                'danger'  => 'bg-danger-500/10 text-danger-400 ring-danger-500/30',
                                                                default   => 'bg-primary-500/10 text-primary-400 ring-primary-500/30',
                                                            };
                                                            return '<div class="rounded-xl ring-1 ring-inset px-4 py-3 ' . $bg . '">' .
                                                                '<p class="text-xs uppercase tracking-wider font-semibold opacity-75">' . e($label) . '</p>' .
                                                                '<p class="mt-1 text-2xl font-bold">' . e($value) . '</p>' .
                                                                '</div>';
                                                        };

                                                        $html .= $tile('Total rezervări', (string) $totalBookings);
                                                        $html .= $tile('Confirmate', (string) $confirmedBookings, 'success');
                                                        $html .= $tile('În așteptare', (string) $pendingBookings, 'warning');
                                                        $html .= $tile('Anulate', (string) $cancelledBookings, 'danger');
                                                        $html .= '</div>';

                                                        $html .= '<div class="grid grid-cols-2 gap-3 mt-3">';
                                                        $html .= $tile('Venit (confirmate)', $revenueDisplay, 'success');
                                                        $html .= $tile('Participanți (confirmate)', (string) $totalParticipants);
                                                        $html .= '</div>';

                                                        if ($totalBookings === 0) {
                                                            $html .= '<p class="mt-4 text-sm text-gray-500">Nu există rezervări încă. Vor apărea aici imediat ce clienții încep să rezerve.</p>';
                                                        }

                                                        return new \Illuminate\Support\HtmlString($html);
                                                    }),
                                            ]),

                                        SC\Section::make('Ultimele rezervări')
                                            ->visible(fn (?\App\Models\Activity $record) => $record && $record->exists)
                                            ->collapsed()
                                            ->schema([
                                                Forms\Components\Placeholder::make('recent_bookings')
                                                    ->hiddenLabel()
                                                    ->content(function (?\App\Models\Activity $record) {
                                                        if (! $record || ! $record->exists) return null;

                                                        $rows = DB::table('activity_bookings')
                                                            ->where('activity_id', $record->id)
                                                            ->whereNull('deleted_at')
                                                            ->orderByDesc('created_at')
                                                            ->limit(20)
                                                            ->get([
                                                                'id', 'booking_date', 'slot_start_time', 'slot_end_time',
                                                                'participants_count', 'status', 'total_cents', 'confirmation_code', 'created_at',
                                                            ]);

                                                        if ($rows->isEmpty()) {
                                                            return new \Illuminate\Support\HtmlString('<p class="text-sm text-gray-500">Nicio rezervare încă.</p>');
                                                        }

                                                        $statusBadge = [
                                                            'pending_payment' => '<span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-amber-500/20 text-amber-400 ring-1 ring-inset ring-amber-500/30">În plată</span>',
                                                            'paid'            => '<span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-green-500/20 text-green-400 ring-1 ring-inset ring-green-500/30">Plătită</span>',
                                                            'confirmed'       => '<span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-emerald-500/20 text-emerald-400 ring-1 ring-inset ring-emerald-500/30">Confirmată</span>',
                                                            'cancelled'       => '<span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-rose-500/20 text-rose-400 ring-1 ring-inset ring-rose-500/30">Anulată</span>',
                                                            'checked_in'     => '<span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-sky-500/20 text-sky-400 ring-1 ring-inset ring-sky-500/30">Check-in</span>',
                                                            'no_show'        => '<span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-gray-500/20 text-gray-400 ring-1 ring-inset ring-gray-500/30">No-show</span>',
                                                        ];

                                                        $html = '<div class="overflow-x-auto"><table class="w-full text-sm"><thead class="text-xs uppercase tracking-wider opacity-60"><tr>' .
                                                            '<th class="px-2 py-1 text-left">Cod</th>' .
                                                            '<th class="px-2 py-1 text-left">Data slot</th>' .
                                                            '<th class="px-2 py-1 text-left">Interval</th>' .
                                                            '<th class="px-2 py-1 text-center">Persoane</th>' .
                                                            '<th class="px-2 py-1 text-right">Total</th>' .
                                                            '<th class="px-2 py-1 text-left">Status</th>' .
                                                            '</tr></thead><tbody>';

                                                        foreach ($rows as $r) {
                                                            $dt = $r->booking_date instanceof \DateTimeInterface ? $r->booking_date->format('d.m.Y') : (string) $r->booking_date;
                                                            $start = is_string($r->slot_start_time) ? substr($r->slot_start_time, 0, 5) : ($r->slot_start_time?->format('H:i') ?? '');
                                                            $end = is_string($r->slot_end_time) ? substr($r->slot_end_time, 0, 5) : ($r->slot_end_time?->format('H:i') ?? '');
                                                            $total = number_format(((int) $r->total_cents) / 100, 0, ',', '.') . ' lei';
                                                            $html .= '<tr class="border-t border-gray-500/10">' .
                                                                '<td class="px-2 py-2 font-mono text-xs">' . e($r->confirmation_code) . '</td>' .
                                                                '<td class="px-2 py-2">' . e($dt) . '</td>' .
                                                                '<td class="px-2 py-2 font-mono">' . e($start . '–' . $end) . '</td>' .
                                                                '<td class="px-2 py-2 text-center">' . (int) $r->participants_count . '</td>' .
                                                                '<td class="px-2 py-2 text-right font-semibold">' . e($total) . '</td>' .
                                                                '<td class="px-2 py-2">' . ($statusBadge[$r->status] ?? e($r->status)) . '</td>' .
                                                                '</tr>';
                                                        }
                                                        $html .= '</tbody></table></div>';

                                                        return new \Illuminate\Support\HtmlString($html);
                                                    }),
                                            ]),

                                        SC\Section::make('Vânzări')
                                            ->visible(fn (?\App\Models\Activity $record) => ! $record || ! $record->exists)
                                            ->schema([
                                                Forms\Components\Placeholder::make('save_first_for_sales')
                                                    ->hiddenLabel()
                                                    ->content(new \Illuminate\Support\HtmlString('<p class="text-sm text-gray-500">Salvează activitatea pentru a vedea rezervările.</p>')),
                                            ]),
                                    ]),

                                // ====================================================
                                // TAB 8: AUDIENȚĂ & FILTRE INTENȚIE
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
                                                        'easy'   => 'Ușor',
                                                        'medium' => 'Mediu',
                                                        'hard'   => 'Greu',
                                                        'expert' => 'Expert',
                                                    ])
                                                    // Native:false renders Filament's custom dropdown with an X
                                                    // clear button when a value is set. Placeholder appears as
                                                    // a selectable "no value" row at the top of the list, so
                                                    // admin can either click X or pick "Fără dificultate" to
                                                    // reset back to NULL.
                                                    ->placeholder('Fără dificultate')
                                                    ->native(false),

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
                        // Acțiuni — Preview + Delete grouped together. Preview opens the
                        // public /activitate/{slug} in a new tab; Delete is moved here
                        // from the page header so both actions are visible in the sticky
                        // sidebar. Both rendered via Placeholder + HtmlString to stay in
                        // sync with EventResource's preview pattern. Delete goes through
                        // a confirm dialog (delete-record Livewire wire) so we don't
                        // accidentally bypass Filament's existing UX.
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

                                // Delete via embedded Filament action (uses the framework's
                                // built-in confirm modal + record delete pipeline + redirect
                                // to the list view). Visible only when the record exists.
                                SC\Actions::make([
                                    \Filament\Actions\Action::make('deleteActivity')
                                        ->label('Șterge activitatea')
                                        ->icon('heroicon-o-trash')
                                        ->color('danger')
                                        ->size('lg')
                                        ->requiresConfirmation()
                                        ->modalHeading('Șterge activitatea?')
                                        ->modalDescription('Această acțiune este ireversibilă. Toate variantele, programul, excepțiile și rezervările asociate vor fi șterse împreună cu activitatea.')
                                        ->modalSubmitActionLabel('Da, șterge')
                                        ->action(function (?\App\Models\Activity $record) {
                                            if ($record && $record->exists) {
                                                $record->delete();
                                            }

                                            \Filament\Notifications\Notification::make()
                                                ->title('Activitate ștearsă.')
                                                ->success()
                                                ->send();

                                            redirect(ActivityResource::getUrl('index'));
                                        }),
                                ])
                                    ->fullWidth(),
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

                        // Organizator — rich quick-info card identical in shape to
                        // EventResource sidebar. Surfaces avatar + clickable name +
                        // email + status badges + commission + activity counts +
                        // total revenue, all in one compact block. Visible only on
                        // edit AND when an organizer is set on the activity.
                        SC\Section::make('Organizator')
                            ->icon('heroicon-o-building-office-2')
                            ->compact()
                            ->visible(fn (?\App\Models\Activity $record) => $record && $record->exists && $record->marketplace_organizer_id)
                            ->schema([
                                Forms\Components\Placeholder::make('organizer_quick_info')
                                    ->hiddenLabel()
                                    ->content(function (?\App\Models\Activity $record) use ($marketplace) {
                                        if (! $record || ! $record->marketplace_organizer_id) return '';

                                        $organizer = MarketplaceOrganizer::find($record->marketplace_organizer_id);
                                        if (! $organizer) return '';

                                        // Commission resolution mirrors EventResource: organizer-level
                                        // override falls back to the marketplace default.
                                        $commissionRate = $organizer->commission_rate ?? $marketplace?->commission_rate ?? 5;
                                        $commissionMode = $organizer->default_commission_mode ?? $marketplace?->commission_mode ?? 'included';
                                        $commissionModeLabel = $commissionMode === 'included' ? 'inclus' : 'peste';

                                        $statusBadge = match ($organizer->status) {
                                            'active'    => '<span class="text-green-600">Activ</span>',
                                            'pending'   => '<span class="text-yellow-600">În așteptare</span>',
                                            'suspended' => '<span class="text-red-600">Suspendat</span>',
                                            default     => e((string) $organizer->status),
                                        };
                                        $verifiedBadge = $organizer->verified_at
                                            ? '<span class="text-green-600">✓ Verificat</span>'
                                            : '<span class="text-gray-500">Neverificat</span>';

                                        // Activity-specific revenue + count (parallel to total_events
                                        // accessor on the organizer model, but scoped to activities).
                                        $activitiesCount = \DB::table('activities')
                                            ->where('marketplace_organizer_id', $organizer->id)
                                            ->whereNull('deleted_at')
                                            ->count();
                                        $activitiesRevenueCents = \DB::table('activity_bookings')
                                            ->join('activities', 'activities.id', '=', 'activity_bookings.activity_id')
                                            ->where('activities.marketplace_organizer_id', $organizer->id)
                                            ->whereIn('activity_bookings.status', ['paid', 'confirmed', 'checked_in'])
                                            ->whereNull('activity_bookings.deleted_at')
                                            ->sum('activity_bookings.total_cents');

                                        $eventsCount = (int) ($organizer->total_events ?? 0);
                                        $eventsRevenue = number_format((float) ($organizer->total_revenue ?? 0), 2, ',', '.');
                                        $activitiesRevenue = number_format($activitiesRevenueCents / 100, 2, ',', '.');

                                        $organizerUrl = e(OrganizerResource::getUrl('view', ['record' => $organizer->id]));
                                        $organizerEditUrl = e(OrganizerResource::getUrl('edit', ['record' => $organizer->id]));
                                        $organizerName = e($organizer->name);
                                        $email = e($organizer->email);
                                        $phone = e($organizer->phone ?? '');
                                        $initials = strtoupper(mb_substr($organizer->name, 0, 2));

                                        $html = '<div class="text-sm space-y-0">';
                                        // Header block — avatar + name link + email
                                        $html .= '<div class="flex items-center gap-3 pb-3">';
                                        $html .= '<div class="flex items-center justify-center w-10 h-10 text-xs font-bold text-white rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 shrink-0">' . e($initials) . '</div>';
                                        $html .= '<div class="min-w-0">';
                                        $html .= '<a href="' . $organizerUrl . '" target="_blank" class="font-semibold text-primary-600 hover:underline block truncate">' . $organizerName . '</a>';
                                        $html .= '<div class="text-xs text-gray-500 truncate">' . $email . '</div>';
                                        $html .= '</div>';
                                        $html .= '</div>';

                                        // Stat rows
                                        $row = fn ($l, $v) => '<div class="flex justify-between py-1.5 border-t border-gray-700/30"><span class="text-gray-500">' . $l . '</span><span class="font-medium">' . $v . '</span></div>';
                                        $html .= $row('Status', $statusBadge . ' · ' . $verifiedBadge);
                                        $html .= $row('Comision (' . $commissionModeLabel . ')', e((string) $commissionRate) . '%');
                                        if ($phone) {
                                            $html .= $row('Telefon', '<a href="tel:' . $phone . '" class="hover:underline">' . $phone . '</a>');
                                        }
                                        $html .= $row('Activități', (string) $activitiesCount);
                                        $html .= $row('Vânzări activități', $activitiesRevenue . ' lei');
                                        if ($eventsCount > 0) {
                                            $html .= $row('Evenimente', (string) $eventsCount);
                                            $html .= $row('Vânzări evenimente', $eventsRevenue . ' RON');
                                        }

                                        // Action links
                                        $html .= '<div class="flex gap-2 pt-3 mt-1 border-t border-gray-700/30">';
                                        $html .= '<a href="' . $organizerUrl . '" target="_blank" class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:text-primary-500">Detalii →</a>';
                                        $html .= '<a href="' . $organizerEditUrl . '" target="_blank" class="inline-flex items-center gap-1 text-xs font-semibold text-gray-500 hover:text-gray-400 ml-auto">Editează →</a>';
                                        $html .= '</div>';

                                        $html .= '</div>';

                                        return new \Illuminate\Support\HtmlString($html);
                                    }),
                            ]),

                        // Locație fizică — same compact panel pattern for venue info.
                        SC\Section::make('Locație fizică')
                            ->icon('heroicon-o-map-pin')
                            ->compact()
                            ->visible(fn (?\App\Models\Activity $record) => $record && $record->exists && $record->venue_id)
                            ->schema([
                                Forms\Components\Placeholder::make('venue_quick_info')
                                    ->hiddenLabel()
                                    ->content(function (?\App\Models\Activity $record) use ($lang) {
                                        $v = $record?->venue;
                                        if (! $v) return '';

                                        $name = is_array($v->name)
                                            ? ($v->name[$lang] ?? $v->name['en'] ?? '—')
                                            : ($v->name ?? '—');
                                        $addressParts = array_filter([$v->address, $v->city, $v->state]);
                                        $address = $addressParts ? implode(', ', $addressParts) : null;
                                        $mapsUrl = ($v->lat && $v->lng)
                                            ? 'https://maps.google.com/?q=' . urlencode($v->lat . ',' . $v->lng)
                                            : null;

                                        $html = '<div class="text-sm space-y-2">';
                                        $html .= '<p class="font-semibold">' . e($name) . '</p>';
                                        if ($address) {
                                            $html .= '<p class="text-gray-500">' . e($address) . '</p>';
                                        }
                                        if ($mapsUrl) {
                                            $html .= '<a href="' . e($mapsUrl) . '" target="_blank" class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:text-primary-500 pt-1">Vezi pe Google Maps →</a>';
                                        }
                                        $html .= '</div>';

                                        return new \Illuminate\Support\HtmlString($html);
                                    }),
                            ]),

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
        ])->columns(1);
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
            RelationManagers\BookingsRelationManager::class,
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

<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\NewsletterResource\Pages;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\Artist;
use App\Models\Event;
use App\Models\MarketplaceCity;
use App\Models\MarketplaceEventCategory;
use App\Models\MarketplaceNewsletter;
use App\Models\MarketplaceContactList;
use App\Models\MarketplaceContactTag;
use App\Models\MarketplaceEmailTemplate;
use App\Models\MarketplaceOrganizer;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Utilities\Get as SGet;
use Filament\Schemas\Components\Utilities\Set as SSet;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class NewsletterResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceNewsletter::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-megaphone';
    protected static \UnitEnum|string|null $navigationGroup = 'Communications';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'Newsletters';

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplace?->id);
    }

    /**
     * Organizer options for the recipients-section pre-filter. Narrowed
     * by city when at least one city is selected (the join goes via the
     * organizer's events). Optionally filtered by name LIKE for search.
     *
     * @return array<int,string>
     */
    public static function organizerOptionsFor(?int $marketplaceId, array $cityIds = [], string $search = ''): array
    {
        if (!$marketplaceId) return [];

        $q = MarketplaceOrganizer::query()
            ->where('marketplace_client_id', $marketplaceId);

        if (!empty($cityIds)) {
            $q->whereExists(function ($sub) use ($cityIds, $marketplaceId) {
                $sub->select(\DB::raw(1))
                    ->from('events')
                    ->whereColumn('events.marketplace_organizer_id', 'marketplace_organizers.id')
                    ->where('events.marketplace_client_id', $marketplaceId)
                    ->whereIn('events.marketplace_city_id', $cityIds);
            });
        }

        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $w->where('name', 'ilike', "%{$search}%")
                    ->orWhere('company_name', 'ilike', "%{$search}%")
                    ->orWhere('company_tax_id', 'ilike', "%{$search}%");
            });
        }

        return $q->orderBy('name')
            ->limit(50)
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Event options narrowed by city + organizer + optional name search.
     * When no upstream filter is set and no search term given, returns
     * an empty array so the dropdown stays clean (admin can still type
     * to search across all events of the marketplace).
     *
     * @return array<int,string>
     */
    public static function eventOptionsFor(?int $marketplaceId, array $cityIds = [], array $organizerIds = [], string $search = '', array $categoryIds = [], array $artistIds = []): array
    {
        if (!$marketplaceId) return [];

        $query = Event::query()
            ->with('venue')
            ->where('marketplace_client_id', $marketplaceId);

        if (!empty($cityIds)) {
            $query->whereIn('marketplace_city_id', $cityIds);
        }
        if (!empty($organizerIds)) {
            $query->whereIn('marketplace_organizer_id', $organizerIds);
        }
        if (!empty($categoryIds)) {
            $query->whereIn('marketplace_event_category_id', $categoryIds);
        }
        if (!empty($artistIds)) {
            $query->whereHas('artists', function ($w) use ($artistIds) {
                $w->whereIn('artists.id', $artistIds);
            });
        }
        if ($search !== '') {
            // Tokenize on whitespace so a search like "cargo flex" matches
            // any event whose title (ro or en) or slug contains BOTH words
            // — in any order, any position. Each token is AND-ed; within a
            // token the three columns are OR-ed.
            $tokens = preg_split('/\s+/', trim($search), -1, PREG_SPLIT_NO_EMPTY);
            $query->where(function ($q) use ($tokens) {
                foreach ($tokens as $token) {
                    $like = '%' . $token . '%';
                    $q->where(function ($w) use ($like) {
                        $w->where('title->ro', 'ilike', $like)
                            ->orWhere('title->en', 'ilike', $like)
                            ->orWhere('slug', 'ilike', $like);
                    });
                }
            });
        }

        $hasUpstream = !empty($cityIds) || !empty($organizerIds) || !empty($categoryIds) || !empty($artistIds);

        return $query->orderByDesc('event_date')
            ->limit($hasUpstream && $search === '' ? 100 : 40)
            ->get()
            ->mapWithKeys(fn (Event $e) => [$e->id => static::formatEventOption($e)])
            ->toArray();
    }

    /**
     * Search the live event catalog for use in newsletter pickers.
     *
     * Filters applied:
     *   - same marketplace_client_id as the current admin context
     *   - is_published = true
     *   - is_cancelled is NULL or false
     *   - event_date >= today (no past events)
     *   - optional [$from, $to] window narrows the dropdown for the
     *     "next week" / "next month" section types
     *
     * Search uses PostgreSQL unaccent + lower on the JSON title text so
     * diacritics don't have to match. Returns id => "{title} ({date}) —
     * {venue}, {city}" labels (matches the reference format requested by
     * the marketplace admin).
     *
     * @return array<int, string>
     */
    public static function searchLiveEvents(?int $marketplaceId, string $search, ?array $window): array
    {
        if (!$marketplaceId) return [];

        $needle = '%' . trim($search) . '%';
        $q = Event::query()
            ->with('venue')
            ->where('marketplace_client_id', $marketplaceId)
            ->where('is_published', true)
            ->where(fn ($w) => $w->where('is_cancelled', false)->orWhereNull('is_cancelled'))
            ->where(function ($w) {
                $w->where('event_date', '>=', now()->toDateString())
                    ->orWhere('range_end_date', '>=', now()->toDateString());
            });

        if ($window) {
            $q->whereBetween('event_date', [$window[0]->toDateString(), $window[1]->toDateString()]);
        }

        if ($search !== '') {
            $q->whereRaw('LOWER(unaccent(title::text)) LIKE LOWER(unaccent(?))', [$needle]);
        }

        return $q->orderBy('event_date')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Event $e) => [$e->id => static::formatEventOption($e)])
            ->toArray();
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();

        return $schema
            ->components([
                // Top-level 4-column grid: 3 cols main content, 1 col sidebar.
                // Stacks to a single column on mobile via Grid's responsive
                // defaults.
                SC\Grid::make(['default' => 1, 'lg' => 4])
                    ->schema([
                        // ============ MAIN COLUMN (span 3) ============
                        SC\Group::make(static::mainColumnSchema($marketplace))
                            ->columnSpan(['default' => 1, 'lg' => 3]),

                        // ============ SIDEBAR (span 1) ============
                        SC\Group::make(static::sidebarSchema($marketplace))
                            ->columnSpan(['default' => 1, 'lg' => 1]),
                    ]),
            ])->columns(1);
    }

    /**
     * Main column: Campaign Details, Recipients, Email Content, Scheduling.
     * Extracted so the layout grid stays readable.
     */
    protected static function mainColumnSchema($marketplace): array
    {
        return [
            SC\Section::make('Campaign Details')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Campaign Name')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Internal name for this campaign'),
                    Forms\Components\Select::make('status')
                        ->options([
                            'draft' => 'Draft',
                            'scheduled' => 'Scheduled',
                            'sending' => 'Sending',
                            'sent' => 'Sent',
                            'cancelled' => 'Cancelled',
                        ])
                        ->default('draft')
                        ->disabled(fn ($record) => in_array($record?->status, ['sending', 'sent'])),
                ])->columns(2),

            SC\Section::make('Recipients')
                ->description('Send to contact lists, tag-filtered customers, or ticket buyers of specific events. Recipients are dedup-ed by email across all sources.')
                ->schema([
                    // Oraș filter — purely a UI helper to narrow the events
                    // list below by city. Not persisted (dehydrated false) —
                    // the actual recipient targeting still goes through
                    // target_event_ids, which dedups customer emails across
                    // any number of selected events in the buildRecipientList
                    // resolver.
                    Forms\Components\Select::make('target_city_ids')
                        ->label('Oraș — filtrează evenimente / organizatori')
                        ->multiple()
                        ->searchable()
                        ->live(onBlur: true)
                        // Static dropdown content — shown when the admin
                        // opens the picker before typing. Loads every
                        // visible city for the marketplace.
                        ->options(fn () => static::buildCityOptions($marketplace))
                        // Typed search uses a PHP-side filter on
                        // diacritic-stripped, lowercased names so
                        // "timisoara" matches "Timișoara" (and vice
                        // versa). Postgres ilike on a JSON column does
                        // case-insensitive but NOT accent-insensitive
                        // matching, hence the in-app filter instead of
                        // a raw whereLike.
                        ->getSearchResultsUsing(function (string $search) use ($marketplace) {
                            $needle = static::normalizeSearch($search);
                            return collect(static::buildCityOptions($marketplace))
                                ->filter(function ($label) use ($needle) {
                                    return $needle === '' || str_contains(static::normalizeSearch($label), $needle);
                                })
                                ->toArray();
                        })
                        ->getOptionLabelsUsing(function (array $values) {
                            return MarketplaceCity::whereIn('id', $values)
                                ->get()
                                ->mapWithKeys(function ($city) {
                                    $name = $city->getTranslation('name', 'ro')
                                        ?? $city->getTranslation('name', 'en')
                                        ?? '—';
                                    return [$city->id => $name];
                                })
                                ->toArray();
                        })
                        ->afterStateUpdated(function (SSet $set) {
                            // Reset event + organizer picks when city
                            // filter changes — they're pre-filtered by
                            // city and any stale rows from a different
                            // city would be confusing.
                            $set('target_event_ids', []);
                            $set('target_organizer_ids', []);
                        })
                        ->helperText('Selectează unul sau mai multe orașe pentru a vedea doar evenimentele/organizatorii de acolo. Caută cu sau fără diacritice. Lasă gol pentru toate.')
                        ->columnSpanFull(),

                    // Organizer pre-filter. Two purposes:
                    //   1. Narrows the events dropdown below to only
                    //      events owned by these organizers (combined
                    //      with the city pre-filter above).
                    //   2. When the events dropdown is left empty, the
                    //      newsletter targets ALL customers who bought
                    //      a valid ticket at ANY event of these
                    //      organizers (optionally narrowed by city) —
                    //      "send a campaign to all buyers of organizer X
                    //      in Bucharest". See
                    //      MarketplaceNewsletter::buildRecipientList.
                    Forms\Components\Select::make('target_organizer_ids')
                        ->label('Organizatori — filtru / target direct')
                        ->multiple()
                        ->searchable()
                        ->live(onBlur: true)
                        ->options(function (SGet $get) use ($marketplace) {
                            $cityIds = $get('target_city_ids') ?? [];
                            return static::organizerOptionsFor($marketplace?->id, $cityIds);
                        })
                        ->getSearchResultsUsing(function (string $search, SGet $get) use ($marketplace) {
                            $cityIds = $get('target_city_ids') ?? [];
                            return static::organizerOptionsFor($marketplace?->id, $cityIds, $search);
                        })
                        ->getOptionLabelsUsing(function (array $values) use ($marketplace) {
                            return MarketplaceOrganizer::where('marketplace_client_id', $marketplace?->id)
                                ->whereIn('id', $values)
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->afterStateUpdated(function (SSet $set) {
                            $set('target_event_ids', []);
                        })
                        ->helperText('Dacă selectezi organizator + nu alegi evenimente: trimite la TOȚI cumpărătorii lui (eventual narrow pe oraș). Dacă alegi și evenimente: organizatorul filtrează doar dropdown-ul.')
                        ->columnSpanFull(),

                    // Category intersect filter. AND'd with city / organizer
                    // / artist when present. "Concerte" + "Qfeel Events" +
                    // "Bucharest" → buyers of Qfeel concerts in Bucharest.
                    // marketplace_event_categories.name is a translatable
                    // jsonb column — Postgres can't ORDER BY jsonb directly
                    // ("could not identify an ordering operator for type
                    // jsonb") and ->pluck('name', 'id') returns arrays as
                    // labels that Filament can't render. Both fixed by
                    // ordering on `name->>'ro'` and extracting the locale
                    // value via mapWithKeys.
                    Forms\Components\Select::make('target_category_ids')
                        ->label('Categorie eveniment — filtru intersecție')
                        ->multiple()
                        ->searchable()
                        ->live(onBlur: true)
                        ->options(function () use ($marketplace) {
                            if (!$marketplace) return [];
                            return MarketplaceEventCategory::where('marketplace_client_id', $marketplace->id)
                                ->orderByRaw("LOWER(COALESCE(name->>'ro', name->>'en', name::text))")
                                ->get(['id', 'name'])
                                ->mapWithKeys(function ($c) {
                                    $name = is_array($c->name)
                                        ? ($c->name['ro'] ?? $c->name['en'] ?? reset($c->name) ?? '—')
                                        : (string) $c->name;
                                    return [$c->id => $name ?: '—'];
                                })
                                ->toArray();
                        })
                        ->afterStateUpdated(function (SSet $set) {
                            $set('target_event_ids', []);
                        })
                        ->helperText('Restrânge cumpărătorii doar la cei care au luat bilete la evenimente din categoriile alese.')
                        ->columnSpanFull(),

                    // Artist intersect filter. Looks up via the event_artist
                    // pivot — admin picks artists, the filter expands to
                    // every event that features one of them.
                    Forms\Components\Select::make('target_artist_ids')
                        ->label('Artist în eveniment — filtru intersecție')
                        ->multiple()
                        ->searchable()
                        ->live(onBlur: true)
                        ->getSearchResultsUsing(function (string $search) use ($marketplace) {
                            if (!$marketplace) return [];
                            $needle = '%' . trim($search) . '%';
                            return Artist::query()
                                ->whereRaw('LOWER(unaccent(name::text)) LIKE LOWER(unaccent(?))', [$needle])
                                ->orderBy('name')
                                ->limit(50)
                                ->get(['id', 'name'])
                                ->mapWithKeys(function ($a) {
                                    $name = is_array($a->name) ? ($a->name['ro'] ?? $a->name['en'] ?? reset($a->name) ?? '—') : ($a->name ?? '—');
                                    return [$a->id => $name];
                                })
                                ->toArray();
                        })
                        ->getOptionLabelsUsing(function (array $values) {
                            return Artist::whereIn('id', $values)
                                ->get(['id', 'name'])
                                ->mapWithKeys(function ($a) {
                                    $name = is_array($a->name) ? ($a->name['ro'] ?? $a->name['en'] ?? reset($a->name) ?? '—') : ($a->name ?? '—');
                                    return [$a->id => $name];
                                })
                                ->toArray();
                        })
                        ->afterStateUpdated(function (SSet $set) {
                            $set('target_event_ids', []);
                        })
                        ->helperText('Restrânge la cumpărătorii care au luat bilet la cel puțin un eveniment care îi includea pe artiștii aleși.')
                        ->columnSpanFull(),

                    Forms\Components\Select::make('target_event_ids')
                        ->label('Evenimente — către cumpărătorii biletelor')
                        ->multiple()
                        ->searchable()
                        // No ->live() here on purpose. Each pill selection
                        // in a multi-select with live() triggers a Livewire
                        // form re-render that destroys the dropdown DOM and
                        // closes it, forcing the admin to click "select" and
                        // retype the search every time. Nothing observes
                        // target_event_ids reactively (the recipient-count
                        // placeholder picks the value up at action time),
                        // so we drop live() and the dropdown stays open
                        // between selections.
                        // Initial dropdown content reacts to the city
                        // filter — selecting cities pre-fills the events
                        // dropdown without requiring the admin to type
                        // anything. When no city is selected, returns an
                        // empty list so the dropdown stays clean (admin
                        // can still search by typing — see below).
                        ->options(function (SGet $get) use ($marketplace) {
                            $cityIds = $get('target_city_ids') ?? [];
                            $orgIds = $get('target_organizer_ids') ?? [];
                            $catIds = $get('target_category_ids') ?? [];
                            $artistIds = $get('target_artist_ids') ?? [];
                            if (empty($cityIds) && empty($orgIds) && empty($catIds) && empty($artistIds)) return [];
                            return static::eventOptionsFor($marketplace?->id, $cityIds, $orgIds, '', $catIds, $artistIds);
                        })
                        ->getSearchResultsUsing(function (string $search, SGet $get) use ($marketplace) {
                            $cityIds = $get('target_city_ids') ?? [];
                            $orgIds = $get('target_organizer_ids') ?? [];
                            $catIds = $get('target_category_ids') ?? [];
                            $artistIds = $get('target_artist_ids') ?? [];
                            return static::eventOptionsFor($marketplace?->id, $cityIds, $orgIds, $search, $catIds, $artistIds);
                        })
                        ->getOptionLabelsUsing(function (array $values) {
                            return Event::whereIn('id', $values)
                                ->get()
                                ->mapWithKeys(fn ($e) => [
                                    $e->id => static::formatEventOption($e),
                                ])
                                ->toArray();
                        })
                        ->helperText('Selectează orașul mai sus → evenimentele se preîncărc automat. Newsletter ajunge la toți cumpărătorii cu bilete valide; un client care a cumpărat la mai multe evenimente primește un singur email.')
                        ->columnSpanFull(),

                    // Contact lists. Labels include subscriber counts and
                    // the (Manual / Dynamic) type tag — Ambilet has
                    // historically held BOTH a "Clienți" (Manual, ~70k)
                    // and a "Clienti" (Dynamic, ~59k) row, which look
                    // alike in a vanilla dropdown but mean very different
                    // cohorts. Showing the count + type avoids the
                    // "which one did I pick?" trap.
                    Forms\Components\Select::make('target_lists')
                        ->label('Contact Lists')
                        ->multiple()
                        ->live(onBlur: true)
                        ->options(function () use ($marketplace) {
                            if (!$marketplace) return [];
                            return MarketplaceContactList::where('marketplace_client_id', $marketplace->id)
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(function ($list) {
                                    $type = ucfirst($list->list_type ?? '—');
                                    $count = number_format((int) ($list->subscriber_count ?? 0), 0, '.', '.');
                                    return [$list->id => "{$list->name} — {$type} ({$count})"];
                                })
                                ->toArray();
                        })
                        ->helperText('Listele sunt sursa de bază (audiența). Combinate cu organizator/eveniment, rezultatul e INTERSECȚIA: doar membrii listei care au cumpărat la scopul ales.'),
                    Forms\Components\Select::make('target_tags')
                        ->label('Contact Tags')
                        ->multiple()
                        ->live(onBlur: true)
                        ->options(function () use ($marketplace) {
                            return MarketplaceContactTag::where('marketplace_client_id', $marketplace?->id)
                                ->pluck('name', 'id');
                        })
                        ->helperText('Filtrează contactele după tag-uri'),
                ])->columns(2),

            SC\Section::make('Email Content')
                    ->schema([
                        // Optional starting point: pick an existing email
                        // template (Communications → Email Templates). The
                        // afterStateUpdated hook copies its subject/body into
                        // the form fields so the organizer can tweak before
                        // sending. We do NOT keep a live link — once forked,
                        // edits to the source template don't reflect here.
                        Forms\Components\Select::make('source_email_template_id')
                            ->label('Pornește de la un template (opțional)')
                            ->options(function () use ($marketplace) {
                                return MarketplaceEmailTemplate::where('marketplace_client_id', $marketplace?->id)
                                    ->where('is_active', true)
                                    ->orderBy('name')
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->live()
                            ->placeholder('— niciun template —')
                            ->helperText('Aplică conținutul unui template existent. Poți edita liber după.')
                            ->columnSpanFull()
                            ->afterStateUpdated(function ($state, SSet $set, SGet $get) use ($marketplace) {
                                if (!$state) return;
                                $tpl = MarketplaceEmailTemplate::where('marketplace_client_id', $marketplace?->id)
                                    ->where('id', $state)
                                    ->first();
                                if (!$tpl) return;
                                if (empty($get('subject'))) $set('subject', $tpl->subject);
                                // Push the template body as a single HTML
                                // section if no sections exist yet — keeps
                                // pre-existing drafts intact.
                                $existing = $get('body_sections') ?? [];
                                if (empty($existing) && !empty($tpl->body_html)) {
                                    $set('body_sections', [[
                                        'type' => 'html',
                                        'html_content' => $tpl->body_html,
                                    ]]);
                                }
                                if (empty($get('body_text')) && !empty($tpl->body_text)) {
                                    $set('body_text', $tpl->body_text);
                                }
                            }),

                        Forms\Components\TextInput::make('subject')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('preview_text')
                            ->maxLength(255)
                            ->helperText('Preview text shown in email client (optional)')
                            ->columnSpanFull(),

                        Forms\Components\Repeater::make('body_sections')
                            ->label('Secțiuni Email')
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->label('Tip secțiune')
                                    ->options([
                                        'text' => 'Text / Rich Content',
                                        'html' => 'HTML personalizat',
                                        'featured_event' => 'Eveniment featured (single hero)',
                                        'recommended_events' => 'Evenimente recomandate',
                                        'hand_picked_events' => 'Evenimente alese',
                                        'events_next_week' => 'Evenimente săptămâna viitoare',
                                        'events_next_month' => 'Evenimente luna viitoare',
                                        'button' => 'Buton CTA',
                                        'spacer' => 'Spațiu / Separator',
                                        'image' => 'Imagine',
                                    ])
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, SSet $set, SGet $get) {
                                        // Seed type-specific defaults the moment
                                        // the admin picks a section type, so the
                                        // form shows brand-red button color and
                                        // a sensible CTA label instead of empty
                                        // fields the user has to fill manually.
                                        if ($state === 'button') {
                                            if (!$get('button_color')) $set('button_color', '#A51C30');
                                            if (!$get('button_text')) $set('button_text', 'Click aici');
                                        }
                                        if ($state === 'spacer' && !$get('height')) {
                                            $set('height', 20);
                                        }
                                    })
                                    ->columnSpanFull(),

                                // Featured event picker — single event, populated
                                // as a hero (image + title + venue/city +
                                // price + CTA). Live events only: published,
                                // not cancelled, event_date >= today. Search
                                // walks the translatable title JSON via
                                // unaccent + LOWER LIKE so "lacul sf" matches
                                // "Lacul Sf. Ana" regardless of diacritics.
                                Forms\Components\Select::make('event_id')
                                    ->label('Selectează evenimentul featured')
                                    ->searchable()
                                    ->getSearchResultsUsing(function (string $search) use ($marketplace) {
                                        return static::searchLiveEvents($marketplace?->id, $search, null);
                                    })
                                    ->getOptionLabelUsing(function ($value) {
                                        $e = Event::with('venue')->find($value);
                                        return $e ? static::formatEventOption($e) : null;
                                    })
                                    ->visible(fn ($get) => $get('type') === 'featured_event')
                                    ->columnSpanFull()
                                    ->helperText('Va popula automat în email: imagine, titlu, preț, venue, oraș și link.'),

                                Forms\Components\TextInput::make('artist_name')
                                    ->label('Subtitlu / Artist (opțional)')
                                    ->visible(fn ($get) => $get('type') === 'featured_event')
                                    ->placeholder('ex: Dirty Shirt')
                                    ->maxLength(120)
                                    ->columnSpanFull(),

                                Forms\Components\Textarea::make('intro_paragraph')
                                    ->label('Paragraf intro (opțional)')
                                    ->visible(fn ($get) => $get('type') === 'featured_event')
                                    ->rows(3)
                                    ->placeholder('Energia trupei ajunge pe scenă într-un show intens...')
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make('cta_label')
                                    ->label('Text buton CTA (opțional)')
                                    ->visible(fn ($get) => $get('type') === 'featured_event')
                                    ->placeholder('Cumpără bilete')
                                    ->maxLength(80)
                                    ->columnSpanFull(),

                                // Text section
                                Forms\Components\RichEditor::make('content')
                                    ->label('Conținut')
                                    ->visible(fn ($get) => $get('type') === 'text')
                                    ->columnSpanFull()
                                    ->helperText('Suportă variabile: {{customer_name}}, {{customer_email}}, {{event:ID:name}}, etc.'),

                                // HTML section
                                Forms\Components\Textarea::make('html_content')
                                    ->label('Cod HTML')
                                    ->visible(fn ($get) => $get('type') === 'html')
                                    ->rows(10)
                                    ->columnSpanFull()
                                    ->helperText('HTML personalizat. Poți importa template-uri externe sau scrie cod HTML direct.'),

                                // Manual event picker — shared across all 4 list
                                // sections (recommended/hand_picked/next_week/
                                // next_month). The 4 sections differ only in
                                // the section TITLE the renderer emits + the
                                // date pre-filter applied to the dropdown:
                                //   recommended_events / hand_picked_events
                                //       → no date filter
                                //   events_next_week
                                //       → starts_at BETWEEN now() AND +14 days
                                //   events_next_month
                                //       → starts_at BETWEEN now() AND +45 days
                                // No status / is_public filter — admin picks
                                // any of their own events.
                                Forms\Components\Select::make('event_ids')
                                    ->label('Selectează evenimente')
                                    ->multiple()
                                    ->searchable()
                                    ->getSearchResultsUsing(function (string $search, callable $get) use ($marketplace) {
                                        $type = $get('type');
                                        $window = match ($type) {
                                            'events_next_week'  => [now(), now()->addDays(14)],
                                            'events_next_month' => [now(), now()->addDays(45)],
                                            default => null,
                                        };
                                        return static::searchLiveEvents($marketplace?->id, $search, $window);
                                    })
                                    ->getOptionLabelsUsing(function (array $values) {
                                        return Event::with('venue')
                                            ->whereIn('id', $values)
                                            ->get()
                                            ->mapWithKeys(fn (Event $e) => [$e->id => static::formatEventOption($e)])
                                            ->toArray();
                                    })
                                    ->visible(fn ($get) => in_array($get('type'), [
                                        'recommended_events',
                                        'hand_picked_events',
                                        'events_next_week',
                                        'events_next_month',
                                    ]))
                                    ->columnSpanFull()
                                    ->helperText(function (callable $get): string {
                                        return match ($get('type')) {
                                            'events_next_week' => 'Dropdown-ul îți arată doar evenimentele cu starts_at în următoarele 14 zile.',
                                            'events_next_month' => 'Dropdown-ul îți arată doar evenimentele cu starts_at în următoarele 45 de zile.',
                                            default => 'Caută și selectează evenimentele pe care vrei să le incluzi.',
                                        };
                                    }),

                                // Optional section title override — when blank
                                // the renderer falls back to a per-type default
                                // (e.g. "Evenimente recomandate" / "Săptămâna
                                // viitoare").
                                Forms\Components\TextInput::make('section_title')
                                    ->label('Titlu secțiune (opțional)')
                                    ->visible(fn ($get) => in_array($get('type'), [
                                        'recommended_events',
                                        'hand_picked_events',
                                        'events_next_week',
                                        'events_next_month',
                                    ]))
                                    ->maxLength(120)
                                    ->columnSpanFull(),

                                // Layout picker — column count for the rendered
                                // grid. "first_hero" variants promote the first
                                // event to a full-width landscape card and flow
                                // the rest into a 2- or 3-column grid below.
                                Forms\Components\Select::make('display_layout')
                                    ->label('Aranjare carduri')
                                    ->options([
                                        '2_cols' => '2 coloane',
                                        '3_cols' => '3 coloane',
                                        '2_cols_first_hero' => '2 coloane, primul fullwidth (landscape)',
                                        '3_cols_first_hero' => '3 coloane, primul fullwidth (landscape)',
                                        '2_cols_mixed' => '2 coloane, mixt (alternează fullwidth + 2 rânduri de 2 coloane)',
                                    ])
                                    ->default('2_cols')
                                    ->visible(fn ($get) => in_array($get('type'), [
                                        'recommended_events',
                                        'hand_picked_events',
                                        'events_next_week',
                                        'events_next_month',
                                    ]))
                                    ->live()
                                    ->helperText('Pe layout-urile "primul fullwidth", evenimentul promovat folosește imaginea landscape. Implicit este primul ca dată, dar poți alege oricare din evenimentele selectate.')
                                    ->columnSpanFull(),

                                // Hero override — when one of the "primul
                                // fullwidth" layouts is picked, the admin can
                                // override which event from the multi-select
                                // is promoted to the landscape hero. The
                                // chosen event is then NOT also rendered in
                                // the column grid below (renderer drops it).
                                // Options are seeded from the current
                                // event_ids picks, so the dropdown reflects
                                // exactly the events the admin has chosen
                                // for the section.
                                Forms\Components\Select::make('hero_event_id')
                                    ->label('Eveniment promovat (fullwidth)')
                                    ->placeholder('Implicit: primul ca dată')
                                    ->options(function ($get) {
                                        $ids = (array) ($get('event_ids') ?? []);
                                        if (empty($ids)) return [];
                                        return Event::with('venue')
                                            ->whereIn('id', $ids)
                                            ->get()
                                            ->mapWithKeys(fn (Event $e) => [$e->id => static::formatEventOption($e)])
                                            ->toArray();
                                    })
                                    ->getOptionLabelUsing(function ($value) {
                                        $e = Event::with('venue')->find($value);
                                        return $e ? static::formatEventOption($e) : null;
                                    })
                                    ->visible(fn ($get) => in_array($get('display_layout'), [
                                        '2_cols_first_hero',
                                        '3_cols_first_hero',
                                        '2_cols_mixed',
                                    ]) && in_array($get('type'), [
                                        'recommended_events',
                                        'hand_picked_events',
                                        'events_next_week',
                                        'events_next_month',
                                    ]))
                                    ->searchable()
                                    ->live()
                                    ->helperText('Alege ce eveniment apare ca PRIMUL hero landscape. Va fi eliminat din grila de coloane pentru a evita duplicarea. Pe layout-ul "mixt", heroes ulteriori sunt aleși cronologic din evenimentele rămase.')
                                    ->columnSpanFull(),

                                // Button fields. Defaults (#A51C30 brand red,
                                // "Click aici" label) are applied via the
                                // afterStateUpdated hook on the type Select
                                // below, since Filament's default() on these
                                // children doesn't fire when the parent type
                                // is switched to "button" inside an existing
                                // repeater item — it only seeds defaults at
                                // initial item create.
                                Forms\Components\TextInput::make('button_text')
                                    ->label('Text buton')
                                    ->default('Click aici')
                                    ->visible(fn ($get) => $get('type') === 'button'),
                                Forms\Components\TextInput::make('button_url')
                                    ->label('URL buton')
                                    ->url()
                                    ->visible(fn ($get) => $get('type') === 'button'),
                                Forms\Components\ColorPicker::make('button_color')
                                    ->label('Culoare')
                                    ->default('#A51C30')
                                    ->dehydrateStateUsing(fn ($state) => $state ?: '#A51C30')
                                    ->visible(fn ($get) => $get('type') === 'button'),

                                // Image upload — drag & drop. Saved on the
                                // `public` disk under `newsletter-images/`.
                                // The relative path stored in `file` is
                                // resolved at render time via
                                // NewsletterRenderer::resolveImageUrl(),
                                // which prepends config('app.url') /storage/.
                                Forms\Components\FileUpload::make('file')
                                    ->label('Imagine')
                                    ->image()
                                    ->imageEditor()
                                    ->disk('public')
                                    ->directory('newsletter-images')
                                    ->maxSize(8192)
                                    ->visible(fn ($get) => $get('type') === 'image')
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('image_link')
                                    ->label('Link la click (opțional)')
                                    ->url()
                                    ->visible(fn ($get) => $get('type') === 'image')
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('alt_text')
                                    ->label('Text alternativ')
                                    ->visible(fn ($get) => $get('type') === 'image')
                                    ->columnSpanFull(),

                                // Spacer height
                                Forms\Components\TextInput::make('height')
                                    ->label('Înălțime (px)')
                                    ->numeric()
                                    ->default(20)
                                    ->visible(fn ($get) => $get('type') === 'spacer')
                                    ->maxWidth('xs'),
                            ])
                            ->reorderable()
                            ->collapsible()
                            ->cloneable()
                            ->itemLabel(fn (array $state): ?string => match ($state['type'] ?? null) {
                                'text' => 'Text / Rich Content',
                                'html' => 'HTML personalizat',
                                'featured_event' => 'Eveniment featured' . (!empty($state['event_id']) ? ' (#' . $state['event_id'] . ')' : ''),
                                'recommended_events' => 'Evenimente recomandate (' . count($state['event_ids'] ?? []) . ')',
                                'hand_picked_events' => 'Evenimente alese (' . count($state['event_ids'] ?? []) . ')',
                                'events_next_week' => 'Săptămâna viitoare (' . count($state['event_ids'] ?? []) . ')',
                                'events_next_month' => 'Luna viitoare (' . count($state['event_ids'] ?? []) . ')',
                                'button' => 'Buton: ' . ($state['button_text'] ?? 'CTA'),
                                'spacer' => 'Spațiu / Separator',
                                'image' => 'Imagine',
                                default => 'Secțiune nouă',
                            })
                            ->defaultItems(0)
                            ->addActionLabel('Adaugă secțiune')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('body_text')
                            ->label('Plain Text Version')
                            ->rows(5)
                            ->columnSpanFull()
                            ->helperText('Versiune text simplu (opțional). Se generează automat dacă lipsește.'),
                    ]),

            SC\Section::make('Scheduling')
                ->schema([
                    Forms\Components\DateTimePicker::make('scheduled_at')
                        ->label('Send At')
                        ->helperText('Leave empty to send immediately when you click "Send Newsletter"')
                        ->minDate(now()),
                ])
                ->visible(fn ($record) => !in_array($record?->status, ['sending', 'sent'])),
        ];
    }

    /**
     * Sidebar column: Sender Information, Recipient stats, Variabile, post-send Statistics.
     */
    protected static function sidebarSchema($marketplace): array
    {
        return [
            SC\Section::make('Sender Information')
                ->schema([
                    Forms\Components\TextInput::make('from_name')
                        ->label('From Name')
                        ->default(fn () => $marketplace?->name)
                        ->required()
                        ->maxLength(100),
                    Forms\Components\TextInput::make('from_email')
                        ->label('From Email')
                        ->email()
                        ->default(function () use ($marketplace) {
                            // Default to noreply@<host> — that's the
                            // verified Brevo sender + bypasses the "do not
                            // reply please" inbox confusion. Marketplaces
                            // can still override per-newsletter.
                            if (!$marketplace) return null;
                            $host = preg_replace('#^https?://#i', '', (string) ($marketplace->domain ?? ''));
                            $host = rtrim($host, '/');
                            return $host !== '' ? "noreply@{$host}" : $marketplace->contact_email;
                        })
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('reply_to')
                        ->label('Reply-To Email')
                        ->email()
                        ->maxLength(255),
                ]),

            SC\Section::make('Statistici email')
                ->schema([
                    Forms\Components\Placeholder::make('recipient_count')
                        ->label('Destinatari unici')
                        ->content(function (SGet $get) use ($marketplace) {
                            // Build a transient instance and use the same
                            // logic the real send-time recipient build uses,
                            // so the count here matches what'll actually be
                            // mailed. IMPORTANT: copy target_organizer_ids +
                            // target_city_ids too — without them the
                            // resolver sees no filter and returns the full
                            // base audience (so "Clienți + Qfeel Bucharest"
                            // came back as the 70k full list instead of
                            // the intersected ~few hundred buyers).
                            $instance = new MarketplaceNewsletter();
                            $instance->marketplace_client_id = $marketplace?->id;
                            $instance->target_lists = $get('target_lists') ?? [];
                            $instance->target_tags = $get('target_tags') ?? [];
                            $instance->target_event_ids = $get('target_event_ids') ?? [];
                            $instance->target_organizer_ids = $get('target_organizer_ids') ?? [];
                            $instance->target_city_ids = $get('target_city_ids') ?? [];
                            $instance->target_category_ids = $get('target_category_ids') ?? [];
                            $instance->target_artist_ids = $get('target_artist_ids') ?? [];
                            // Honour the dedup toggle live so the total
                            // updates the moment the admin flips it.
                            $instance->exclude_recent_recipients = (bool) $get('exclude_recent_recipients');
                            $instance->recent_recipient_window_hours = (int) ($get('recent_recipient_window_hours') ?: 48);

                            $hasTargeting = !empty($instance->target_lists)
                                || !empty($instance->target_tags)
                                || !empty($instance->target_event_ids)
                                || !empty($instance->target_organizer_ids)
                                || !empty($instance->target_city_ids)
                                || !empty($instance->target_category_ids)
                                || !empty($instance->target_artist_ids);
                            if (!$hasTargeting) {
                                return new HtmlString('<span class="text-gray-500">Selectează evenimente / liste / organizator / oraș / categorie / artist</span>');
                            }

                            try {
                                $b = $instance->getRecipientBreakdown();
                            } catch (\Throwable $e) {
                                return new HtmlString('<span class="text-red-600">Eroare la calculul destinatarilor: ' . e($e->getMessage()) . '</span>');
                            }

                            $html = '<div class="space-y-1.5">';
                            $html .= '<div class="text-2xl font-bold text-primary-600">' . number_format($b['total']) . '</div>';
                            $html .= '<div class="text-xs text-gray-500 dark:text-gray-400">Email-uri unice (după dedup)</div>';

                            // Per-source breakdown helps explain why the
                            // total may differ from the user's intuition —
                            // e.g. a contact list may contain more customer
                            // accounts than the "type" suggests, or sources
                            // may overlap.
                            $rows = [];
                            if (($b['lists'] ?? 0) > 0) {
                                $rows[] = '<div class="flex items-center justify-between"><span class="text-gray-600">Din liste</span><span class="font-semibold">' . number_format($b['lists']) . '</span></div>';
                            }
                            if (($b['organizers'] ?? 0) > 0) {
                                $rows[] = '<div class="flex items-center justify-between"><span class="text-gray-600">Organizatori</span><span class="font-semibold">' . number_format($b['organizers']) . '</span></div>';
                            }
                            if (($b['tags'] ?? 0) > 0) {
                                $rows[] = '<div class="flex items-center justify-between"><span class="text-gray-600">Din tag-uri</span><span class="font-semibold">' . number_format($b['tags']) . '</span></div>';
                            }
                            if (($b['events'] ?? 0) > 0) {
                                $rows[] = '<div class="flex items-center justify-between"><span class="text-gray-600">Cumpărători evenimente</span><span class="font-semibold">' . number_format($b['events']) . '</span></div>';
                            }
                            if (!empty($rows)) {
                                $html .= '<div class="border-t border-gray-200 dark:border-gray-700 pt-1.5 mt-1.5 space-y-1 text-xs">' . implode('', $rows) . '</div>';
                            }
                            $html .= '</div>';

                            return new HtmlString($html);
                        }),
                    // Recent-recipient dedup. The placeholder tells the admin
                    // how many of the targeted recipients already received
                    // ANOTHER newsletter from this marketplace within the
                    // toggle's window (default 48h). When the toggle is on,
                    // those emails get stripped at recipient-build time so
                    // the same person isn't mailed twice in quick succession.
                    Forms\Components\Placeholder::make('recent_recipient_overlap')
                        ->label('Destinatari care au primit alt newsletter recent')
                        ->content(function (SGet $get) use ($marketplace) {
                            $instance = new MarketplaceNewsletter();
                            $instance->marketplace_client_id = $marketplace?->id;
                            $instance->target_lists = $get('target_lists') ?? [];
                            $instance->target_tags = $get('target_tags') ?? [];
                            $instance->target_event_ids = $get('target_event_ids') ?? [];
                            $instance->target_organizer_ids = $get('target_organizer_ids') ?? [];
                            $instance->target_city_ids = $get('target_city_ids') ?? [];
                            $instance->target_category_ids = $get('target_category_ids') ?? [];
                            $instance->target_artist_ids = $get('target_artist_ids') ?? [];
                            $instance->recent_recipient_window_hours = (int) ($get('recent_recipient_window_hours') ?: 48);

                            // Force OFF for the overlap calculation — we want
                            // to count overlap against the FULL audience, not
                            // after dedup (which would always be 0 when on).
                            $instance->exclude_recent_recipients = false;

                            $hasTargeting = !empty($instance->target_lists)
                                || !empty($instance->target_tags)
                                || !empty($instance->target_event_ids)
                                || !empty($instance->target_organizer_ids)
                                || !empty($instance->target_city_ids)
                                || !empty($instance->target_category_ids)
                                || !empty($instance->target_artist_ids);
                            if (!$hasTargeting) {
                                return new HtmlString('<span class="text-gray-500 text-xs">Selectează audiența pentru a vedea suprapunerea</span>');
                            }

                            try {
                                $overlap = $instance->getRecentRecipientOverlapCount();
                            } catch (\Throwable $e) {
                                return new HtmlString('<span class="text-red-600 text-xs">Eroare: ' . e($e->getMessage()) . '</span>');
                            }

                            $hours = $instance->recent_recipient_window_hours;
                            $isOn = (bool) $get('exclude_recent_recipients');
                            $color = $isOn ? '#f59e0b' : '#6b7280';
                            $label = $isOn
                                ? sprintf('vor fi <strong>excluși</strong> (au primit alt newsletter în ultimele %dh)', $hours)
                                : sprintf('au primit alt newsletter în ultimele %dh', $hours);
                            $hint = $isOn
                                ? '<div class="text-xs text-amber-600 mt-1">Vor primi: <strong>' . number_format($instance->getRecipientCount()) . '</strong> (după excludere)</div>'
                                : '<div class="text-xs text-gray-500 mt-1">Activează toggle-ul de mai jos pentru a-i exclude din această trimitere.</div>';

                            return new HtmlString(
                                '<div>' .
                                    '<div class="text-2xl font-bold" style="color:' . $color . ';">' . number_format($overlap) . '</div>' .
                                    '<div class="text-xs text-gray-600">' . $label . '</div>' .
                                    $hint .
                                '</div>'
                            );
                        }),

                    Forms\Components\Toggle::make('exclude_recent_recipients')
                        ->label('Exclude destinatarii care au primit newsletter recent')
                        ->helperText('Când e activ, email-urile care au primit deja un newsletter de la acest marketplace în fereastra de mai jos sunt sărite din trimiterea curentă.')
                        ->default(false)
                        ->live(),

                    Forms\Components\TextInput::make('recent_recipient_window_hours')
                        ->label('Fereastră (ore)')
                        ->helperText('Câte ore în urmă să verificăm. Implicit 48h.')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(720)
                        ->default(48)
                        ->live()
                        ->visible(fn (SGet $get) => (bool) $get('exclude_recent_recipients'))
                        ->suffix('ore'),

                    Forms\Components\Placeholder::make('targeted_events_summary')
                        ->label('Evenimente țintă')
                        ->content(function (SGet $get) {
                            $ids = $get('target_event_ids') ?? [];
                            if (empty($ids)) return '—';
                            $names = Event::whereIn('id', $ids)
                                ->limit(5)
                                ->get()
                                ->map(fn ($e) => static::formatEventOption($e))
                                ->implode("\n");
                            return new HtmlString('<div class="space-y-1 text-xs text-gray-700 dark:text-gray-300">' . nl2br(e($names)) . '</div>');
                        })
                        ->visible(fn (SGet $get) => !empty($get('target_event_ids'))),
                    Forms\Components\Placeholder::make('targeted_lists_summary')
                        ->label('Liste țintă')
                        ->content(function (SGet $get) use ($marketplace) {
                            $ids = $get('target_lists') ?? [];
                            if (empty($ids)) return '—';
                            $names = MarketplaceContactList::whereIn('id', $ids)
                                ->where('marketplace_client_id', $marketplace?->id)
                                ->pluck('name')
                                ->implode(', ');
                            return $names ?: '—';
                        })
                        ->visible(fn (SGet $get) => !empty($get('target_lists'))),
                ]),

            SC\Section::make('Variabile disponibile')
                ->schema([
                    Forms\Components\Placeholder::make('variables_info')
                        ->label('')
                        ->content(new HtmlString(
                            '<div class="space-y-3 text-xs">' .
                            '<div>' .
                                '<p class="mb-1 font-medium text-gray-700 dark:text-gray-300">Per destinatar:</p>' .
                                '<div class="flex flex-wrap gap-1">' .
                                    '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-[11px] font-mono">{{customer_name}}</code>' .
                                    '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-[11px] font-mono">{{customer_email}}</code>' .
                                    '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-[11px] font-mono">{{marketplace_name}}</code>' .
                                    '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-[11px] font-mono">{{unsubscribe_url}}</code>' .
                                '</div>' .
                            '</div>' .
                            '<div>' .
                                '<p class="mb-1 font-medium text-gray-700 dark:text-gray-300">Eveniment (înlocuiește ID):</p>' .
                                '<div class="flex flex-wrap gap-1">' .
                                    '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-[11px] font-mono">{{event:ID:name}}</code>' .
                                    '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-[11px] font-mono">{{event:ID:date}}</code>' .
                                    '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-[11px] font-mono">{{event:ID:venue}}</code>' .
                                    '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-[11px] font-mono">{{event:ID:image}}</code>' .
                                    '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-[11px] font-mono">{{event:ID:url}}</code>' .
                                    '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-[11px] font-mono">{{event:ID:price}}</code>' .
                                '</div>' .
                            '</div>' .
                            '</div>'
                        )),
                ])
                ->collapsed(),

            // Trimitere — visible on every state, including draft, so the
            // organizer always sees the campaign's send status in the same
            // place. For an unsent draft the placeholder explains there's
            // nothing to show yet.
            SC\Section::make('Statistici trimitere')
                ->schema([
                    Forms\Components\Placeholder::make('send_stats')
                        ->label('')
                        ->content(function ($record) {
                            if (!$record) {
                                return new HtmlString('<p class="text-xs text-gray-500">Salvează newsletter-ul ca să vezi statisticile.</p>');
                            }
                            if ($record->status === 'draft') {
                                return new HtmlString('<p class="text-xs text-gray-500">Newsletter-ul nu a fost trimis încă. După trimitere apar aici: numărul de destinatari, câți au deschis emailul (open rate) și câți au făcut click (click rate).</p>');
                            }

                            $sent = (int) $record->sent_count;
                            $total = (int) $record->total_recipients;
                            $openHits = (int) $record->opened_count;
                            $clickHits = (int) $record->clicked_count;
                            // Headline numbers + rates use distinct recipients so
                            // image-proxy prefetch (Gmail/Yahoo) and multi-device
                            // re-opens don't inflate the displayed open rate above
                            // 100%. Raw hit totals are still surfaced below as
                            // breakdown rows for transparency.
                            $opened = (int) $record->unique_opens_count;
                            $clicked = (int) $record->unique_clicks_count;
                            $openRate = $sent > 0 ? min(100.0, round(($opened / $sent) * 100, 2)) : 0;
                            $clickRate = $sent > 0 ? min(100.0, round(($clicked / $sent) * 100, 2)) : 0;

                            // Two prominent KPI tiles (Trimis + Open rate),
                            // followed by a compact details list.
                            $html = '<div class="space-y-3">';
                            $html .= '<div class="grid grid-cols-2 gap-2">';
                            $html .= '<div class="rounded-lg bg-green-50 dark:bg-green-900/20 p-2 text-center">'
                                . '<div class="text-xs text-gray-600 dark:text-gray-400">Trimis</div>'
                                . '<div class="text-xl font-bold text-green-700 dark:text-green-400">' . number_format($sent) . '</div>'
                                . '<div class="text-[10px] text-gray-500">din ' . number_format($total) . '</div>'
                                . '</div>';
                            $html .= '<div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 p-2 text-center">'
                                . '<div class="text-xs text-gray-600 dark:text-gray-400">Open rate</div>'
                                . '<div class="text-xl font-bold text-blue-700 dark:text-blue-400">' . $openRate . '%</div>'
                                . '<div class="text-[10px] text-gray-500">' . number_format($opened) . ' au deschis</div>'
                                . '</div>';
                            $html .= '</div>';

                            $html .= '<div class="space-y-1.5 text-xs">';
                            $html .= '<div class="flex items-center justify-between"><span class="text-gray-600">Total destinatari</span><span class="font-semibold">' . number_format($total) . '</span></div>';
                            $html .= '<div class="flex items-center justify-between"><span class="text-gray-600">Trimise</span><span class="font-semibold">' . number_format($sent) . '</span></div>';
                            if ((int) $record->failed_count > 0) {
                                $html .= '<div class="flex items-center justify-between"><span class="text-gray-600">Eșuate</span><span class="font-semibold text-red-600">' . number_format((int) $record->failed_count) . '</span></div>';
                            }
                            $html .= '<div class="flex items-center justify-between"><span class="text-gray-600">Deschise (unic)</span><span class="font-semibold">' . number_format($opened) . ' (' . $openRate . '%)</span></div>';
                            if ($openHits !== $opened) {
                                $html .= '<div class="flex items-center justify-between"><span class="text-gray-500 text-[11px]">↳ hit-uri totale pe pixel</span><span class="text-gray-500 text-[11px]">' . number_format($openHits) . '</span></div>';
                            }
                            $html .= '<div class="flex items-center justify-between"><span class="text-gray-600">Click-uri (unic)</span><span class="font-semibold">' . number_format($clicked) . ' (' . $clickRate . '%)</span></div>';
                            if ($clickHits !== $clicked) {
                                $html .= '<div class="flex items-center justify-between"><span class="text-gray-500 text-[11px]">↳ click-uri totale</span><span class="text-gray-500 text-[11px]">' . number_format($clickHits) . '</span></div>';
                            }
                            if ((int) $record->unsubscribed_count > 0) {
                                $html .= '<div class="flex items-center justify-between"><span class="text-gray-600">Dezabonări</span><span class="font-semibold text-amber-600">' . number_format((int) $record->unsubscribed_count) . '</span></div>';
                            }
                            $html .= '</div>';

                            // Timeline strip: started/completed timestamps so
                            // the organizer can correlate the rates with when
                            // the campaign actually ran.
                            $timeline = [];
                            if ($record->scheduled_at) {
                                $timeline[] = 'Programat: ' . $record->scheduled_at->translatedFormat('d M Y, H:i');
                            }
                            if ($record->started_at) {
                                $timeline[] = 'Început: ' . $record->started_at->translatedFormat('d M Y, H:i');
                            }
                            if ($record->completed_at) {
                                $timeline[] = 'Finalizat: ' . $record->completed_at->translatedFormat('d M Y, H:i');
                            }
                            if (!empty($timeline)) {
                                $html .= '<div class="border-t border-gray-200 dark:border-gray-700 pt-2 space-y-0.5 text-[11px] text-gray-500">';
                                foreach ($timeline as $line) {
                                    $html .= '<div>' . e($line) . '</div>';
                                }
                                $html .= '</div>';
                            }

                            $html .= '</div>';
                            return new HtmlString($html);
                        }),
                ]),
        ];
    }

    /**
     * Build the [city_id => label] map shown in the Oraș filter. Shared
     * between the static options() callback and the diacritic-aware
     * search results closure so both display the same labels.
     */
    protected static function buildCityOptions($marketplace): array
    {
        if (!$marketplace) return [];
        return MarketplaceCity::where('marketplace_client_id', $marketplace->id)
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(function ($city) {
                $name = $city->getTranslation('name', 'ro')
                    ?? $city->getTranslation('name', 'en')
                    ?? '—';
                $label = $city->event_count
                    ? "{$name} ({$city->event_count} evenimente)"
                    : $name;
                return [$city->id => $label];
            })
            ->toArray();
    }

    /**
     * Lowercase + strip diacritics so admin can search "timisoara"
     * and match "Timișoara" / "TIMIȘOARA" / "Timisoara" all the same.
     */
    protected static function normalizeSearch(string $s): string
    {
        return strtolower(\Illuminate\Support\Str::ascii(trim($s)));
    }

    /**
     * Render an event option label like
     *   "Festivalul X (15.07.2026) — Centrul Cultural, Tușnad"
     * Format requested by marketplace admin: title, then date in parens,
     * then venue + city after the em-dash. Falls back gracefully when
     * venue/city is missing or when the event has no event_date.
     */
    public static function formatEventOption(Event $event): string
    {
        $title = $event->getTranslation('title', 'ro')
            ?? $event->getTranslation('title', 'en')
            ?? (is_array($event->title) ? (reset($event->title) ?: '') : ($event->title ?? ''));
        $title = $title ?: 'Eveniment #' . $event->id;

        $dateSrc = $event->event_date ?? $event->range_start_date ?? null;
        $date = $dateSrc ? \Carbon\Carbon::parse($dateSrc)->format('d.m.Y') : null;

        $venueName = '';
        if ($event->venue) {
            $venueName = is_array($event->venue->name)
                ? ($event->venue->name['ro'] ?? $event->venue->name['en'] ?? reset($event->venue->name) ?? '')
                : (string) ($event->venue->name ?? '');
        }
        $venueName = $venueName ?: (string) ($event->suggested_venue_name ?? '');
        $city = (string) ($event->venue?->city ?? $event->city ?? '');
        $venueChunk = trim($venueName . ($venueName && $city ? ', ' : '') . $city);

        $base = $date ? "{$title} ({$date})" : $title;
        return $venueChunk !== '' ? "{$base} — {$venueChunk}" : $base;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subject')
                    ->limit(40)
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'scheduled' => 'warning',
                        'sending' => 'info',
                        'sent' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('total_recipients')
                    ->label('Recipients')
                    ->numeric(),
                Tables\Columns\TextColumn::make('open_rate')
                    ->label('Open Rate')
                    ->suffix('%')
                    ->visible(fn () => true),
                Tables\Columns\TextColumn::make('click_rate')
                    ->label('Click Rate')
                    ->state(function ($record) {
                        $sent = (int) ($record->sent_count ?? 0);
                        if ($sent <= 0) return '—';
                        $clicks = (int) ($record->clicked_count ?? 0);
                        return min(100, round(($clicks / $sent) * 100, 1)) . '%';
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('purchase_count')
                    ->label('Comenzi')
                    ->numeric()
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('purchase_amount_cents')
                    ->label('Venit')
                    ->state(function ($record) {
                        $cents = (int) ($record->purchase_amount_cents ?? 0);
                        if ($cents <= 0) return '—';
                        $currency = $record->marketplaceClient?->currency ?? 'RON';
                        return number_format($cents / 100, 2, ',', '.') . ' ' . $currency;
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('commission_attributed')
                    ->label('Comision')
                    ->state(function ($record) {
                        if (!isset($record->commission_attributed)) return '—';
                        $val = (float) $record->commission_attributed;
                        if ($val <= 0) return '—';
                        $currency = $record->marketplaceClient?->currency ?? 'RON';
                        return number_format($val, 2, ',', '.') . ' ' . $currency;
                    })
                    ->sortable()
                    ->tooltip('Comisionul marketplace-ului încasat din comenzile atribuite acestui newsletter (sum commission_amount pe orders cu status success).'),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Scheduled')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(function ($query) {
                // Single subquery per row to attach SUM(commission_amount)
                // for success-status orders attributed to this newsletter.
                // Avoids N+1 the table column would otherwise trigger and
                // keeps sort-by-commission working without a stored column.
                //
                // selectSub() goes through addSelect() under the hood,
                // which REPLACES the default `*` projection. Without the
                // explicit table.* below, the rows came back with only
                // the subquery column and Filament couldn't resolve `id`
                // to build the edit URL → "Missing parameter: record" 500
                // on /marketplace/newsletters.
                $table = (new \App\Models\MarketplaceNewsletter())->getTable();
                return $query
                    ->addSelect($table . '.*')
                    ->selectSub(
                        \App\Models\Order::selectRaw('COALESCE(SUM(commission_amount), 0)')
                            ->whereColumn('newsletter_attribution_id', $table . '.id')
                            ->whereIn('status', ['paid', 'confirmed', 'completed', 'partially_refunded']),
                        'commission_attributed'
                    );
            })
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'scheduled' => 'Scheduled',
                        'sending' => 'Sending',
                        'sent' => 'Sent',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('send')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Send Newsletter')
                    ->modalDescription('Are you sure you want to send this newsletter? This action cannot be undone.')
                    ->visible(fn ($record) => $record->status === 'draft')
                    ->action(function ($record) {
                        $record->createRecipients();
                        $record->startSending();
                        \App\Jobs\SendNewsletterJob::dispatch($record);
                    }),
                Action::make('schedule')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->form([
                        Forms\Components\DateTimePicker::make('scheduled_at')
                            ->label('Send At')
                            ->required()
                            ->minDate(now()),
                    ])
                    ->visible(fn ($record) => $record->status === 'draft')
                    ->action(function ($record, array $data) {
                        $record->createRecipients();
                        $record->schedule(new \DateTime($data['scheduled_at']));
                    }),
                Action::make('cancel')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'scheduled')
                    ->action(fn ($record) => $record->cancel()),
                Action::make('duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function ($record) {
                        $new = $record->replicate();
                        $new->name = $record->name . ' (Copy)';
                        $new->status = 'draft';
                        $new->scheduled_at = null;
                        $new->started_at = null;
                        $new->completed_at = null;
                        $new->total_recipients = 0;
                        $new->sent_count = 0;
                        $new->failed_count = 0;
                        $new->opened_count = 0;
                        $new->clicked_count = 0;
                        $new->unsubscribed_count = 0;
                        $new->save();

                        return redirect(static::getUrl('edit', ['record' => $new]));
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn ($records) => $records->every(fn ($r) => $r->status === 'draft')),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewsletters::route('/'),
            'create' => Pages\CreateNewsletter::route('/create'),
            'edit' => Pages\EditNewsletter::route('/{record}/edit'),
        ];
    }
}

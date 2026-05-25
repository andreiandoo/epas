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

        return $schema->schema([
            Forms\Components\Hidden::make('marketplace_client_id')
                ->default($marketplace?->id),

            // ============================================================
            // 1. IDENTITATE + MEDIA
            // ============================================================
            SC\Section::make('Identitate')
                ->description('Nume, slug, descrieri și imagini afișate pe pagina publică.')
                ->schema([
                    SC\Tabs::make('Title Translations')
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
                                        ->maxLength(280),
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
                        ])->columnSpanFull(),

                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->maxLength(191)
                        ->rule('alpha_dash')
                        ->placeholder('auto-generate din titlu RO')
                        ->helperText('Folosit în URL public: /activitate/{slug}'),

                    Forms\Components\FileUpload::make('cover_image_url')
                        ->label('Imagine de copertă')
                        ->image()
                        ->disk('public')
                        ->directory('activities/covers')
                        ->visibility('public'),

                    Forms\Components\FileUpload::make('hero_image_url')
                        ->label('Imagine hero (pagină publică)')
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

            // ============================================================
            // 2. LOCAȚIE + CATEGORIE
            // ============================================================
            SC\Section::make('Locație și categorie')
                ->description('Unde se desfășoară activitatea și unde apare în meniurile publice.')
                ->schema([
                    Forms\Components\Select::make('marketplace_organizer_id')
                        ->label('Organizator (locație)')
                        ->options(function () use ($marketplace) {
                            return MarketplaceOrganizer::where('marketplace_client_id', $marketplace?->id)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('venue_id')
                        ->label('Locație fizică (venue)')
                        ->options(function () use ($marketplace) {
                            return Venue::where('marketplace_client_id', $marketplace?->id)
                                ->orderBy('id')
                                ->get()
                                ->mapWithKeys(fn ($v) => [
                                    $v->id => ($v->getTranslation('name', 'ro') ?? $v->getTranslation('name', 'en') ?? 'Venue #'.$v->id)
                                        . ($v->city ? ' — '.$v->city : ''),
                                ])
                                ->toArray();
                        })
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('marketplace_city_id')
                        ->label('Oraș')
                        ->options(function () use ($marketplace) {
                            $lang = $marketplace?->language ?? 'ro';
                            return MarketplaceCity::where('marketplace_client_id', $marketplace?->id)
                                ->orderBy('sort_order')
                                ->get()
                                ->mapWithKeys(fn ($c) => [$c->id => $c->name[$lang] ?? $c->name['en'] ?? $c->slug])
                                ->toArray();
                        })
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('marketplace_category_id')
                        ->label('Categorie principală')
                        ->options(function () use ($marketplace) {
                            $lang = $marketplace?->language ?? 'ro';
                            return MarketplaceCategory::where('marketplace_client_id', $marketplace?->id)
                                ->whereNull('parent_id')
                                ->orderBy('sort_order')
                                ->get()
                                ->mapWithKeys(fn ($c) => [$c->id => $c->name[$lang] ?? $c->name['en'] ?? $c->slug])
                                ->toArray();
                        })
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(fn (\Filament\Schemas\Components\Utilities\Set $set) => $set('marketplace_subcategory_id', null)),

                    Forms\Components\Select::make('marketplace_subcategory_id')
                        ->label('Subcategorie')
                        ->options(function (\Filament\Schemas\Components\Utilities\Get $get) use ($marketplace) {
                            $parentId = $get('marketplace_category_id');
                            if (! $parentId) {
                                return [];
                            }
                            $lang = $marketplace?->language ?? 'ro';
                            return MarketplaceCategory::where('marketplace_client_id', $marketplace?->id)
                                ->where('parent_id', $parentId)
                                ->orderBy('sort_order')
                                ->get()
                                ->mapWithKeys(fn ($c) => [$c->id => $c->name[$lang] ?? $c->name['en'] ?? $c->slug])
                                ->toArray();
                        })
                        ->searchable(),

                    Forms\Components\Textarea::make('meeting_point')
                        ->label('Punct de întâlnire / instrucțiuni de acces')
                        ->rows(2)
                        ->placeholder('ex: Recepția mall-ului, etajul 2, lângă scara rulantă')
                        ->columnSpanFull(),
                ])
                ->columns(2),

            // ============================================================
            // 3. PROGRAM ȘI SLOTURI
            // ============================================================
            SC\Section::make('Program și sloturi')
                ->description('Cum se generează sloturile rezervabile din program + durată + buffer.')
                ->schema([
                    Forms\Components\TextInput::make('duration_minutes')
                        ->label('Durată sesiune (minute)')
                        ->numeric()
                        ->default(60)
                        ->minValue(5)
                        ->maxValue(1440)
                        ->required()
                        ->helperText('Cât durează o sesiune individuală.'),

                    Forms\Components\TextInput::make('slot_interval_minutes')
                        ->label('Interval între sloturi (minute)')
                        ->numeric()
                        ->default(60)
                        ->minValue(5)
                        ->maxValue(1440)
                        ->required()
                        ->helperText('La cât timp pornește următorul slot. Ex: 60 = slot la fiecare oră.'),

                    Forms\Components\TextInput::make('buffer_minutes')
                        ->label('Buffer între sesiuni (minute)')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->maxValue(120)
                        ->helperText('Timp de curățenie/reset.'),

                    Forms\Components\TextInput::make('capacity_per_slot')
                        ->label('Capacitate per slot')
                        ->numeric()
                        ->default(1)
                        ->minValue(1)
                        ->maxValue(1000)
                        ->required()
                        ->helperText('Câți oameni încap în același slot.'),

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
                                            1 => 'Luni',
                                            2 => 'Marți',
                                            3 => 'Miercuri',
                                            4 => 'Joi',
                                            5 => 'Vineri',
                                            6 => 'Sâmbătă',
                                            7 => 'Duminică',
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
                        ->columnSpanFull()
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
                        ->columnSpanFull()
                        ->columns(1),
                ])
                ->columns(4),

            // ============================================================
            // 4. CONSTRAINTS REZERVARE
            // ============================================================
            SC\Section::make('Constraints rezervare')
                ->description('Cât în avans poate rezerva un client.')
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
                        ->helperText('Ex: 2 = nu se poate rezerva cu mai puțin de 2 ore înainte.'),

                    Forms\Components\TextInput::make('booking_max_advance_days')
                        ->label('Maxim avans (zile)')
                        ->numeric()
                        ->default(60)
                        ->minValue(1)
                        ->helperText('Cât în avans se poate rezerva.'),

                    Forms\Components\Textarea::make('cancellation_policy')
                        ->label('Politică de anulare')
                        ->rows(3)
                        ->placeholder('ex: Anularea cu minim 24h înainte aduce refund integral.')
                        ->columnSpanFull(),
                ])
                ->columns(2),

            // ============================================================
            // 5. CONȚINUT SEO + FAQs
            // ============================================================
            SC\Section::make('Conținut SEO + FAQs')
                ->description('Body editorial + întrebări frecvente. Apar pe pagina publică și în JSON-LD FAQPage.')
                ->collapsed()
                ->schema([
                    SC\Tabs::make('SEO Body Translations')
                        ->tabs([
                            SC\Tabs\Tab::make('Română')
                                ->schema([
                                    Forms\Components\TextInput::make('seo_body_title.ro')
                                        ->label('Titlu corp SEO (RO)')
                                        ->maxLength(190),
                                    Forms\Components\RichEditor::make('seo_body.ro')
                                        ->label('Corp text (RO)')
                                        ->toolbarButtons(['bold', 'italic', 'link', 'h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'undo', 'redo'])
                                        ->columnSpanFull(),
                                ]),
                            SC\Tabs\Tab::make('English')
                                ->schema([
                                    Forms\Components\TextInput::make('seo_body_title.en')
                                        ->label('SEO body title (EN)')
                                        ->maxLength(190),
                                    Forms\Components\RichEditor::make('seo_body.en')
                                        ->label('Body (EN)')
                                        ->toolbarButtons(['bold', 'italic', 'link', 'h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'undo', 'redo'])
                                        ->columnSpanFull(),
                                ]),
                        ])->columnSpanFull(),

                    Forms\Components\Repeater::make('faqs')
                        ->label('Întrebări frecvente')
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

                    SC\Tabs::make('Meta SEO')
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
                        ])->columnSpanFull(),
                ])
                ->columns(1),

            // ============================================================
            // 6. FLAGS + AUDIENCE
            // ============================================================
            SC\Section::make('Flag-uri și audiență')
                ->description('Caracteristici care alimentează filtrele de intenție și listing-urile featured.')
                ->collapsed()
                ->schema([
                    Forms\Components\Toggle::make('is_published')
                        ->label('Publicat')
                        ->helperText('Doar activitățile publicate apar pe site.')
                        ->default(false),

                    Forms\Components\Toggle::make('is_featured')->label('Promovat'),
                    Forms\Components\Toggle::make('is_homepage_featured')->label('Featured pe homepage'),
                    Forms\Components\Toggle::make('is_category_featured')->label('Featured pe pagina de categorie'),
                    Forms\Components\Toggle::make('is_city_featured')->label('Featured pe pagina de oraș'),

                    Forms\Components\Toggle::make('is_indoor')->label('Indoor'),
                    Forms\Components\Toggle::make('is_outdoor')->label('Outdoor'),
                    Forms\Components\Toggle::make('is_kid_friendly')->label('Potrivit copiilor'),
                    Forms\Components\Toggle::make('is_accessible')->label('Accesibil persoanelor cu dizabilități'),
                    Forms\Components\Toggle::make('is_weather_sensitive')->label('Depinde de vreme'),

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
                ->columns(3),
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
                    ->searchable()
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

<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\AffiliateEventResource\Pages;
use App\Filament\Marketplace\Resources\VenueResource;
use App\Models\AffiliateEventSource;
use App\Models\Event;
use App\Models\EventGenre;
use App\Models\EventTag;
use App\Models\EventType;
use App\Models\MarketplaceCity;
use App\Models\MarketplaceEventCategory;
use App\Models\MarketplaceRegion;
use App\Models\Venue;
use Filament\Forms;
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
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class AffiliateEventResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = Event::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-top-right-on-square';
    protected static \UnitEnum|string|null $navigationGroup = 'Content';
    protected static ?int $navigationSort = 9;
    protected static ?string $navigationLabel = 'Evenimente Afiliere';
    protected static ?string $modelLabel = 'Eveniment Afiliere';
    protected static ?string $pluralModelLabel = 'Evenimente Afiliere';
    protected static ?string $slug = 'affiliate-events';

    public static function getNavigationBadge(): ?string
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) {
            return null;
        }

        $count = static::getEloquentQuery()->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();

        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $marketplace?->id)
            ->where('is_affiliate', true);
    }

    public static function form(Schema $schema): Schema
    {
        $today = Carbon::today();
        $marketplace = static::getMarketplaceClient();

        $lang = $marketplace->language ?? $marketplace->locale ?? null;
        $marketplaceLanguage = (!empty($lang)) ? $lang : 'ro';

        $t = function (string $ro, string $en) use ($marketplaceLanguage): string {
            return $marketplaceLanguage === 'ro' ? $ro : $en;
        };

        return $schema->schema([
            Forms\Components\Hidden::make('marketplace_client_id')
                ->default($marketplace?->id),
            Forms\Components\Hidden::make('is_affiliate')
                ->default(true),

            SC\Grid::make(4)->schema([
                // ========== LEFT COLUMN (3/4) ==========
                SC\Group::make()
                    ->columnSpan(3)
                    ->schema([
                        SC\Tabs::make('AffiliateEventTabs')
                            ->persistTabInQueryString()
                            ->tabs([
                                // ========== TAB 1: DETALII ==========
                                SC\Tabs\Tab::make($t('Detalii', 'Details'))
                                    ->key('detalii')
                                    ->icon('heroicon-o-document-text')
                                    ->schema([
                        // AFFILIATE SOURCE & URL
                        SC\Section::make($t('Sursă Afiliere', 'Affiliate Source'))
                            ->description($t(
                                'Sursa externă de unde provine evenimentul. Vânzarea se face pe website-ul sursă.',
                                'External source of the event. Sale happens on the source website.'
                            ))
                            ->icon('heroicon-o-arrow-top-right-on-square')
                            ->schema([
                                SC\Grid::make(2)->schema([
                                    Forms\Components\Select::make('affiliate_event_source_id')
                                        ->label($t('Sursa', 'Source'))
                                        ->options(function () use ($marketplace) {
                                            return AffiliateEventSource::query()
                                                ->where('marketplace_client_id', $marketplace?->id)
                                                ->where('status', 'active')
                                                ->orderBy('name')
                                                ->pluck('name', 'id');
                                        })
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->createOptionForm([
                                            Forms\Components\TextInput::make('name')
                                                ->label($t('Nume sursă', 'Source name'))
                                                ->required()
                                                ->maxLength(190),
                                            Forms\Components\TextInput::make('website_url')
                                                ->label('Website URL')
                                                ->url()
                                                ->maxLength(500),
                                        ])
                                        ->createOptionUsing(function (array $data) use ($marketplace): int {
                                            $source = AffiliateEventSource::create([
                                                'marketplace_client_id' => $marketplace?->id,
                                                'name' => $data['name'],
                                                'slug' => Str::slug($data['name']),
                                                'website_url' => $data['website_url'] ?? null,
                                                'status' => 'active',
                                            ]);
                                            return $source->id;
                                        }),
                                    Forms\Components\TextInput::make('affiliate_url')
                                        ->label($t('URL Cumpărare Bilete', 'Ticket Purchase URL'))
                                        ->helperText($t(
                                            'Link-ul unde utilizatorul va fi redirecționat pentru a cumpăra bilete',
                                            'The link where the user will be redirected to buy tickets'
                                        ))
                                        ->url()
                                        ->required()
                                        ->maxLength(1000)
                                        ->placeholder('https://www.exemplu.ro/eveniment/bilete'),
                                ]),
                            ]),

                        // EVENT DETAILS
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
                                                if ($state) {
                                                    $baseSlug = Str::slug($state);
                                                    if ($record && $record->exists && $record->id) {
                                                        $set('slug', $baseSlug . '-' . $record->id);
                                                        if (!$record->event_series) {
                                                            $set('event_series', 'AMB-' . $record->id);
                                                        }
                                                    } else {
                                                        $nextId = (Event::max('id') ?? 0) + 1;
                                                        $set('slug', $baseSlug . '-' . $nextId);
                                                        $set('event_series', 'AMB-' . $nextId);
                                                    }
                                                }
                                            }),
                                        Forms\Components\TextInput::make('slug')
                                            ->label('Slug')
                                            ->maxLength(190)
                                            ->rule('alpha_dash'),
                                        Forms\Components\TextInput::make('event_series')
                                            ->label($t('Serie eveniment', 'Event series'))
                                            ->placeholder('AMB-[ID]')
                                            ->maxLength(50)
                                            ->disabled(fn (?Event $record) => $record && $record->exists && $record->event_series)
                                            ->dehydrated(true),
                                    ])->columns(3)->columnSpanFull(),
                            ]),

                        // CATEGORY
                        SC\Section::make($t('Categorie', 'Category'))
                            ->schema([
                                SC\Grid::make(2)->schema([
                                    Forms\Components\Select::make('marketplace_event_category_id')
                                        ->label($t('Categorie', 'Category'))
                                        ->options(function () use ($marketplace, $marketplaceLanguage) {
                                            return MarketplaceEventCategory::query()
                                                ->where('marketplace_client_id', $marketplace?->id)
                                                ->orderBy('sort_order')
                                                ->get()
                                                ->mapWithKeys(fn ($cat) => [
                                                    $cat->id => $cat->name[$marketplaceLanguage] ?? $cat->name['ro'] ?? 'Unnamed'
                                                ]);
                                        })
                                        ->searchable()
                                        ->preload()
                                        ->nullable(),
                                    Forms\Components\TextInput::make('target_price')
                                        ->label($t('Preț orientativ (RON)', 'Target price (RON)'))
                                        ->helperText($t(
                                            'Prețul minim/de referință afișat pe site',
                                            'Minimum/reference price displayed on the website'
                                        ))
                                        ->numeric()
                                        ->step(0.01)
                                        ->minValue(0)
                                        ->prefix('RON'),
                                ]),
                            ])
                            ->collapsible(),

                        // FEATURED
                        SC\Section::make($t('Setări Featured', 'Featured Settings'))
                            ->schema([
                                SC\Grid::make(3)->schema([
                                    Forms\Components\Toggle::make('is_homepage_featured')
                                        ->label($t('Featured pe Homepage', 'Homepage Featured'))
                                        ->onIcon('heroicon-m-home')
                                        ->offIcon('heroicon-m-home'),
                                    Forms\Components\Toggle::make('is_general_featured')
                                        ->label($t('Featured General', 'General Featured'))
                                        ->onIcon('heroicon-m-star')
                                        ->offIcon('heroicon-m-star'),
                                    Forms\Components\Toggle::make('is_category_featured')
                                        ->label($t('Featured în Categorie', 'Category Featured'))
                                        ->onIcon('heroicon-m-tag')
                                        ->offIcon('heroicon-m-tag'),
                                ]),
                            ])
                            ->collapsible()
                            ->collapsed(),
                                    ]), // End Tab 1

                                // ========== TAB 2: PROGRAM ==========
                                SC\Tabs\Tab::make($t('Program', 'Schedule'))
                                    ->key('program')
                                    ->icon('heroicon-o-calendar')
                                    ->schema([
                        SC\Section::make($t('Program', 'Schedule'))
                            ->schema([
                                Forms\Components\Radio::make('duration_mode')
                                    ->label($t('Durată', 'Duration'))
                                    ->options([
                                        'single_day' => $t('O singură zi', 'Single day'),
                                        'range' => $t('Interval', 'Range'),
                                        'multi_day' => $t('Mai multe zile', 'Multiple days'),
                                    ])
                                    ->inline()
                                    ->default('single_day')
                                    ->required()
                                    ->live(),

                                // Single day
                                SC\Grid::make(4)->schema([
                                    Forms\Components\DatePicker::make('event_date')
                                        ->label($t('Data', 'Date'))
                                        ->native(false),
                                    Forms\Components\TimePicker::make('start_time')
                                        ->label($t('Ora start', 'Start time'))
                                        ->seconds(false)
                                        ->native(true),
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
                                            ->native(false)
                                            ->required(),
                                        Forms\Components\TimePicker::make('start_time')
                                            ->label($t('Start', 'Start'))
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
                                    ->columns(3),
                            ])->columns(1),

                        // LOCATION
                        SC\Section::make($t('Locație', 'Location'))
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

                                                if ($venue->city) {
                                                    $cityName = strtolower(trim($venue->city));
                                                    $matchedCity = MarketplaceCity::where('marketplace_client_id', $marketplace?->id)
                                                        ->where('is_visible', true)
                                                        ->get()
                                                        ->first(function ($city) use ($cityName) {
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
                                            ->tooltip($t('Adaugă locație nouă', 'Add new venue'))
                                            ->url(fn () => VenueResource::getUrl('create'))
                                            ->openUrlInNewTab()
                                    )
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
                                    ->nullable(),
                                Forms\Components\TextInput::make('address')
                                    ->label($t('Adresă', 'Address'))
                                    ->maxLength(255),
                            ])->columns(2),
                                    ]), // End Tab 2

                                // ========== TAB 3: CONȚINUT ==========
                                SC\Tabs\Tab::make($t('Conținut', 'Content'))
                                    ->key('continut')
                                    ->icon('heroicon-o-pencil-square')
                                    ->schema([
                        SC\Section::make($t('Descriere', 'Description'))
                            ->schema([
                                Forms\Components\Textarea::make("short_description.{$marketplaceLanguage}")
                                    ->label($t('Descriere scurtă', 'Short description'))
                                    ->rows(3)
                                    ->maxLength(500),
                                Forms\Components\RichEditor::make("description.{$marketplaceLanguage}")
                                    ->label($t('Descriere completă', 'Full description'))
                                    ->columnSpanFull(),
                            ]),

                        SC\Section::make($t('Taxonomii', 'Taxonomies'))
                            ->schema([
                                Forms\Components\Select::make('eventTypes')
                                    ->label($t('Tipuri eveniment', 'Event types'))
                                    ->multiple()
                                    ->relationship('eventTypes', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),
                                Forms\Components\Select::make('eventGenres')
                                    ->label($t('Genuri', 'Genres'))
                                    ->multiple()
                                    ->relationship('eventGenres', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),
                                Forms\Components\Select::make('tags')
                                    ->label($t('Tag-uri', 'Tags'))
                                    ->multiple()
                                    ->relationship('tags', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),
                            ])->columns(3)
                            ->collapsible(),

                        SC\Section::make($t('Artiști', 'Artists'))
                            ->schema([
                                Forms\Components\Select::make('artists')
                                    ->label($t('Artiști', 'Artists'))
                                    ->multiple()
                                    ->relationship('artists', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),
                            ])
                            ->collapsible(),

                        // MEDIA
                        SC\Section::make('Media')
                            ->schema([
                                Forms\Components\TextInput::make('poster_url')
                                    ->label($t('URL Poster', 'Poster URL'))
                                    ->helperText($t(
                                        'Link direct către imaginea posterului (vertical, 600x900px recomandat)',
                                        'Direct link to the poster image (vertical, 600x900px recommended)'
                                    ))
                                    ->url()
                                    ->maxLength(1000),
                                Forms\Components\TextInput::make('hero_image_url')
                                    ->label($t('URL Imagine Hero', 'Hero Image URL'))
                                    ->helperText($t(
                                        'Link direct către imaginea hero (orizontal, 1920x600px recomandat)',
                                        'Direct link to the hero image (horizontal, 1920x600px recommended)'
                                    ))
                                    ->url()
                                    ->maxLength(1000),
                            ])->columns(2)
                            ->collapsible(),
                                    ]), // End Tab 3
                            ]),
                    ]),

                // ========== RIGHT COLUMN (1/4) - SIDEBAR ==========
                SC\Group::make()
                    ->columnSpan(1)
                    ->schema([
                        // PUBLISH
                        SC\Section::make($t('Publicare', 'Publishing'))
                            ->schema([
                                Forms\Components\Toggle::make('is_published')
                                    ->label($t('Publicat', 'Published'))
                                    ->default(true)
                                    ->onIcon('heroicon-m-eye')
                                    ->offIcon('heroicon-m-eye-slash'),
                            ]),

                        // AFFILIATE INFO (shown on edit)
                        SC\Section::make($t('Informații Afiliere', 'Affiliate Info'))
                            ->visible(fn (?Event $record) => $record && $record->exists)
                            ->schema(fn (?Event $record) => $record ? [
                                Forms\Components\Placeholder::make('source_name')
                                    ->label($t('Sursă', 'Source'))
                                    ->content($record->affiliateEventSource?->name ?? '—'),
                                Forms\Components\Placeholder::make('affiliate_link')
                                    ->label($t('Link cumpărare', 'Purchase link'))
                                    ->content(fn () => $record->affiliate_url
                                        ? new \Illuminate\Support\HtmlString(
                                            '<a href="' . e($record->affiliate_url) . '" target="_blank" class="text-primary-500 underline text-sm truncate block">'
                                            . e(Str::limit($record->affiliate_url, 40))
                                            . '</a>'
                                        )
                                        : '—'
                                    ),
                            ] : []),

                        // EVENT STATUS
                        SC\Section::make($t('Status Eveniment', 'Event Status'))
                            ->visible(fn (?Event $record) => $record && $record->exists)
                            ->schema(fn (?Event $record) => $record ? [
                                Forms\Components\Placeholder::make('views')
                                    ->label($t('Vizualizări', 'Views'))
                                    ->content($record->views_count ?? 0),
                                Forms\Components\Placeholder::make('interested')
                                    ->label($t('Interesați', 'Interested'))
                                    ->content($record->interested_count ?? 0),
                                Forms\Components\Placeholder::make('created')
                                    ->label($t('Creat la', 'Created at'))
                                    ->content($record->created_at?->format('d.m.Y H:i')),
                            ] : []),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $marketplace = static::getMarketplaceClient();
        $lang = $marketplace->language ?? $marketplace->locale ?? 'ro';

        return $table
            ->columns([
                Tables\Columns\TextColumn::make("title.{$lang}")
                    ->label('Titlu')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('affiliateEventSource.name')
                    ->label('Sursă')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('marketplace_city_id')
                    ->label('Oraș')
                    ->formatStateUsing(function ($state) use ($lang) {
                        if (!$state) return '—';
                        $city = MarketplaceCity::find($state);
                        return $city ? ($city->name[$lang] ?? $city->name['ro'] ?? '—') : '—';
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('event_date')
                    ->label('Data')
                    ->formatStateUsing(function ($state, Event $record) {
                        return match ($record->duration_mode) {
                            'single_day' => $record->event_date?->format('d.m.Y'),
                            'range' => ($record->range_start_date?->format('d.m.Y') ?? '') . ' - ' . ($record->range_end_date?->format('d.m.Y') ?? ''),
                            'multi_day' => isset($record->multi_slots[0]['date'])
                                ? Carbon::parse($record->multi_slots[0]['date'])->format('d.m.Y') . ' (+' . (count($record->multi_slots) - 1) . ')'
                                : '—',
                            default => $state ? Carbon::parse($state)->format('d.m.Y') : '—',
                        };
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('target_price')
                    ->label('Preț')
                    ->formatStateUsing(fn ($state) => $state ? number_format((float)$state, 2) . ' RON' : '—')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_published')
                    ->label('Publicat')
                    ->boolean()
                    ->trueIcon('heroicon-o-eye')
                    ->falseIcon('heroicon-o-eye-slash')
                    ->trueColor('success')
                    ->falseColor('gray'),
                Tables\Columns\TextColumn::make('affiliate_url')
                    ->label('URL Afiliere')
                    ->limit(30)
                    ->url(fn ($record) => $record->affiliate_url, shouldOpenInNewTab: true)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('views_count')
                    ->label('Views')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creat la')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('affiliate_event_source_id')
                    ->label('Sursă')
                    ->options(function () use ($marketplace) {
                        return AffiliateEventSource::query()
                            ->where('marketplace_client_id', $marketplace?->id)
                            ->pluck('name', 'id');
                    }),
                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('Publicat'),
                Tables\Filters\SelectFilter::make('marketplace_event_category_id')
                    ->label('Categorie')
                    ->options(function () use ($marketplace, $lang) {
                        return MarketplaceEventCategory::query()
                            ->where('marketplace_client_id', $marketplace?->id)
                            ->get()
                            ->mapWithKeys(fn ($cat) => [
                                $cat->id => $cat->name[$lang] ?? $cat->name['ro'] ?? 'Unnamed'
                            ]);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('open_affiliate')
                    ->label('Deschide')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('info')
                    ->url(fn (Event $record) => $record->affiliate_url)
                    ->openUrlInNewTab()
                    ->visible(fn (Event $record) => !empty($record->affiliate_url)),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAffiliateEvents::route('/'),
            'create' => Pages\CreateAffiliateEvent::route('/create'),
            'edit' => Pages\EditAffiliateEvent::route('/{record}/edit'),
        ];
    }
}

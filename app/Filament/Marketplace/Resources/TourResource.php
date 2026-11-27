<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\TourResource\Pages;
use App\Models\Artist;
use App\Models\MarketplaceOrganizer;
use App\Models\Tour;
use App\Support\SearchHelper;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Utilities\Set as SSet;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class TourResource extends Resource
{
    use \App\Filament\Marketplace\Concerns\TranslatesResourceLabels;

    use HasMarketplaceContext;

    protected static ?string $model = Tour::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-microphone';
    protected static ?string $navigationLabel = 'Turnee';
    protected static ?string $modelLabel = 'Turneu';
    protected static ?string $pluralModelLabel = 'Turnee';
    protected static \UnitEnum|string|null $navigationGroup = null;
    protected static ?int $navigationSort = 5;

    public static function getNavigationBadge(): ?string
    {
        try {
            $marketplace = static::getMarketplaceClient();
            if (!$marketplace) return null;
            return (string) static::getEloquentQuery()->count();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function getEloquentQuery(): Builder
    {
        $marketplaceId = static::getMarketplaceClientId();
        return parent::getEloquentQuery()
            ->when($marketplaceId, fn ($q) => $q->where('marketplace_client_id', $marketplaceId));
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();

        return $schema->schema([
            SC\Grid::make(4)->schema([
                // ============================================
                // Main column (3/4)
                // ============================================
                SC\Group::make()->columnSpan(3)->schema([

                    // IDENTITATE
                    SC\Section::make(__('Identitate'))
                        ->icon('heroicon-o-identification')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label(__('Nume turneu'))
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function ($state, SSet $set, $context) {
                                    if ($state && $context === 'create') {
                                        $set('slug', Str::slug($state));
                                    }
                                }),
                            Forms\Components\TextInput::make('slug')
                                ->label(__('Slug'))
                                ->required()
                                ->maxLength(190)
                                ->unique(ignoreRecord: true)
                                ->rule('alpha_dash')
                                ->placeholder('auto-generat-din-nume'),
                            Forms\Components\Radio::make('type')
                                ->label(__('Tip grupare'))
                                ->options([
                                    'serie_evenimente' => __('Serie evenimente'),
                                    'turneu' => __('Turneu'),
                                ])
                                ->default('turneu')
                                ->inline()
                                ->required(),
                            Forms\Components\Select::make('artist_id')
                                ->label(__('Artist principal'))
                                ->options(fn () => Artist::query()
                                    ->when($marketplace, fn ($q) => $q->whereHas(
                                        'marketplaceClients',
                                        fn ($q2) => $q2->where('marketplace_artist_partners.marketplace_client_id', $marketplace->id)
                                    ))
                                    ->orderBy('name')
                                    ->limit(500)
                                    ->pluck('name', 'id')
                                    ->toArray())
                                ->searchable()
                                ->required()
                                ->helperText(__('Acest artist va putea selecta turneul în EventResource → Tab "Turneu".')),
                        ])
                        ->columns(2),

                    // IMAGINI
                    SC\Section::make(__('Imagini'))
                        ->icon('heroicon-o-photo')
                        ->schema([
                            Forms\Components\FileUpload::make('cover_url')
                                ->label(__('Imagine principală (landscape)'))
                                ->helperText(__('Recomandat: 1600×900 px. Apare în hero pe pagina turneului.'))
                                ->image()
                                ->disk('public')
                                ->directory('tours')
                                ->visibility('public')
                                ->maxSize(10240)
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                ->afterStateUpdated(fn ($livewire) => $livewire->skipRender()),
                            Forms\Components\FileUpload::make('poster_url')
                                ->label(__('Poster (portrait)'))
                                ->helperText(__('Recomandat: 1080×1620 px. Apare lângă hero pe mobile / în carduri.'))
                                ->image()
                                ->disk('public')
                                ->directory('tours')
                                ->visibility('public')
                                ->maxSize(10240)
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                ->afterStateUpdated(fn ($livewire) => $livewire->skipRender()),
                        ])
                        ->columns(2),

                    // DESCRIERI
                    SC\Section::make(__('Descrieri'))
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            SC\Tabs::make('descriptions')
                                ->tabs([
                                    SC\Tabs\Tab::make('Română')
                                        ->schema([
                                            Forms\Components\Textarea::make('short_description.ro')
                                                ->label(__('Descriere scurtă (RO)'))
                                                ->rows(3)
                                                ->maxLength(500)
                                                ->helperText(__('Apare în hero / metadate.'))
                                                ->columnSpanFull(),
                                            Forms\Components\RichEditor::make('description.ro')
                                                ->label(__('Descriere detaliată (RO)'))
                                                ->columnSpanFull(),
                                        ]),
                                    SC\Tabs\Tab::make('English')
                                        ->schema([
                                            Forms\Components\Textarea::make('short_description.en')
                                                ->label(__('Short description (EN)'))
                                                ->rows(3)
                                                ->maxLength(500)
                                                ->columnSpanFull(),
                                            Forms\Components\RichEditor::make('description.en')
                                                ->label(__('Description (EN)'))
                                                ->columnSpanFull(),
                                        ]),
                                ])
                                ->columnSpanFull(),
                        ]),

                    // SETLIST
                    SC\Section::make(__('Setlist'))
                        ->icon('heroicon-o-musical-note')
                        ->description(__('Lista pieselor cântate pe turneu, în ordine.'))
                        ->collapsible()->collapsed()->persistCollapsed()
                        ->schema([
                            Forms\Components\Repeater::make('setlist')
                                ->hiddenLabel()
                                ->schema([
                                    Forms\Components\TextInput::make('title')
                                        ->label(__('Titlu piesă'))
                                        ->required()
                                        ->maxLength(255),
                                ])
                                ->reorderable()
                                ->orderColumn('sort_order')
                                ->defaultItems(0)
                                ->addActionLabel(__('Adaugă piesă'))
                                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                                ->collapsible()
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('setlist_duration_minutes')
                                ->label(__('Durată totală setlist'))
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(600)
                                ->suffix('minute')
                                ->helperText(__('Durata generală a întregului setlist.')),
                        ]),

                    // FAQ
                    SC\Section::make(__('Întrebări frecvente (FAQ)'))
                        ->icon('heroicon-o-question-mark-circle')
                        ->collapsible()->collapsed()->persistCollapsed()
                        ->schema([
                            Forms\Components\Repeater::make('faq')
                                ->hiddenLabel()
                                ->schema([
                                    Forms\Components\TextInput::make('question')
                                        ->label(__('Întrebare'))
                                        ->required()
                                        ->maxLength(255),
                                    Forms\Components\Textarea::make('answer')
                                        ->label(__('Răspuns'))
                                        ->required()
                                        ->rows(3)
                                        ->maxLength(2000),
                                ])
                                ->reorderable()
                                ->defaultItems(0)
                                ->addActionLabel(__('Adaugă întrebare'))
                                ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
                                ->collapsible()
                                ->columnSpanFull(),
                        ]),

                    // ALTE DETALII
                    SC\Section::make(__('Alte detalii'))
                        ->icon('heroicon-o-information-circle')
                        ->schema([
                            Forms\Components\TextInput::make('age_min')
                                ->label(__('Vârstă minimă'))
                                ->placeholder(__('ex: 18+, 16+, fără restricție'))
                                ->maxLength(20),
                            Forms\Components\Select::make('marketplace_organizer_id')
                                ->label(__('Organizator'))
                                ->options(fn () => MarketplaceOrganizer::query()
                                    ->when($marketplace, fn ($q) => $q->where('marketplace_client_id', $marketplace->id))
                                    ->orderBy('name')
                                    ->limit(500)
                                    ->pluck('name', 'id')
                                    ->toArray())
                                ->searchable()
                                ->placeholder(__('Selectează organizator')),
                        ])
                        ->columns(2),

                    // EVENIMENTE ASOCIATE — read-only on edit
                    SC\Section::make(__('Evenimente asociate'))
                        ->icon('heroicon-o-calendar-days')
                        ->description(__('Sumarizare a evenimentelor atașate acestui turneu. Pentru a atașa un eveniment, mergi la editarea evenimentului → tab Turneu.'))
                        ->visible(fn ($record) => $record !== null)
                        ->schema([
                            Forms\Components\Placeholder::make('linked_events_view')
                                ->label('')
                                ->content(fn ($record) => new \Illuminate\Support\HtmlString(
                                    view('filament.marketplace.resources.tours.linked-events', ['tour' => $record])->render()
                                ))
                                ->columnSpanFull(),
                        ]),
                ]),

                // ============================================
                // Sidebar (1/4)
                // ============================================
                SC\Group::make()->columnSpan(1)->schema([
                    SC\Section::make(__('Status'))
                        ->icon('heroicon-o-flag')
                        ->schema([
                            Forms\Components\Select::make('status')
                                ->label(__('Status'))
                                ->options([
                                    'planning' => __('În planificare'),
                                    'on_sale' => __('În vânzare'),
                                    'completed' => __('Finalizat'),
                                    'cancelled' => __('Anulat'),
                                ])
                                ->default('planning')
                                ->required(),
                            Forms\Components\Textarea::make('routing_notes')
                                ->label(__('Note interne (routing/logistică)'))
                                ->rows(4)
                                ->maxLength(2000),
                        ]),

                    SC\Section::make(__('Înregistrare'))
                        ->icon('heroicon-o-clock')
                        ->visible(fn ($record) => $record !== null)
                        ->schema([
                            Forms\Components\Placeholder::make('created_at')
                                ->label(__('Creat la'))
                                ->content(fn ($record) => \App\Support\MarketplaceTz::fmt($record?->created_at, 'd.m.Y H:i', fallback: '—')),
                            Forms\Components\Placeholder::make('updated_at')
                                ->label(__('Actualizat la'))
                                ->content(fn ($record) => \App\Support\MarketplaceTz::fmt($record?->updated_at, 'd.m.Y H:i', fallback: '—')),
                        ]),
                ]),
            ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('cover_url')
                    ->label('')
                    ->disk('public')
                    ->square()
                    ->size(48),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Nume'))
                    ->searchable(query: fn ($query, string $search) => SearchHelper::search($query, 'name', $search))
                    ->sortable()
                    ->wrap()
                    ->url(fn (Tour $record) => static::getUrl('edit', ['record' => $record->getKey()])),
                Tables\Columns\TextColumn::make('artist.name')
                    ->label(__('Artist'))
                    ->sortable()
                    ->searchable(query: fn ($query, string $search) => $query->whereHas(
                        'artist',
                        fn ($q) => SearchHelper::search($q, 'name', $search)
                    )),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('Tip'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'serie_evenimente' => 'Serie evenimente',
                        'turneu' => 'Turneu',
                        default => $state ?: '—',
                    })
                    ->color(fn ($state) => $state === 'turneu' ? 'info' : 'gray'),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'planning' => 'În planificare',
                        'on_sale' => 'În vânzare',
                        'completed' => 'Finalizat',
                        'cancelled' => 'Anulat',
                        default => $state ?: '—',
                    })
                    ->color(fn ($state) => match ($state) {
                        'planning' => 'gray',
                        'on_sale' => 'success',
                        'completed' => 'info',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('event_count')
                    ->label(__('Evenimente'))
                    ->getStateUsing(fn (Tour $r) => $r->events()->count())
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('period')
                    ->label(__('Perioadă'))
                    ->getStateUsing(function (Tour $r) {
                        $p = $r->period;
                        if (!$p['start']) return '—';
                        if (!$p['end'] || $p['start']->isSameDay($p['end'])) {
                            return $p['start']->format('d.m.Y');
                        }
                        return $p['start']->format('d.m.Y') . ' → ' . $p['end']->format('d.m.Y');
                    }),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Ultima modificare'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label(__('Tip'))
                    ->options([
                        'serie_evenimente' => __('Serie evenimente'),
                        'turneu' => __('Turneu'),
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        'planning' => __('În planificare'),
                        'on_sale' => __('În vânzare'),
                        'completed' => __('Finalizat'),
                        'cancelled' => __('Anulat'),
                    ]),
                Tables\Filters\SelectFilter::make('artist_id')
                    ->label(__('Artist'))
                    ->relationship('artist', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTours::route('/'),
            'create' => Pages\CreateTour::route('/create'),
            'edit' => Pages\EditTour::route('/{record}/edit'),
        ];
    }
}

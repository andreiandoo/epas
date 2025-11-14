<?php

namespace App\Filament\Resources\Artists;

use App\Filament\Resources\Artists\Pages\CreateArtist;
use App\Filament\Resources\Artists\Pages\EditArtist;
use App\Filament\Resources\Artists\Pages\ListArtists;
use App\Filament\Resources\Artists\Pages\ViewArtist;
use App\Filament\Resources\Artists\Pages\ArtistStats;
use Illuminate\Database\Eloquent\Builder;
use App\Support\Locations;
use Filament\Forms;
use App\Models\Artist;
use App\Models\ArtistGenre;
use App\Models\ArtistType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\DB;

class ArtistResource extends Resource
{
    protected static ?string $model = Artist::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user';
    protected static \UnitEnum|string|null $navigationGroup = 'Catalog';
    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            // === BASICS (flat, fără container) ===
            SC\Group::make()
                ->extraAttributes(['id' => 'artist-basics','data-ep-section'=>'basics'])
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Artist name')
                        ->required()
                        ->maxLength(190)
                        ->placeholder('Type the artist name…')
                        ->extraAttributes(['class' => 'ep-title']) // titlu mare (ai deja CSS-ul global)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, Set $set) {
                            if ($state) $set('slug', Str::slug($state));
                        }),

                    SC\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->helperText('Editable URL slug.')
                            ->maxLength(190)
                            ->rule('alpha_dash')
                            ->unique(ignoreRecord: true)
                            ->prefixIcon('heroicon-m-link'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ]),
                ]),

            // === CONTENT ===
            SC\Section::make('Content')
                ->extraAttributes(['id' => 'artist-content'])
                ->schema([
                    Forms\Components\RichEditor::make('bio_html.en')
                        ->label('Bio (EN)')
                        ->default('')           // editorul primește mereu string, nu null
                        ->columnSpanFull()
                        ->placeholder('Write the artist bio (HTML allowed)…'),
                ])
                ->columns(1),

            // === SOCIAL & IDs ===
            SC\Section::make('Social & IDs')
                ->extraAttributes(['id' => 'artist-social'])
                ->schema([
                    Forms\Components\TextInput::make('website')
                        ->label('Website')
                        ->url()->rule('url')->maxLength(255)
                        ->prefixIcon('heroicon-m-globe-alt')
                        ->placeholder('https://example.com'),

                    Forms\Components\TextInput::make('facebook_url')
                        ->label('Facebook profile')
                        ->default('https://www.facebook.com/')
                        ->url()->rule('url')->maxLength(255)
                        ->prefixIcon('heroicon-m-link'),

                    Forms\Components\TextInput::make('instagram_url')
                        ->label('Instagram profile')
                        ->default('https://www.instagram.com/')
                        ->url()->rule('url')->maxLength(255)
                        ->prefixIcon('heroicon-m-link'),

                    Forms\Components\TextInput::make('tiktok_url')
                        ->label('TikTok profile')
                        ->default('https://www.tiktok.com/@')
                        ->url()->rule('url')->maxLength(255)
                        ->prefixIcon('heroicon-m-link'),

                    Forms\Components\TextInput::make('youtube_url')
                        ->label('YouTube channel/page')
                        ->default('https://www.youtube.com/')
                        ->url()->rule('url')->maxLength(255)
                        ->prefixIcon('heroicon-m-link'),

                    Forms\Components\TextInput::make('youtube_id')
                        ->label('YouTube ID')
                        ->maxLength(190)
                        ->helperText('Used to fetch channel stats & videos.'),

                    Forms\Components\TextInput::make('spotify_url')
                        ->label('Spotify profile')
                        ->default('https://open.spotify.com/artist/')
                        ->url()->rule('url')->maxLength(255)
                        ->prefixIcon('heroicon-m-link'),

                    Forms\Components\TextInput::make('spotify_id')
                        ->label('Spotify ID')
                        ->maxLength(190)
                        ->helperText('Used to fetch Spotify stats & show playlist.'),
                ])
                ->columns(2),

            // === MEDIA (cu validare dimensiuni minime) ===
            SC\Section::make('Media')
                ->extraAttributes(['id' => 'artist-media'])
                ->schema([
                    Forms\Components\FileUpload::make('main_image_url')
                        ->label('Main image (horizontal)')
                        ->image()
                        ->directory('artists/hero')
                        ->disk('public')->visibility('public')
                        ->maxSize(4096)
                        ->rules(['image','mimes:jpg,jpeg,png,webp','dimensions:min_width=1600,min_height=900'])
                        ->helperText('Min 1600×900 px (JPG/PNG/WebP).'),

                    Forms\Components\FileUpload::make('logo_url')
                        ->label('Logo (horizontal)')
                        ->image()
                        ->directory('artists/logo')
                        ->disk('public')->visibility('public')
                        ->maxSize(2048)
                        ->rules(['image','mimes:png,webp,jpg,jpeg','dimensions:min_width=800,min_height=300'])
                        ->helperText('Min 800×300 px (PNG/JPG/WebP).'),

                    Forms\Components\FileUpload::make('portrait_url')
                        ->label('Portrait (vertical)')
                        ->image()
                        ->directory('artists/portrait')
                        ->disk('public')->visibility('public')
                        ->maxSize(4096)
                        ->rules(['image','mimes:jpg,jpeg,png,webp','dimensions:min_width=900,min_height=1200'])
                        ->helperText('Min 900×1200 px (JPG/PNG/WebP).'),
                ])
                ->columns(3),

            // === VIDEOS ===
            SC\Section::make('YouTube videos')
                ->extraAttributes(['id' => 'artist-videos'])
                ->schema([
                    Forms\Components\Repeater::make('youtube_videos')
                        ->label('Video URLs')
                        ->addActionLabel('Add video')
                        ->schema([
                            Forms\Components\TextInput::make('url')
                                ->label('YouTube URL')
                                ->placeholder('https://www.youtube.com/watch?v=...')
                                ->url()->rule('url')->required(),
                        ])
                        ->default([])
                        ->collapsed()
                        ->columns(1),
                ])
                ->columns(1),

            // === TAXONOMIES (Type -> filtered Genres) ===
            SC\Section::make('Taxonomies')
                ->extraAttributes(['id' => 'artist-taxonomies'])
                ->schema([
                    Forms\Components\Select::make('artistTypes')
                        ->label('Artist types')
                        ->relationship('artistTypes', 'name')
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->live()
                        ->helperText('Select one or more types. Genres list will follow.'),

                    Forms\Components\Select::make('artistGenres')
                        ->label('Artist genres')
                        ->multiple()
                        ->options(function (Get $get) {
                            $typeIds = $get('artistTypes') ?: [];
                            if (empty($typeIds)) {
                                return [];
                            }

                            $genreIds = \Illuminate\Support\Facades\DB::table('artist_type_allowed_genre')
                                ->whereIn('artist_type_id', (array) $typeIds)
                                ->pluck('artist_genre_id')
                                ->unique()
                                ->all();

                            return \App\Models\ArtistGenre::query()
                                ->whereIn('id', $genreIds)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->preload(false)
                        ->searchable()
                        ->helperText('Genres are filtered by selected types.')
                        ->visible(fn (Get $get) => filled($get('artistTypes'))),
                ])
                ->columns(2),


            // === OTHER DATA (locații în cascadă + contacte) ===
            SC\Section::make('Other data')
                ->extraAttributes(['id' => 'artist-other'])
                ->schema([
                    SC\Grid::make(3)->schema([
                        Forms\Components\Select::make('country')
                            ->label('Country')
                            ->options(Locations::countries())
                            ->searchable()
                            ->live()
                            ->preload(false),

                        // State / County (dependent de country)
                        Forms\Components\Select::make('state')
                            ->label('State / County')
                            ->options(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('country')
                                ? Locations::states($get('country'))
                                : [])
                            ->searchable()
                            ->live()
                            ->preload(false),

                        // City (dependent de country + state)
                        Forms\Components\Select::make('city')
                            ->label('City')
                            ->options(fn (\Filament\Schemas\Components\Utilities\Get $get) =>
                                ($get('country') && $get('state'))
                                    ? Locations::cityOptions($get('country'), $get('state'))
                                    : []
                            )
                            ->searchable()
                            ->preload(false),
                    ]),

                    // Phone + Email pe un rând
                    SC\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('phone')
                            ->label('Phone')
                            ->maxLength(120)
                            ->prefixIcon('heroicon-m-phone'),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(190)
                            ->prefixIcon('heroicon-m-envelope'),
                    ]),

                    // Manager
                    SC\Section::make("Manager contact")->schema([
                        SC\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('manager_first_name')->label('First name')->maxLength(120),
                            Forms\Components\TextInput::make('manager_last_name')->label('Last name')->maxLength(120),
                            Forms\Components\TextInput::make('manager_email')->label('Email')->email()->maxLength(190),
                        ]),
                        SC\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('manager_phone')->label('Phone')->maxLength(120)->prefixIcon('heroicon-m-phone'),
                            Forms\Components\TextInput::make('manager_website')->label('Website')->url()->rule('url')->maxLength(255)->prefixIcon('heroicon-m-globe-alt'),
                        ]),
                    ])->collapsible(),

                    // Agent
                    SC\Section::make("Booking agent contact")->schema([
                        SC\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('agent_first_name')->label('First name')->maxLength(120),
                            Forms\Components\TextInput::make('agent_last_name')->label('Last name')->maxLength(120),
                            Forms\Components\TextInput::make('agent_email')->label('Email')->email()->maxLength(190),
                        ]),
                        SC\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('agent_phone')->label('Phone')->maxLength(120)->prefixIcon('heroicon-m-phone'),
                            Forms\Components\TextInput::make('agent_website')->label('Website')->url()->rule('url')->maxLength(255)->prefixIcon('heroicon-m-globe-alt'),
                        ]),
                    ])->collapsible(),
                ])
                ->columns(1),

            // === STATS (read-only, followers separate pe canal) ===
            SC\Section::make('Artist stats (read-only)')
                ->extraAttributes(['id' => 'artist-stats'])
                ->schema([
                    Forms\Components\Placeholder::make('stats_events_last_year')
                        ->label('Events in last 12 months')
                        ->content(fn (?Artist $record) => $record ? (string) $record->eventsLastYearCount() : '—'),

                    Forms\Components\Placeholder::make('stats_tickets')
                        ->label('Tickets sold vs listed (last 12 months)')
                        ->content(function (?Artist $record) {
                            if (! $record) return '—';
                            $row = $record->ticketsSoldLastYear();
                            if (! $row) return '—';
                            $sold   = (int) ($row['sold']   ?? 0);
                            $listed = (int) ($row['listed'] ?? 0);
                            return "{$sold} sold / {$listed} listed";
                        }),

                    Forms\Components\Placeholder::make('stats_avg')
                        ->label('Avg tickets per event / Avg price')
                        ->content(function (?Artist $record) {
                            if (! $record) return '—';
                            $row = $record->ticketsSoldLastYear();
                            if (! $row) return '—';
                            $avg = $row['avg_per_event'] ?? 0;
                            $price = $row['avg_price'] !== null ? number_format($row['avg_price'], 2) : '—';
                            return "{$avg} / {$price}";
                        }),

                    SC\Grid::make(5)->schema([
                        Forms\Components\Placeholder::make('followers_fb')->label('Facebook followers')->content(fn($r)=>$r? (string)($r->facebook_followers ?? '—') : '—'),
                        Forms\Components\Placeholder::make('followers_ig')->label('Instagram followers')->content(fn($r)=>$r? (string)($r->instagram_followers ?? '—') : '—'),
                        Forms\Components\Placeholder::make('followers_tt')->label('TikTok followers')->content(fn($r)=>$r? (string)($r->tiktok_followers ?? '—') : '—'),
                        Forms\Components\Placeholder::make('followers_sp')->label('Spotify listeners')->content(fn($r)=>$r? (string)($r->spotify_followers ?? '—') : '—'),
                        Forms\Components\Placeholder::make('followers_yt')->label('YouTube subscribers')->content(fn($r)=>$r? (string)($r->youtube_followers ?? '—') : '—'),
                    ]),
                ])
                ->columns(1),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TagsColumn::make('artistTypes.name')
                    ->label('Types')
                    ->badge()
                    ->limit(2)
                    ->separator(',')
                    ->toggleable(),

                Tables\Columns\TagsColumn::make('artistGenres.name')
                    ->label('Genres')
                    ->badge()
                    ->limit(3)
                    ->separator(',')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('events_count')
                    ->label('Events')
                    ->counts('events')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->url(fn (Artist $record) => static::getUrl('view', ['record' => $record->getKey()])),

                Tables\Columns\TextColumn::make('country')
                    ->label('Country')
                    ->badge()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('city')
                    ->label('City')
                    ->badge()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->nullable(),

                Tables\Filters\SelectFilter::make('artistTypes')
                    ->label('Type')
                    ->relationship('artistTypes', 'name'),

                Tables\Filters\SelectFilter::make('artistGenres')
                    ->label('Genre')
                    ->relationship('artistGenres', 'name'),

                Tables\Filters\Filter::make('location')
                    ->label('Location')
                    ->form([
                        Forms\Components\Select::make('country')
                            ->label('Country')
                            ->options(fn () => \App\Support\Locations::countries())
                            ->searchable()
                            ->live(),

                        Forms\Components\Select::make('state')
                            ->label('State / County')
                            ->options(fn ($get) =>
                                $get('country') ? \App\Support\Locations::states($get('country')) : []
                            )
                            ->searchable()
                            ->live()
                            ->disabled(fn ($get) => blank($get('country'))),

                        Forms\Components\Select::make('city')
                            ->label('City')
                            ->options(fn ($get) =>
                                ($get('country') && $get('state'))
                                    ? \App\Support\Locations::cityOptions($get('country'), $get('state'))
                                    : []
                            )
                            ->searchable()
                            ->disabled(fn ($get) => blank($get('country')) || blank($get('state'))),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data) {
                        $query->when(
                            filled($data['country'] ?? null),
                            fn ($q) => $q->where('country', $data['country'])
                        );

                        if (filled($data['state'] ?? null)) {
                            $state = $data['state'];
                            $query->where(function ($qq) use ($state) {
                                if (\Illuminate\Support\Facades\Schema::hasColumn('artists', 'state')) {
                                    $qq->orWhere('state', $state);
                                }
                                if (\Illuminate\Support\Facades\Schema::hasColumn('artists', 'county')) {
                                    $qq->orWhere('county', $state);
                                }
                            });
                        }

                        $query->when(
                            filled($data['city'] ?? null),
                            fn ($q) => $q->where('city', $data['city'])
                        );

                        return $query;
                    }),

                Tables\Filters\Filter::make('romania')
                    ->label('Romania')
                    ->query(fn ($q) => $q->where('country', 'Romania'))
                    ->toggle(),

                Tables\Filters\Filter::make('has_image')
                    ->label('Has image')
                    ->query(fn ($q) => $q->where(function ($w) {
                        $w->whereNotNull('portrait_url')
                        ->orWhereNotNull('hero_image_url')
                        ->orWhereNotNull('logo_url');
                    }))
                    ->toggle(),
            ])
            ->actions([
                // \Filament\Tables\Actions\Action::make('view')
                //     ->label('View')
                //     ->icon('heroicon-m-eye')
                //     ->url(fn (\App\Models\Artist $record) => static::getUrl('view', ['record' => $record->getKey()])),
                // \Filament\Tables\Actions\EditAction::make()
                //     ->label('Edit'),
            ])
            ->defaultSort('created_at', 'desc');
    }



    public static function getRelations(): array
    {
        return [
            // add relation managers here later if needed
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListArtists::route('/'),
            'create' => CreateArtist::route('/create'),
            'view'   => ViewArtist::route('/{record}'),
            //'view'   => ViewArtist::route('/{slug}'),
            'edit'   => EditArtist::route('/{record}/edit'),
            'stats'  => ArtistStats::route('/{record}/stats'),
        ];
    }
}

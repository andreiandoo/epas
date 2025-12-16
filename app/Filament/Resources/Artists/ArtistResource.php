<?php

namespace App\Filament\Resources\Artists;

use App\Filament\Resources\Artists\Pages\CreateArtist;
use App\Filament\Resources\Artists\Pages\EditArtist;
use App\Filament\Resources\Artists\Pages\ListArtists;
use App\Filament\Resources\Artists\Pages\ViewArtist;
use App\Filament\Resources\Artists\Pages\ArtistStats;
use App\Filament\Resources\Artists\Pages\ImportArtists;
use Illuminate\Database\Eloquent\Builder;
use App\Support\Locations;
use App\Filament\Forms\Components\TranslatableField;
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
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use App\Jobs\FetchArtistSocialStats;

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
                    TranslatableField::richEditor('bio_html', 'Bio')
                        ->columnSpanFull(),
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

            // === SOCIAL STATS ===
            SC\Section::make('Social Stats')
                ->extraAttributes(['id' => 'artist-social-stats'])
                ->description('Stats are auto-fetched when YouTube ID or Spotify ID is saved')
                ->schema([
                    // YouTube Stats
                    Forms\Components\TextInput::make('followers_youtube')
                        ->label('YouTube Subscribers')
                        ->numeric()
                        ->minValue(0)
                        ->disabled()
                        ->dehydrated(),

                    Forms\Components\TextInput::make('youtube_total_views')
                        ->label('YouTube Total Views')
                        ->numeric()
                        ->minValue(0)
                        ->disabled()
                        ->dehydrated(),

                    // Spotify Stats
                    Forms\Components\TextInput::make('spotify_monthly_listeners')
                        ->label('Spotify Followers')
                        ->numeric()
                        ->minValue(0)
                        ->disabled()
                        ->dehydrated(),

                    Forms\Components\TextInput::make('spotify_popularity')
                        ->label('Spotify Popularity')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->disabled()
                        ->dehydrated()
                        ->helperText('0-100 score'),

                    // Manual entry fields
                    Forms\Components\TextInput::make('followers_facebook')
                        ->label('Facebook Followers')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('Manual entry'),

                    Forms\Components\TextInput::make('followers_instagram')
                        ->label('Instagram Followers')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('Manual entry'),

                    Forms\Components\TextInput::make('followers_tiktok')
                        ->label('TikTok Followers')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('Manual entry'),
                ])
                ->columns(4)
                ->collapsible(),

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
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('name', app()->getLocale()))
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->live()
                        ->helperText('Select one or more types. Genres list will follow.'),

                    Forms\Components\Select::make('artistGenres')
                        ->label('Artist genres')
                        ->relationship('artistGenres', 'name')
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('name', app()->getLocale()))
                        ->multiple()
                        ->preload()
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
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->url(fn (Artist $record) => static::getUrl('view', ['record' => $record->getKey()])),

                Tables\Columns\TextColumn::make('completeness')
                    ->label('Info %')
                    ->getStateUsing(function (Artist $record) {
                        $fields = [
                            'name', 'slug', 'website', 'facebook_url', 'instagram_url',
                            'tiktok_url', 'spotify_url', 'youtube_url', 'youtube_id',
                            'spotify_id', 'country', 'city', 'main_image_url',
                        ];
                        $filled = 0;
                        foreach ($fields as $field) {
                            if (!empty($record->$field)) $filled++;
                        }
                        // Check youtube_videos (at least one)
                        if (!empty($record->youtube_videos) && is_array($record->youtube_videos) && count($record->youtube_videos) > 0) {
                            $filled++;
                        }
                        return round(($filled / 14) * 100);
                    })
                    ->formatStateUsing(fn ($state) => $state . '%')
                    ->badge()
                    ->color(fn ($state): string => match (true) {
                        $state >= 80 => 'success',
                        $state >= 60 => 'info',
                        $state >= 30 => 'warning',
                        default => 'danger',
                    })
                    ->sortable(query: function ($query, string $direction) {
                        // Sort by number of filled fields
                        return $query->orderByRaw("
                            (CASE WHEN name IS NOT NULL AND name != '' THEN 1 ELSE 0 END +
                             CASE WHEN slug IS NOT NULL AND slug != '' THEN 1 ELSE 0 END +
                             CASE WHEN website IS NOT NULL AND website != '' THEN 1 ELSE 0 END +
                             CASE WHEN facebook_url IS NOT NULL AND facebook_url != '' THEN 1 ELSE 0 END +
                             CASE WHEN instagram_url IS NOT NULL AND instagram_url != '' THEN 1 ELSE 0 END +
                             CASE WHEN tiktok_url IS NOT NULL AND tiktok_url != '' THEN 1 ELSE 0 END +
                             CASE WHEN spotify_url IS NOT NULL AND spotify_url != '' THEN 1 ELSE 0 END +
                             CASE WHEN youtube_url IS NOT NULL AND youtube_url != '' THEN 1 ELSE 0 END +
                             CASE WHEN youtube_id IS NOT NULL AND youtube_id != '' THEN 1 ELSE 0 END +
                             CASE WHEN spotify_id IS NOT NULL AND spotify_id != '' THEN 1 ELSE 0 END +
                             CASE WHEN country IS NOT NULL AND country != '' THEN 1 ELSE 0 END +
                             CASE WHEN city IS NOT NULL AND city != '' THEN 1 ELSE 0 END +
                             CASE WHEN main_image_url IS NOT NULL AND main_image_url != '' THEN 1 ELSE 0 END +
                             CASE WHEN youtube_videos IS NOT NULL AND youtube_videos != '[]' AND youtube_videos != '' THEN 1 ELSE 0 END
                            ) {$direction}
                        ");
                    }),

                Tables\Columns\TextColumn::make('country')
                    ->label('Country')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('city')
                    ->label('City')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('events_count')
                    ->label('Events')
                    ->counts('events')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('types_display')
                    ->label('Types')
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->artistTypes->map(fn($t) => $t->getTranslation('name', app()->getLocale()))->implode(', '))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('genres_display')
                    ->label('Genres')
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->artistGenres->map(fn($g) => $g->getTranslation('name', app()->getLocale()))->implode(', '))
                    ->toggleable(),

                // Social Links checkmarks
                Tables\Columns\IconColumn::make('has_facebook')
                    ->label('FB')
                    ->getStateUsing(fn (Artist $record) => !empty($record->facebook_url) && $record->facebook_url !== 'https://www.facebook.com/')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->sortable(query: fn ($query, string $direction) => $query->orderByRaw("CASE WHEN facebook_url IS NOT NULL AND facebook_url != '' AND facebook_url != 'https://www.facebook.com/' THEN 1 ELSE 0 END {$direction}"))
                    ->toggleable(),

                Tables\Columns\IconColumn::make('has_instagram')
                    ->label('IG')
                    ->getStateUsing(fn (Artist $record) => !empty($record->instagram_url) && $record->instagram_url !== 'https://www.instagram.com/')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->sortable(query: fn ($query, string $direction) => $query->orderByRaw("CASE WHEN instagram_url IS NOT NULL AND instagram_url != '' AND instagram_url != 'https://www.instagram.com/' THEN 1 ELSE 0 END {$direction}"))
                    ->toggleable(),

                Tables\Columns\IconColumn::make('has_tiktok')
                    ->label('TikTok')
                    ->getStateUsing(fn (Artist $record) => !empty($record->tiktok_url) && $record->tiktok_url !== 'https://www.tiktok.com/@')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->sortable(query: fn ($query, string $direction) => $query->orderByRaw("CASE WHEN tiktok_url IS NOT NULL AND tiktok_url != '' AND tiktok_url != 'https://www.tiktok.com/@' THEN 1 ELSE 0 END {$direction}"))
                    ->toggleable(),

                Tables\Columns\IconColumn::make('has_spotify')
                    ->label('Spotify')
                    ->getStateUsing(fn (Artist $record) => !empty($record->spotify_url) && $record->spotify_url !== 'https://open.spotify.com/artist/')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->sortable(query: fn ($query, string $direction) => $query->orderByRaw("CASE WHEN spotify_url IS NOT NULL AND spotify_url != '' AND spotify_url != 'https://open.spotify.com/artist/' THEN 1 ELSE 0 END {$direction}"))
                    ->toggleable(),

                Tables\Columns\IconColumn::make('has_youtube')
                    ->label('YT')
                    ->getStateUsing(fn (Artist $record) => !empty($record->youtube_url) && $record->youtube_url !== 'https://www.youtube.com/')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->sortable(query: fn ($query, string $direction) => $query->orderByRaw("CASE WHEN youtube_url IS NOT NULL AND youtube_url != '' AND youtube_url != 'https://www.youtube.com/' THEN 1 ELSE 0 END {$direction}"))
                    ->toggleable(),

                // Social Stats columns
                Tables\Columns\TextColumn::make('followers_youtube')
                    ->label('YT Subs')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn ($state) => $state ? number_format($state) : '—'),

                Tables\Columns\TextColumn::make('youtube_total_views')
                    ->label('YT Views')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn ($state) => $state ? number_format($state) : '—'),

                Tables\Columns\TextColumn::make('spotify_monthly_listeners')
                    ->label('Spotify')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn ($state) => $state ? number_format($state) : '—'),

                Tables\Columns\TextColumn::make('spotify_popularity')
                    ->label('SP Pop')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn ($state) => $state !== null ? $state . '/100' : '—'),

                Tables\Columns\TextColumn::make('social_stats_updated_at')
                    ->label('Stats Updated')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Edit')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->since(),

                // Edit link
                Tables\Columns\TextColumn::make('edit_link')
                    ->label('')
                    ->getStateUsing(fn () => '✏️')
                    ->url(fn (Artist $record) => static::getUrl('edit', ['record' => $record->getKey()]))
                    ->tooltip('Edit artist'),

                // Delete link
                Tables\Columns\TextColumn::make('delete_link')
                    ->label('')
                    ->getStateUsing(fn () => '🗑️')
                    ->url(fn (Artist $record) => static::getUrl('edit', ['record' => $record->getKey()]) . '?delete=1')
                    ->tooltip('Delete artist'),
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
            ->defaultSort('created_at', 'desc')
            ->actions([])
            ->bulkActions([])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation(),
                    BulkAction::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation(),
                    BulkAction::make('assignTypes')
                        ->label('Assign Types')
                        ->icon('heroicon-o-tag')
                        ->form([
                            Forms\Components\Select::make('artist_types')
                                ->label('Artist Types')
                                ->multiple()
                                ->options(ArtistType::all()->pluck('name', 'id')->map(fn ($name) => is_array($name) ? ($name['en'] ?? $name['ro'] ?? reset($name)) : $name))
                                ->required(),
                            Forms\Components\Toggle::make('replace')
                                ->label('Replace existing (otherwise append)')
                                ->default(false),
                        ])
                        ->action(function (Collection $records, array $data) {
                            foreach ($records as $artist) {
                                if ($data['replace']) {
                                    $artist->artistTypes()->sync($data['artist_types']);
                                } else {
                                    $artist->artistTypes()->syncWithoutDetaching($data['artist_types']);
                                }
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('assignGenres')
                        ->label('Assign Genres')
                        ->icon('heroicon-o-musical-note')
                        ->form([
                            Forms\Components\Select::make('artist_genres')
                                ->label('Artist Genres')
                                ->multiple()
                                ->options(ArtistGenre::all()->pluck('name', 'id')->map(fn ($name) => is_array($name) ? ($name['en'] ?? $name['ro'] ?? reset($name)) : $name))
                                ->required(),
                            Forms\Components\Toggle::make('replace')
                                ->label('Replace existing (otherwise append)')
                                ->default(false),
                        ])
                        ->action(function (Collection $records, array $data) {
                            foreach ($records as $artist) {
                                if ($data['replace']) {
                                    $artist->artistGenres()->sync($data['artist_genres']);
                                } else {
                                    $artist->artistGenres()->syncWithoutDetaching($data['artist_genres']);
                                }
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('fetchSocialStats')
                        ->label('Fetch Social Stats')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Fetch Social Stats')
                        ->modalDescription(fn (Collection $records) => "This will queue jobs to fetch YouTube, Spotify, Facebook, and TikTok stats for {$records->count()} selected artists. Artists without social profile IDs will be skipped. Note: TikTok API has limitations.")
                        ->action(function (Collection $records) {
                            $queued = 0;
                            $skipped = 0;

                            foreach ($records as $artist) {
                                // Check if artist has any social IDs
                                $hasSocialIds = !empty($artist->youtube_id)
                                    || !empty($artist->spotify_id)
                                    || !empty($artist->facebook_url)
                                    || !empty($artist->instagram_url)
                                    || !empty($artist->tiktok_url);

                                if ($hasSocialIds) {
                                    FetchArtistSocialStats::dispatch($artist->id);
                                    $queued++;
                                } else {
                                    $skipped++;
                                }
                            }

                            Notification::make()
                                ->title('Social Stats Fetch Queued')
                                ->body("Queued {$queued} artists for stats update." . ($skipped > 0 ? " Skipped {$skipped} without social profiles." : ''))
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
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
            'import' => ImportArtists::route('/import'),
            'view'   => ViewArtist::route('/{record}'),
            //'view'   => ViewArtist::route('/{slug}'),
            'edit'   => EditArtist::route('/{record}/edit'),
            'stats'  => ArtistStats::route('/{record}/stats'),
        ];
    }
}

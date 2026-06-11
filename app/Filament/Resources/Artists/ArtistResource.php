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
use UnitEnum;
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

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-user';
    protected static UnitEnum|string|null $navigationGroup = 'Catalog';
    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Grid::make(4)->schema([
                // ========== LEFT COLUMN (3/4) ==========
                SC\Group::make()->columnSpan(3)->schema([

                    // === BASICS ===
                    SC\Grid::make(2)->schema([
                        SC\Group::make()->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Artist name')
                                ->required()
                                ->maxLength(190)
                                ->placeholder('Type the artist name…')
                                ->extraAttributes(['class' => 'ep-title'])
                                ->live(onBlur: true)
                                ->afterStateUpdated(function ($state, Set $set) {
                                    if ($state) $set('slug', Str::slug($state));
                                }),
                            Forms\Components\TextInput::make('slug')
                                ->label('Slug')
                                ->helperText('Editable URL slug.')
                                ->maxLength(190)
                                ->rule('alpha_dash')
                                ->unique(ignoreRecord: true)
                                ->prefixIcon('heroicon-m-link'),
                        ]),
                        SC\Section::make('Media')->compact()->schema([
                            SC\Grid::make(3)->schema([
                                Forms\Components\FileUpload::make('main_image_url')
                                    ->label('Main (horiz.)')
                                    ->image()
                                    ->directory('artists/hero')
                                    ->disk('public')->visibility('public')
                                    ->maxSize(4096)
                                    ->rules(['image','mimes:jpg,jpeg,png,webp','dimensions:min_width=1600,min_height=900'])
                                    ->helperText('1600×900+')
                                    ->afterStateUpdated(fn ($livewire) => $livewire->skipRender()),
                                Forms\Components\FileUpload::make('logo_url')
                                    ->label('Logo (horiz.)')
                                    ->image()
                                    ->directory('artists/logo')
                                    ->disk('public')->visibility('public')
                                    ->maxSize(2048)
                                    ->rules(['image','mimes:png,webp,jpg,jpeg','dimensions:min_width=800,min_height=300'])
                                    ->helperText('800×300+')
                                    ->afterStateUpdated(fn ($livewire) => $livewire->skipRender()),
                                Forms\Components\FileUpload::make('portrait_url')
                                    ->label('Portrait (vert.)')
                                    ->image()
                                    ->directory('artists/portrait')
                                    ->disk('public')->visibility('public')
                                    ->maxSize(4096)
                                    ->rules(['image','mimes:jpg,jpeg,png,webp','dimensions:min_width=900,min_height=1200'])
                                    ->helperText('900×1200+')
                                    ->afterStateUpdated(fn ($livewire) => $livewire->skipRender()),
                            ]),
                        ]),
                    ]),

                    // === CONTENT ===
                    SC\Section::make('Content')->compact()->schema([
                        TranslatableField::richEditor('bio_html', 'Bio')->columnSpanFull(),
                    ])->columns(1),

                    // === TAXONOMIES + LOCATION ===
                    SC\Grid::make(2)->schema([
                        SC\Section::make('Taxonomies')->compact()->schema([
                            Forms\Components\Select::make('artistTypes')
                                ->label('Artist types')
                                ->relationship('artistTypes', 'name')
                                ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('name', app()->getLocale()))
                                ->multiple()->preload()->searchable()->live(),
                            Forms\Components\Select::make('artistGenres')
                                ->label('Artist genres')
                                ->relationship('artistGenres', 'name')
                                ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('name', app()->getLocale()))
                                ->multiple()->preload()->searchable()
                                ->visible(fn (Get $get) => filled($get('artistTypes'))),
                        ]),
                        SC\Section::make('Location')->compact()->schema([
                            Forms\Components\Select::make('country')
                                ->label('Country')
                                ->options(Locations::countries())
                                ->searchable()->live()->preload(false),
                            Forms\Components\Select::make('state')
                                ->label('State / County')
                                ->options(fn (Get $get) => $get('country') ? Locations::states($get('country')) : [])
                                ->searchable()->live()->preload(false),
                            Forms\Components\Select::make('city')
                                ->label('City')
                                ->options(fn (Get $get) => ($get('country') && $get('state')) ? Locations::cityOptions($get('country'), $get('state')) : [])
                                ->searchable()->preload(false),
                        ]),
                    ]),

                    // === SOCIAL & IDs ===
                    SC\Section::make('Social & IDs')->compact()->collapsible()->collapsed()->schema([
                        Forms\Components\TextInput::make('website')->label('Website')->url()->rule('url')->maxLength(255)->prefixIcon('heroicon-m-globe-alt')->placeholder('https://example.com'),
                        Forms\Components\TextInput::make('facebook_url')->label('Facebook')->default('https://www.facebook.com/')->url()->rule('url')->maxLength(255)->prefixIcon('heroicon-m-link'),
                        Forms\Components\TextInput::make('instagram_url')->label('Instagram')->default('https://www.instagram.com/')->url()->rule('url')->maxLength(255)->prefixIcon('heroicon-m-link'),
                        Forms\Components\TextInput::make('tiktok_url')->label('TikTok')->default('https://www.tiktok.com/@')->url()->rule('url')->maxLength(255)->prefixIcon('heroicon-m-link'),
                        Forms\Components\TextInput::make('youtube_url')->label('YouTube')->default('https://www.youtube.com/')->url()->rule('url')->maxLength(255)->prefixIcon('heroicon-m-link'),
                        Forms\Components\TextInput::make('youtube_id')->label('YouTube ID')->maxLength(190)->helperText('Used to fetch channel stats & videos.'),
                        Forms\Components\TextInput::make('spotify_url')->label('Spotify')->default('https://open.spotify.com/artist/')->url()->rule('url')->maxLength(255)->prefixIcon('heroicon-m-link'),
                        Forms\Components\TextInput::make('spotify_id')->label('Spotify ID')->maxLength(190)->helperText('Used to fetch Spotify stats & show playlist.'),
                        Forms\Components\TextInput::make('twitter_url')->label('Twitter / X')->default('https://x.com/')->url()->rule('url')->maxLength(255)->prefixIcon('heroicon-m-link'),
                        Forms\Components\TextInput::make('wiki_url')->label('Wikipedia')->url()->rule('url')->maxLength(255)->prefixIcon('heroicon-m-link'),
                        Forms\Components\TextInput::make('lastfm_url')->label('Last.fm')->url()->rule('url')->maxLength(255)->prefixIcon('heroicon-m-link'),
                        Forms\Components\TextInput::make('itunes_url')->label('Apple Music')->url()->rule('url')->maxLength(255)->prefixIcon('heroicon-m-link'),
                        Forms\Components\TextInput::make('musicbrainz_url')->label('MusicBrainz')->url()->rule('url')->maxLength(255)->prefixIcon('heroicon-m-link'),
                    ])->columns(2),

                    // === SOCIAL STATS (editable) ===
                    SC\Section::make('Social Stats (editable)')->compact()->collapsible()->collapsed()->schema([
                        Forms\Components\TextInput::make('followers_youtube')->label('YouTube Subscribers')->numeric()->minValue(0)->disabled()->dehydrated(),
                        Forms\Components\TextInput::make('youtube_total_views')->label('YouTube Total Views')->numeric()->minValue(0)->disabled()->dehydrated(),
                        Forms\Components\TextInput::make('spotify_monthly_listeners')->label('Spotify Followers')->numeric()->minValue(0)->disabled()->dehydrated(),
                        Forms\Components\TextInput::make('spotify_popularity')->label('Spotify Popularity (0-100)')->numeric()->minValue(0)->maxValue(100)->disabled()->dehydrated(),
                        Forms\Components\TextInput::make('twitter_followers')->label('Twitter / X Followers')->numeric()->minValue(0)->helperText('Manual entry'),
                        Forms\Components\TextInput::make('followers_facebook')->label('Facebook Followers')->numeric()->minValue(0)->helperText('Manual entry'),
                        Forms\Components\TextInput::make('followers_instagram')->label('Instagram Followers')->numeric()->minValue(0)->helperText('Manual entry'),
                        Forms\Components\TextInput::make('followers_tiktok')->label('TikTok Followers')->numeric()->minValue(0)->helperText('Manual entry'),
                    ])->columns(4),

                    // === YOUTUBE VIDEOS ===
                    SC\Section::make('YouTube videos')->compact()->collapsible()->collapsed()->schema([
                        Forms\Components\Repeater::make('youtube_videos')
                            ->label('Video URLs')
                            ->addActionLabel('Add video')
                            ->schema([
                                Forms\Components\TextInput::make('url')->label('YouTube URL')->placeholder('https://www.youtube.com/watch?v=...')->url()->rule('url')->required(),
                            ])
                            ->default([])
                            ->collapsed()
                            ->columns(1),
                    ]),

                    // === CONTACT + MANAGER (side by side) ===
                    SC\Grid::make(2)->schema([
                        SC\Section::make('Contact')
                            ->icon('heroicon-o-envelope')
                            ->collapsible()->collapsed()->persistCollapsed()
                            ->schema([
                                Forms\Components\TextInput::make('email')->label('Email')->email()->maxLength(190)->prefixIcon('heroicon-o-envelope'),
                                Forms\Components\TextInput::make('phone')->label('Phone')->maxLength(120)->prefixIcon('heroicon-o-phone'),
                            ])->columns(2),
                        SC\Section::make('Manager')
                            ->icon('heroicon-o-user')
                            ->collapsible()->collapsed()->persistCollapsed()
                            ->schema([
                                Forms\Components\TextInput::make('manager_first_name')->label('First name')->maxLength(120),
                                Forms\Components\TextInput::make('manager_last_name')->label('Last name')->maxLength(120),
                                Forms\Components\TextInput::make('manager_email')->label('Email')->email()->maxLength(190),
                                Forms\Components\TextInput::make('manager_phone')->label('Phone')->maxLength(120),
                                Forms\Components\TextInput::make('manager_website')->label('Website')->url()->rule('url')->maxLength(255),
                            ])->columns(2),
                    ]),

                    // === AGENT + AGENCY (side by side) ===
                    SC\Grid::make(2)->schema([
                        SC\Section::make('Booking Agent')
                            ->icon('heroicon-o-briefcase')
                            ->collapsible()->collapsed()->persistCollapsed()
                            ->schema([
                                Forms\Components\TextInput::make('agent_first_name')->label('First name')->maxLength(120),
                                Forms\Components\TextInput::make('agent_last_name')->label('Last name')->maxLength(120),
                                Forms\Components\TextInput::make('agent_email')->label('Email')->email()->maxLength(190),
                                Forms\Components\TextInput::make('agent_phone')->label('Phone')->maxLength(120),
                                Forms\Components\TextInput::make('agent_website')->label('Website')->url()->rule('url')->maxLength(255),
                            ])->columns(2),
                        SC\Section::make('Booking Agency')
                            ->icon('heroicon-o-building-office')
                            ->collapsible()->collapsed()->persistCollapsed()
                            ->schema([
                                Forms\Components\TextInput::make('booking_agency.name')->label('Agency Name')->placeholder('e.g. Universal Music Romania'),
                                Forms\Components\TextInput::make('booking_agency.email')->label('Email')->email()->placeholder('booking@agency.com'),
                                Forms\Components\TextInput::make('booking_agency.phone')->label('Phone')->placeholder('+40 ...'),
                                Forms\Components\TextInput::make('booking_agency.website')->label('Website')->url()->placeholder('https://...'),
                            ])->columns(2),
                    ]),

                    // === PRICING ===
                    SC\Section::make('Pricing')
                        ->icon('heroicon-o-banknotes')
                        ->collapsible()->collapsed()->persistCollapsed()
                        ->schema([
                            SC\Grid::make(2)->schema([
                                Forms\Components\TextInput::make('min_fee_concert')
                                    ->label('Min Fee Concert (€)')
                                    ->numeric()->minValue(0)->step(100)
                                    ->placeholder('e.g. 5000'),
                                Forms\Components\TextInput::make('max_fee_concert')
                                    ->label('Max Fee Concert (€)')
                                    ->numeric()->minValue(0)->step(100)
                                    ->placeholder('e.g. 15000'),
                                Forms\Components\TextInput::make('min_fee_festival')
                                    ->label('Min Fee Festival (€)')
                                    ->numeric()->minValue(0)->step(100)
                                    ->placeholder('e.g. 8000'),
                                Forms\Components\TextInput::make('max_fee_festival')
                                    ->label('Max Fee Festival (€)')
                                    ->numeric()->minValue(0)->step(100)
                                    ->placeholder('e.g. 25000'),
                            ]),
                            Forms\Components\Placeholder::make('avg_revenue_per_concert')
                                ->label('Avg Revenue per Concert (calculated)')
                                ->content(function (?Artist $record) {
                                    if (!$record || !$record->exists) return '—';
                                    $perEvent = \Illuminate\Support\Facades\DB::table('orders as o')
                                        ->join('tickets as t', 't.order_id', '=', 'o.id')
                                        ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
                                        ->join('event_artist as ea', function ($join) {
                                            $join->on('ea.event_id', '=', \Illuminate\Support\Facades\DB::raw('COALESCE(tt.event_id, t.event_id, t.marketplace_event_id)'));
                                        })
                                        ->where('ea.artist_id', $record->id)
                                        ->whereIn('o.status', ['paid', 'confirmed', 'completed'])
                                        ->select('ea.event_id', \Illuminate\Support\Facades\DB::raw('SUM(o.total) as event_total'))
                                        ->groupBy('ea.event_id')
                                        ->get();
                                    if ($perEvent->isEmpty()) return '—';
                                    $avg = $perEvent->avg('event_total');
                                    return new \Illuminate\Support\HtmlString(
                                        '<span style="font-size:18px;font-weight:700;color:#22c55e;">' . number_format((float)$avg, 2) . ' RON</span>'
                                    );
                                }),
                        ]),
                ]),

                // ========== RIGHT SIDEBAR (1/4) ==========
                SC\Group::make()->columnSpan(1)->schema([

                    // Artist Preview Card
                    SC\Section::make('')->compact()->schema([
                        Forms\Components\Placeholder::make('artist_preview')
                            ->hiddenLabel()
                            ->content(function (?Artist $record) {
                                if (!$record || !$record->exists) return '';
                                $image = $record->main_image_url
                                    ? asset('storage/' . $record->main_image_url)
                                    : 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&color=7F9CF5&background=EBF4FF';
                                $location = collect([$record->city, $record->country])->filter()->join(', ') ?: '—';
                                $badges = '';
                                if ($record->is_active) {
                                    $badges .= '<span style="display:inline-block;padding:3px 8px;border-radius:20px;font-size:11px;font-weight:600;background:rgba(16,185,129,0.15);color:#10B981;margin-right:4px;">✓ Active</span>';
                                }
                                $types = $record->artistTypes->map(fn($t) => $t->getTranslation('name', app()->getLocale()))->filter();
                                foreach ($types as $type) {
                                    $badges .= '<span style="display:inline-block;padding:3px 8px;border-radius:20px;font-size:11px;font-weight:600;background:#334155;color:#E2E8F0;margin-right:4px;">' . e($type) . '</span>';
                                }
                                return new \Illuminate\Support\HtmlString("
                                    <div style='display:flex;gap:10px;align-items:center;'>
                                        <img src='{$image}' alt='" . e($record->name) . "' style='width:56px;height:56px;border-radius:50%;object-fit:cover;border:2px solid #334155;'>
                                        <div>
                                            <div style='font-size:16px;font-weight:700;color:white;'>" . e($record->name) . "</div>
                                            <div style='font-size:12px;color:#64748B;'>{$location}</div>
                                        </div>
                                    </div>
                                    <div style='margin-top:10px;display:flex;flex-wrap:wrap;gap:4px;'>{$badges}</div>
                                ");
                            }),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->onColor('success')
                            ->offColor('gray'),
                    ]),

                    // Artist Stats
                    SC\Section::make('Artist Stats')
                        ->icon('heroicon-o-chart-bar')
                        ->compact()
                        ->visible(fn (?Artist $record) => $record?->exists)
                        ->schema([
                            Forms\Components\Placeholder::make('artist_stats_sidebar')
                                ->hiddenLabel()
                                ->content(function (?Artist $record) {
                                    if (!$record) return '';
                                    $eventsCount = $record->eventsLastYearCount();
                                    $tickets = $record->ticketsSoldLastYear();
                                    $sold = (int) ($tickets['sold'] ?? 0);
                                    $listed = (int) ($tickets['listed'] ?? 0);
                                    $avg = $tickets['avg_per_event'] ?? 0;
                                    $price = ($tickets['avg_price'] !== null) ? number_format($tickets['avg_price'], 2) : '—';

                                    $row = fn ($label, $value, $color = '#E2E8F0') => "
                                        <div style='display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid rgba(51,65,85,0.5);'>
                                            <span style='font-size:12px;color:#64748B;'>{$label}</span>
                                            <span style='font-size:12px;font-weight:600;color:{$color};'>{$value}</span>
                                        </div>";

                                    return new \Illuminate\Support\HtmlString(
                                        $row('Events (12 months)', $eventsCount) .
                                        $row('Tickets sold / listed', "{$sold} / {$listed}") .
                                        $row('Avg per event', $avg) .
                                        $row('Avg price', $price)
                                    );
                                }),
                        ]),

                    // Social Stats (visual)
                    SC\Section::make('Social Stats')
                        ->icon('heroicon-o-signal')
                        ->compact()
                        ->collapsed()
                        ->visible(fn (?Artist $record) => $record?->exists)
                        ->schema([
                            Forms\Components\Placeholder::make('social_stats_visual')
                                ->hiddenLabel()
                                ->content(function (?Artist $record) {
                                    if (!$record) return '';
                                    $fmt = fn (?int $n) => (!$n) ? '-' : ($n >= 1000000 ? round($n/1000000,1).'M' : ($n >= 1000 ? round($n/1000,1).'K' : (string)$n));
                                    $stats = [
                                        ['bg' => '#1DB954', 'value' => $record->spotify_monthly_listeners, 'label' => 'Spotify'],
                                        ['bg' => '#FF0000', 'value' => $record->followers_youtube, 'label' => 'YouTube'],
                                        ['bg' => '#E4405F', 'value' => $record->instagram_followers, 'label' => 'Instagram'],
                                        ['bg' => '#1877F2', 'value' => $record->facebook_followers, 'label' => 'Facebook'],
                                        ['bg' => '#000000', 'value' => $record->tiktok_followers, 'label' => 'TikTok'],
                                    ];
                                    $html = "<div style='display:grid;grid-template-columns:repeat(2,1fr);gap:8px;'>";
                                    foreach ($stats as $s) {
                                        $v = $fmt($s['value']);
                                        $html .= "<div style='text-align:center;'><div style='width:22px;height:22px;margin:0 auto 4px;border-radius:5px;display:flex;align-items:center;justify-content:center;background:{$s['bg']};'><span style='font-size:10px;color:white;font-weight:700;'>" . strtoupper(mb_substr($s['label'], 0, 1)) . "</span></div><div style='font-size:13px;font-weight:700;color:white;'>{$v}</div><div style='font-size:10px;color:#64748B;'>{$s['label']}</div></div>";
                                    }
                                    $html .= "</div>";
                                    return new \Illuminate\Support\HtmlString($html);
                                }),
                        ]),

                    // Evenimente
                    SC\Section::make('Evenimente')
                        ->icon('heroicon-o-calendar')
                        ->compact()
                        ->visible(fn (?Artist $record) => $record?->exists)
                        ->schema([
                            Forms\Components\Placeholder::make('events_stats')
                                ->hiddenLabel()
                                ->content(function (?Artist $record) {
                                    if (!$record) return '';
                                    $total = $record->events()->count();
                                    $upcoming = $record->events()->where('event_date', '>=', now())->count();
                                    $past = $record->events()->where('event_date', '<', now())->count();
                                    $row = fn ($label, $value, $color = '#E2E8F0') => "
                                        <div style='display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid rgba(51,65,85,0.5);'>
                                            <span style='font-size:12px;color:#64748B;'>{$label}</span>
                                            <span style='font-size:12px;font-weight:600;color:{$color};'>{$value}</span>
                                        </div>";
                                    return new \Illuminate\Support\HtmlString(
                                        $row('Total', $total) .
                                        $row('Upcoming', $upcoming, '#10B981') .
                                        $row('Past', $past, '#64748B')
                                    );
                                }),
                        ]),

                    // Informații
                    SC\Section::make('Info')
                        ->icon('heroicon-o-information-circle')
                        ->compact()
                        ->visible(fn (?Artist $record) => $record?->exists)
                        ->schema([
                            Forms\Components\Placeholder::make('meta_info')
                                ->hiddenLabel()
                                ->content(function (?Artist $record) {
                                    if (!$record) return '';
                                    $created = $record->created_at->format('d M Y');
                                    $updated = $record->updated_at->diffForHumans();
                                    $row = fn ($label, $value) => "
                                        <div style='display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid rgba(51,65,85,0.5);'>
                                            <span style='font-size:12px;color:#64748B;'>{$label}</span>
                                            <span style='font-size:12px;font-weight:600;color:#E2E8F0;'>{$value}</span>
                                        </div>";
                                    return new \Illuminate\Support\HtmlString(
                                        $row('Created', $created) .
                                        $row('Modified', $updated) .
                                        $row('ID', "<span style='font-family:monospace;color:#64748B;'>{$record->id}</span>")
                                    );
                                }),
                        ]),
                ]),
            ]),
        ]) ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable(query: fn ($query, string $search) => \App\Support\SearchHelper::search($query, 'name', $search))
                    ->sortable()
                    ->url(fn (Artist $record) => static::getUrl('edit', ['record' => $record->getKey()])),

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

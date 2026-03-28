<?php

namespace App\Filament\Tenant\Pages;

use App\Enums\TenantType;
use App\Filament\Forms\Components\TranslatableField;
use App\Models\Artist;
use App\Support\Locations;
use BackedEnum;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ArtistProfile extends Page
{
    use Forms\Concerns\InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-user';
    protected static ?string $navigationLabel = 'Artist Profile';
    protected static \UnitEnum|string|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 0;
    protected string $view = 'filament.tenant.pages.artist-profile';

    public ?array $data = [];

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()?->tenant;
        if (!$tenant) return false;
        return in_array($tenant->tenant_type, [TenantType::TenantArtist, TenantType::Artist])
            && $tenant->artist_id !== null;
    }

    public function mount(): void
    {
        $tenant = auth()->user()->tenant;
        $artist = $tenant?->artist;

        if (!$artist) {
            abort(404, 'No artist profile linked to this tenant.');
        }

        $this->form->fill([
            'name' => $artist->name,
            'slug' => $artist->slug,
            'bio_html' => $artist->bio_html,
            'main_image_url' => $artist->main_image_url,
            'logo_url' => $artist->logo_url,
            'portrait_url' => $artist->portrait_url,
            'country' => $artist->country,
            'state' => $artist->state,
            'city' => $artist->city,
            'email' => $artist->email,
            'phone' => $artist->phone,
            'website' => $artist->website,
            'facebook_url' => $artist->facebook_url,
            'instagram_url' => $artist->instagram_url,
            'tiktok_url' => $artist->tiktok_url,
            'youtube_url' => $artist->youtube_url,
            'youtube_id' => $artist->youtube_id,
            'spotify_url' => $artist->spotify_url,
            'spotify_id' => $artist->spotify_id,
            'twitter_url' => $artist->twitter_url,
            'wiki_url' => $artist->wiki_url,
            'lastfm_url' => $artist->lastfm_url,
            'itunes_url' => $artist->itunes_url,
            'musicbrainz_url' => $artist->musicbrainz_url,
            'manager_first_name' => $artist->manager_first_name,
            'manager_last_name' => $artist->manager_last_name,
            'manager_email' => $artist->manager_email,
            'manager_phone' => $artist->manager_phone,
            'manager_website' => $artist->manager_website,
            'agent_first_name' => $artist->agent_first_name,
            'agent_last_name' => $artist->agent_last_name,
            'agent_email' => $artist->agent_email,
            'agent_phone' => $artist->agent_phone,
            'agent_website' => $artist->agent_website,
            'booking_agency' => $artist->booking_agency ?? [],
            'min_fee_concert' => $artist->min_fee_concert,
            'max_fee_concert' => $artist->max_fee_concert,
            'min_fee_festival' => $artist->min_fee_festival,
            'max_fee_festival' => $artist->max_fee_festival,
            'youtube_videos' => $artist->youtube_videos ?? [],
        ]);
    }

    public function form(Schema $form): Schema
    {
        $artist = auth()->user()->tenant?->artist;

        return $form
            ->schema([
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
                                    ->extraAttributes(['class' => 'ep-title'])
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if ($state) $set('slug', Str::slug($state));
                                    }),
                                Forms\Components\TextInput::make('slug')
                                    ->label('Slug')
                                    ->helperText('URL slug.')
                                    ->maxLength(190)
                                    ->rule('alpha_dash')
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
                                        ->helperText('1600×900+'),
                                    Forms\Components\FileUpload::make('logo_url')
                                        ->label('Logo (horiz.)')
                                        ->image()
                                        ->directory('artists/logo')
                                        ->disk('public')->visibility('public')
                                        ->maxSize(2048)
                                        ->helperText('800×300+'),
                                    Forms\Components\FileUpload::make('portrait_url')
                                        ->label('Portrait (vert.)')
                                        ->image()
                                        ->directory('artists/portrait')
                                        ->disk('public')->visibility('public')
                                        ->maxSize(4096)
                                        ->helperText('900×1200+'),
                                ]),
                            ]),
                        ]),

                        // === CONTENT ===
                        SC\Section::make('Bio')->compact()->schema([
                            TranslatableField::richEditor('bio_html', 'Bio')->columnSpanFull(),
                        ])->columns(1),

                        // === LOCATION ===
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
                        ])->columns(3),

                        // === SOCIAL & IDs ===
                        SC\Section::make('Social & IDs')->compact()->collapsible()->collapsed()->schema([
                            Forms\Components\TextInput::make('website')->label('Website')->url()->maxLength(255)->prefixIcon('heroicon-m-globe-alt'),
                            Forms\Components\TextInput::make('facebook_url')->label('Facebook')->url()->maxLength(255)->prefixIcon('heroicon-m-link'),
                            Forms\Components\TextInput::make('instagram_url')->label('Instagram')->url()->maxLength(255)->prefixIcon('heroicon-m-link'),
                            Forms\Components\TextInput::make('tiktok_url')->label('TikTok')->url()->maxLength(255)->prefixIcon('heroicon-m-link'),
                            Forms\Components\TextInput::make('youtube_url')->label('YouTube')->url()->maxLength(255)->prefixIcon('heroicon-m-link'),
                            Forms\Components\TextInput::make('youtube_id')->label('YouTube ID')->maxLength(190),
                            Forms\Components\TextInput::make('spotify_url')->label('Spotify')->url()->maxLength(255)->prefixIcon('heroicon-m-link'),
                            Forms\Components\TextInput::make('spotify_id')->label('Spotify ID')->maxLength(190),
                            Forms\Components\TextInput::make('twitter_url')->label('Twitter / X')->url()->maxLength(255)->prefixIcon('heroicon-m-link'),
                            Forms\Components\TextInput::make('wiki_url')->label('Wikipedia')->url()->maxLength(255)->prefixIcon('heroicon-m-link'),
                            Forms\Components\TextInput::make('lastfm_url')->label('Last.fm')->url()->maxLength(255)->prefixIcon('heroicon-m-link'),
                            Forms\Components\TextInput::make('itunes_url')->label('Apple Music')->url()->maxLength(255)->prefixIcon('heroicon-m-link'),
                            Forms\Components\TextInput::make('musicbrainz_url')->label('MusicBrainz')->url()->maxLength(255)->prefixIcon('heroicon-m-link'),
                        ])->columns(2),

                        // === YOUTUBE VIDEOS ===
                        SC\Section::make('YouTube videos')->compact()->collapsible()->collapsed()->schema([
                            Forms\Components\Repeater::make('youtube_videos')
                                ->label('Video URLs')
                                ->addActionLabel('Add video')
                                ->schema([
                                    Forms\Components\TextInput::make('url')->label('YouTube URL')->url()->required(),
                                ])
                                ->default([])
                                ->collapsed()
                                ->columns(1),
                        ]),

                        // === CONTACT + MANAGER ===
                        SC\Grid::make(2)->schema([
                            SC\Section::make('Contact')
                                ->icon('heroicon-o-envelope')
                                ->collapsible()->collapsed()->persistCollapsed()
                                ->schema([
                                    Forms\Components\TextInput::make('email')->label('Email')->email()->maxLength(190),
                                    Forms\Components\TextInput::make('phone')->label('Phone')->maxLength(120),
                                ])->columns(2),
                            SC\Section::make('Manager')
                                ->icon('heroicon-o-user')
                                ->collapsible()->collapsed()->persistCollapsed()
                                ->schema([
                                    Forms\Components\TextInput::make('manager_first_name')->label('First name')->maxLength(120),
                                    Forms\Components\TextInput::make('manager_last_name')->label('Last name')->maxLength(120),
                                    Forms\Components\TextInput::make('manager_email')->label('Email')->email()->maxLength(190),
                                    Forms\Components\TextInput::make('manager_phone')->label('Phone')->maxLength(120),
                                    Forms\Components\TextInput::make('manager_website')->label('Website')->url()->maxLength(255),
                                ])->columns(2),
                        ]),

                        // === AGENT + AGENCY ===
                        SC\Grid::make(2)->schema([
                            SC\Section::make('Booking Agent')
                                ->icon('heroicon-o-briefcase')
                                ->collapsible()->collapsed()->persistCollapsed()
                                ->schema([
                                    Forms\Components\TextInput::make('agent_first_name')->label('First name')->maxLength(120),
                                    Forms\Components\TextInput::make('agent_last_name')->label('Last name')->maxLength(120),
                                    Forms\Components\TextInput::make('agent_email')->label('Email')->email()->maxLength(190),
                                    Forms\Components\TextInput::make('agent_phone')->label('Phone')->maxLength(120),
                                    Forms\Components\TextInput::make('agent_website')->label('Website')->url()->maxLength(255),
                                ])->columns(2),
                            SC\Section::make('Booking Agency')
                                ->icon('heroicon-o-building-office')
                                ->collapsible()->collapsed()->persistCollapsed()
                                ->schema([
                                    Forms\Components\TextInput::make('booking_agency.name')->label('Agency Name'),
                                    Forms\Components\TextInput::make('booking_agency.email')->label('Email')->email(),
                                    Forms\Components\TextInput::make('booking_agency.phone')->label('Phone'),
                                    Forms\Components\TextInput::make('booking_agency.website')->label('Website')->url(),
                                ])->columns(2),
                        ]),

                        // === PRICING ===
                        SC\Section::make('Pricing')
                            ->icon('heroicon-o-banknotes')
                            ->collapsible()->collapsed()->persistCollapsed()
                            ->schema([
                                SC\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('min_fee_concert')->label('Min Fee Concert (€)')->numeric()->minValue(0),
                                    Forms\Components\TextInput::make('max_fee_concert')->label('Max Fee Concert (€)')->numeric()->minValue(0),
                                    Forms\Components\TextInput::make('min_fee_festival')->label('Min Fee Festival (€)')->numeric()->minValue(0),
                                    Forms\Components\TextInput::make('max_fee_festival')->label('Max Fee Festival (€)')->numeric()->minValue(0),
                                ]),
                            ]),
                    ]),

                    // ========== RIGHT SIDEBAR (1/4) ==========
                    SC\Group::make()->columnSpan(1)->schema([
                        // Artist Preview Card
                        SC\Section::make('')->compact()->schema([
                            Forms\Components\Placeholder::make('artist_preview')
                                ->hiddenLabel()
                                ->content(function () use ($artist) {
                                    if (!$artist) return '';
                                    $image = $artist->main_image_url
                                        ? asset('storage/' . $artist->main_image_url)
                                        : 'https://ui-avatars.com/api/?name=' . urlencode($artist->name) . '&color=7F9CF5&background=EBF4FF';
                                    $location = collect([$artist->city, $artist->country])->filter()->join(', ') ?: '—';
                                    return new \Illuminate\Support\HtmlString("
                                        <div style='display:flex;gap:10px;align-items:center;'>
                                            <img src='{$image}' alt='" . e($artist->name) . "' style='width:56px;height:56px;border-radius:50%;object-fit:cover;border:2px solid #334155;'>
                                            <div>
                                                <div style='font-size:16px;font-weight:700;color:white;'>" . e($artist->name) . "</div>
                                                <div style='font-size:12px;color:#64748B;'>{$location}</div>
                                            </div>
                                        </div>
                                    ");
                                }),
                        ]),

                        // Artist Stats
                        SC\Section::make('Stats (12 months)')
                            ->icon('heroicon-o-chart-bar')
                            ->compact()
                            ->schema([
                                Forms\Components\Placeholder::make('artist_stats_sidebar')
                                    ->hiddenLabel()
                                    ->content(function () use ($artist) {
                                        if (!$artist) return '';
                                        $eventsCount = $artist->eventsLastYearCount();
                                        $tickets = $artist->ticketsSoldLastYear();
                                        $sold = (int) ($tickets['sold'] ?? 0);
                                        $listed = (int) ($tickets['listed'] ?? 0);
                                        $row = fn ($label, $value) => "<div style='display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(51,65,85,0.5);'><span style='font-size:12px;color:#64748B;'>{$label}</span><span style='font-size:12px;font-weight:600;color:#E2E8F0;'>{$value}</span></div>";
                                        return new \Illuminate\Support\HtmlString(
                                            $row('Events', $eventsCount) .
                                            $row('Tickets sold / listed', "{$sold} / {$listed}") .
                                            $row('Avg per event', $tickets['avg_per_event'] ?? 0) .
                                            $row('Avg price', ($tickets['avg_price'] !== null) ? number_format($tickets['avg_price'], 2) : '—')
                                        );
                                    }),
                            ]),

                        // Social Stats
                        SC\Section::make('Social Stats')
                            ->icon('heroicon-o-signal')
                            ->compact()->collapsed()
                            ->schema([
                                Forms\Components\Placeholder::make('social_stats_visual')
                                    ->hiddenLabel()
                                    ->content(function () use ($artist) {
                                        if (!$artist) return '';
                                        $fmt = fn (?int $n) => (!$n) ? '-' : ($n >= 1000000 ? round($n/1000000,1).'M' : ($n >= 1000 ? round($n/1000,1).'K' : (string)$n));
                                        $stats = [
                                            ['bg' => '#1DB954', 'value' => $artist->spotify_monthly_listeners, 'label' => 'Spotify'],
                                            ['bg' => '#FF0000', 'value' => $artist->followers_youtube, 'label' => 'YouTube'],
                                            ['bg' => '#E4405F', 'value' => $artist->instagram_followers, 'label' => 'Instagram'],
                                            ['bg' => '#1877F2', 'value' => $artist->facebook_followers, 'label' => 'Facebook'],
                                            ['bg' => '#000000', 'value' => $artist->tiktok_followers, 'label' => 'TikTok'],
                                        ];
                                        $html = "<div style='display:grid;grid-template-columns:repeat(2,1fr);gap:8px;'>";
                                        foreach ($stats as $s) {
                                            $v = $fmt($s['value']);
                                            $html .= "<div style='text-align:center;'><div style='width:22px;height:22px;margin:0 auto 4px;border-radius:5px;background:{$s['bg']};display:flex;align-items:center;justify-content:center;'><span style='font-size:10px;color:white;font-weight:700;'>" . strtoupper(mb_substr($s['label'], 0, 1)) . "</span></div><div style='font-size:13px;font-weight:700;color:white;'>{$v}</div><div style='font-size:10px;color:#64748B;'>{$s['label']}</div></div>";
                                        }
                                        return new \Illuminate\Support\HtmlString($html . "</div>");
                                    }),
                            ]),
                    ]),
                ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $artist = auth()->user()->tenant?->artist;

        if (!$artist) {
            Notification::make()->danger()->title('No artist linked')->send();
            return;
        }

        $artist->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'bio_html' => $data['bio_html'],
            'main_image_url' => $data['main_image_url'],
            'logo_url' => $data['logo_url'],
            'portrait_url' => $data['portrait_url'],
            'country' => $data['country'],
            'state' => $data['state'],
            'city' => $data['city'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'website' => $data['website'],
            'facebook_url' => $data['facebook_url'],
            'instagram_url' => $data['instagram_url'],
            'tiktok_url' => $data['tiktok_url'],
            'youtube_url' => $data['youtube_url'],
            'youtube_id' => $data['youtube_id'],
            'spotify_url' => $data['spotify_url'],
            'spotify_id' => $data['spotify_id'],
            'twitter_url' => $data['twitter_url'],
            'wiki_url' => $data['wiki_url'],
            'lastfm_url' => $data['lastfm_url'],
            'itunes_url' => $data['itunes_url'],
            'musicbrainz_url' => $data['musicbrainz_url'],
            'manager_first_name' => $data['manager_first_name'],
            'manager_last_name' => $data['manager_last_name'],
            'manager_email' => $data['manager_email'],
            'manager_phone' => $data['manager_phone'],
            'manager_website' => $data['manager_website'],
            'agent_first_name' => $data['agent_first_name'],
            'agent_last_name' => $data['agent_last_name'],
            'agent_email' => $data['agent_email'],
            'agent_phone' => $data['agent_phone'],
            'agent_website' => $data['agent_website'],
            'booking_agency' => $data['booking_agency'] ?? [],
            'min_fee_concert' => $data['min_fee_concert'],
            'max_fee_concert' => $data['max_fee_concert'],
            'min_fee_festival' => $data['min_fee_festival'],
            'max_fee_festival' => $data['max_fee_festival'],
            'youtube_videos' => $data['youtube_videos'] ?? [],
        ]);

        Notification::make()
            ->success()
            ->title('Artist profile updated')
            ->body('Your artist profile has been saved.')
            ->send();
    }

    public function getTitle(): string
    {
        return 'Artist Profile';
    }
}

<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\ArtistResource\Pages;
use App\Models\Artist;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Utilities\Set as SSet;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use Illuminate\Support\Str;

class ArtistResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = Artist::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Artiști';
    protected static ?string $modelLabel = 'Artist';
    protected static ?string $pluralModelLabel = 'Artiști';
    protected static \UnitEnum|string|null $navigationGroup = null;
    protected static ?int $navigationSort = 4;

    public static function getNavigationBadge(): ?string
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) return null;

        return (string) static::getEloquentQuery()->count();
    }

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplace?->id);
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();

        return $schema->schema([
            // Hidden marketplace_client_id
            Forms\Components\Hidden::make('marketplace_client_id')
                ->default($marketplace?->id),

            // Set as partner automatically when created from marketplace
            Forms\Components\Hidden::make('is_partner')
                ->default(true),

            SC\Grid::make(4)->schema([
                SC\Group::make()->columnSpan(3)->schema([
                    SC\Grid::make(5)->schema([
                        // NAME & SLUG
                        SC\Section::make('Identitate Artist')
                            ->icon('heroicon-o-identification')
                            ->columnSpan(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nume artist')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, SSet $set, $context) {
                                        if ($state && $context === 'create') $set('slug', Str::slug($state));
                                    }),
                                Forms\Components\TextInput::make('slug')
                                    ->label('Slug')
                                    ->required()
                                    ->maxLength(190)
                                    ->unique(ignoreRecord: true)
                                    ->rule('alpha_dash')
                                    ->placeholder('auto-generated-from-name'),

                                // Real-time search for existing artists in core DB (only on create)
                                Forms\Components\Placeholder::make('existing_artists_search')
                                    ->label('')
                                    ->content(function ($get) use ($marketplace) {
                                        $name = $get('name');
                                        if (empty($name) || mb_strlen($name) < 2) {
                                            return '';
                                        }

                                        $artists = Artist::where('name', 'LIKE', "%{$name}%")
                                            ->where(function ($q) use ($marketplace) {
                                                $q->whereNull('marketplace_client_id')
                                                    ->orWhere('marketplace_client_id', $marketplace?->id);
                                            })
                                            ->limit(5)
                                            ->get();

                                        if ($artists->isEmpty()) {
                                            return new \Illuminate\Support\HtmlString(
                                                '<div class="text-xs text-gray-400 italic py-1">Niciun artist existent găsit pentru „' . e($name) . '"</div>'
                                            );
                                        }

                                        $html = '<div class="space-y-1 py-1">'
                                            . '<div class="text-xs font-medium text-amber-600 mb-1">⚠ Artiști existenți cu nume similar:</div>';

                                        foreach ($artists as $artist) {
                                            $isPartner = $artist->marketplace_client_id === $marketplace?->id;
                                            $city = $artist->city ? ' — ' . e($artist->city) : '';

                                            if ($isPartner) {
                                                // Already a partner - link to edit
                                                $editUrl = ArtistResource::getUrl('edit', ['record' => $artist]);
                                                $html .= '<div class="flex items-center justify-between gap-2 px-2 py-1 rounded bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">'
                                                    . '<span class="text-xs"><strong>' . e($artist->name) . '</strong>' . $city . '</span>'
                                                    . '<a href="' . $editUrl . '" class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium text-green-700 bg-green-100 rounded hover:bg-green-200">'
                                                    . '✓ Partener — Editează</a>'
                                                    . '</div>';
                                            } else {
                                                // Available to add as partner
                                                $partnerUrl = \App\Filament\Marketplace\Pages\PartnerArtists::getUrl();
                                                $html .= '<div class="flex items-center justify-between gap-2 px-2 py-1 rounded bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">'
                                                    . '<span class="text-xs"><strong>' . e($artist->name) . '</strong>' . $city . '</span>'
                                                    . '<a href="' . $partnerUrl . '?tableSearch=' . urlencode($artist->name) . '" class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium text-amber-700 bg-amber-100 rounded hover:bg-amber-200">'
                                                    . '+ Adaugă partener</a>'
                                                    . '</div>';
                                            }
                                        }

                                        $html .= '</div>';
                                        return new \Illuminate\Support\HtmlString($html);
                                    })
                                    ->visible(fn ($context) => $context === 'create'),
                            ])->columns(1),

                        // IMAGES
                        SC\Section::make('Media')
                            ->icon('heroicon-o-photo')
                            ->columnSpan(3)
                            ->schema([
                                Forms\Components\FileUpload::make('main_image_url')
                                    ->label('Imagine principală')
                                    ->image()
                                    ->disk('public')
                                    ->directory('artists')
                                    ->visibility('public')
                                    ->maxSize(10240)
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                                    ->afterStateUpdated(fn ($livewire) => $livewire->skipRender()),
                                Forms\Components\FileUpload::make('logo_url')
                                    ->label('Logo')
                                    ->image()
                                    ->disk('public')
                                    ->directory('artists/logos')
                                    ->visibility('public')
                                    ->maxSize(10240)
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                                    ->afterStateUpdated(fn ($livewire) => $livewire->skipRender()),
                                Forms\Components\FileUpload::make('portrait_url')
                                    ->label('Portret')
                                    ->image()
                                    ->disk('public')
                                    ->directory('artists/portraits')
                                    ->visibility('public')
                                    ->maxSize(10240)
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                                    ->afterStateUpdated(fn ($livewire) => $livewire->skipRender()),
                            ])->columns(3),
                    ]),
                    // BIOGRAPHY - EN/RO
                    SC\Section::make('Biografie')
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            SC\Tabs::make('Bio Translations')
                                ->tabs([
                                    SC\Tabs\Tab::make('Română')
                                        ->schema([
                                            Forms\Components\RichEditor::make('bio_html.ro')
                                                ->label('Biografie (RO)')
                                                ->columnSpanFull(),
                                        ]),
                                    SC\Tabs\Tab::make('English')
                                        ->schema([
                                            Forms\Components\RichEditor::make('bio_html.en')
                                                ->label('Biography (EN)')
                                                ->columnSpanFull(),
                                        ]),
                                ])->columnSpanFull(),
                        ]),
                    SC\Grid::make(2)->schema([
                        // LOCATION
                        SC\Section::make('Locație')
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                Forms\Components\TextInput::make('city')
                                    ->label('Oraș')
                                    ->maxLength(120)
                                    ->placeholder('e.g. București'),
                                Forms\Components\TextInput::make('country')
                                    ->label('Țară')
                                    ->maxLength(120)
                                    ->placeholder('e.g. România'),
                            ])->columns(1),

                        // TYPES & GENRES
                        SC\Section::make('Categorii')
                            ->icon('heroicon-o-tag')
                            ->schema([
                                Forms\Components\Select::make('artistTypes')
                                    ->label('Tip artist')
                                    ->relationship('artistTypes', 'slug')
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('name', 'ro') ?: $record->getTranslation('name', 'en'))
                                    ->multiple()
                                    ->preload()
                                    ->searchable(),

                                Forms\Components\Select::make('artistGenres')
                                    ->label('Genuri muzicale')
                                    ->relationship('artistGenres', 'slug')
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('name', 'ro') ?: $record->getTranslation('name', 'en'))
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name.ro')
                                            ->label('Nume (RO)')
                                            ->required(),
                                        Forms\Components\TextInput::make('name.en')
                                            ->label('Nume (EN)'),
                                        Forms\Components\TextInput::make('slug')
                                            ->label('Slug')
                                            ->helperText('Se generează automat dacă e gol'),
                                    ])
                                    ->createOptionUsing(function (array $data): int {
                                        $slug = $data['slug'] ?? Str::slug($data['name']['ro'] ?? $data['name']['en'] ?? 'genre');
                                        return \App\Models\ArtistGenre::create([
                                            'name' => array_filter([
                                                'ro' => $data['name']['ro'] ?? null,
                                                'en' => $data['name']['en'] ?? null,
                                            ]),
                                            'slug' => $slug,
                                        ])->id;
                                    })
                                    ->multiple()
                                    ->preload()
                                    ->searchable(),
                            ])->columns(1),
                    ]),

                    // SOCIAL & LINKS
                    SC\Section::make('Social Media & Link-uri')
                        ->icon('heroicon-o-link')
                        ->collapsible()
                        ->collapsed()
                        ->schema([
                            Forms\Components\TextInput::make('website')
                                ->label('Website')
                                ->url()
                                ->placeholder('https://...')
                                ->prefixIcon('heroicon-o-globe-alt'),
                            Forms\Components\TextInput::make('facebook_url')
                                ->label('Facebook')
                                ->url()
                                ->placeholder('https://facebook.com/...')
                                ->prefixIcon('heroicon-o-link'),
                            Forms\Components\TextInput::make('instagram_url')
                                ->label('Instagram')
                                ->url()
                                ->placeholder('https://instagram.com/...')
                                ->prefixIcon('heroicon-o-link'),
                            Forms\Components\TextInput::make('tiktok_url')
                                ->label('TikTok')
                                ->url()
                                ->placeholder('https://tiktok.com/@...')
                                ->prefixIcon('heroicon-o-link'),
                            Forms\Components\TextInput::make('youtube_url')
                                ->label('YouTube')
                                ->url()
                                ->placeholder('https://youtube.com/...')
                                ->prefixIcon('heroicon-o-play'),
                            Forms\Components\TextInput::make('spotify_url')
                                ->label('Spotify')
                                ->url()
                                ->placeholder('https://open.spotify.com/artist/...')
                                ->prefixIcon('heroicon-o-musical-note'),
                            Forms\Components\TextInput::make('spotify_id')
                                ->label('Spotify Artist ID')
                                ->placeholder('e.g. 4gzpq5DPGxSnKTe4SA8HAU')
                                ->helperText('Se găsește în URL-ul Spotify după /artist/')
                                ->prefixIcon('heroicon-o-musical-note'),
                            Forms\Components\TextInput::make('youtube_id')
                                ->label('YouTube Channel ID')
                                ->placeholder('e.g. UCq-Fj5jknLsUf-MWSy4_brA')
                                ->helperText('ID-ul canalului YouTube (nu numele)')
                                ->prefixIcon('heroicon-o-play'),
                        ])->columns(2),

                    // YOUTUBE VIDEOS - compact layout
                    SC\Section::make('Videoclipuri YouTube')
                        ->description('Maxim 5 videoclipuri')
                        ->icon('heroicon-o-play')
                        ->collapsible()
                        ->collapsed()
                        ->schema([
                            Forms\Components\Repeater::make('youtube_videos')
                                ->hiddenLabel()
                                ->schema([
                                    Forms\Components\TextInput::make('url')
                                        ->hiddenLabel()
                                        ->placeholder('https://www.youtube.com/watch?v=...')
                                        ->url()
                                        ->required()
                                        ->prefixIcon('heroicon-o-play')
                                        ->columnSpanFull(),
                                ])
                                ->maxItems(5)
                                ->defaultItems(0)
                                ->addActionLabel('Adaugă videoclip')
                                ->reorderable()
                                ->reorderableWithDragAndDrop()
                                ->collapsed()
                                ->itemLabel(fn (array $state) => $state['url'] ?? 'Video')
                                ->columnSpanFull(),
                            ]),

                    SC\Grid::make(2)->schema([
                        // CONTACT
                        SC\Section::make('Contact')
                            ->icon('heroicon-o-envelope')
                            ->collapsible()->collapsed()
                            ->schema([
                                Forms\Components\TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->placeholder('contact@artist.com')
                                    ->prefixIcon('heroicon-o-envelope'),
                                Forms\Components\TextInput::make('phone')
                                    ->label('Telefon')
                                    ->maxLength(64)
                                    ->placeholder('+40 ...')
                                    ->prefixIcon('heroicon-o-phone'),
                            ])->columns(2),
                        // MANAGER
                        SC\Section::make('Manager')
                            ->icon('heroicon-o-user')
                            ->collapsible()->collapsed()
                            ->schema([
                                Forms\Components\TextInput::make('manager_first_name')
                                    ->label('Prenume'),
                                Forms\Components\TextInput::make('manager_last_name')
                                    ->label('Nume'),
                                Forms\Components\TextInput::make('manager_email')
                                    ->label('Email')
                                    ->email(),
                                Forms\Components\TextInput::make('manager_phone')
                                    ->label('Telefon'),
                            ])->columns(2),
                    ]),

                    SC\Grid::make(2)->schema([
                        SC\Section::make('Agent Booking')
                            ->icon('heroicon-o-briefcase')
                            ->collapsible()->collapsed()
                            ->schema([
                                Forms\Components\TextInput::make('agent_first_name')
                                    ->label('Prenume'),
                                Forms\Components\TextInput::make('agent_last_name')
                                    ->label('Nume'),
                                Forms\Components\TextInput::make('agent_email')
                                    ->label('Email')
                                    ->email(),
                                Forms\Components\TextInput::make('agent_phone')
                                    ->label('Telefon'),
                            ])->columns(2),
                        // BOOKING AGENCY
                        SC\Section::make('Agenție de Booking')
                            ->icon('heroicon-o-building-office')
                            ->collapsible()->collapsed()
                            ->schema([
                                Forms\Components\TextInput::make('booking_agency.name')
                                    ->label('Nume Agenție')
                                    ->placeholder('e.g. Universal Music Romania'),
                                Forms\Components\TextInput::make('booking_agency.email')
                                    ->label('Email')
                                    ->email()
                                    ->placeholder('booking@agency.com'),
                                Forms\Components\TextInput::make('booking_agency.phone')
                                    ->label('Telefon')
                                    ->placeholder('+40 ...'),
                                Forms\Components\TextInput::make('booking_agency.website')
                                    ->label('Website')
                                    ->url()
                                    ->placeholder('https://...'),
                            ])->columns(2),
                    ]),

                    // PARTNER NOTES (internal)
                    SC\Section::make('Note interne')
                        ->description('Note interne despre acest artist (nu sunt vizibile public)')
                        ->icon('heroicon-o-lock-closed')
                        ->collapsible()->collapsed()
                        ->schema([
                            Forms\Components\Textarea::make('partner_notes')
                                ->label('Note')
                                ->placeholder('Note despre parteneriat, contracte, etc.')
                                ->rows(4)
                                ->columnSpanFull(),
                        ]),
                ]),
                SC\Group::make()->columnSpan(1)->schema([
                    // Artist Preview Card
                    SC\Section::make('')
                        ->compact()
                        ->schema([
                            Forms\Components\Placeholder::make('artist_preview')
                                ->hiddenLabel()
                                ->content(function (?Artist $record) {
                                    if (!$record) return '';

                                    $image = $record->main_image_url 
                                        ? asset('storage/' . $record->main_image_url) 
                                        : 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&color=7F9CF5&background=EBF4FF';
                                    
                                    $location = collect([$record->city, $record->country])->filter()->join(', ') ?: 'Locație necunoscută';
                                    
                                    $badges = '';
                                    if ($record->is_active) {
                                        $badges .= '<span style="display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: rgba(16, 185, 129, 0.15); color: #10B981; margin-right: 6px;">✓ Activ</span>';
                                    }
                                    if ($record->is_featured) {
                                        $badges .= '<span style="display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: rgba(245, 158, 11, 0.15); color: #F59E0B; margin-right: 6px;">★ Promovat</span>';
                                    }
                                    
                                    // Get artist types
                                    $types = $record->artistTypes->map(fn($t) => $t->getTranslation('name', 'ro') ?: $t->getTranslation('name', 'en'))->filter();
                                    foreach ($types as $type) {
                                        $badges .= '<span style="display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: #334155; color: #E2E8F0; margin-right: 6px;">' . e($type) . '</span>';
                                    }

                                    return new \Illuminate\Support\HtmlString("
                                        <div style='display: flex; gap: 12px; align-items: center;'>
                                            <img src='{$image}' alt='" . e($record->name) . "' style='width: 64px; height: 64px; border-radius: 50%; object-fit: cover; border: 3px solid #334155;'>
                                            <div style='flex: 1;'>
                                                <div style='font-size: 18px; font-weight: 700; color: white; margin-bottom: 4px;'>" . e($record->name) . "</div>
                                                <div style='font-size: 13px; color: #64748B; display: flex; align-items: center; gap: 4px;'>
                                                    <svg style='width: 12px; height: 12px;' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z'/></svg>
                                                    {$location}
                                                </div>
                                            </div>
                                        </div>
                                        <div style='margin-top: 12px; display: flex; flex-wrap: wrap; gap: 6px;'>
                                            {$badges}
                                        </div>
                                    ");
                                }),
                        ]),

                    // Status Toggles
                    SC\Section::make('Status')
                        ->icon('heroicon-o-eye')
                        ->compact()
                        ->schema([
                            Forms\Components\Toggle::make('is_active')
                                ->label('Activ')
                                ->helperText('Artistul apare pe site')
                                ->default(true)
                                ->onColor('success')
                                ->offColor('gray'),
                            Forms\Components\Toggle::make('is_featured')
                                ->label('Promovat')
                                ->helperText('Apare în secțiunea de artiști promovați')
                                ->default(false)
                                ->onColor('warning')
                                ->offColor('gray'),
                        ]),
                    // SOCIAL STATS
                    SC\Section::make('Social Stats')
                        ->icon('heroicon-o-chart-bar')
                        ->compact()
                        ->collapsed()
                        ->visible(fn (?Artist $record) => $record?->exists)
                        ->schema([
                            Forms\Components\Placeholder::make('social_stats_preview')
                                ->hiddenLabel()
                                ->content(function (?Artist $record) {
                                    if (!$record) return '';

                                    $stats = [
                                        [
                                            'icon' => 'spotify',
                                            'bg' => '#1DB954',
                                            'value' => $record->spotify_monthly_listeners,
                                            'label' => 'Monthly Listeners',
                                            'svg' => '<path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/>',
                                        ],
                                        [
                                            'icon' => 'spotify',
                                            'bg' => '#1DB954',
                                            'value' => $record->spotify_popularity,
                                            'label' => 'Spotify Popularity',
                                            'svg' => '<path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/>',
                                        ],
                                        [
                                            'icon' => 'youtube',
                                            'bg' => '#FF0000',
                                            'value' => $record->followers_youtube,
                                            'label' => 'Subscribers',
                                            'svg' => '<path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>',
                                        ],
                                        [
                                            'icon' => 'youtube',
                                            'bg' => '#FF0000',
                                            'value' => $record->youtube_total_views,
                                            'label' => 'Views',
                                            'svg' => '<path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>',
                                        ],
                                        [
                                            'icon' => 'instagram',
                                            'bg' => 'linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888)',
                                            'value' => $record->instagram_followers,
                                            'label' => 'Followers',
                                            'svg' => '<path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>',
                                        ],
                                        [
                                            'icon' => 'facebook',
                                            'bg' => '#1877F2',
                                            'value' => $record->facebook_followers,
                                            'label' => 'Followers',
                                            'svg' => '<path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>',
                                        ],
                                        [
                                            'icon' => 'tiktok',
                                            'bg' => '#000000',
                                            'value' => $record->tiktok_followers,
                                            'label' => 'Followers',
                                            'svg' => '<path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>',
                                        ],
                                    ];

                                    $html = "<div style='display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px;'>";
                                    
                                    foreach ($stats as $stat) {
                                        $value = $stat['value'] ? self::formatNumber($stat['value']) : '-';
                                        $bgStyle = str_contains($stat['bg'], 'gradient') 
                                            ? "background: {$stat['bg']};" 
                                            : "background: {$stat['bg']};";
                                        
                                        $html .= "
                                            <div style='text-align: center;'>
                                                <div style='width: 24px; height: 24px; margin: 0 auto 6px; border-radius: 6px; display: flex; align-items: center; justify-content: center; {$bgStyle}'>
                                                    <svg style='width: 14px; height: 14px; color: white;' fill='currentColor' viewBox='0 0 24 24'>{$stat['svg']}</svg>
                                                </div>
                                                <div style='font-size: 14px; font-weight: 700; color: white;'>{$value}</div>
                                                <div style='font-size: 10px; color: #64748B; margin-top: 2px;'>{$stat['label']}</div>
                                            </div>
                                        ";
                                    }
                                    
                                    $html .= "</div>";

                                    return new \Illuminate\Support\HtmlString($html);
                                }),
                        ]),

                    // Evenimente Stats
                    SC\Section::make('Evenimente')
                        ->icon('heroicon-o-calendar')
                        ->compact()
                        ->visible(fn (?Artist $record) => $record?->exists)
                        ->schema([
                            Forms\Components\Placeholder::make('events_stats')
                                ->hiddenLabel()
                                ->content(function (?Artist $record) {
                                    if (!$record) return '';

                                    // Presupunând că ai o relație events() pe Artist
                                    $totalEvents = $record->events()->count();
                                    $upcomingEvents = $record->events()->where('event_date', '>=', now())->count();
                                    $pastEvents = $record->events()->where('event_date', '<', now())->count();

                                    return new \Illuminate\Support\HtmlString("
                                        <div style='space-y: 0;'>
                                            <div style='display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                                                <span style='font-size: 13px; color: #64748B;'>Total evenimente</span>
                                                <span style='font-size: 13px; font-weight: 600; color: #E2E8F0;'>{$totalEvents}</span>
                                            </div>
                                            <div style='display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                                                <span style='font-size: 13px; color: #64748B;'>Viitoare</span>
                                                <span style='font-size: 13px; font-weight: 600; color: #10B981;'>{$upcomingEvents}</span>
                                            </div>
                                            <div style='display: flex; justify-content: space-between; align-items: center; padding: 8px 0;'>
                                                <span style='font-size: 13px; color: #64748B;'>Încheiate</span>
                                                <span style='font-size: 13px; font-weight: 600; color: #64748B;'>{$pastEvents}</span>
                                            </div>
                                        </div>
                                    ");
                                }),
                        ]),

                    // Meta Info
                    SC\Section::make('Informații')
                        ->icon('heroicon-o-information-circle')
                        ->compact()
                        ->visible(fn (?Artist $record) => $record?->exists)
                        ->schema([
                            Forms\Components\Placeholder::make('meta_info')
                                ->hiddenLabel()
                                ->content(function (?Artist $record) {
                                    if (!$record) return '';

                                    $createdAt = $record->created_at->format('d M Y');
                                    $updatedAt = $record->updated_at->diffForHumans();

                                    return new \Illuminate\Support\HtmlString("
                                        <div>
                                            <div style='display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                                                <span style='font-size: 13px; color: #64748B;'>Creat</span>
                                                <span style='font-size: 13px; font-weight: 600; color: #E2E8F0;'>{$createdAt}</span>
                                            </div>
                                            <div style='display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                                                <span style='font-size: 13px; color: #64748B;'>Modificat</span>
                                                <span style='font-size: 13px; font-weight: 600; color: #E2E8F0;'>{$updatedAt}</span>
                                            </div>
                                            <div style='display: flex; justify-content: space-between; align-items: center; padding: 8px 0;'>
                                                <span style='font-size: 13px; color: #64748B;'>ID</span>
                                                <span style='font-size: 11px; font-weight: 600; color: #64748B; font-family: monospace;'>{$record->id}</span>
                                            </div>
                                        </div>
                                    ");
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
                Tables\Columns\ImageColumn::make('main_image_url')
                    ->label('Imagine')
                    ->circular()
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?name=A&color=7F9CF5&background=EBF4FF'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nume')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city')
                    ->label('Oraș')
                    ->sortable(),
                Tables\Columns\TextColumn::make('artistTypes')
                    ->label('Tip')
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->artistTypes->map(fn($t) => $t->getTranslation('name', 'ro') ?: $t->getTranslation('name', 'en'))->filter()->toArray())
                    ->toggleable(),
                Tables\Columns\TextColumn::make('artistGenres')
                    ->label('Genuri')
                    ->badge()
                    ->color('info')
                    ->getStateUsing(fn ($record) => $record->artistGenres->map(fn($g) => $g->getTranslation('name', 'ro') ?: $g->getTranslation('name', 'en'))->filter()->toArray())
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Promovat')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activ')
                    ->boolean()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Doar promovați'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Doar activi'),
                Tables\Filters\SelectFilter::make('artistTypes')
                    ->label('Tip artist')
                    ->relationship('artistTypes', 'name')
                    ->preload()
                    ->multiple(),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArtists::route('/'),
            'create' => Pages\CreateArtist::route('/create'),
            'edit' => Pages\EditArtist::route('/{record}/edit'),
        ];
    }

    /**
     * Format large numbers with K/M/B suffixes
     */
    protected static function formatNumber(?int $number): string
    {
        if ($number === null || $number === 0) {
            return '-';
        }

        if ($number >= 1000000000) {
            return round($number / 1000000000, 1) . 'B';
        }

        if ($number >= 1000000) {
            return round($number / 1000000, 1) . 'M';
        }

        if ($number >= 1000) {
            return round($number / 1000, 1) . 'K';
        }

        return number_format($number);
    }
}

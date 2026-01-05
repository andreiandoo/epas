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

            // NAME & SLUG
            SC\Section::make('Identitate Artist')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nume artist')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, SSet $set) {
                            if ($state) $set('slug', Str::slug($state));
                        }),
                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->maxLength(190)
                        ->unique(ignoreRecord: true)
                        ->rule('alpha_dash')
                        ->placeholder('auto-generated-from-name'),
                ])->columns(2),

            // IMAGES
            SC\Section::make('Media')
                ->schema([
                    Forms\Components\FileUpload::make('main_image_url')
                        ->label('Imagine principală')
                        ->image()
                        ->imagePreviewHeight('200')
                        ->disk('public')
                        ->directory('artists')
                        ->visibility('public'),
                    Forms\Components\FileUpload::make('logo_url')
                        ->label('Logo')
                        ->image()
                        ->disk('public')
                        ->directory('artists/logos')
                        ->visibility('public'),
                    Forms\Components\FileUpload::make('portrait_url')
                        ->label('Portret')
                        ->image()
                        ->disk('public')
                        ->directory('artists/portraits')
                        ->visibility('public'),
                ])->columns(3),

            // LOCATION
            SC\Section::make('Locație')
                ->schema([
                    Forms\Components\TextInput::make('city')
                        ->label('Oraș')
                        ->maxLength(120)
                        ->placeholder('e.g. București'),
                    Forms\Components\TextInput::make('country')
                        ->label('Țară')
                        ->maxLength(120)
                        ->placeholder('e.g. România'),
                ])->columns(2),

            // TYPES & GENRES
            SC\Section::make('Categorii')
                ->schema([
                    Forms\Components\Select::make('artistTypes')
                        ->label('Tip artist')
                        ->relationship(name: 'artistTypes', titleAttribute: 'id')
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('name', 'ro') ?: $record->getTranslation('name', 'en') ?: ($record->name['ro'] ?? $record->name['en'] ?? 'N/A'))
                        ->multiple()
                        ->preload()
                        ->searchable(),
                    Forms\Components\Select::make('artistGenres')
                        ->label('Genuri muzicale')
                        ->relationship(name: 'artistGenres', titleAttribute: 'id')
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('name', 'ro') ?: $record->getTranslation('name', 'en') ?: ($record->name['ro'] ?? $record->name['en'] ?? 'N/A'))
                        ->multiple()
                        ->preload()
                        ->searchable(),
                ])->columns(2),

            // EXTERNAL IDs
            SC\Section::make('ID-uri Externe')
                ->description('ID-urile pentru integrări cu servicii externe (pentru embed-uri și statistici)')
                ->collapsible()
                ->collapsed()
                ->schema([
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

            // SOCIAL & LINKS
            SC\Section::make('Social Media & Link-uri')
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
                ])->columns(2),

            // SOCIAL STATS
            SC\Section::make('Statistici Social Media')
                ->description('Numărul de urmăritori pe diferite platforme (actualizate automat sau manual)')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('spotify_monthly_listeners')
                        ->label('Spotify Monthly Listeners')
                        ->numeric()
                        ->placeholder('1000000'),
                    Forms\Components\TextInput::make('spotify_popularity')
                        ->label('Spotify Popularity (0-100)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100),
                    Forms\Components\TextInput::make('instagram_followers')
                        ->label('Instagram Followers')
                        ->numeric()
                        ->placeholder('500000'),
                    Forms\Components\TextInput::make('facebook_followers')
                        ->label('Facebook Followers')
                        ->numeric()
                        ->placeholder('300000'),
                    Forms\Components\TextInput::make('youtube_followers')
                        ->label('YouTube Subscribers')
                        ->numeric()
                        ->placeholder('200000'),
                    Forms\Components\TextInput::make('tiktok_followers')
                        ->label('TikTok Followers')
                        ->numeric()
                        ->placeholder('100000'),
                ])->columns(3),

            // YOUTUBE VIDEOS
            SC\Section::make('Videoclipuri YouTube')
                ->description('Lista de videoclipuri YouTube pentru embed (maxim 5)')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\Repeater::make('youtube_videos')
                        ->label('')
                        ->schema([
                            Forms\Components\TextInput::make('video_id')
                                ->label('YouTube Video ID')
                                ->placeholder('e.g. dQw4w9WgXcQ')
                                ->required(),
                            Forms\Components\TextInput::make('title')
                                ->label('Titlu video')
                                ->placeholder('Numele piesei'),
                        ])
                        ->columns(2)
                        ->maxItems(5)
                        ->defaultItems(0)
                        ->addActionLabel('Adaugă videoclip')
                        ->columnSpanFull(),
                ]),

            // CONTACT
            SC\Section::make('Contact')
                ->collapsible()
                ->collapsed()
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
                ->collapsible()
                ->collapsed()
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
                ])->columns(4),

            // AGENT
            SC\Section::make('Agent Booking')
                ->collapsible()
                ->collapsed()
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
                ])->columns(4),

            // BOOKING AGENCY
            SC\Section::make('Agenție de Booking')
                ->description('Informații despre agenția de booking pentru acest artist')
                ->collapsible()
                ->collapsed()
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
                ])->columns(4),

            // BIOGRAPHY - EN/RO
            SC\Section::make('Biografie')
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

            // STATUS FLAGS
            SC\Section::make('Status')
                ->schema([
                    Forms\Components\Toggle::make('is_active')
                        ->label('Activ')
                        ->default(true),
                    Forms\Components\Toggle::make('is_featured')
                        ->label('Promovat')
                        ->helperText('Artistul va apărea în secțiunea de artiști promovați'),
                ])->columns(2),

            // PARTNER NOTES (internal)
            SC\Section::make('Note interne')
                ->description('Note interne despre acest artist (nu sunt vizibile public)')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('partner_notes')
                        ->label('Note')
                        ->placeholder('Note despre parteneriat, contracte, etc.')
                        ->rows(4)
                        ->columnSpanFull(),
                ]),
        ]);
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
                Tables\Columns\TextColumn::make('artistTypes.name')
                    ->label('Tip')
                    ->badge()
                    ->separator(',')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('artistGenres.name')
                    ->label('Genuri')
                    ->badge()
                    ->color('info')
                    ->separator(',')
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
}

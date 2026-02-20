<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\VenueResource\Pages;
use App\Models\Venue;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Utilities\Set as SSet;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class VenueResource extends Resource
{
    protected static ?string $model = Venue::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office';
    protected static \UnitEnum|string|null $navigationGroup = null;
    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()->where('tenant_id', $tenant?->id);
    }

    public static function form(Schema $schema): Schema
    {
        $tenant = auth()->user()->tenant;

        return $schema->schema([
            // Hidden tenant_id
            Forms\Components\Hidden::make('tenant_id')
                ->default($tenant?->id),

            // NAME & SLUG - EN/RO
            SC\Section::make('Venue Identity')
                ->schema([
                    SC\Tabs::make('Name Translations')
                        ->tabs([
                            SC\Tabs\Tab::make('English')
                                ->schema([
                                    Forms\Components\TextInput::make('name.en')
                                        ->label('Venue name (EN)')
                                        ->required()
                                        ->maxLength(255)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function ($state, SSet $set) {
                                            if ($state) $set('slug', Str::slug($state));
                                        }),
                                ]),
                            SC\Tabs\Tab::make('Română')
                                ->schema([
                                    Forms\Components\TextInput::make('name.ro')
                                        ->label('Nume locație (RO)')
                                        ->maxLength(255),
                                ]),
                        ])->columnSpanFull(),

                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->maxLength(190)
                        ->unique(ignoreRecord: true)
                        ->rule('alpha_dash')
                        ->placeholder('auto-generated-from-name'),
                ])->columns(1),

            // IMAGE & GALLERY
            SC\Section::make('Media')
                ->schema([
                    Forms\Components\FileUpload::make('image_url')
                        ->label('Main image')
                        ->image()
                        ->imagePreviewHeight('200')
                        ->disk('public')
                        ->directory('venues')
                        ->visibility('public'),
                    Forms\Components\FileUpload::make('gallery')
                        ->label('Gallery')
                        ->image()
                        ->multiple()
                        ->directory('venues/gallery')
                        ->visibility('public')
                        ->reorderable()
                        ->columnSpanFull(),

                    // Video field
                    SC\Grid::make(2)->schema([
                        Forms\Components\Select::make('video_type')
                            ->label('Video Type')
                            ->options([
                                'youtube' => 'YouTube Link',
                                'upload' => 'Upload Video',
                            ])
                            ->placeholder('No video')
                            ->live()
                            ->nullable(),
                        Forms\Components\TextInput::make('video_url')
                            ->label('YouTube URL')
                            ->url()
                            ->placeholder('https://www.youtube.com/watch?v=...')
                            ->prefixIcon('heroicon-o-play')
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('video_type') === 'youtube'),
                    ])->columnSpanFull(),
                    Forms\Components\FileUpload::make('video_url')
                        ->label('Upload Video')
                        ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/ogg'])
                        ->disk('public')
                        ->directory('venues/videos')
                        ->visibility('public')
                        ->maxSize(102400) // 100MB
                        ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('video_type') === 'upload')
                        ->columnSpanFull(),
                ])->columns(2),

            // LOCATION
            SC\Section::make('Location')
                ->schema([
                    Forms\Components\TextInput::make('address')
                        ->label('Address')
                        ->maxLength(255)
                        ->placeholder('Street and number'),
                    Forms\Components\TextInput::make('city')
                        ->label('City')
                        ->maxLength(120)
                        ->placeholder('e.g. București'),
                    Forms\Components\TextInput::make('state')
                        ->label('State/Region')
                        ->maxLength(120)
                        ->placeholder('e.g. Ilfov'),
                    Forms\Components\TextInput::make('country')
                        ->label('Country')
                        ->maxLength(120)
                        ->placeholder('e.g. RO'),
                    Forms\Components\TextInput::make('lat')
                        ->label('Latitude')
                        ->numeric()
                        ->step('0.0000001')
                        ->placeholder('44.4268'),
                    Forms\Components\TextInput::make('lng')
                        ->label('Longitude')
                        ->numeric()
                        ->step('0.0000001')
                        ->placeholder('26.1025'),
                    Forms\Components\TextInput::make('google_maps_url')
                        ->label('Google Maps Link')
                        ->url()
                        ->placeholder('https://maps.google.com/...')
                        ->prefixIcon('heroicon-o-map')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('google_place_id')
                        ->label('Google Place ID')
                        ->placeholder('Auto-detected or paste manually')
                        ->helperText('Used to fetch Google Reviews. Auto-detected from Maps link or venue name.')
                        ->prefixIcon('heroicon-o-star')
                        ->columnSpanFull(),
                ])->columns(3),

            // CAPACITY
            SC\Section::make('Capacity')
                ->schema([
                    Forms\Components\TextInput::make('capacity_total')
                        ->label('Total capacity')
                        ->numeric()
                        ->minValue(0)
                        ->placeholder('e.g. 12000'),
                    Forms\Components\TextInput::make('capacity_standing')
                        ->label('Standing')
                        ->numeric()
                        ->minValue(0)
                        ->placeholder('e.g. 8000'),
                    Forms\Components\TextInput::make('capacity_seated')
                        ->label('Seated')
                        ->numeric()
                        ->minValue(0)
                        ->placeholder('e.g. 4000'),
                ])->columns(3),

            // FACILITIES
            SC\Section::make('Facilități')
                ->description('Selectează facilitățile disponibile la această locație')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\CheckboxList::make('facilities')
                        ->label('')
                        ->options(Venue::getFacilitiesOptions())
                        ->columns(4)
                        ->gridDirection('row')
                        ->searchable()
                        ->bulkToggleable()
                        ->columnSpanFull(),
                ]),

            // CONTACT & LINKS
            SC\Section::make('Contact & Links')
                ->schema([
                    Forms\Components\TextInput::make('phone')
                        ->label('Phone 1')
                        ->maxLength(64)
                        ->placeholder('+40 ...')
                        ->prefixIcon('heroicon-o-phone'),
                    Forms\Components\TextInput::make('phone2')
                        ->label('Phone 2')
                        ->maxLength(64)
                        ->placeholder('+40 ...')
                        ->prefixIcon('heroicon-o-phone'),
                    Forms\Components\TextInput::make('email')
                        ->label('Email 1')
                        ->email()
                        ->placeholder('contact@example.com')
                        ->prefixIcon('heroicon-o-envelope'),
                    Forms\Components\TextInput::make('email2')
                        ->label('Email 2')
                        ->email()
                        ->placeholder('reservations@example.com')
                        ->prefixIcon('heroicon-o-envelope'),
                    Forms\Components\TextInput::make('website_url')
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
                    Forms\Components\DatePicker::make('established_at')
                        ->label('Established')
                        ->native(false),
                ])->columns(2),

            // DESCRIPTION - EN/RO
            SC\Section::make('Description')
                ->schema([
                    SC\Tabs::make('Description Translations')
                        ->tabs([
                            SC\Tabs\Tab::make('English')
                                ->schema([
                                    Forms\Components\RichEditor::make('description.en')
                                        ->label('Description (EN)')
                                        ->columnSpanFull(),
                                ]),
                            SC\Tabs\Tab::make('Română')
                                ->schema([
                                    Forms\Components\RichEditor::make('description.ro')
                                        ->label('Descriere (RO)')
                                        ->columnSpanFull(),
                                ]),
                        ])->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city')
                    ->sortable(),
                Tables\Columns\TextColumn::make('capacity')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVenues::route('/'),
            'create' => Pages\CreateVenue::route('/create'),
            'edit' => Pages\EditVenue::route('/{record}/edit'),
        ];
    }
}

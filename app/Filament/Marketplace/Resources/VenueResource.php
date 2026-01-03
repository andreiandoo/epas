<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\VenueResource\Pages;
use App\Models\Venue;
use App\Models\MarketplaceVenueCategory;
use Filament\Actions\DeleteBulkAction;
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

class VenueResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = Venue::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office';
    protected static \UnitEnum|string|null $navigationGroup = null;
    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplace?->id);
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();

        return $schema->schema([
            // Hidden tenant_id
            Forms\Components\Hidden::make('marketplace_client_id')
                ->default($marketplace?->id),

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

            // SCHEDULE
            SC\Section::make('Program')
                ->description('Program de funcționare al locației')
                ->schema([
                    Forms\Components\Textarea::make('schedule')
                        ->label('Program')
                        ->placeholder("Luni - Vineri: 10:00 - 22:00\nSâmbătă - Duminică: 12:00 - 24:00")
                        ->rows(5)
                        ->columnSpanFull(),
                ]),

            // VENUE CATEGORIES
            SC\Section::make('Categorii')
                ->description('Adaugă locația în una sau mai multe categorii')
                ->schema([
                    Forms\Components\Select::make('venueCategories')
                        ->label('Categorii locație')
                        ->relationship(
                            'venueCategories',
                            'name',
                            fn (Builder $query) => $query->where('marketplace_client_id', static::getMarketplaceClient()?->id)
                        )
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('name', 'ro') ?? $record->getTranslation('name', 'en'))
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name.ro')
                                ->label('Nume categorie (RO)')
                                ->required(),
                            Forms\Components\TextInput::make('name.en')
                                ->label('Category name (EN)'),
                            Forms\Components\TextInput::make('icon')
                                ->label('Icon (emoji)')
                                ->placeholder('🎭'),
                            Forms\Components\ColorPicker::make('color')
                                ->label('Culoare'),
                        ])
                        ->createOptionUsing(function (array $data) {
                            $data['marketplace_client_id'] = static::getMarketplaceClient()?->id;
                            return MarketplaceVenueCategory::create($data)->id;
                        })
                        ->columnSpanFull(),
                ]),

            // PARTNER NOTES (internal)
            SC\Section::make('Note interne')
                ->description('Note interne despre această locație (nu sunt vizibile public)')
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
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('Imagine')
                    ->circular()
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?name=V&color=7F9CF5&background=EBF4FF'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nume')
                    ->formatStateUsing(fn ($record) => $record->getTranslation('name', 'ro') ?? $record->getTranslation('name', 'en'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city')
                    ->label('Oraș')
                    ->sortable(),
                Tables\Columns\TextColumn::make('capacity_total')
                    ->label('Capacitate')
                    ->sortable()
                    ->numeric(),
                Tables\Columns\TextColumn::make('venueCategories.name')
                    ->label('Categorii')
                    ->badge()
                    ->separator(',')
                    ->formatStateUsing(fn ($state, $record) => $record->venueCategories->map(fn ($c) => $c->icon . ' ' . ($c->getTranslation('name', 'ro') ?? $c->getTranslation('name', 'en')))->join(', '))
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_partner')
                    ->label('Partener')
                    ->boolean()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_partner')
                    ->label('Doar parteneri'),
                Tables\Filters\SelectFilter::make('venueCategories')
                    ->label('Categorie')
                    ->relationship('venueCategories', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->icon . ' ' . ($record->getTranslation('name', 'ro') ?? $record->getTranslation('name', 'en')))
                    ->preload()
                    ->multiple(),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
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

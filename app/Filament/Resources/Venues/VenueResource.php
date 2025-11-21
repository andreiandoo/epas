<?php

namespace App\Filament\Resources\Venues;

use App\Filament\Resources\Venues\Pages;
use App\Filament\Forms\Components\TranslatableField;
use App\Models\Venue;
use BackedEnum;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class VenueResource extends Resource
{
    protected static ?string $model = Venue::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';
    protected static \UnitEnum|string|null $navigationGroup = 'Venues & Mapping';
    protected static ?int $navigationSort = 30;
    protected static ?string $modelLabel = 'Venue';

    public static function form(Schema $schema): Schema
    {
        // IMPORTANT: folosim ->schema([...]) (nu ->components([...]))
        return $schema->schema([
            // HEADER: Nume mare pe un rând, apoi slug + tenant
            SC\Section::make('Header')->schema([
                TranslatableField::make('name', 'Venue name')
                    ->columnSpanFull(),

                SC\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->placeholder('arena-nationala')
                        ->hint('URL-friendly ID. Se auto-generează din nume; îl poți ajusta.')
                        ->hintIcon('heroicon-o-information-circle')
                        ->maxLength(190)
                        ->unique(ignoreRecord: true)
                        ->afterStateUpdated(fn ($state, \Filament\Schemas\Components\Utilities\Set $set) =>
                            $set('slug', \Illuminate\Support\Str::slug($state))
                        )
                        ->dehydrated(true)
                        ->required(),

                    Forms\Components\Select::make('tenant_id')
                        ->label('Tenant asociat')
                        ->relationship('tenant','name')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->hint('Poate fi gol, caz în care venue-ul e „public".')
                        ->hintIcon('heroicon-o-information-circle'),
                ]),
            ])->columns(1),

            SC\Section::make('Identity')->schema([
                // drag&drop image uploader cu preview
                Forms\Components\FileUpload::make('image_url')
                    ->label('Imagine principală')
                    ->image()
                    ->imagePreviewHeight('250')
                    ->disk('public')
                    ->directory('venues')
                    ->visibility('public')
                    ->openable()
                    ->downloadable()
                    ->hint('Trage & plasează o imagine sau alege un fișier.')
                    ->hintIcon('heroicon-o-information-circle'),

                Forms\Components\FileUpload::make('gallery')
                    ->label('Galerie imagini')
                    ->image()
                    ->multiple()
                    ->directory('venues/gallery')
                    ->visibility('public')
                    ->reorderable()
                    ->openable()
                    ->downloadable()
                    ->hint('Poți uploada mai multe imagini care vor forma galeria venue-ului.')
                    ->hintIcon('heroicon-o-information-circle')
                    ->columnSpanFull(),
            ]),

            SC\Section::make('Location')->schema([
                Forms\Components\TextInput::make('address')
                    ->label('Adresa')->maxLength(255)
                    ->placeholder('Strada și numărul')
                    ->live(onBlur: true),
                Forms\Components\TextInput::make('city')
                    ->label('Oraș')->maxLength(120)
                    ->placeholder('Ex: București')
                    ->prefixIcon('heroicon-o-map-pin')
                    ->live(onBlur: true),
                Forms\Components\TextInput::make('state')
                    ->label('Județ')->maxLength(120)
                    ->placeholder('Ex: Ilfov'),
                Forms\Components\TextInput::make('country')
                    ->label('Țara')->maxLength(120)
                    ->placeholder('Ex: RO')
                    ->live(onBlur: true),
                Forms\Components\TextInput::make('lat')
                    ->label('Latitudine')->numeric()->step('0.0000001')
                    ->placeholder('44.4268'),
                Forms\Components\TextInput::make('lng')
                    ->label('Longitudine')->numeric()->step('0.0000001')
                    ->placeholder('26.1025'),
                SC\Actions::make([
                    Actions\Action::make('geocode')
                        ->label('Auto-detect coordinates')
                        ->icon('heroicon-o-map-pin')
                        ->color('info')
                        ->action(function (\Filament\Schemas\Components\Utilities\Get $get, \Filament\Schemas\Components\Utilities\Set $set) {
                            $address = $get('address');
                            $city = $get('city');
                            $country = $get('country');

                            if (empty($city)) {
                                \Filament\Notifications\Notification::make()
                                    ->title('City is required for geocoding')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            // Build address string
                            $fullAddress = collect([$address, $city, $country])
                                ->filter()
                                ->implode(', ');

                            // Get Google Maps API key from settings
                            $settings = \App\Models\Setting::first();
                            $apiKey = $settings?->google_maps_api_key;

                            if (empty($apiKey)) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Google Maps API key not configured')
                                    ->body('Please add your API key in Settings > Connections')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            try {
                                $response = \Illuminate\Support\Facades\Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                                    'address' => $fullAddress,
                                    'key' => $apiKey,
                                ]);

                                $data = $response->json();

                                if ($data['status'] === 'OK' && !empty($data['results'])) {
                                    $location = $data['results'][0]['geometry']['location'];
                                    $set('lat', $location['lat']);
                                    $set('lng', $location['lng']);

                                    \Filament\Notifications\Notification::make()
                                        ->title('Coordinates detected')
                                        ->body("Lat: {$location['lat']}, Lng: {$location['lng']}")
                                        ->success()
                                        ->send();
                                } else {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Could not find coordinates')
                                        ->body('Try adding more address details')
                                        ->warning()
                                        ->send();
                                }
                            } catch (\Exception $e) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Geocoding failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                ])->columnSpanFull(),
            ])->columns(3),

            SC\Section::make('Capacity')->schema([
                Forms\Components\TextInput::make('capacity_total')
                    ->label('Total')->numeric()->minValue(0)->placeholder('Ex: 12000')
                    ->prefixIcon('heroicon-o-users'),
                Forms\Components\TextInput::make('capacity_standing')
                    ->label('În picioare')->numeric()->minValue(0)->placeholder('Ex: 8000'),
                Forms\Components\TextInput::make('capacity_seated')
                    ->label('Așezați')->numeric()->minValue(0)->placeholder('Ex: 4000'),
            ])->columns(3),

            SC\Section::make('Contact & Links')->schema([
                Forms\Components\TextInput::make('phone')->label('Telefon')->maxLength(64)->placeholder('+40 ...')->prefixIcon('heroicon-o-phone'),
                Forms\Components\TextInput::make('email')->label('Email')->email()->placeholder('contact@exemplu.ro')->prefixIcon('heroicon-o-envelope'),
                Forms\Components\TextInput::make('website_url')->label('Website')->url()->placeholder('https://...')->prefixIcon('heroicon-o-globe-alt'),
                Forms\Components\TextInput::make('facebook_url')->label('Facebook')->url()->placeholder('https://facebook.com/...')->prefixIcon('heroicon-o-link'),
                Forms\Components\TextInput::make('instagram_url')->label('Instagram')->url()->placeholder('https://instagram.com/...')->prefixIcon('heroicon-o-link'),
                Forms\Components\TextInput::make('tiktok_url')->label('TikTok')->url()->placeholder('https://tiktok.com/@...')->prefixIcon('heroicon-o-link'),
                Forms\Components\DatePicker::make('established_at')->label('Pe piață din')->native(false),
            ])->columns(2),

            SC\Section::make('Descriere')->schema([
                TranslatableField::richEditor('description', 'Descriere')
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name.en')
                    ->label('Nume')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record) => static::getUrl('view', ['record' => $record->slug])),

                Tables\Columns\TextColumn::make('city')->label('Oraș')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('state')->label('Județ')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('country')->label('Țara')->sortable()->toggleable(),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->sortable()
                    ->toggleable()
                    ->badge(),

                Tables\Columns\TextColumn::make('capacity_total')
                    ->label('Capacitate')
                    ->numeric()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('established_at')
                    ->label('Din')
                    ->date()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creat')
                    ->dateTime()
                    ->since(),

                Tables\Columns\TextColumn::make('edit_link')
                    ->label('Edit')
                    ->state('Open')
                    ->url(fn ($record) => static::getUrl('edit', ['record' => $record->slug]))
                    ->openUrlInNewTab(false),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenant','name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(null)       // ⟵ IMPORTANT: previne linkul implicit spre `view` fără record
            ->actions([])
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // Hero Section with Image and Name
                Infolists\Components\Section::make()
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\ImageEntry::make('image_url')
                                    ->label('')
                                    ->disk('public')
                                    ->height(200)
                                    ->extraImgAttributes(['class' => 'rounded-xl object-cover'])
                                    ->columnSpan(1),
                                Infolists\Components\Group::make([
                                    Infolists\Components\TextEntry::make('name')
                                        ->label('')
                                        ->formatStateUsing(fn ($record) => $record->getTranslation('name', app()->getLocale()) ?? $record->getTranslation('name', 'en'))
                                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                        ->weight('bold'),
                                    Infolists\Components\TextEntry::make('location')
                                        ->label('')
                                        ->state(fn ($record) => collect([$record->address, $record->city, $record->state, $record->country])->filter()->implode(', '))
                                        ->icon('heroicon-o-map-pin')
                                        ->color('gray'),
                                    Infolists\Components\TextEntry::make('tenant.name')
                                        ->label('Tenant')
                                        ->badge()
                                        ->color('primary')
                                        ->placeholder('Public venue'),
                                ])->columnSpan(2),
                            ]),
                    ])
                    ->columnSpanFull(),

                // Statistics Grid
                Infolists\Components\Section::make('Capacity & Info')
                    ->icon('heroicon-o-users')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('capacity_total')
                                    ->label('Total Capacity')
                                    ->numeric()
                                    ->placeholder('—')
                                    ->icon('heroicon-o-users'),
                                Infolists\Components\TextEntry::make('capacity_standing')
                                    ->label('Standing')
                                    ->numeric()
                                    ->placeholder('—'),
                                Infolists\Components\TextEntry::make('capacity_seated')
                                    ->label('Seated')
                                    ->numeric()
                                    ->placeholder('—'),
                                Infolists\Components\TextEntry::make('established_at')
                                    ->label('Established')
                                    ->date('Y')
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->collapsible(),

                // Location & Map
                Infolists\Components\Section::make('Location')
                    ->icon('heroicon-o-map')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\Group::make([
                                    Infolists\Components\TextEntry::make('address')
                                        ->label('Address')
                                        ->placeholder('—'),
                                    Infolists\Components\TextEntry::make('city')
                                        ->label('City')
                                        ->placeholder('—'),
                                    Infolists\Components\TextEntry::make('state')
                                        ->label('State/Region')
                                        ->placeholder('—'),
                                    Infolists\Components\TextEntry::make('country')
                                        ->label('Country')
                                        ->placeholder('—'),
                                    Infolists\Components\TextEntry::make('coordinates')
                                        ->label('Coordinates')
                                        ->state(fn ($record) => $record->lat && $record->lng ? "{$record->lat}, {$record->lng}" : null)
                                        ->placeholder('—'),
                                ]),
                                Infolists\Components\ViewEntry::make('map')
                                    ->view('filament.infolists.entries.venue-map')
                                    ->visible(fn ($record) => $record->lat && $record->lng),
                            ]),
                    ])
                    ->collapsible(),

                // Contact Information
                Infolists\Components\Section::make('Contact & Social')
                    ->icon('heroicon-o-phone')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('phone')
                                    ->label('Phone')
                                    ->icon('heroicon-o-phone')
                                    ->url(fn ($state) => $state ? "tel:{$state}" : null)
                                    ->placeholder('—'),
                                Infolists\Components\TextEntry::make('email')
                                    ->label('Email')
                                    ->icon('heroicon-o-envelope')
                                    ->url(fn ($state) => $state ? "mailto:{$state}" : null)
                                    ->placeholder('—'),
                                Infolists\Components\TextEntry::make('website_url')
                                    ->label('Website')
                                    ->icon('heroicon-o-globe-alt')
                                    ->url(fn ($state) => $state)
                                    ->openUrlInNewTab()
                                    ->placeholder('—'),
                                Infolists\Components\TextEntry::make('facebook_url')
                                    ->label('Facebook')
                                    ->icon('heroicon-o-link')
                                    ->url(fn ($state) => $state)
                                    ->openUrlInNewTab()
                                    ->placeholder('—'),
                                Infolists\Components\TextEntry::make('instagram_url')
                                    ->label('Instagram')
                                    ->icon('heroicon-o-link')
                                    ->url(fn ($state) => $state)
                                    ->openUrlInNewTab()
                                    ->placeholder('—'),
                                Infolists\Components\TextEntry::make('tiktok_url')
                                    ->label('TikTok')
                                    ->icon('heroicon-o-link')
                                    ->url(fn ($state) => $state)
                                    ->openUrlInNewTab()
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->collapsible(),

                // Description
                Infolists\Components\Section::make('Description')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Infolists\Components\TextEntry::make('description')
                            ->label('')
                            ->html()
                            ->formatStateUsing(fn ($record) => new HtmlString($record->getTranslation('description', app()->getLocale()) ?? $record->getTranslation('description', 'en') ?? '<em class="text-gray-400">No description</em>'))
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                // Gallery
                Infolists\Components\Section::make('Gallery')
                    ->icon('heroicon-o-photo')
                    ->schema([
                        Infolists\Components\ImageEntry::make('gallery')
                            ->label('')
                            ->disk('public')
                            ->height(150)
                            ->extraImgAttributes(['class' => 'rounded-lg object-cover'])
                            ->stacked()
                            ->limit(10)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn ($record) => !empty($record->gallery)),

                // Metadata
                Infolists\Components\Section::make('System Info')
                    ->icon('heroicon-o-cog')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('slug')
                                    ->label('Slug')
                                    ->badge()
                                    ->color('gray'),
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Created')
                                    ->dateTime(),
                                Infolists\Components\TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            VenueResource\RelationManagers\SeatingLayoutsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListVenues::route('/'),
            'create' => Pages\CreateVenue::route('/create'),
            'view'   => Pages\ViewVenue::route('/{record}'),
            'edit'   => Pages\EditVenue::route('/{record}/edit'),
            'stats'  => Pages\VenueStats::route('/{record}/stats'),
        ];
    }

    public static function getGlobalSearchResultActionUrl(Model $record): string
    {
        return static::getUrl('edit', ['record' => $record->slug]);
    }

    public static function getRecordRouteKeyName(): ?string
    {
        return 'slug';
    }
}

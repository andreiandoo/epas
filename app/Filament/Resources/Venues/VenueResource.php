<?php

namespace App\Filament\Resources\Venues;

use App\Filament\Resources\Venues\Pages;
use App\Filament\Forms\Components\TranslatableField;
use App\Models\Venue;
use BackedEnum;
use Filament\Actions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
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
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'URL-friendly ID. Se auto-generează din nume; îl poți ajusta.')
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
                        ->hintIcon('heroicon-o-information-circle', tooltip: 'Poate fi gol, caz în care venue-ul e „public".'),
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
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Trage & plasează o imagine sau alege un fișier.'),

                Forms\Components\FileUpload::make('gallery')
                    ->label('Galerie imagini')
                    ->image()
                    ->multiple()
                    ->directory('venues/gallery')
                    ->visibility('public')
                    ->reorderable()
                    ->openable()
                    ->downloadable()
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Poți uploada mai multe imagini care vor forma galeria venue-ului.')
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
                Forms\Components\TextInput::make('google_maps_url')
                    ->label('Google Maps Link')
                    ->url()
                    ->placeholder('https://maps.google.com/...')
                    ->prefixIcon('heroicon-o-map')
                    ->columnSpanFull(),
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
                Forms\Components\TextInput::make('phone')->label('Telefon 1')->maxLength(64)->placeholder('+40 ...')->prefixIcon('heroicon-o-phone'),
                Forms\Components\TextInput::make('phone2')->label('Telefon 2')->maxLength(64)->placeholder('+40 ...')->prefixIcon('heroicon-o-phone'),
                Forms\Components\TextInput::make('email')->label('Email 1')->email()->placeholder('contact@exemplu.ro')->prefixIcon('heroicon-o-envelope'),
                Forms\Components\TextInput::make('email2')->label('Email 2')->email()->placeholder('rezervari@exemplu.ro')->prefixIcon('heroicon-o-envelope'),
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
                Tables\Columns\TextColumn::make('name')
                    ->label('Nume')
                    ->formatStateUsing(fn ($record) => $record->getTranslation('name', 'ro') ?: $record->getTranslation('name', 'en') ?: '-')
                    ->searchable(query: function ($query, string $search) {
                        return $query->where('name', 'like', "%{$search}%");
                    })
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
            ->bulkActions([])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
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

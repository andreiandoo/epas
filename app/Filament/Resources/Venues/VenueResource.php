<?php

namespace App\Filament\Resources\Venues;

use App\Filament\Resources\Venues\Pages;
use App\Filament\Forms\Components\TranslatableField;
use App\Models\Venue;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

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
                    ->placeholder('Strada și numărul'),
                Forms\Components\TextInput::make('city')
                    ->label('Oraș')->maxLength(120)
                    ->placeholder('Ex: București')
                    ->prefixIcon('heroicon-o-map-pin'),
                Forms\Components\TextInput::make('state')
                    ->label('Județ')->maxLength(120)
                    ->placeholder('Ex: Ilfov'),
                Forms\Components\TextInput::make('country')
                    ->label('Țara')->maxLength(120)
                    ->placeholder('Ex: RO'),
                Forms\Components\TextInput::make('lat')
                    ->label('Latitudine')->numeric()->step('0.0000001')
                    ->placeholder('44.4268'),
                Forms\Components\TextInput::make('lng')
                    ->label('Longitudine')->numeric()->step('0.0000001')
                    ->placeholder('26.1025'),
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

<?php

namespace App\Filament\Organizer\Resources;

use App\Models\Event;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationGroup = 'Events';
    protected static ?string $navigationLabel = 'My Events';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        $organizer = auth('organizer')->user()?->organizer;

        return parent::getEloquentQuery()
            ->where('organizer_id', $organizer?->id);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Event')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Basic Info')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Forms\Components\Section::make('Event Details')
                                    ->schema([
                                        Forms\Components\TextInput::make('title.ro')
                                            ->label('Event Title (RO)')
                                            ->required()
                                            ->maxLength(255)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, Forms\Set $set, $context) {
                                                if ($context === 'create' && $state) {
                                                    $set('slug', \Illuminate\Support\Str::slug($state));
                                                }
                                            }),

                                        Forms\Components\TextInput::make('title.en')
                                            ->label('Event Title (EN)')
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('slug')
                                            ->label('URL Slug')
                                            ->required()
                                            ->maxLength(255)
                                            ->unique(ignoreRecord: true),

                                        Forms\Components\Textarea::make('short_description.ro')
                                            ->label('Short Description (RO)')
                                            ->rows(2)
                                            ->maxLength(500),

                                        Forms\Components\RichEditor::make('description.ro')
                                            ->label('Full Description (RO)')
                                            ->columnSpanFull(),
                                    ])->columns(2),
                            ]),

                        Forms\Components\Tabs\Tab::make('Date & Time')
                            ->icon('heroicon-o-clock')
                            ->schema([
                                Forms\Components\Section::make('When')
                                    ->schema([
                                        Forms\Components\Select::make('duration_mode')
                                            ->label('Event Type')
                                            ->options([
                                                'single_day' => 'Single Day',
                                                'range' => 'Date Range',
                                                'multi_day' => 'Multi-Day (Custom)',
                                            ])
                                            ->default('single_day')
                                            ->required()
                                            ->live(),

                                        // Single Day Fields
                                        Forms\Components\DatePicker::make('event_date')
                                            ->label('Event Date')
                                            ->required()
                                            ->visible(fn (Forms\Get $get) => $get('duration_mode') === 'single_day'),

                                        Forms\Components\TimePicker::make('door_time')
                                            ->label('Doors Open')
                                            ->visible(fn (Forms\Get $get) => $get('duration_mode') === 'single_day'),

                                        Forms\Components\TimePicker::make('start_time')
                                            ->label('Start Time')
                                            ->visible(fn (Forms\Get $get) => $get('duration_mode') === 'single_day'),

                                        Forms\Components\TimePicker::make('end_time')
                                            ->label('End Time')
                                            ->visible(fn (Forms\Get $get) => $get('duration_mode') === 'single_day'),

                                        // Range Fields
                                        Forms\Components\DatePicker::make('range_start_date')
                                            ->label('Start Date')
                                            ->required()
                                            ->visible(fn (Forms\Get $get) => $get('duration_mode') === 'range'),

                                        Forms\Components\DatePicker::make('range_end_date')
                                            ->label('End Date')
                                            ->required()
                                            ->visible(fn (Forms\Get $get) => $get('duration_mode') === 'range'),

                                        Forms\Components\TimePicker::make('range_start_time')
                                            ->label('Daily Start Time')
                                            ->visible(fn (Forms\Get $get) => $get('duration_mode') === 'range'),

                                        Forms\Components\TimePicker::make('range_end_time')
                                            ->label('Daily End Time')
                                            ->visible(fn (Forms\Get $get) => $get('duration_mode') === 'range'),
                                    ])->columns(2),
                            ]),

                        Forms\Components\Tabs\Tab::make('Location')
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                Forms\Components\Section::make('Venue')
                                    ->schema([
                                        Forms\Components\Select::make('venue_id')
                                            ->label('Venue')
                                            ->relationship('venue', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->createOptionForm([
                                                Forms\Components\TextInput::make('name')
                                                    ->required(),
                                                Forms\Components\TextInput::make('address'),
                                                Forms\Components\TextInput::make('city'),
                                            ]),

                                        Forms\Components\Textarea::make('address')
                                            ->label('Address Override')
                                            ->helperText('Leave empty to use venue address'),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Media')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                Forms\Components\Section::make('Images')
                                    ->schema([
                                        Forms\Components\FileUpload::make('poster_url')
                                            ->label('Event Poster')
                                            ->image()
                                            ->directory('events/posters')
                                            ->maxSize(5120),

                                        Forms\Components\FileUpload::make('hero_image_url')
                                            ->label('Hero Image')
                                            ->image()
                                            ->directory('events/heroes')
                                            ->maxSize(5120),
                                    ])->columns(2),
                            ]),

                        Forms\Components\Tabs\Tab::make('Settings')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Forms\Components\Section::make('Event Status')
                                    ->schema([
                                        Forms\Components\Toggle::make('is_sold_out')
                                            ->label('Sold Out'),

                                        Forms\Components\Toggle::make('door_sales_only')
                                            ->label('Door Sales Only')
                                            ->helperText('Disable online ticket sales'),

                                        Forms\Components\Toggle::make('is_cancelled')
                                            ->label('Cancelled'),

                                        Forms\Components\Textarea::make('cancel_reason')
                                            ->label('Cancellation Reason')
                                            ->visible(fn (Forms\Get $get) => $get('is_cancelled')),
                                    ])->columns(2),

                                Forms\Components\Section::make('Links')
                                    ->schema([
                                        Forms\Components\TextInput::make('website_url')
                                            ->label('Event Website')
                                            ->url(),

                                        Forms\Components\TextInput::make('facebook_url')
                                            ->label('Facebook Event')
                                            ->url(),
                                    ])->columns(2),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('poster_url')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?name=E&background=random'),

                Tables\Columns\TextColumn::make('title.ro')
                    ->label('Event')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Date')
                    ->date('M j, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('venue.name')
                    ->label('Venue')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_sold_out')
                    ->label('Sold Out')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-minus-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\IconColumn::make('is_cancelled')
                    ->label('Cancelled')
                    ->boolean()
                    ->trueIcon('heroicon-o-x-circle')
                    ->trueColor('danger')
                    ->falseIcon('heroicon-o-minus-circle')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('tickets_count')
                    ->label('Tickets')
                    ->counts('tickets')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_cancelled')
                    ->label('Cancelled'),
                Tables\Filters\TernaryFilter::make('is_sold_out')
                    ->label('Sold Out'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('start_date', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Event Overview')
                    ->schema([
                        Infolists\Components\ImageEntry::make('poster_url')
                            ->label('Poster')
                            ->height(200),
                        Infolists\Components\TextEntry::make('title.ro')
                            ->label('Title'),
                        Infolists\Components\TextEntry::make('start_date')
                            ->label('Date')
                            ->date(),
                        Infolists\Components\TextEntry::make('venue.name')
                            ->label('Venue'),
                    ])->columns(4),

                Infolists\Components\Section::make('Status')
                    ->schema([
                        Infolists\Components\IconEntry::make('is_sold_out')
                            ->label('Sold Out')
                            ->boolean(),
                        Infolists\Components\IconEntry::make('is_cancelled')
                            ->label('Cancelled')
                            ->boolean(),
                        Infolists\Components\IconEntry::make('door_sales_only')
                            ->label('Door Sales Only')
                            ->boolean(),
                    ])->columns(3),

                Infolists\Components\Section::make('Description')
                    ->schema([
                        Infolists\Components\TextEntry::make('short_description.ro')
                            ->label('Short Description'),
                        Infolists\Components\TextEntry::make('description.ro')
                            ->label('Full Description')
                            ->html(),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            EventResource\RelationManagers\TicketTypesRelationManager::class,
            EventResource\RelationManagers\OrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => EventResource\Pages\ListEvents::route('/'),
            'create' => EventResource\Pages\CreateEvent::route('/create'),
            'view' => EventResource\Pages\ViewEvent::route('/{record}'),
            'edit' => EventResource\Pages\EditEvent::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $organizer = auth('organizer')->user()?->organizer;
        if (!$organizer) {
            return null;
        }

        $upcoming = static::getEloquentQuery()->upcoming()->count();
        return $upcoming > 0 ? (string) $upcoming : null;
    }
}
